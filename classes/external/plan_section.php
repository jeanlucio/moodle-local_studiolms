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
 * External web service that synchronously plans activities for a course section.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_studiolms\local\ai_json;
use local_studiolms\local\ai_resolver;

/**
 * Returns an AI-generated activity plan for one section without writing anything to the DB.
 */
class plan_section extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'   => new external_value(PARAM_INT, 'Target course id.'),
            'sectionnum' => new external_value(
                PARAM_INT,
                'Target section number; -1 means a new section will be created.'
            ),
            'theme'      => new external_value(PARAM_TEXT, 'Section theme or content focus.'),
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
     * Calls the AI provider to plan activities for the given section.
     *
     * Returns the list for teacher review; nothing is persisted here.
     *
     * @param int $courseid Target course id.
     * @param int $sectionnum Target section number; -1 if a new section will be created.
     * @param string $theme Section theme or content focus.
     * @param string $bloom Bloom's taxonomy level or 'general'.
     * @param string $reference Optional reference material for the AI.
     * @return array Payload with the planned activities.
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $theme,
        string $bloom = 'general',
        string $reference = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
            'theme'      => $theme,
            'bloom'      => $bloom,
            'reference'  => $reference,
        ]);

        $course = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);

        if ($params['sectionnum'] >= 0) {
            $secrecord = $DB->get_record(
                'course_sections',
                ['course' => $course->id, 'section' => $params['sectionnum']],
                '*',
                MUST_EXIST
            );
            $sectionname = ($secrecord->name !== '' && $secrecord->name !== null)
                ? format_string($secrecord->name)
                : get_string('section_number', 'local_studiolms', $params['sectionnum']);
        } else {
            $sectionname = $params['theme'];
        }

        $raw = self::call_ai($sectionname, $params['theme'], $params['bloom'], $params['reference']);

        $activities = [];
        foreach ($raw as $item) {
            $type = $item['type'];
            $activities[] = [
                'type'      => $type,
                'typelabel' => get_string('activity_' . $type, 'local_studiolms'),
                'title'     => $item['title'],
            ];
        }

        return ['activities' => $activities];
    }

    /**
     * Calls the AI and returns a validated list of activity definitions.
     *
     * Falls back to a single page activity on any error.
     *
     * @param string $sectionname The section display name passed to the AI.
     * @param string $theme The course theme.
     * @param string $bloom Bloom's taxonomy level.
     * @param string $reference Optional reference material.
     * @return array Array of {type, title} definitions.
     */
    private static function call_ai(
        string $sectionname,
        string $theme,
        string $bloom,
        string $reference
    ): array {
        $maxattempts = 3;
        $backoffseconds = 2;
        $language = current_language();
        $system = 'Return ONLY a valid JSON array of learning activities for a course section. '
            . 'Example: [{"type":"page","title":"Introduction"},{"type":"quiz","title":"Check"}]. '
            . 'Allowed types: page, quiz, forum, assign, label, glossary. '
            . 'Return between 3 and 6 activities. Titles must be under 80 characters. '
            . "Write all titles in the language identified by the code: {$language}.";

        if ($bloom !== 'general' && $bloom !== '') {
            $system .= " Cognitive level (Bloom's taxonomy): {$bloom}.";
        }

        $user = "Section: {$sectionname}\nCourse theme: {$theme}";
        if ($reference !== '') {
            $user .= "\n\nReference material:\n" . mb_substr($reference, 0, 3000);
        }

        $allowed = ['page', 'quiz', 'forum', 'assign', 'label', 'glossary'];

        for ($attempt = 1; $attempt <= $maxattempts; $attempt++) {
            try {
                $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
                if (!is_array($decoded) || empty($decoded)) {
                    throw new \moodle_exception('invalidairesponse', 'local_studiolms');
                }
                $activities = [];
                foreach ($decoded as $item) {
                    if (!isset($item['type'], $item['title'])) {
                        continue;
                    }
                    $type = in_array($item['type'], $allowed, true) ? $item['type'] : 'page';
                    $activities[] = [
                        'type'  => $type,
                        'title' => mb_substr((string) $item['title'], 0, 80),
                    ];
                }
                if (!empty($activities)) {
                    return $activities;
                }
            } catch (\Throwable $e) {
                debugging('StudioLMS section plan attempt failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            if ($attempt < $maxattempts) {
                sleep($backoffseconds);
            }
        }

        return [['type' => 'page', 'title' => $sectionname]];
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'type'      => new external_value(PARAM_ALPHA, 'Activity type identifier.'),
                    'typelabel' => new external_value(PARAM_TEXT, 'Localised activity type label.'),
                    'title'     => new external_value(PARAM_TEXT, 'Activity title.'),
                ])
            ),
        ]);
    }
}
