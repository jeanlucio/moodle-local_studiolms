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
 * Web service function declarations for the local_studiolms plugin.
 *
 * The wizard web services (generate_outline, populate_course, get_progress)
 * are declared here as they are implemented in later phases of the roadmap.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_studiolms_generate_outline' => [
        'classname' => 'local_studiolms\external\generate_outline',
        'methodname' => 'execute',
        'description' => 'Generates a course outline from the wizard briefing.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/studiolms:generate',
    ],
];
