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

/**
 * Tests that every call to the AI of a generation says where the text is for.
 *
 * The context is what makes the switch for AI tools of the course hold for the whole of a long run, and what makes
 * core_ai log the use under the course and not under the site.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\ai_resolver
 * @covers \local_studiolms\local\page_builder
 * @covers \local_studiolms\local\activity_content
 * @covers \local_studiolms\local\glossary_builder
 * @covers \local_studiolms\local\quiz_builder
 * @covers \local_studiolms\local\outline_generator
 * @covers \local_studiolms\local\restyle\polisher
 * @covers \local_studiolms\external\generate_outline
 * @covers \local_studiolms\external\plan_section
 */
final class ai_context_test extends \advanced_testcase {
    /** @var \context[] The context each call to the AI came with, in order; null for a call with none. */
    private array $seen = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user, ?\context $context = null): string {
            $this->seen[] = $context;
            // A usable plan for a section, so that the call does not end in the debugging message of a bad answer.
            return str_contains($system, 'learning activities') ? '[{"type":"page","title":"A page"}]' : '{}';
        });
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Asserts that there was at least one call and that every one came with the given context.
     *
     * @param \context $expected The context of the course or of the activity.
     * @return void
     */
    private function assert_every_call_is_for(\context $expected): void {
        $this->assertNotEmpty($this->seen, 'The AI was not called.');
        foreach ($this->seen as $context) {
            $this->assertNotNull($context, 'A call to the AI came with no context.');
            $this->assertSame($expected->id, $context->id);
        }
    }

    /**
     * The page is planned for the course.
     *
     * @return void
     */
    public function test_a_page_is_planned_for_the_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        page_builder::render($context, 'Botany', 'Roots', 'Soil', []);

        $this->assert_every_call_is_for($context);
    }

    /**
     * The introduction of a forum and of an assignment are written for the course.
     *
     * @return void
     */
    public function test_the_text_of_a_forum_and_of_an_assignment_is_written_for_the_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        activity_content::forum_intro($context, 'Botany', 'Roots', 'Discuss');
        activity_content::assign_intro($context, 'Botany', 'Roots', 'Write');

        $this->assertCount(2, $this->seen);
        $this->assert_every_call_is_for($context);
    }

    /**
     * The terms of a glossary are written for the course that gets the glossary.
     *
     * @return void
     */
    public function test_the_terms_of_a_glossary_are_written_for_the_course(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        glossary_builder::create($course, 0, 'Terms', 'Botany');

        $this->assert_every_call_is_for(\context_course::instance($course->id));
    }

    /**
     * The questions of a quiz are written for the quiz.
     *
     * @return void
     */
    public function test_the_questions_of_a_quiz_are_written_for_the_quiz(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $quiz = course_builder::add_quiz($course, 0, 'Test', '<p>i</p>');

        quiz_builder::create($quiz->coursemodule, $quiz->instance, 'Botany', 'Test', 3);

        $this->assert_every_call_is_for(\context_module::instance($quiz->coursemodule));
    }

    /**
     * The outline and the plan of a section are asked for the course.
     *
     * @return void
     */
    public function test_the_outline_and_the_plan_of_a_section_are_asked_for_the_course(): void {
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        try {
            \local_studiolms\external\generate_outline::execute($course->id, 'Botany');
        } catch (\moodle_exception $e) {
            // The empty answer of the test provider is no outline; only the call matters here.
            $this->assertNotEmpty($this->seen);
        }
        \local_studiolms\external\plan_section::execute($course->id, 1, 'Botany');

        $this->assert_every_call_is_for(\context_course::instance($course->id));
    }

    /**
     * The new text of a page being restyled is written for the course it is in.
     *
     * @return void
     */
    public function test_the_text_of_a_restyle_is_written_for_the_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        restyle\polisher::polish(restyle\segmenter::split('<h2>Roots</h2><p>Soil text.</p>'), 5, $context);

        $this->assert_every_call_is_for($context);
    }

    /**
     * No call of the generation leaves the context out. A call added later that forgets it is caught here, which
     * the tests above cannot do for code they do not reach.
     *
     * @return void
     */
    public function test_no_call_to_generate_text_leaves_the_context_out(): void {
        global $CFG;

        $dir = $CFG->dirroot . '/local/studiolms/classes';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $found = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'ai_resolver.php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $offset = 0;
            while (($at = strpos($source, 'ai_resolver::generate_text(', $offset)) !== false) {
                $found++;
                $offset = $at + 1;
                // The call, up to the statement that ends it, holds the third argument.
                $call = substr($source, $at, strpos($source, ';', $at) - $at);
                $this->assertStringContainsString(
                    '$context',
                    $call,
                    $file->getFilename() . ' calls generate_text() without the context.'
                );
            }
        }
        $this->assertGreaterThan(5, $found, 'The scan found no calls: the check is not looking at the code.');
    }
}
