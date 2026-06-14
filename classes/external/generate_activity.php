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
 * External web service that generates a single activity synchronously.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_studiolms\local\ai_json;
use local_studiolms\local\ai_resolver;
use local_studiolms\local\course_builder;
use local_studiolms\local\glossary_builder;
use local_studiolms\local\page_builder;
use local_studiolms\local\quiz_builder;

/**
 * Generates one activity in an existing course section and returns its cmid.
 */
class generate_activity extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Target course id.'),
            'sectionnum' => new external_value(PARAM_INT, 'Target section number.'),
            'type'       => new external_value(PARAM_ALPHA, 'Activity type.'),
            'title'      => new external_value(PARAM_TEXT, 'Activity title.'),
            'theme'      => new external_value(PARAM_TEXT, 'Course theme or topic for AI generation.'),
            'bloom'      => new external_value(
                PARAM_ALPHA,
                "Bloom's taxonomy level.",
                VALUE_DEFAULT,
                'general'
            ),
            'reference'  => new external_value(
                PARAM_TEXT,
                'Optional reference material.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Generates one activity in the given section and returns its view URL.
     *
     * @param int $courseid Target course id.
     * @param int $sectionnum Target section number.
     * @param string $type Activity type (page, quiz, forum, assign, label, glossary).
     * @param string $title Activity title.
     * @param string $theme Course theme or topic used by the AI.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param string $reference Optional reference material for the AI.
     * @return array Payload with cmid, viewurl and degraded flag.
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $type,
        string $title,
        string $theme,
        string $bloom,
        string $reference
    ): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'type'       => $type,
            'title'      => $title,
            'theme'      => $theme,
            'bloom'      => $bloom,
            'reference'  => $reference,
        ]);

        $course = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);

        $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => $params['sectionnum']],
            'id',
            MUST_EXIST
        );

        $type       = $params['type'];
        $title      = $params['title'];
        $theme      = $params['theme'];
        $bloom      = $params['bloom'];
        $reference  = $params['reference'];
        $sectionnum = $params['sectionnum'];
        $degraded   = false;
        $result     = null;

        switch ($type) {
            case 'page':
                $preset = '';
                $html = page_builder::render(
                    $theme,
                    $theme,
                    $title,
                    [],
                    $reference,
                    $bloom,
                    [],
                    $degraded,
                    $preset
                );
                $result = course_builder::add_page($course, $sectionnum, $title, $html);
                break;

            case 'label':
                $result = course_builder::add_label(
                    $course,
                    $sectionnum,
                    \html_writer::tag('h4', s($title))
                );
                break;

            case 'forum':
                $intro = self::generate_html('forum', $theme, $title, $reference, $bloom, $degraded);
                $result = course_builder::add_forum($course, $sectionnum, $title, $intro);
                break;

            case 'assign':
                $intro = self::generate_html('assign', $theme, $title, $reference, $bloom, $degraded);
                $result = course_builder::add_assign($course, $sectionnum, $title, $intro);
                break;

            case 'glossary':
                $result = glossary_builder::create($course, $sectionnum, $title, $theme);
                $degraded = empty(glossary_builder::get_terms($result->instance));
                break;

            case 'quiz':
                $intro = \html_writer::tag('p', s($title));
                $result = course_builder::add_quiz($course, $sectionnum, $title, $intro);
                try {
                    $created = quiz_builder::create(
                        $result->coursemodule,
                        $result->instance,
                        $theme,
                        $title,
                        5
                    );
                    $degraded = $created === 0;
                } catch (\Throwable $e) {
                    debugging('StudioLMS quiz questions failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $degraded = true;
                }
                break;

            default:
                throw new \coding_exception('Unsupported activity type: ' . $type);
        }

        $cmid = $result->coursemodule;

        if ($type === 'label') {
            $viewurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        } else {
            $viewurl = (new \moodle_url('/mod/' . $type . '/view.php', ['id' => $cmid]))->out(false);
        }

        return [
            'cmid'     => $cmid,
            'viewurl'  => $viewurl,
            'degraded' => $degraded,
        ];
    }

    /**
     * Generates clean HTML content for an activity using the AI provider.
     *
     * Falls back to a plain paragraph when the AI is unavailable.
     *
     * @param string $kind Activity kind: 'forum' or 'assign'.
     * @param string $theme Course theme or topic.
     * @param string $title Activity title.
     * @param string $reference Optional reference material.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param bool $degraded Set to true when AI content could not be generated.
     * @return string Clean HTML content.
     */
    private static function generate_html(
        string $kind,
        string $theme,
        string $title,
        string $reference,
        string $bloom,
        bool &$degraded
    ): string {
        $language = current_language();
        $instructions = [
            'forum'  => 'Write a short forum introduction with a guiding discussion question.',
            'assign' => 'Write clear assignment instructions and the expected deliverables.',
        ];
        $instruction = $instructions[$kind] ?? $instructions['forum'];

        try {
            $system = $instruction
                . ' Return ONLY a valid JSON object shaped like {"content": "..."}'
                . ' where content is clean HTML (headings, paragraphs, lists) with no markdown fences.'
                . " Write in the language identified by the code: {$language}.";
            if ($bloom !== 'general' && $bloom !== '') {
                $system .= " Cognitive level (Bloom's taxonomy): {$bloom}.";
            }
            $user = "Course theme: {$theme}\nTitle: {$title}";
            if ($reference !== '') {
                $user .= "\n\nReference material:\n" . mb_substr($reference, 0, 3000);
            }
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
            $html = is_array($decoded) ? trim((string) ($decoded['content'] ?? '')) : '';
            if ($html === '' || trim(html_to_text($html)) === '') {
                throw new \moodle_exception('invalidairesponse', 'local_studiolms');
            }
            return clean_text($html, FORMAT_HTML);
        } catch (\Throwable $e) {
            $degraded = true;
            return \html_writer::tag('p', s($title));
        }
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid'     => new external_value(PARAM_INT, 'Course module id of the created activity.'),
            'viewurl'  => new external_value(PARAM_TEXT, 'URL to view the created activity.'),
            'degraded' => new external_value(PARAM_BOOL, 'True if AI content generation failed.'),
        ]);
    }
}
