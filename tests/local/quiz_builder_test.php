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

    /**
     * True/false and multiple choice answers written as text keep their meaning.
     *
     * Regression test: the strings "false" and "0" are truthy in PHP, so a question whose answer the
     * AI wrote as "false" was stored with true as the right answer.
     */
    public function test_answers_written_as_text_are_not_inverted(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                ['type' => 'truefalse', 'question' => 'Is it false?', 'answer' => 'false'],
                ['type' => 'truefalse', 'question' => 'Is it true?', 'answer' => 'true'],
                ['type' => 'multichoice', 'question' => 'Pick the right one', 'options' => [
                    ['text' => 'wrong', 'correct' => 'false'],
                    ['text' => 'right', 'correct' => 'true'],
                    ['text' => 'also wrong', 'correct' => '0'],
                ]],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 3);

        $this->assertSame(3, $added);
        $isfalse = $DB->get_record('question', ['name' => 'Is it false?'], '*', MUST_EXIST);
        $istrue = $DB->get_record('question', ['name' => 'Is it true?'], '*', MUST_EXIST);
        $answers = fn(int $id) => $DB->get_records('question_answers', ['question' => $id], 'id ASC', 'answer, fraction');
        $this->assertSame(1.0, (float) $answers($istrue->id)['True']->fraction);
        $this->assertSame(0.0, (float) $answers($isfalse->id)['True']->fraction);
        $this->assertSame(1.0, (float) $answers($isfalse->id)['False']->fraction);
        $multi = $DB->get_record('question', ['name' => 'Pick the right one'], '*', MUST_EXIST);
        $fractions = array_map(fn($a) => (float) $a->fraction, $answers((int) $multi->id));
        $this->assertSame(1.0, $fractions['<p>right</p>'] ?? $fractions['right']);
        $this->assertSame(0.0, $fractions['<p>wrong</p>'] ?? $fractions['wrong']);
    }

    /**
     * An answer the AI did not give in a way that can be read is not turned into "false": the question is left out,
     * since a wrong key is worse than a missing question. The answers written in the language of the course are read.
     */
    public function test_a_true_false_question_with_an_unreadable_answer_is_left_out(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                ['type' => 'truefalse', 'question' => 'No answer given'],
                ['type' => 'truefalse', 'question' => 'Answer in prose', 'answer' => 'talvez'],
                ['type' => 'truefalse', 'question' => 'Verdadeiro written out', 'answer' => 'Verdadeiro'],
                ['type' => 'truefalse', 'question' => 'Falso written out', 'answer' => 'Falso'],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 4);

        $this->assertSame(2, $added);
        $this->assertFalse($DB->record_exists('question', ['name' => 'No answer given']));
        $this->assertFalse($DB->record_exists('question', ['name' => 'Answer in prose']));
        $key = static function (string $name) use ($DB): array {
            $question = $DB->get_record('question', ['name' => $name], '*', MUST_EXIST);
            $answers = $DB->get_records('question_answers', ['question' => $question->id], 'id ASC', 'id, fraction');
            return array_values(array_map(fn($a) => (float) $a->fraction, $answers));
        };
        // The core stores the answers of a true/false question as True, then False.
        $this->assertSame([1.0, 0.0], $key('Verdadeiro written out'));
        $this->assertSame([0.0, 1.0], $key('Falso written out'));
    }

    /**
     * An option whose "correct" cannot be read puts the question out, because it could be the right one; an option
     * with no "correct" at all is a wrong one, and "Sim" is a right one.
     */
    public function test_a_choice_with_an_unreadable_correct_flag_is_left_out(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                ['type' => 'multichoice', 'question' => 'Unreadable flag', 'options' => [
                    ['text' => 'a', 'correct' => 'talvez'], ['text' => 'b', 'correct' => true], ['text' => 'c', 'correct' => false],
                ]],
                ['type' => 'multichoice', 'question' => 'Flag in portuguese', 'options' => [
                    ['text' => 'a', 'correct' => 'Sim'], ['text' => 'b'], ['text' => 'c', 'correct' => 'Não'],
                ]],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 2);

        $this->assertSame(1, $added);
        $this->assertFalse($DB->record_exists('question', ['name' => 'Unreadable flag']));
        $kept = $DB->get_record('question', ['name' => 'Flag in portuguese'], '*', MUST_EXIST);
        $fractions = array_map(
            fn($a) => (float) $a->fraction,
            $DB->get_records('question_answers', ['question' => $kept->id], 'id ASC', 'id, fraction')
        );
        $this->assertSame([1.0, 0.0, 0.0], array_values($fractions));
    }

    /**
     * Questions and fields of the wrong type are skipped instead of failing the whole quiz.
     */
    public function test_questions_with_fields_of_the_wrong_type_are_skipped(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'questions' => [
                'a string instead of a question',
                ['type' => 'truefalse', 'question' => ['x'], 'answer' => true],
                ['type' => ['truefalse'], 'question' => 'Type as a list'],
                ['type' => 'shortanswer', 'question' => 'Answers as text', 'answers' => 'Paris'],
                ['type' => 'multichoice', 'question' => 'Options as text', 'options' => 'a,b'],
                ['type' => 'multichoice', 'question' => 'Option text as a list', 'options' => [
                    ['text' => ['a'], 'correct' => true], ['text' => ['b'], 'correct' => true],
                ]],
                ['type' => 'truefalse', 'question' => 'Feedback as a list', 'answer' => true, 'generalfeedback' => ['x']],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        $added = quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Geo', 'Test', 7);

        $this->assertSame(1, $added);
    }

    /**
     * The default question category of a quiz is made by the core, on every version, and asking again returns the
     * same one.
     *
     * @return void
     */
    public function test_the_default_category_of_a_quiz_is_made_by_the_core_once(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $context = \context_module::instance($quiz->cmid);
        $method = new \ReflectionMethod(quiz_builder::class, 'ensure_question_category');
        $method->setAccessible(true);

        $first = $method->invoke(null, $context);
        $second = $method->invoke(null, $context);

        $this->assertNotEmpty($first->id);
        $this->assertSame((int) $first->id, (int) $second->id);
        $top = question_get_top_category($context->id, true);
        $this->assertSame((int) $top->id, (int) $DB->get_field('question_categories', 'parent', ['id' => $first->id]));
    }
}
