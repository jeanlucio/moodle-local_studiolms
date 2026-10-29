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

namespace local_studiolms\local;

use local_studiolms\local\restyle\overview;

/**
 * Tests for the list of the pages and labels that can be restyled.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\restyle\overview
 */
final class restyle_overview_test extends \advanced_testcase {
    /**
     * Only pages and labels are listed, by section, and a section with none is left out.
     *
     * @return void
     */
    public function test_only_pages_and_labels_are_listed_by_section(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'A page', 'section' => 1]);
        $label = $generator->create_module('label', ['course' => $course->id, 'intro' => '<p>A label</p>', 'section' => 1]);
        $generator->create_module('forum', ['course' => $course->id, 'name' => 'A forum', 'section' => 1]);
        $generator->create_module('forum', ['course' => $course->id, 'name' => 'Only forum', 'section' => 3]);
        $this->setAdminUser();

        $sections = overview::sections($course);

        $this->assertCount(1, $sections);
        $this->assertSame([(int) $page->cmid, (int) $label->cmid], array_column($sections[0]['activities'], 'cmid'));
        $this->assertSame(
            [get_string('modulename', 'page'), get_string('modulename', 'label')],
            array_column($sections[0]['activities'], 'type')
        );
        $this->assertSame('Restyle A page', $sections[0]['activities'][0]['buttonlabel']);
    }

    /**
     * The hidden state and the highlighted activity are told, and an activity in deletion is not listed.
     *
     * @return void
     */
    public function test_hidden_highlighted_and_deleted_activities(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator();
        $hidden = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $shown = $generator->create_module('page', ['course' => $course->id]);
        $this->setAdminUser();

        $activities = overview::sections($course, (int) $shown->cmid)[0]['activities'];

        $this->assertSame([true, false], array_column($activities, 'hidden'));
        $this->assertSame([false, true], array_column($activities, 'highlight'));
        $this->assertSame((int) $hidden->cmid, $activities[0]['cmid']);
    }

    /**
     * The name is plain text, shortened, because the template escapes it once.
     *
     * @return void
     */
    public function test_the_name_is_plain_text_and_short(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator();
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Salt & <b>pepper</b>']);
        $generator->create_module('page', ['course' => $course->id, 'name' => str_repeat('long ', 40)]);
        $this->setAdminUser();

        $activities = overview::sections($course)[0]['activities'];

        $this->assertSame('Salt & pepper', $activities[0]['name']);
        $this->assertLessThanOrEqual(90, \core_text::strlen($activities[1]['name']));
        $this->assertStringEndsWith('…', $activities[1]['name']);
    }

    /**
     * A student sees nothing that is hidden from them.
     *
     * @return void
     */
    public function test_a_student_is_not_shown_hidden_activities(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $this->setUser($student);

        $this->assertSame([], overview::sections($course));
    }

    /**
     * The name of a section is plain text, since the template escapes it once.
     *
     * @return void
     */
    public function test_the_name_of_a_section_is_plain_text(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('course_sections', 'name', 'P&D', ['course' => $course->id, 'section' => 1]);
        rebuild_course_cache($course->id, true);
        $this->setAdminUser();

        $this->assertSame('P&D', overview::sections($course)[0]['name']);
    }
}
