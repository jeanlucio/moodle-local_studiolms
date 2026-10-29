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
 * Tests for the rich content of the activities other than the page.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Checks the recipe of each activity: the blocks it is made of, what the AI fills, and what happens when it fails.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(activity_content::class)]
final class activity_content_test extends \advanced_testcase {
    /** @var array An answer that fills the recipe of the forum and of the assignment. */
    private const ANSWER = [
        'intro' => 'Framing text',
        'question' => 'What changed?',
        'steps' => [
            ['title' => 'Write', 'description' => 'A first post'],
            ['title' => 'Reply', 'description' => 'To two colleagues'],
            ['title' => 'Review', 'description' => 'Your own post'],
        ],
        'tip' => 'Be brief',
        'deliverables' => ['A file', 'A link'],
        'criteria' => [['criterion' => 'Clarity', 'expectation' => 'Clear text']],
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => json_encode(self::ANSWER));
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * The forum introduction is a page title, the framing text, the question, three steps and a tip.
     *
     * @return void
     */
    public function test_forum_intro_is_made_of_the_recipe_blocks(): void {
        $degraded = false;

        $html = activity_content::forum_intro('Biology', 'Cells', 'Discuss', '', 'general', $degraded);

        $this->assertFalse($degraded);
        $this->assertStringContainsString('<h2 class="studiolms-heading-h2"', $html);
        $this->assertStringContainsString('Discuss', $html);
        $this->assertStringContainsString('Framing text', $html);
        $this->assertStringContainsString('What changed?', $html);
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="infographicSteps"'));
        $this->assertSame(3, substr_count($html, 'slms-steps__item"'));
        $this->assertStringContainsString('Be brief', $html);
        $this->assertSame(2, substr_count($html, 'data-slms-block-type="callout"'));
    }

    /**
     * The assignment instructions hold the deliverables, the steps and the criteria as a table.
     *
     * @return void
     */
    public function test_assign_intro_holds_deliverables_steps_and_criteria(): void {
        $degraded = false;

        $html = activity_content::assign_intro('Biology', 'Cells', 'Essay', '', 'general', $degraded);

        $this->assertFalse($degraded);
        $this->assertStringContainsString('A file', $html);
        $this->assertStringContainsString('A link', $html);
        $this->assertStringContainsString('data-slms-block-type="infographicSteps"', $html);
        $this->assertStringContainsString('data-slms-block-type="table"', $html);
        $this->assertStringContainsString('Clarity', $html);
        $this->assertStringContainsString('Clear text', $html);
        $this->assertStringContainsString('<h3 class="studiolms-heading-h3"', $html, 'the criteria are a section');
    }

    /**
     * What the AI writes is plain text: markup in it is shown as text, never run.
     *
     * @return void
     */
    public function test_the_text_of_the_ai_is_never_markup(): void {
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => json_encode([
            'intro' => 'Safe<script>alert(1)</script><img src=x onerror=alert(2)>',
            'question' => '"><b onmouseover=alert(3)>Q</b>',
            'steps' => [['title' => '<i>x</i>', 'description' => '<img src=y onerror=alert(4)>']],
        ]));
        $degraded = false;

