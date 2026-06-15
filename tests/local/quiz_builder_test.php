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
 * Tests for the quiz builder.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the quiz builder.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_builder::class)]
final class quiz_builder_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Valid AI questions of each supported type are added to the quiz.
     *
     * @return void
     */
    public function test_create_adds_questions(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                [
                    'type' => 'multichoice',
                    'question' => 'Which is a loop?',
                    'options' => [
                        ['text' => 'for', 'correct' => true],
                        ['text' => 'int', 'correct' => false],
                    ],
                ],
                ['type' => 'truefalse', 'question' => 'PHP is a language.', 'answer' => true],
                ['type' => 'shortanswer', 'question' => 'Keyword to define a function?', 'answers' => ['function']],
            ],
        ]));

        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Programming', 'Test', 3);
        $this->assertSame(3, $added);

        global $DB;
        $slots = $DB->count_records('quiz_slots', ['quizid' => $quiz->instance]);
        $this->assertSame(3, $slots);
    }

    /**
     * When the AI returns no questions, create() reports zero and adds nothing.
     *
     * @return void
     */
    public function test_create_with_no_questions_returns_zero(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => '{"questions": []}');

        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $this->assertSame(0, quiz_builder::create($quiz->coursemodule, $quiz->instance, 'T', 'Test', 3));
    }
}
