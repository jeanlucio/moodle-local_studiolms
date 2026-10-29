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

namespace local_studiolms;

use advanced_testcase;
use navigation_node;

/**
 * Tests for the navigation callbacks of lib.php.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::local_studiolms_extend_settings_navigation
 */
final class lib_test extends advanced_testcase {
    /**
     * Builds the real settings navigation of an activity, the way core builds it on a page.
     *
     * Initialising it already runs this plugin's callback, so the callback is never called by hand.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $cm The course module record.
     * @return \settings_navigation
     */
    private function build_settings_navigation(\stdClass $course, \stdClass $cm): \settings_navigation {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
        $settingsnav = new \settings_navigation($PAGE);
        $settingsnav->initialise();
        return $settingsnav;
    }

    /**
     * Makes a course with an activity and a user enrolled with a role.
     *
     * @param string $modname The module of the activity.
     * @param string $role The role of the user.
     * @return array The course, the course module record and the user.
     */
    private function activity_and_user(string $modname, string $role): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module($modname, ['course' => $course->id]);
        $cm = get_coursemodule_from_instance($modname, $activity->id, $course->id, false, MUST_EXIST);
        $user = $generator->create_and_enrol($course, $role);
        return [$course, $cm, $user];
    }

    /**
     * The teacher gets the entry in the settings of a page, pointing at the restyle list for that page.
     *
     * @return void
     */
    public function test_the_teacher_gets_the_entry_in_a_page(): void {
        $this->resetAfterTest();
        [$course, $cm, $teacher] = $this->activity_and_user('page', 'editingteacher');
        $this->setUser($teacher);

        $settingsnav = $this->build_settings_navigation($course, $cm);

        $node = $settingsnav->find('local_studiolms_restyle', navigation_node::TYPE_SETTING);
        $this->assertNotFalse($node);
        $this->assertStringContainsString('/local/studiolms/restyle.php', $node->action->out(false));
        $this->assertStringContainsString('cmid=' . $cm->id, $node->action->out(false));
        $this->assertSame(get_string('restyle_menu', 'local_studiolms'), $node->text);
    }

    /**
     * A student, a teacher who lost the capability, a label and a forum get no entry.
     *
     * @return void
     */
    public function test_nobody_else_and_no_other_activity_gets_the_entry(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $cm, $student] = $this->activity_and_user('page', 'student');
        $this->setUser($student);
        $this->assertFalse(
            $this->build_settings_navigation($course, $cm)->find('local_studiolms_restyle', navigation_node::TYPE_SETTING)
        );

        [$course, $cm, $teacher] = $this->activity_and_user('page', 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('local/studiolms:restyle', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id, true);
        $this->setUser($teacher);
        $this->assertFalse(
            $this->build_settings_navigation($course, $cm)->find('local_studiolms_restyle', navigation_node::TYPE_SETTING)
        );

        foreach (['label', 'forum'] as $modname) {
            [$course, $cm, $teacher] = $this->activity_and_user($modname, 'editingteacher');
            $this->setUser($teacher);
            $this->assertFalse(
                $this->build_settings_navigation($course, $cm)->find('local_studiolms_restyle', navigation_node::TYPE_SETTING),
                $modname
            );
        }
    }

    /**
     * A page whose settings navigation has no module settings node gets no entry, and nothing breaks.
     *
     * @return void
     */
    public function test_no_entry_without_the_module_settings_node(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/local/studiolms/lib.php');
        [$course, $cm, $teacher] = $this->activity_and_user('page', 'editingteacher');
        $this->setUser($teacher);
        $PAGE = new \moodle_page();
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $settingsnav = $this->createMock(\settings_navigation::class);
        $settingsnav->expects($this->once())->method('find')->willReturn(false);

        local_studiolms_extend_settings_navigation($settingsnav, \context_module::instance($cm->id));
    }
}
