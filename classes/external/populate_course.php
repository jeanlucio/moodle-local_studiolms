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
 * External web service that queues the course population (wizard step 3).
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
use local_studiolms\local\ai_resolver;
use local_studiolms\task\generate_course_task;

/**
 * Persists the reviewed outline and enqueues the background generation task.
 */
class populate_course extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course id.'),
            'outlineid' => new external_value(PARAM_INT, 'Reviewed outline id.'),
            'wipe' => new external_value(PARAM_BOOL, 'Whether to wipe the course first.', VALUE_DEFAULT, false),
            'objectives' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A learning objective.')
            ),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Section title.'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'type' => new external_value(PARAM_ALPHA, 'Activity type.'),
                            'title' => new external_value(PARAM_TEXT, 'Activity title.'),
                        ])
                    ),
                ])
            ),
        ]);
    }

    /**
     * Stores the edited outline and queues the generation task.
     *
     * @param int $courseid Target course id.
     * @param int $outlineid Reviewed outline id.
     * @param bool $wipe Whether to wipe the course first.
     * @param array $objectives Learning objectives.
     * @param array $sections Sections with their activities.
     * @return array Payload with the created progress id.
     */
    public static function execute(
        int $courseid,
        int $outlineid,
        bool $wipe,
        array $objectives,
        array $sections
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'outlineid' => $outlineid,
            'wipe' => $wipe,
            'objectives' => $objectives,
            'sections' => $sections,
        ]);

        $course = get_course($params['courseid']);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);
        if ($params['wipe']) {
            require_capability('moodle/course:manageactivities', $context);
        }

        // Fail fast: the background task cannot generate without AI.
        if (!ai_resolver::is_available()) {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        }

        $outline = $DB->get_record('local_studiolms_outline', [
            'id' => $params['outlineid'],
            'userid' => $USER->id,
            'courseid' => $course->id,
        ], '*', MUST_EXIST);

        $outline->outlinejson = json_encode([
            'objectives' => $params['objectives'],
            'sections' => $params['sections'],
        ]);
        $outline->timemodified = time();
        $DB->update_record('local_studiolms_outline', $outline);

        $now = time();
        $progressid = $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid' => $outline->id,
            'userid' => $USER->id,
            'step' => 0,
            'total' => 0,
            'message' => '',
            'status' => 'queued',
            'courseid' => $course->id,
            'createditems' => '[]',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $task = new generate_course_task();
        $task->set_custom_data(['progressid' => $progressid, 'wipe' => $params['wipe'] ? 1 : 0]);
        $task->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($task);

        return ['progressid' => $progressid];
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'progressid' => new external_value(PARAM_INT, 'Created progress id.'),
        ]);
    }
}
