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
 * Tests for the generate_activity web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;

/**
 * Unit tests for the single-activity web service's core capability checks.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_activity::class)]
final class generate_activity_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(
            static fn(string $system, string $user): string => '{"content": "<p>x</p>"}'
        );
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Creates a course with an editing teacher whose role is denied one capability.
     *
     * @param string $capability The capability to prevent for the editing teacher role.
     * @return array [course, teacher].
     */
    private function teacher_denied(string $capability): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability($capability, CAP_PREVENT, $roleid, \context_course::instance($course->id)->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        return [$course, $teacher];
    }

    /**
     * Regression test: local/studiolms:generate used to create any activity type regardless of the
     * core mod/<type>:addinstance capability, so a role denied forums could still create them.
     */
    public function test_activity_requires_addinstance_for_its_type(): void {
        global $DB;

        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        try {
            generate_activity::execute($course->id, 1, 'forum', 'Discuss', 'Theme', 'general', '');
            $this->fail('Creating a forum without mod/forum:addinstance must be refused.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(0, $DB->count_records('forum', ['course' => $course->id]));
        }
    }

    /**
     * Activity creation is gated by moodle/course:manageactivities.
     */
    public function test_activity_requires_manageactivities(): void {
        [$course, $teacher] = $this->teacher_denied('moodle/course:manageactivities');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        generate_activity::execute($course->id, 1, 'label', 'Intro', 'Theme', 'general', '');
    }

    /**
     * A type the user may add is still created.
     */
    public function test_activity_is_created_when_core_capabilities_are_held(): void {
        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        $result = generate_activity::execute($course->id, 1, 'label', 'Intro', 'Theme', 'general', '');

        $this->assertGreaterThan(0, $result['cmid']);
    }

    /**
     * Points the AI at a payload that satisfies every builder: page plan, HTML body, quiz questions and terms.
     *
     * @return void
     */
    private function universal_ai(): void {
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => json_encode([
            'strategy'  => 'blocks',
            'blocks'    => [['type' => 'callout', 'html' => '<p>Universal block</p>']],
            'content'   => '<p>Universal body</p>',
            'questions' => [
                ['type' => 'truefalse', 'question' => 'PHP is a language.', 'answer' => true],
            ],
            'terms'     => [['term' => 'Variable', 'definition' => 'A named storage.']],
        ]));
    }

    /**
     * Logs in a teacher of a fresh two-section course.
     *
     * @return \stdClass The course.
     */
    private function course_with_teacher(): \stdClass {
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        return $course;
    }

    /**
     * A page is built from the AI plan and stored with the rendered blocks.
     */
    public function test_page_is_created_from_the_ai_plan(): void {
        global $DB;

        $this->universal_ai();
        $course = $this->course_with_teacher();

        $result = generate_activity::execute($course->id, 1, 'page', 'Intro', 'Theme', 'general', '');

        $this->assertFalse($result['degraded']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $result['cmid'], $result['viewurl']);
        $cm = get_coursemodule_from_id('page', $result['cmid'], $course->id, false, MUST_EXIST);
        $this->assertStringContainsString('Universal block', $DB->get_field('page', 'content', ['id' => $cm->instance]));
    }

    /**
     * Forum and assignment introductions come from the AI body, cleaned.
     */
    public function test_forum_and_assign_get_the_generated_intro(): void {
        global $DB;

        $this->universal_ai();
        $course = $this->course_with_teacher();

        $forum = generate_activity::execute($course->id, 1, 'forum', 'Discuss', 'Theme', 'general', '');
        $assign = generate_activity::execute($course->id, 1, 'assign', 'Essay', 'Theme', 'general', '');

        $this->assertFalse($forum['degraded']);
        $this->assertFalse($assign['degraded']);
        $forumcm = get_coursemodule_from_id('forum', $forum['cmid'], $course->id, false, MUST_EXIST);
        $assigncm = get_coursemodule_from_id('assign', $assign['cmid'], $course->id, false, MUST_EXIST);
        $this->assertStringContainsString('Universal body', $DB->get_field('forum', 'intro', ['id' => $forumcm->instance]));
        $this->assertStringContainsString('Universal body', $DB->get_field('assign', 'intro', ['id' => $assigncm->instance]));
    }

    /**
     * A quiz gets its questions and a glossary its terms.
     */
    public function test_quiz_and_glossary_are_filled_by_the_ai(): void {
        global $DB;

        $this->universal_ai();
        $course = $this->course_with_teacher();

        $quiz = generate_activity::execute($course->id, 1, 'quiz', 'Check', 'Theme', 'general', '');
        $glossary = generate_activity::execute($course->id, 1, 'glossary', 'Terms', 'Theme', 'general', '');

        $this->assertFalse($quiz['degraded']);
        $this->assertFalse($glossary['degraded']);
        $quizcm = get_coursemodule_from_id('quiz', $quiz['cmid'], $course->id, false, MUST_EXIST);
        $this->assertSame(1, $DB->count_records('quiz_slots', ['quizid' => $quizcm->instance]));
        $glossarycm = get_coursemodule_from_id('glossary', $glossary['cmid'], $course->id, false, MUST_EXIST);
        $this->assertCount(1, \local_studiolms\local\glossary_builder::get_terms($glossarycm->instance));
    }

    /**
     * A label links back to the course page, since it has no page of its own.
     */
    public function test_label_links_to_the_course(): void {
        $this->universal_ai();
        $course = $this->course_with_teacher();

        $result = generate_activity::execute($course->id, 1, 'label', 'Divider', 'Theme', 'general', '');

        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $result['viewurl']);
    }

    /**
     * When the AI gives nothing usable every type is still created, but flagged as degraded.
     */
    public function test_unusable_ai_output_creates_degraded_activities(): void {
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => 'not json');
        $course = $this->course_with_teacher();

        foreach (['page', 'forum', 'assign', 'quiz', 'glossary'] as $type) {
            $result = generate_activity::execute($course->id, 1, $type, 'Item ' . $type, 'Theme', 'general', '');
            $this->assertGreaterThan(0, $result['cmid'], $type);
            $this->assertTrue($result['degraded'], $type);
        }
    }

    /**
     * A type the generators do not know is refused, and nothing is created.
     */
    public function test_unsupported_type_is_refused_and_creates_nothing(): void {
        global $DB;

        $this->universal_ai();
        $course = $this->course_with_teacher();
        $before = $DB->count_records('course_modules', ['course' => $course->id]);

        try {
            generate_activity::execute($course->id, 1, 'video', 'Clip', 'Theme', 'general', '');
            $this->fail('An unsupported type must be refused.');
        } catch (\coding_exception $e) {
            $this->assertSame($before, $DB->count_records('course_modules', ['course' => $course->id]));
        }
    }

    /**
     * Regression guard for the section scope: a section that exists only in another course is refused.
     */
    public function test_section_of_another_course_is_refused(): void {
        $this->universal_ai();
        $this->getDataGenerator()->create_course(['numsections' => 4]);
        $course = $this->course_with_teacher();

        $this->expectException(\dml_missing_record_exception::class);
        generate_activity::execute($course->id, 3, 'label', 'Divider', 'Theme', 'general', '');
    }

    /**
     * AI output is untrusted: script and event handlers in a generated introduction never reach the database.
     */
    public function test_ai_html_is_sanitized_before_it_is_stored(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => json_encode([
            'content' => '<p>Safe text</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        ]));
        $course = $this->course_with_teacher();

        $result = generate_activity::execute($course->id, 1, 'forum', 'Discuss', 'Theme', 'general', '');

        $cm = get_coursemodule_from_id('forum', $result['cmid'], $course->id, false, MUST_EXIST);
        $intro = $DB->get_field('forum', 'intro', ['id' => $cm->instance]);
        $this->assertStringContainsString('Safe text', $intro);
        $this->assertStringNotContainsString('<script', $intro);
        $this->assertStringNotContainsString('onerror', $intro);
    }
}
