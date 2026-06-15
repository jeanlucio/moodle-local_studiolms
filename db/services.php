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

    'local_studiolms_populate_course' => [
        'classname' => 'local_studiolms\external\populate_course',
        'methodname' => 'execute',
        'description' => 'Queues the background task that populates the course from the outline.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/studiolms:generate',
    ],

    'local_studiolms_get_progress' => [
        'classname' => 'local_studiolms\external\get_progress',
        'methodname' => 'execute',
        'description' => 'Returns the progress of a background course generation.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/studiolms:generate',
    ],

    'local_studiolms_plan_section' => [
        'classname'    => 'local_studiolms\external\plan_section',
        'methodname'   => 'execute',
        'description'  => 'Plans activities for a course section using the AI provider.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/studiolms:generate',
    ],

    'local_studiolms_generate_section' => [
        'classname' => 'local_studiolms\external\generate_section',
        'methodname' => 'execute',
        'description' => 'Queues the background task that populates one existing section with AI activities.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/studiolms:generate',
    ],

    'local_studiolms_generate_activity' => [
        'classname' => 'local_studiolms\external\generate_activity',
        'methodname' => 'execute',
        'description' => 'Generates a single activity synchronously in an existing course section.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/studiolms:generate',
    ],
];
