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
 * Lists the pages and labels of a course so the teacher can restyle one into a hidden copy.
 *
 * Reached from the StudioLMS landing of the course (courseid) or from the settings of an activity (cmid).
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
if ($cmid) {
    [$course] = get_course_and_cm_from_cmid($cmid);
} else {
    $course = get_course(required_param('courseid', PARAM_INT));
}
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/studiolms:restyle', $context);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/local/studiolms/restyle.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('restyle_heading', 'local_studiolms'));
$PAGE->set_heading(format_string($course->fullname));

$backurl = (new moodle_url('/local/studiolms/generate.php', ['courseid' => $course->id]))->out(false);

// A course that has the AI tools turned off is not offered the restyle: nothing would be generated.
if (!\local_studiolms\local\ai_resolver::is_enabled_in_context($context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('aidisabledincourse', 'local_studiolms'), \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->single_button($backurl, get_string('back_to_course', 'local_studiolms'), 'get');
    echo $OUTPUT->footer();
    exit;
}

// Restyling needs the AI, so stop with the same guidance as the wizard instead of failing on the first click.
if (!\local_studiolms\local\ai_resolver::is_available()) {
    $isadmin = has_capability('moodle/site:config', context_system::instance());
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_studiolms/wizard_noai', [
        'heading'        => get_string('noai_heading', 'local_studiolms'),
        'intro'          => get_string('noai_intro', 'local_studiolms'),
        'isadmin'        => $isadmin,
        'adminhint'      => get_string('noai_admin', 'local_studiolms'),
        'adminlink'      => (new moodle_url('/admin/settings.php', ['section' => 'aiprovider']))->out(false),
        'adminlinklabel' => get_string('noai_adminlink', 'local_studiolms'),
        'teacherhint'    => get_string('noai_teacher', 'local_studiolms'),
        'cancelurl'      => $backurl,
        'backlabel'      => get_string('back_to_course', 'local_studiolms'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

$sections = \local_studiolms\local\restyle\overview::sections($course, $cmid);

$PAGE->requires->js_call_amd(
    'local_studiolms/ai_policy',
    'init',
    [\local_studiolms\local\ai_resolver::policy_agreed((int) $USER->id)]
);
$PAGE->requires->js_call_amd('local_studiolms/restyle', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studiolms/restyle_list', [
    'heading' => get_string('restyle_heading', 'local_studiolms'),
    'intro' => get_string('restyle_intro', 'local_studiolms'),
    'notice' => get_string('restyle_ai_notice', 'local_studiolms'),
    'polishlabel' => get_string('restyle_polish', 'local_studiolms'),
    'polishhelp' => get_string('restyle_polish_help', 'local_studiolms'),
    'none' => get_string('restyle_none', 'local_studiolms'),
    'hassections' => !empty($sections),
    'sections' => $sections,
    'backurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
]);
echo $OUTPUT->footer();