        $html = activity_content::forum_intro('T', 'S', 'F', '', 'general', $degraded);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b onmouseover', $html);
        $this->assertStringNotContainsString('onerror=', $html);
    }

    /**
     * A field of the wrong type is skipped, and an answer with nothing usable leaves the title as a heading.
     *
     * @return void
     */
    public function test_an_unusable_answer_degrades_to_the_heading(): void {
        foreach (['not json', '{}', '{"intro": ["x"], "steps": "no", "question": {"a": 1}}'] as $answer) {
            ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $answer);
            $degraded = false;

            $html = activity_content::forum_intro('T', 'S', 'Discuss', '', 'general', $degraded);

            $this->assertTrue($degraded, $answer);
            $this->assertStringContainsString('Discuss', $html);
            $this->assertStringNotContainsString('Array', $html);
            $this->assertStringNotContainsString('infographicSteps', $html);
        }
    }

    /**
     * An AI that throws, or that is not configured, degrades instead of failing the whole generation.
     *
     * @return void
     */
    public function test_an_ai_that_fails_degrades(): void {
        ai_resolver::set_provider_for_testing(function (string $system, string $user): string {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        });
        $degraded = false;

        $html = activity_content::assign_intro('T', 'S', 'Essay', '', 'general', $degraded);

        $this->assertDebuggingCalled();
        $this->assertTrue($degraded);
        $this->assertStringContainsString('Essay', $html);
    }

    /**
     * The recipe asks for few steps and rows, whatever the AI sends.
     *
     * @return void
     */
    public function test_the_number_of_steps_and_criteria_is_capped(): void {
        $many = array_fill(0, 12, ['title' => 'Step', 'description' => 'd']);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => json_encode([
            'intro' => 'x', 'steps' => $many,
            'criteria' => array_fill(0, 12, ['criterion' => 'C', 'expectation' => 'e']),
        ]));
        $degraded = false;

        $forum = activity_content::forum_intro('T', 'S', 'F', '', 'general', $degraded);
        $assign = activity_content::assign_intro('T', 'S', 'A', '', 'general', $degraded);

        $this->assertSame(3, substr_count($forum, 'slms-steps__item"'));
        $this->assertSame(4, substr_count($assign, 'slms-steps__item"'));
        $this->assertSame(6, substr_count($assign, '<tr>'), 'a header and five criteria');
    }

    /**
     * The briefing's reference material and the language reach the AI.
     *
     * @return void
     */
    public function test_the_prompt_carries_the_reference_and_the_language(): void {
        $seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen = [$system, $user];
            return json_encode(self::ANSWER);
        });
        $degraded = false;

        activity_content::forum_intro('Biology', 'Cells', 'Discuss', 'Read chapter 3', 'analyze', $degraded);

        $this->assertStringContainsString('Read chapter 3', $seen[1]);
        $this->assertStringContainsString('Cells', $seen[1]);
        $this->assertStringContainsString('analyze', $seen[0]);
        $this->assertStringContainsString(current_language(), $seen[0]);
    }

    /**
     * A label is its title as a heading, and lists the other activities of its section.
     *
     * @return void
     */
    public function test_label_lists_the_activities_of_its_section(): void {
        $alone = activity_content::label('Start here');
        $this->assertStringContainsString('<h3 class="studiolms-heading-h3"', $alone);
        $this->assertStringNotContainsString('data-slms-block-type="callout"', $alone);

        $html = activity_content::label('Start here', ['Start here', 'Read this', '', '<b>Do that</b>']);

        $this->assertStringContainsString('data-slms-block-type="callout"', $html);
        $this->assertStringContainsString('Read this', $html);
        $this->assertSame(2, substr_count($html, '<li>'), 'its own title and the empty one are left out');
        $this->assertStringNotContainsString('<b>Do that', $html);
    }

    /**
     * The quiz introduction shows the numbers of the quiz, and says so when there is no limit.
     *
     * @return void
     */
    public function test_quiz_intro_shows_the_real_numbers(): void {
        $html = activity_content::quiz_intro('Check', 5, 0, 0);

        $this->assertStringContainsString('data-slms-block-type="infographic"', $html);
        $this->assertStringContainsString('>5<', $html);
        $this->assertStringContainsString(get_string('rich_unlimited', 'local_studiolms'), $html);
        $this->assertStringContainsString(get_string('rich_no_limit', 'local_studiolms'), $html);

        $limited = activity_content::quiz_intro('Check', 0, 3, 1800);
        $this->assertStringContainsString('>3<', $limited);
        $this->assertStringContainsString(format_time(1800), $limited);
        $this->assertStringNotContainsString(get_string('rich_quiz_questions', 'local_studiolms'), $limited);
    }

    /**
     * The introduction of a quiz is written once the questions are in, from its settings, and the cache is fresh.
     *
     * @return void
     */
    public function test_write_quiz_intro_stores_the_intro_of_the_quiz(): void {
        global $DB;

        $this->resetAfterTest();
        // Where the filter is installed it would take the place of the numbers; this test is about the fixed numbers.
        set_config('enable_quizinfo', 0, 'filter_studiolms');
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'attempts' => 2, 'timelimit' => 600,
        ]);

        activity_content::write_quiz_intro(
            (object) ['coursemodule' => $quiz->cmid, 'instance' => $quiz->id],
            (int) $course->id,
            'Check',
            4
        );

        $intro = $DB->get_field('quiz', 'intro', ['id' => $quiz->id]);
        $this->assertStringContainsString('data-slms-block-type="infographic"', $intro);
        $this->assertStringContainsString('>4<', $intro);
        $this->assertStringContainsString('>2<', $intro);
        $this->assertEquals(FORMAT_HTML, $DB->get_field('quiz', 'introformat', ['id' => $quiz->id]));
    }

    /**
     * With the widget on, the introduction of a quiz carries the marker of the filter instead of fixed numbers.
     *
     * @return void
     */
    public function test_quiz_intro_can_leave_the_rules_to_the_widget(): void {
        $html = activity_content::quiz_intro('Check', 5, 2, 600, true);

        $this->assertStringContainsString('data-slms-block-type="widgetQuizInfo"', $html);
        $this->assertStringContainsString('<span class="slms-widget-marker">[QUIZINFO]</span>', $html);
        $this->assertStringContainsString('data-slms-state="' . base64_encode(rawurlencode('{}')) . '"', $html);
        $this->assertStringNotContainsString('data-slms-block-type="infographic"', $html);
        $this->assertStringNotContainsString(get_string('rich_quiz_attempts', 'local_studiolms'), $html);
        $this->assertStringContainsString('Check', $html);
    }

    /**
     * The widget is used only where the filter is installed, has the widget on and is active in the course.
     *
     * @return void
     */
    public function test_quiz_rules_are_live_only_where_the_filter_applies(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/filterlib.php');

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->setAdminUser();

        if (!class_exists(\filter_studiolms\widgets::class)) {
            $this->assertFalse(activity_content::quiz_rules_are_live((int) $quiz->cmid), 'no filter, fixed numbers');
            return;
        }

        filter_set_global_state('studiolms', TEXTFILTER_DISABLED);
        $this->assertFalse(activity_content::quiz_rules_are_live((int) $quiz->cmid), 'filter off in the site');

        filter_set_global_state('studiolms', TEXTFILTER_ON);
        $this->assertTrue(activity_content::quiz_rules_are_live((int) $quiz->cmid), 'filter on');

        set_config('enable_quizinfo', 0, 'filter_studiolms');
        $this->assertFalse(activity_content::quiz_rules_are_live((int) $quiz->cmid), 'widget off in the site');
        set_config('enable_quizinfo', 1, 'filter_studiolms');

        filter_set_local_state('studiolms', \context_course::instance($course->id)->id, TEXTFILTER_OFF);
        $this->assertFalse(activity_content::quiz_rules_are_live((int) $quiz->cmid), 'filter off in the course');
    }

    /**
     * Where the filter applies, the written introduction holds the marker, and the numbers are left out.
     *
     * @return void
     */
    public function test_write_quiz_intro_writes_the_marker_where_the_filter_applies(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/filterlib.php');
        if (!class_exists(\filter_studiolms\widgets::class)) {
            $this->markTestSkipped('filter_studiolms not installed in this environment.');
        }
        filter_set_global_state('studiolms', TEXTFILTER_ON);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'attempts' => 2]);

        activity_content::write_quiz_intro(
            (object) ['coursemodule' => $quiz->cmid, 'instance' => $quiz->id],
            (int) $course->id,
            'Check',
            4
        );

        $intro = $DB->get_field('quiz', 'intro', ['id' => $quiz->id]);
        $this->assertStringContainsString('[QUIZINFO]', $intro);
        $this->assertStringNotContainsString('data-slms-block-type="infographic"', $intro);
    }

    /**
     * The glossary introduction is a heading, the sentence of the plugin and a how-to.
     *
     * @return void
     */
    public function test_glossary_intro(): void {
        $html = activity_content::glossary_intro('Terms');

        $this->assertStringContainsString('Terms', $html);
        $this->assertStringContainsString(get_string('glossary_intro', 'local_studiolms'), $html);
        $this->assertStringContainsString(get_string('rich_glossary_howto', 'local_studiolms'), $html);
    }

    /**
     * A step the AI wrote with no title, or that is not a step, is left out of the forum introduction.
     *
     * @return void
     */
    public function test_steps_with_no_title_are_left_out(): void {
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(static fn(): string => json_encode([
            'intro' => 'Framing text',
            'question' => 'What changed?',
            'steps' => [['title' => ''], 'not a step', ['title' => 'Real step', 'description' => 'Do it']],
            'tip' => 'Be brief',
        ]));
        $degraded = false;

        $html = activity_content::forum_intro('Biology', 'Cells', 'Discuss', '', 'general', $degraded);

        $this->assertSame(1, substr_count($html, 'slms-steps__item"'));
        $this->assertStringContainsString('Real step', $html);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * An introduction written after the activity was created records the change: the time moves and the update of the
     * module is logged, so the search index and the observers see it.
     *
     * @return void
     */
    public function test_a_late_introduction_records_the_update_of_the_module(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enable_quizinfo', 0, 'filter_studiolms');
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $DB->set_field('quiz', 'timemodified', 1000, ['id' => $quiz->id]);
        $sink = $this->redirectEvents();

        activity_content::write_quiz_intro(
            (object) ['coursemodule' => $quiz->cmid, 'instance' => $quiz->id],
            (int) $course->id,
            'Check',
            3
        );

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\course_module_updated);
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame((int) $quiz->cmid, (int) reset($events)->objectid);
        $this->assertGreaterThan(1000, (int) $DB->get_field('quiz', 'timemodified', ['id' => $quiz->id]));
    }
}
