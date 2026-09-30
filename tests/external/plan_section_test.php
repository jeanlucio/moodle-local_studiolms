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
 * Tests for the plan_section web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;

/**
 * Unit tests for the section planning web service: permission, section scope and AI handling.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plan_section::class)]
final class plan_section_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Creates a course with an editing teacher who is logged in.
     *
     * @return \stdClass The course.
     */
    private function course_with_teacher(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        return $course;
    }

    /**
     * Valid activities are returned with a label, unknown types become pages and titles are capped at 80.
     */
    public function test_activities_are_validated_and_labelled(): void {
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => json_encode([
            ['type' => 'quiz', 'title' => 'Check'],
            ['type' => 'video', 'title' => str_repeat('a', 120)],
            ['type' => 'forum'],
            'not an item',
        ]));
        $course = $this->course_with_teacher();

        $result = plan_section::execute($course->id, 1, 'Loops');

        $this->assertCount(2, $result['activities']);
        $this->assertSame('quiz', $result['activities'][0]['type']);
        $this->assertSame(get_string('activity_quiz', 'local_studiolms'), $result['activities'][0]['typelabel']);
        $this->assertSame('page', $result['activities'][1]['type']);
        $this->assertSame(80, mb_strlen($result['activities'][1]['title']));
    }

    /**
     * Nothing is written: planning only asks the AI.
     */
    public function test_planning_writes_nothing(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(
            static fn(string $system, string $user): string => '[{"type":"page","title":"Intro"}]'
        );
        $course = $this->course_with_teacher();
        $before = $DB->count_records('course_modules');

        plan_section::execute($course->id, 1, 'Loops');

        $this->assertSame($before, $DB->count_records('course_modules'));
        $this->assertSame(0, $DB->count_records('local_studiolms_progress'));
    }

    /**
     * The section name reaches the prompt, and -1 (a section still to be created) uses the theme instead.
     */
    public function test_prompt_names_the_section_or_falls_back_to_the_theme(): void {
        global $DB;

        $users = [];
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$users): string {
            $users[] = $user;
            return '[{"type":"page","title":"Intro"}]';
        });
        $course = $this->course_with_teacher();
        $DB->set_field('course_sections', 'name', 'Unit one', ['course' => $course->id, 'section' => 1]);

        plan_section::execute($course->id, 1, 'Loops');
        plan_section::execute($course->id, -1, 'Recursion');

        $this->assertStringContainsString('Section: Unit one', $users[0]);
        $this->assertStringContainsString('Section: Recursion', $users[1]);
    }

    /**
     * Regression guard for the section scope: a section that exists only in another course is refused.
     */
    public function test_section_of_another_course_is_refused(): void {
        $this->getDataGenerator()->create_course(['numsections' => 4]);
        $course = $this->course_with_teacher();

        $this->expectException(\dml_missing_record_exception::class);
        plan_section::execute($course->id, 3, 'Loops');
    }

    /**
     * A user without the capability is refused before the AI is called.
     */
    public function test_requires_generate_capability(): void {
        $calls = 0;
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$calls): string {
            $calls++;
            return '[]';
        });
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        try {
            plan_section::execute($course->id, 1, 'Loops');
            $this->fail('A student must not be able to plan a section.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(0, $calls);
        }
    }

    /**
     * When the AI keeps failing, a single page named after the section is returned instead of an error.
     */
    public function test_falls_back_to_one_page_when_the_ai_fails(): void {
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => 'not json');
        $course = $this->course_with_teacher();

        $result = plan_section::execute($course->id, 1, 'Loops');

        $this->assertDebuggingCalledCount(3);
        $this->assertCount(1, $result['activities']);
        $this->assertSame('page', $result['activities'][0]['type']);
        $this->assertSame(get_string('section_number', 'local_studiolms', 1), $result['activities'][0]['title']);
    }
}
