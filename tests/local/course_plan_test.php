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
 * Tests for the course plan page, which is built from the outline of the course.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the plan of the course: what it lists must be what the course really holds.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_plan::class)]
final class course_plan_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Builds the plan of a two-section course.
     *
     * @param array $sections The sections, or the default ones.
     * @param array $objectives The objectives, or the default ones.
     * @param bool $degraded Set to true when the AI wrote none of the sentences.
     * @return string The HTML.
     */
    private function build(array $sections = [], array $objectives = ['Know A', 'Do B'], bool &$degraded = false): string {
        $course = $this->getDataGenerator()->create_course();
        $sections = $sections ?: [
            ['title' => 'First part', 'activities' => [
                ['type' => 'glossary', 'title' => 'Terms'],
                ['type' => 'page', 'title' => 'Read A'],
                ['type' => 'quiz', 'title' => 'Quiz A'],
            ]],
            ['title' => 'Second part', 'activities' => [
                ['type' => 'assign', 'title' => 'Task B'],
                ['type' => 'forum', 'title' => 'Talk B'],
            ]],
        ];
        return course_plan::render(\context_course::instance($course->id), 'Botany', 'Course X', $sections, $objectives, $degraded);
    }

    /**
     * The plan lists the real sections, objectives and graded activities, with nothing left to fill in.
     */
    public function test_plan_is_built_from_the_outline_with_nothing_left_to_fill(): void {
        ai_resolver::set_provider_for_testing(fn(string $s, string $u): string => json_encode([
            'general' => 'Understand plants.',
            'sections' => ['About the first part.', 'About the second part.'],
        ]));

        $degraded = true;
        $html = $this->build([], ['Know A', 'Do B'], $degraded);

        $this->assertFalse($degraded);
        $said = [
            'Course X', 'First part', 'Second part', 'Know A', 'Do B', 'Understand plants.', 'About the second part.',
        ];
        foreach ($said as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringContainsString('Quiz A', $html);
        $this->assertStringContainsString('Task B', $html);
        $this->assertStringNotContainsString('Talk B', $html, 'A forum is not graded, so it is not in the assessment table.');
        $this->assertDoesNotMatchRegularExpression('/\[[^\]]+\]/', strip_tags($html), 'No placeholder is left.');
        foreach (['stylizedHeading', 'callout', 'infographicSteps', 'table'] as $type) {
            $this->assertStringContainsString('data-slms-block-type="' . $type . '"', $html, $type);
        }
    }

    /**
     * Without the AI the plan is still complete: only the sentences the AI writes are missing.
     */
    public function test_plan_without_the_ai_keeps_the_outline(): void {
        ai_resolver::set_provider_for_testing(function (string $s, string $u): string {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        });

        $degraded = false;
        $html = $this->build([], ['Know A', 'Do B'], $degraded);

        $this->assertTrue($degraded);
        $this->assertDebuggingCalled();
        $this->assertStringContainsString('First part', $html);
        $this->assertStringContainsString('Know A', $html);
        $this->assertStringContainsString('Read A', $html, 'The section lists its activities when the AI wrote no sentence.');
    }

    /**
     * What the outline and the AI say is escaped once and never becomes markup.
     */
    public function test_plan_escapes_what_the_outline_and_the_ai_say(): void {
        ai_resolver::set_provider_for_testing(fn(string $s, string $u): string => json_encode([
            'general' => 'Plants <script>alert(1)</script> & trees',
            'sections' => ['<img src=x onerror=alert(2)>'],
        ]));

        $html = $this->build([['title' => 'Q & A <b>x</b>', 'activities' => [['type' => 'quiz', 'title' => 'T & U']]]]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<b>x', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('Q &amp; A', $html);
    }

    /**
     * An activity type the builder does not know is a page in the plan too, as it is in the course.
     */
    public function test_plan_names_an_unknown_activity_type_as_a_page(): void {
        ai_resolver::set_provider_for_testing(fn(string $s, string $u): string => '{}');

        $html = $this->build([['title' => 'Only', 'activities' => [['type' => 'video', 'title' => 'Clip']]]]);

        $this->assertStringContainsString(get_string('activity_page', 'local_studiolms') . ': 1', $html);
    }

    /**
     * A course with nothing graded has no assessment table, and one without objectives has no objectives box.
     */
    public function test_plan_leaves_out_what_the_course_does_not_have(): void {
        ai_resolver::set_provider_for_testing(fn(string $s, string $u): string => '{}');

        $html = $this->build([['title' => 'Only', 'activities' => [['type' => 'page', 'title' => 'Read']]]], []);

        $this->assertStringNotContainsString('data-slms-block-type="table"', $html);
        $this->assertSame(0, substr_count($html, '🎯'));
    }
}
