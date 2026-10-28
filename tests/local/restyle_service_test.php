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

use local_studiolms\local\restyle\adapter;
use local_studiolms\local\restyle\service;

/**
 * Tests for the restyle of a page or label into a hidden copy.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\restyle\service
 * @covers \local_studiolms\local\restyle\adapter
 * @covers \local_studiolms\local\restyle\planner
 */
final class restyle_service_test extends \advanced_testcase {
    /** @var string The HTML of the pages used here. */
    private const HTML = '<h2>The cell</h2><p>Cells are the unit of life.</p><p>Remember: they have a membrane.</p>'
        . '<p><img src="https://example.com/cell.png" alt="A cell"></p>';

    /**
     * Makes the AI answer that the second piece is a callout.
     *
     * @return void
     */
    private function ai_answers_a_callout(): void {
        ai_resolver::set_provider_for_testing(
            fn(string $system, string $user): string => '{"blocks":[{"type":"callout","lines":[2]}]}'
        );
    }

    /**
     * Makes a course with a teacher and a page.
     *
     * @param string $content The content of the page.
     * @return array The course, the page, and the teacher.
     */
    private function course_with_page(string $content = self::HTML): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'name' => 'Cells', 'content' => $content, 'contentformat' => FORMAT_HTML,
        ]);
        return [$course, $page, $teacher];
    }

    /**
     * The page is copied, the copy is hidden and named, and only the copy is restyled.
     *
     * @return void
     */
    public function test_a_page_is_restyled_into_a_hidden_copy_and_the_original_is_untouched(): void {
        global $DB;

        $this->resetAfterTest();
        $this->ai_answers_a_callout();
        [$course, $page, $teacher] = $this->course_with_page();
        $this->setUser($teacher);

        $copy = service::restyle($page->cmid);

        $this->assertNotEquals($page->cmid, $copy->cmid);
        $this->assertSame('Cells (StudioLMS)', $copy->name);
        $this->assertStringContainsString('update=' . $copy->cmid, $copy->editurl);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $copy->cmid, $copy->viewurl);

        $modinfo = get_fast_modinfo($course->id);
        $this->assertSame('Cells (StudioLMS)', $modinfo->get_cm($copy->cmid)->name);
        $this->assertFalse((bool) $modinfo->get_cm($copy->cmid)->visible, 'the copy is born hidden');
        $this->assertTrue((bool) $modinfo->get_cm($page->cmid)->visible, 'the original stays as it was');
        $this->assertSame('Cells', $modinfo->get_cm($page->cmid)->name);

        $sequence = array_values(array_filter(explode(',', $DB->get_field(
            'course_sections',
            'sequence',
            ['id' => $modinfo->get_cm($page->cmid)->section]
        ))));
        $this->assertSame([(string) $page->cmid, (string) $copy->cmid], $sequence, 'the copy is right after the original');

        $this->assertSame(self::HTML, $DB->get_field('page', 'content', ['id' => $page->id]), 'original untouched');
        $newcontent = $DB->get_field('page', 'content', ['id' => $modinfo->get_cm($copy->cmid)->instance]);
        $this->assertStringContainsString('data-slms-block-type="callout"', $newcontent);
        $this->assertStringContainsString('data-slms-block-type="stylizedHeading"', $newcontent);
        $this->assertStringContainsString('Remember: they have a membrane.', $newcontent);
        $this->assertStringContainsString('<img src="https://example.com/cell.png"', $newcontent);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A label is restyled in its introduction, with a smaller limit of blocks.
     *
     * @return void
     */
    public function test_a_label_is_restyled(): void {
        global $DB;

        $this->resetAfterTest();
        $this->ai_answers_a_callout();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id, 'intro' => self::HTML, 'introformat' => FORMAT_HTML,
        ]);
        $this->setUser($teacher);

        $copy = service::restyle($label->cmid);

        $modinfo = get_fast_modinfo($course->id);
        $this->assertFalse((bool) $modinfo->get_cm($copy->cmid)->visible);
        $this->assertSame(self::HTML, $DB->get_field('label', 'intro', ['id' => $label->id]));
        $this->assertStringContainsString(
            'data-slms-block-type="callout"',
            $DB->get_field('label', 'intro', ['id' => $modinfo->get_cm($copy->cmid)->instance])
        );
        $this->assertSame(4, adapter::max_blocks('label'));
        $this->assertSame(12, adapter::max_blocks('page'));
        $this->assertSame(0, adapter::max_blocks('forum'));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * When the AI gives no usable plan no copy is made, so a failure leaves nothing behind.
     *
     * @return void
     */
    public function test_no_copy_is_made_when_the_plan_fails(): void {
        global $DB;

        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => 'no idea');
        [$course, $page, $teacher] = $this->course_with_page();
        $this->setUser($teacher);
        $before = $DB->count_records('course_modules', ['course' => $course->id]);

        try {
            service::restyle($page->cmid);
            $this->fail('A failed plan must stop the restyle.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_noplan', $e->errorcode);
        }
        $this->assertSame($before, $DB->count_records('course_modules', ['course' => $course->id]));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A message of the AI layer (no provider, tools off in the course) reaches the teacher as it is.
     *
     * @return void
     */
    public function test_the_message_of_the_ai_layer_is_not_hidden(): void {
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(function (): string {
            throw new \moodle_exception('aidisabledincourse', 'local_studiolms');
        });
        [, $page, $teacher] = $this->course_with_page();
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aidisabledincourse', 'local_studiolms'));
        service::restyle($page->cmid);
    }

    /**
     * A page that is only a picture has nothing to organise, and the AI is not even asked.
     *
     * @return void
     */
    public function test_a_page_with_no_text_is_refused_before_the_ai_and_the_copy(): void {
        global $DB;

        $this->resetAfterTest();
        $called = false;
        ai_resolver::set_provider_for_testing(function () use (&$called): string {
            $called = true;
            return '{}';
        });
        [$course, $page, $teacher] = $this->course_with_page('<p><img src="https://example.com/a.png" alt=""></p>');
        $this->setUser($teacher);
        $before = $DB->count_records('course_modules', ['course' => $course->id]);

        try {
            service::restyle($page->cmid);
            $this->fail('Nothing to restyle must stop the restyle.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_nochange', $e->errorcode);
        }
        $this->assertFalse($called);
        $this->assertSame($before, $DB->count_records('course_modules', ['course' => $course->id]));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Only HTML is restyled, and only pages and labels.
     *
     * @return void
     */
    public function test_other_formats_and_other_modules_are_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->ai_answers_a_callout();
        [$course, $page, $teacher] = $this->course_with_page();
        $this->setUser($teacher);

        $DB->set_field('page', 'contentformat', FORMAT_MARKDOWN, ['id' => $page->id]);
        try {
            service::restyle($page->cmid);
            $this->fail('Text that is not HTML must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_notsupportedformat', $e->errorcode);
        }

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        try {
            service::restyle($forum->cmid);
            $this->fail('A forum must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_notsupported', $e->errorcode);
        }
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * The teacher of the course may restyle; a student, a teacher of another course and a teacher without the
     * capability of the plugin may not, and nothing is copied for them.
     *
     * @return void
     */
    public function test_only_who_holds_the_capabilities_can_restyle(): void {
        global $DB;

        $this->resetAfterTest();
        $this->ai_answers_a_callout();
        [$course, $page, $teacher] = $this->course_with_page();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_and_enrol($other, 'editingteacher');
        $before = $DB->count_records('course_modules');

        foreach ([$student, $stranger] as $user) {
            $this->setUser($user);
            try {
                service::restyle($page->cmid);
                $this->fail('A user without the capabilities must not restyle.');
            } catch (\required_capability_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('local/studiolms:restyle', CAP_PROHIBIT, $roleid, \context_course::instance($course->id)->id, true);
        $this->setUser($teacher);
        try {
            service::restyle($page->cmid);
            $this->fail('A teacher without the plugin capability must not restyle.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString(get_string('studiolms:restyle', 'local_studiolms'), $e->getMessage());
        }
        $this->assertSame($before, $DB->count_records('course_modules'));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * The adapter reads HTML only, and a write raises the revision of the page so browsers fetch the new content.
     *
     * @return void
     */
    public function test_the_adapter_writes_the_page_and_raises_its_revision(): void {
        global $DB;

        $this->resetAfterTest();
        [, $page] = $this->course_with_page();
        $revision = (int) $DB->get_field('page', 'revision', ['id' => $page->id]);

        adapter::write('page', (int) $page->id, '<p>New</p>');

        $this->assertSame('<p>New</p>', adapter::read('page', (int) $page->id));
        $this->assertSame($revision + 1, (int) $DB->get_field('page', 'revision', ['id' => $page->id]));
        $DB->set_field('page', 'contentformat', FORMAT_PLAIN, ['id' => $page->id]);
        $this->assertNull(adapter::read('page', (int) $page->id));
        $this->assertTrue(adapter::supports('label'));
        $this->assertFalse(adapter::supports('quiz'));
    }
}
