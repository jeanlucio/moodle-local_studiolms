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
 * Library functions for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the "Fill with AI (Studio)" entry to the course navigation.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course being viewed.
 * @param context_course $context The course context.
 * @return void
 */
function local_studiolms_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/studiolms:generate', $context)) {
        return;
    }

    $navigation->add(
        get_string('fillwithai', 'local_studiolms'),
        new moodle_url('/local/studiolms/generate.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_studiolms_generate',
        new pix_icon('icon', '', 'local_studiolms')
    );
}
