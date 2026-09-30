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

    /**
     * Unusable question definitions are skipped, the rest are added, and question text is sanitized.
     *
     * @return void
     */
    public function test_create_skips_unusable_questions_and_sanitizes_text(): void {
        global $DB;

        $evil = '<script>alert(1)</script>';
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                ['type' => 'truefalse', 'question' => 'Valid? ' . $evil, 'answer' => false, 'generalfeedback' => $evil . 'ok'],
                ['type' => 'essay', 'question' => 'Unsupported type'],
                ['type' => 'truefalse', 'question' => ''],
                ['type' => 'shortanswer', 'question' => 'No answers given', 'answers' => ['', 42]],
                ['type' => 'multichoice', 'question' => 'One option only', 'options' => [['text' => 'a', 'correct' => true]]],
                ['type' => 'multichoice', 'question' => 'Nothing correct', 'options' => [
                    ['text' => 'a', 'correct' => false], ['text' => 'b', 'correct' => false],
                ]],
                ['type' => 'multichoice', 'question' => 'Two correct', 'options' => [
                    ['text' => 'a ' . $evil, 'correct' => true], ['text' => 'b', 'correct' => true], ['text' => 'c'],
                ]],
                ['type' => 'shortanswer', 'question' => 'Capital?', 'answers' => ['Paris', ' ', 'paris']],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 8);

        $this->assertSame(3, $added);
        $this->assertSame(3, $DB->count_records('quiz_slots', ['quizid' => $quiz->instance]));
        foreach ($DB->get_records('question') as $question) {
            $this->assertStringNotContainsString('<script', $question->questiontext . $question->generalfeedback);
        }
        foreach ($DB->get_records('question_answers') as $answer) {
            $this->assertStringNotContainsString('<script', $answer->answer);
        }
        $this->assertSame(0, (int) $DB->get_field('qtype_multichoice_options', 'single', [], MUST_EXIST));
        $this->assertSame(2, $DB->count_records('question_answers', ['fraction' => 0.5]));
    }

    /**
     * When the AI call fails outright the exception reaches the caller, which decides how to degrade.
     *
     * @return void
     */
    public function test_create_lets_an_ai_failure_propagate(): void {
        ai_resolver::set_provider_for_testing(static function (string $s, string $u): string {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        });
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $this->expectException(\moodle_exception::class);
        quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 3);
    }
}
