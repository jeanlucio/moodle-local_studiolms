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

/**
 * Adds the "Restyle with StudioLMS" entry to the settings of a page.
 *
 * @param settings_navigation $settingsnav The settings navigation.
 * @param context $context The context of the page being viewed.
 * @return void
 */
function local_studiolms_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    global $PAGE;

    // The page object exposes cm only through __get(), so empty() and isset() cannot be used: compare with null.
    $cm = $PAGE->cm;
    if ($context->contextlevel !== CONTEXT_MODULE || $cm === null || $cm->modname !== 'page') {
        return;
    }
    if (!has_all_capabilities(['local/studiolms:restyle', 'moodle/course:manageactivities'], $context)) {
        return;
    }
    $modulesettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }
    $modulesettings->add(
        get_string('restyle_menu', 'local_studiolms'),
        new moodle_url('/local/studiolms/restyle.php', ['cmid' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_studiolms_restyle',
        new pix_icon('icon', '', 'local_studiolms')
    );
}
