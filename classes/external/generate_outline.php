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
 * External web service that generates a course outline (wizard step 2).
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_studiolms\local\outline_generator;

/**
 * Generates and persists a draft outline from the wizard briefing.
 */
class generate_outline extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course id.'),
            'theme' => new external_value(PARAM_TEXT, 'Course theme or content focus.'),
            'reference' => new external_value(PARAM_TEXT, 'Optional reference material.', VALUE_DEFAULT, ''),
            'bloom' => new external_value(PARAM_ALPHA, "Predominant Bloom's level.", VALUE_DEFAULT, 'general'),
            'structure' => new external_value(PARAM_ALPHA, 'Structure preset: free or abc.', VALUE_DEFAULT, 'free'),
            'mode' => new external_value(PARAM_ALPHA, 'Generation mode: standard or gamified.', VALUE_DEFAULT, 'standard'),
            'profile' => new external_value(PARAM_ALPHA, 'Gamification profile.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Generates the outline, stores it as a draft and returns it for review.
     *
     * @param int $courseid Target course id.
     * @param string $theme Course theme or content focus.
     * @param string $reference Optional reference material.
     * @param string $bloom Predominant Bloom's level.
     * @param string $structure Structure preset.
     * @param string $mode Generation mode.
     * @param string $profile Gamification profile.
     * @return array Outline payload with outlineid, objectives and sections.
     */
    public static function execute(
        int $courseid,
        string $theme,
        string $reference = '',
        string $bloom = 'general',
        string $structure = 'free',
        string $mode = 'standard',
        string $profile = ''
    ): array {
        global $DB, $USER;

        [
            'courseid' => $courseid,
            'theme' => $theme,
            'reference' => $reference,
            'bloom' => $bloom,
            'structure' => $structure,
            'mode' => $mode,
            'profile' => $profile,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'theme' => $theme,
            'reference' => $reference,
            'bloom' => $bloom,
            'structure' => $structure,
            'mode' => $mode,
            'profile' => $profile,
        ]);

        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);

        $briefing = [
            'theme' => $theme,
            'reference' => $reference,
            'bloom' => $bloom,
            'structure' => $structure,
            'mode' => $mode,
            'profile' => $profile,
        ];

        $outline = outline_generator::generate($briefing);

        $now = time();
        $record = (object) [
            'userid' => $USER->id,
            'status' => 'reviewed',
            'courseid' => $course->id,
            'briefingjson' => json_encode($briefing),
            'outlinejson' => json_encode($outline),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $outlineid = $DB->insert_record('local_studiolms_outline', $record);

        $sections = [];
        foreach ($outline['sections'] as $section) {
            $activities = [];
            foreach ($section['activities'] as $activity) {
                $activities[] = [
                    'type' => $activity['type'],
                    'typelabel' => get_string('activity_' . $activity['type'], 'local_studiolms'),
                    'title' => $activity['title'],
                ];
            }
            $sections[] = [
                'title' => $section['title'],
                'activities' => $activities,
            ];
        }

        return [
            'outlineid' => $outlineid,
            'objectives' => $outline['objectives'],
            'sections' => $sections,
        ];
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'outlineid' => new external_value(PARAM_INT, 'Stored outline id.'),
            'objectives' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A learning objective.')
            ),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Section title.'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'type' => new external_value(PARAM_ALPHA, 'Activity type.'),
                            'typelabel' => new external_value(PARAM_TEXT, 'Localised activity type label.'),
                            'title' => new external_value(PARAM_TEXT, 'Activity title.'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
