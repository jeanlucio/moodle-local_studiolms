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
 * Creates the course glossary and its AI-generated entries.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use stdClass;

/**
 * Builds a single continuous glossary and fills it with key terms from the AI.
 */
class glossary_builder {
    /**
     * Creates the glossary activity and inserts the generated terms as entries.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The glossary name.
     * @param string $theme The course theme used to generate the terms.
     * @return stdClass The add_moduleinfo result (coursemodule and instance).
     */
    public static function create(stdClass $course, int $sectionnum, string $name, string $theme): stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/glossary/lib.php');

        $intro = activity_content::glossary_intro($name);
        $result = course_builder::add_glossary($course, $sectionnum, $name, $intro);

        $terms = self::generate_terms($theme);
        if (empty($terms)) {
            return $result;
        }

        // The core API (not a plain insert) fires entry_created, updates completion and resets the concept cache
        // that the autolink filter reads, so the new terms are linked straight away.
        $cm = get_coursemodule_from_id('glossary', $result->coursemodule, $course->id, false, MUST_EXIST);
        $glossary = $DB->get_record('glossary', ['id' => $result->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        foreach ($terms as $term) {
            glossary_edit_entry((object) [
                'id' => null,
                'concept' => $term['term'],
                'definition_editor' => [
                    'text' => $term['definition'],
                    'format' => FORMAT_HTML,
                    'itemid' => file_get_unused_draft_itemid(),
                ],
                'aliases' => '',
                'usedynalink' => 1,
                'casesensitive' => 0,
                'fullmatch' => 1,
            ], $course, $cm, $glossary, $context);
        }

        return $result;
    }

    /**
     * Reads the entries of a glossary as term/definition pairs.
     *
     * @param int $glossaryid The glossary instance id.
     * @return array List of ['term' => string, 'definition' => string].
     */
    public static function get_terms(int $glossaryid): array {
        global $DB;

        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossaryid], 'id ASC', 'id, concept, definition');
        $terms = [];
        foreach ($entries as $entry) {
            $terms[] = [
                'term' => $entry->concept,
                'definition' => content_to_text($entry->definition, FORMAT_HTML),
            ];
        }
        return $terms;
    }

    /**
     * Generates key glossary terms for the given theme.
     *
     * @param string $theme The course theme.
     * @return array List of ['term' => string, 'definition' => string].
     */
    private static function generate_terms(string $theme): array {
        $language = current_language();
        $system = 'You are an instructional designer creating a course glossary. '
            . 'Return ONLY a valid JSON object, no markdown or commentary, shaped like '
            . '{"terms": [{"term": "...", "definition": "..."}]}. Use double quotes and no trailing commas. '
            . "Write the terms and definitions in the language identified by the code: {$language}. "
            . 'Produce between 8 and 12 concise, essential terms.';
        $user = "Course theme or focus: {$theme}";

        try {
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        } catch (\Throwable $e) {
            return [];
        }
        if ($decoded === null || empty($decoded['terms']) || !is_array($decoded['terms'])) {
            return [];
        }

        $terms = [];
        foreach ($decoded['terms'] as $item) {
            $term = is_array($item) ? ai_json::text($item['term'] ?? null) : '';
            $definition = is_array($item) ? ai_json::text($item['definition'] ?? null) : '';
            if ($term === '' || $definition === '') {
                continue;
            }
            $terms[] = ['term' => $term, 'definition' => $definition];
        }
        return $terms;
    }
}
