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
 * External web service that reports the background generation progress.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns the current progress for a generation owned by the requesting user.
 */
class get_progress extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'progressid' => new external_value(PARAM_INT, 'Progress id to poll.'),
        ]);
    }

    /**
     * Reads the progress row after validating ownership and course access.
     *
     * @param int $progressid Progress id to poll.
     * @return array The current progress payload.
     */
    public static function execute(int $progressid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['progressid' => $progressid]);

        $progress = $DB->get_record('local_studiolms_progress', [
            'id' => $params['progressid'],
            'userid' => $USER->id,
        ], '*', MUST_EXIST);

        $context = \context_course::instance($progress->courseid);
        self::validate_context($context);
        require_capability('local/studiolms:generate', $context);

        return [
            'step' => (int) $progress->step,
            'total' => (int) $progress->total,
            'message' => (string) $progress->message,
            'status' => $progress->status,
            'errormsg' => (string) ($progress->errormsg ?? ''),
            'courseid' => (int) $progress->courseid,
        ];
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'step' => new external_value(PARAM_INT, 'Completed steps.'),
            'total' => new external_value(PARAM_INT, 'Total steps.'),
            'message' => new external_value(PARAM_TEXT, 'Current step message.'),
            'status' => new external_value(PARAM_ALPHA, 'queued, running, completed or failed.'),
            'errormsg' => new external_value(PARAM_TEXT, 'Error message when failed.'),
            'courseid' => new external_value(PARAM_INT, 'Target course id.'),
        ]);
    }
}
