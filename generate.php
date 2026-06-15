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
 * StudioLMS course builder wizard entry point.
 *
 * Always runs in the context of an existing course; the teacher reaches it
 * from the course navigation. Renders the mode-selection landing (single
 * activity, section, or full-course wizard) and pre-renders all views so
 * transitions happen client-side without page reloads.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/studiolms:generate', $context);

$url = new moodle_url('/local/studiolms/generate.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('generate_heading', 'local_studiolms'));
$PAGE->set_heading(format_string($course->fullname));

$cancelurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

// Section list for the single-activity form.

$sectionrecords = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
$sections = [];
foreach ($sectionrecords as $sec) {
    $name = ($sec->name !== '' && $sec->name !== null)
        ? format_string($sec->name)
        : get_string('section_number', 'local_studiolms', $sec->section);
    $sections[] = ['num' => (int) $sec->section, 'name' => $name];
}

$typekeys = ['page', 'label', 'quiz', 'forum', 'assign', 'glossary'];
$activitytypes = [];
foreach ($typekeys as $value) {
    $activitytypes[] = [
        'value' => $value,
        'label' => get_string('activity_' . $value, 'local_studiolms'),
    ];
}

// Full-course wizard data (step 1).

$playerhudinstalled = array_key_exists('playerhud', core_component::get_plugin_list('block'));

$bloomdefinitions = [
    'remember'  => 'bloom_remember',
    'understand' => 'bloom_understand',
    'apply'     => 'bloom_apply',
    'analyze'   => 'bloom_analyze',
    'evaluate'  => 'bloom_evaluate',
    'create'    => 'bloom_create',
];
$bloomlevels = [];
foreach ($bloomdefinitions as $value => $stringkey) {
    $bloomlevels[] = [
        'value'   => $value,
        'label'   => get_string($stringkey, 'local_studiolms'),
        'checked' => $value === 'apply',
    ];
}

$profiledefinitions = [
    'conquest'  => 'profile_conquest',
    'narrative' => 'profile_narrative',
    'social'    => 'profile_social',
];
$profiles = [];
foreach ($profiledefinitions as $value => $stringkey) {
    $profiles[] = [
        'value'   => $value,
        'label'   => get_string($stringkey, 'local_studiolms'),
        'checked' => $value === 'narrative',
    ];
}

$step1context = [
    'courseid'   => $course->id,
    'sesskey'    => sesskey(),
    'cancelurl'  => $cancelurl,
    'steplabel'  => get_string('step_of', 'local_studiolms', (object) ['current' => 1, 'total' => 3]),
    'playerhud'  => $playerhudinstalled,
    'bloomlevels' => $bloomlevels,
    'profiles'   => $profiles,
    'hidden'     => true,
];

// AMD modules.

$PAGE->requires->js_call_amd('local_studiolms/wizard_landing', 'init');
$PAGE->requires->js_call_amd('local_studiolms/wizard_step1', 'init');
$PAGE->requires->js_call_amd('local_studiolms/wizard_activity', 'init');
$PAGE->requires->js_call_amd('local_studiolms/wizard_section', 'init');

// Output.

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_studiolms/wizard_landing', [
    'cancelurl'      => $cancelurl,
    'logourl'        => $OUTPUT->image_url('logo', 'local_studiolms')->out(false),
    'landingheading' => get_string('landing_heading', 'local_studiolms', $USER->firstname),
]);
echo $OUTPUT->render_from_template('local_studiolms/wizard_step1', $step1context);
echo $OUTPUT->render_from_template('local_studiolms/wizard_activity', [
    'courseid'      => $course->id,
    'sections'      => $sections,
    'activitytypes' => $activitytypes,
]);
echo \html_writer::tag('div', '', [
    'id'         => 'local-studiolms-section-types',
    'hidden'     => true,
    'data-types' => json_encode($activitytypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
]);
echo $OUTPUT->render_from_template('local_studiolms/wizard_section', [
    'courseid' => $course->id,
    'sections' => $sections,
]);
echo html_writer::div('', 'd-none', ['id' => 'local-studiolms-step2']);
echo $OUTPUT->footer();
