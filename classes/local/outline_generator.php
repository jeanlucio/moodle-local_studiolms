<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course outline generator for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use moodle_exception;

/**
 * Builds the prompt, calls the AI provider and parses the outline JSON.
 *
 * The outline is the lightweight structure reviewed in step 2 of the wizard:
 * learning objectives plus sections and the activities they contain. The full
 * content of each activity is only produced later, in step 3.
 */
class outline_generator {
    /** @var int Maximum number of AI calls per outline before giving up. */
    private const MAX_ATTEMPTS = 3;

    /** @var int Fixed backoff in seconds between attempts. */
    private const BACKOFF_SECONDS = 2;

    /** @var string[] Activity types accepted in the generated outline. */
    private const ALLOWED_TYPES = ['page', 'label', 'quiz', 'forum', 'assign', 'glossary'];

    /**
     * Generates a course outline from the wizard briefing.
     *
     * @param array $briefing Briefing data: theme, reference, bloom, structure, mode, profile.
     * @return array Normalised outline with 'objectives' and 'sections' keys.
     * @throws moodle_exception If the AI never returns a valid outline.
     */
    public static function generate(array $briefing): array {
        $systemprompt = self::build_system_prompt($briefing);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $userprompt = self::build_user_prompt($briefing, $attempt > 1);
            $raw = ai_resolver::generate_text($systemprompt, $userprompt);
            $decoded = self::decode_json($raw);

            if ($decoded !== null) {
                $outline = self::normalise($decoded);
                if (!empty($outline['sections'])) {
                    return $outline;
                }
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep(self::BACKOFF_SECONDS);
            }
        }

        throw new moodle_exception('invalidairesponse', 'local_studiolms');
    }

    /**
     * Builds the system prompt encoding Backward Design, Bloom's level and the JSON schema.
     *
     * @param array $briefing Briefing data.
     * @return string The system instruction.
     */
    private static function build_system_prompt(array $briefing): string {
        $language = current_language();
        $bloom = clean_param($briefing['bloom'] ?? 'general', PARAM_ALPHA);
        $abc = ($briefing['structure'] ?? 'free') === 'abc';

        $lines = [
            'You are an instructional designer building a course outline for Moodle.',
            'Apply Backward Design: first derive the learning objectives, then the assessments '
                . 'that verify them, then the learning activities that prepare for those assessments.',
        ];

        if ($bloom !== 'general' && $bloom !== '') {
            $lines[] = "The predominant cognitive level (Bloom's taxonomy) for this course is: {$bloom}. "
                . 'Choose activity types and verbs that match this level.';
        }

        $lines[] = "Write every title and objective in the language identified by the code: {$language}.";

        if ($abc) {
            $lines[] = 'Use ABC Learning Design: give each section a balanced mix of acquisition, '
                . 'investigation, practice, production and discussion activities.';
        }

        $types = implode(', ', self::ALLOWED_TYPES);
        $lines[] = "Each activity has a type from this exact list: {$types}.";
        $lines[] = 'Include exactly one glossary activity for the whole course and place it as '
            . 'the first activity of the first section.';
        $lines[] = 'Return ONLY a valid JSON object, with no markdown fences or commentary, '
            . 'using exactly this shape: '
            . '{"objectives": ["..."], "sections": [{"title": "...", '
            . '"activities": [{"type": "page", "title": "..."}]}]}.';
        $lines[] = 'Use double quotes for every key and string. Do not add trailing commas, '
            . 'comments or any text before or after the JSON object.';
        $lines[] = 'Produce between 3 and 6 sections, each with 2 to 4 activities.';

        return implode("\n", $lines);
    }

    /**
     * Builds the user prompt with the theme and optional reference material.
     *
     * @param array $briefing Briefing data.
     * @param bool $strict Whether to append a stricter JSON-only reminder (retry).
     * @return string The user prompt.
     */
    private static function build_user_prompt(array $briefing, bool $strict): string {
        $theme = clean_param($briefing['theme'] ?? '', PARAM_TEXT);
        $reference = clean_param($briefing['reference'] ?? '', PARAM_TEXT);

        $prompt = "Course theme or focus: {$theme}";
        if ($reference !== '') {
            $prompt .= "\n\nReference material provided by the teacher:\n{$reference}";
        }
        if ($strict) {
            $prompt .= "\n\nReturn only valid JSON, no markdown, no explanations.";
        }

        return $prompt;
    }

    /**
     * Decodes the AI response into an array, tolerating markdown code fences.
     *
     * @param string $raw Raw AI response.
     * @return array|null Decoded array, or null when the JSON is invalid.
     */
    private static function decode_json(string $raw): ?array {
        $text = trim($raw);
        $fence = str_repeat(chr(96), 3);
        $text = preg_replace('/^' . $fence . '(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*' . $fence . '$/', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $text = substr($text, $start, $end - $start + 1);

        // Drop trailing commas before a closing brace or bracket, a common LLM mistake.
        $text = preg_replace('/,(\s*[}\]])/', '$1', $text);

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Validates and cleans the decoded outline into a predictable structure.
     *
     * @param array $decoded Raw decoded outline.
     * @return array Outline with 'objectives' (string[]) and 'sections' arrays.
     */
    private static function normalise(array $decoded): array {
        $objectives = [];
        foreach ($decoded['objectives'] ?? [] as $objective) {
            if (is_string($objective) && trim($objective) !== '') {
                $objectives[] = clean_param($objective, PARAM_TEXT);
            }
        }

        $sections = [];
        foreach ($decoded['sections'] ?? [] as $section) {
            if (!is_array($section) || empty($section['title'])) {
                continue;
            }
            $activities = [];
            foreach ($section['activities'] ?? [] as $activity) {
                if (!is_array($activity) || empty($activity['title'])) {
                    continue;
                }
                $type = clean_param($activity['type'] ?? 'page', PARAM_ALPHA);
                if (!in_array($type, self::ALLOWED_TYPES, true)) {
                    $type = 'page';
                }
                $activities[] = [
                    'type' => $type,
                    'title' => clean_param($activity['title'], PARAM_TEXT),
                ];
            }
            $sections[] = [
                'title' => clean_param($section['title'], PARAM_TEXT),
                'activities' => $activities,
            ];
        }

        $sections = self::enforce_single_glossary($sections);

        return ['objectives' => $objectives, 'sections' => $sections];
    }

    /**
     * Ensures the course has exactly one glossary, as the first activity of the first section.
     *
     * @param array $sections Sections with their activities.
     * @return array Sections with the glossary deduplicated and repositioned.
     */
    private static function enforce_single_glossary(array $sections): array {
        if (empty($sections)) {
            return $sections;
        }

        $glossarytitle = null;
        foreach ($sections as $index => $section) {
            $kept = [];
            foreach ($section['activities'] as $activity) {
                if ($activity['type'] === 'glossary') {
                    $glossarytitle = $glossarytitle ?? $activity['title'];
                    continue;
                }
                $kept[] = $activity;
            }
            $sections[$index]['activities'] = $kept;
        }

        if ($glossarytitle === null) {
            $glossarytitle = get_string('glossary_default_title', 'local_studiolms');
        }
        array_unshift($sections[0]['activities'], ['type' => 'glossary', 'title' => $glossarytitle]);

        return $sections;
    }
}
