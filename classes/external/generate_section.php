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
 * External web service that queues section-level content generation.
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
use local_studiolms\local\ai_resolver;
use local_studiolms\task\generate_section_task;

/**
 * Validates the teacher-approved activity plan and enqueues the background generation task.
 */
class generate_section extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'       => new external_value(PARAM_INT, 'Target course id.'),
            'sectionnum'     => new external_value(
                PARAM_INT,
                'Target section number; -1 means create a new section at the end.'
            ),
            'theme'          => new external_value(
                PARAM_TEXT,
                'Section theme — passed to the task for HTML content generation.'
            ),
            'activitiesjson' => new external_value(
                PARAM_RAW,
                'JSON array of teacher-approved {type, title} activities.'
            ),
            'bloom'          => new external_value(
                PARAM_ALPHA,
                "Bloom's taxonomy level.",
                VALUE_DEFAULT,
                'general'
            ),
            'reference'      => new external_value(
                PARAM_TEXT,
                'Optional reference material for the content generation task.',
                VALUE_DEFAULT,
                ''
            ),
            'wipe'           => new external_value(
                PARAM_BOOL,
                'Delete existing activities in the section before generating.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Validates the request, creates a progress record and queues the generation task.
     *
     * @param int $courseid Target course id.
     * @param int $sectionnum Target section number; -1 to create a new section.
     * @param string $theme Section theme for HTML content generation.
     * @param string $activitiesjson JSON array of approved {type, title} activities.
     * @param string $bloom Bloom's taxonomy level or 'general'.
     * @param string $reference Optional reference material for the AI.
     * @param bool $wipe Delete existing activities in the section first.
     * @return array Payload with the created progress id.
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $theme,
        string $activitiesjson,
        string $bloom = 'general',
        string $reference = '',
        bool $wipe = false
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'       => $courseid,
            'sectionnum'     => $sectionnum,
            'theme'          => $theme,
            'activitiesjson' => $activitiesjson,
            'bloom'          => $bloom,
            'reference'      => $reference,
            'wipe'           => $wipe,
        ]);

        $course = get_course($params['courseid']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);

        // Fail fast: the background task cannot generate without AI.
        if (!ai_resolver::is_available()) {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        }

        if ($params['sectionnum'] >= 0) {
            $DB->get_record(
                'course_sections',
                ['course' => $course->id, 'section' => $params['sectionnum']],
                'id',
                MUST_EXIST
            );
        }

        $activities = json_decode($params['activitiesjson'], true);
        if (!is_array($activities) || empty($activities)) {
            throw new \coding_exception('activitiesjson must be a non-empty JSON array.');
        }

        $now = time();
        $progressid = $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid'    => null,
            'userid'       => $USER->id,
            'step'         => 0,
            'total'        => 0,
            'message'      => '',
            'status'       => 'queued',
            'courseid'     => $course->id,
            'createditems' => json_encode(['cmids' => []]),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $task = new generate_section_task();
        $task->set_custom_data([
            'progressid'     => $progressid,
            'courseid'       => $course->id,
            'sectionnum'     => $params['sectionnum'],
            'theme'          => $params['theme'],
            'bloom'          => $params['bloom'],
            'reference'      => $params['reference'],
            'wipe'           => $params['wipe'],
            'activitiesjson' => $params['activitiesjson'],
        ]);
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
