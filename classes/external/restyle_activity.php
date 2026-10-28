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
 * External web service that restyles a page or label into a hidden copy.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_studiolms\local\restyle\service;

/**
 * Restyles an existing page or label: the original stays as it is and the new layout goes to a hidden copy.
 */
class restyle_activity extends external_api {
    /**
     * Describes the parameters accepted by the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the page or label to restyle.'),
            'polish' => new external_value(
                PARAM_BOOL,
                'True to let the AI reorganise and polish the text, false to keep every word.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Restyles the activity into a hidden copy.
     *
     * @param int $cmid Course module id of the page or label.
     * @param bool $polish True to let the AI reorganise and polish the text.
     * @return array The copy: cmid, name, editurl and viewurl.
     */
    public static function execute(int $cmid, bool $polish = false): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'polish' => $polish]);

        [, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        self::validate_context(context_module::instance($cm->id));

        return (array) service::restyle($cm->id, (bool) $params['polish']);
    }

    /**
     * Describes the value returned by the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the hidden copy.'),
            'name' => new external_value(PARAM_TEXT, 'Name of the copy.'),
            'editurl' => new external_value(PARAM_URL, 'Address of the form that edits the copy with the Studio.'),
            'viewurl' => new external_value(PARAM_URL, 'Address that shows the copy.'),
        ]);
    }
}
