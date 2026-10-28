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

namespace local_studiolms\external;

use core_external\external_api;
use local_studiolms\local\ai_resolver;

/**
 * Tests for the web service that restyles a page or label into a hidden copy.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\external\restyle_activity
 */
final class restyle_activity_test extends \advanced_testcase {
    /**
     * The web service restyles the page for the teacher and returns the hidden copy.
     *
     * @return void
     */
    public function test_the_teacher_gets_the_hidden_copy(): void {
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => '{"blocks":[]}');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'name' => 'Cells', 'content' => '<h2>The cell</h2><p>Text.</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $this->setUser($teacher);

        $result = external_api::clean_returnvalue(
            restyle_activity::execute_returns(),
            restyle_activity::execute($page->cmid)
        );

        $this->assertSame('Cells (StudioLMS)', $result['name']);
        $this->assertNotEquals($page->cmid, $result['cmid']);
        $this->assertStringContainsString('/course/modedit.php', $result['editurl']);
        $this->assertFalse((bool) get_fast_modinfo($course->id)->get_cm($result['cmid'])->visible);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * The web service takes the option to polish the text, off by default.
     *
     * @return void
     */
    public function test_the_polish_option_is_passed_on(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'content' => '<h2>The cell</h2><p>Cells are the unit of life.</p>',
            'contentformat' => FORMAT_HTML,
        ]);
        $this->setUser($teacher);
        $seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen[] = $system;
            return '{"blocks":[{"type":"paragraph","html":"Cells are the unit of life."}]}';
        });

        restyle_activity::execute($page->cmid);
        restyle_activity::execute($page->cmid, true);

        $this->assertStringContainsString('layout planner', $seen[0]);
        $this->assertStringContainsString('instructional designer', $seen[1]);
        $this->assertTrue(isset(restyle_activity::execute_parameters()->keys['polish']));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A student cannot use the web service, and an activity that does not exist is refused.
     *
     * @return void
     */
    public function test_a_student_and_a_missing_activity_are_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($student);

        try {
            restyle_activity::execute($page->cmid);
            $this->fail('A student must not restyle.');
        } catch (\moodle_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->setAdminUser();
        $this->expectException(\dml_exception::class);
        restyle_activity::execute($page->cmid + 1000);
    }
}
