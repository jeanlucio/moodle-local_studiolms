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
 * Tests for the background section generation task.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\task;

use local_studiolms\local\ai_resolver;
use local_studiolms\local\preset_loader;

/**
 * Integration tests for the section generation task's wipe permission check.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_section_task::class)]
final class generate_section_task_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(
            static fn(string $system, string $user): string => '{"content": "<p>Generated body</p>"}'
        );
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        preset_loader::set_directory_for_testing(null);
        parent::tearDown();
    }

    /**
     * Seeds a queued progress record.
     *
     * @param int $userid The owner user id.
     * @param int $courseid The target course id.
     * @return int The created progress id.
     */
    private function seed_progress(int $userid, int $courseid): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid'    => null,
            'userid'       => $userid,
            'step'         => 0,
            'total'        => 0,
            'message'      => '',
            'status'       => 'queued',
            'courseid'     => $courseid,
            'createditems' => json_encode(['cmids' => []]),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Runs the configured task synchronously.
     *
     * @param int $progressid The progress record id.
     * @param int $courseid The target course id.
     * @param int $sectionnum The target section number.
     * @param array $activities Activities to generate.
     * @param bool $wipe Whether to wipe the section first.
     * @param array $extra Custom data overrides (theme, bloom, reference).
     * @return void
     */
    private function run_task(
        int $progressid,
        int $courseid,
        int $sectionnum,
        array $activities,
        bool $wipe,
        array $extra = []
    ): void {
        $task = new generate_section_task();
        $task->set_custom_data($extra + [
            'progressid'     => $progressid,
            'courseid'       => $courseid,
            'sectionnum'     => $sectionnum,
            'theme'          => 'Theme',
            'bloom'          => 'general',
            'reference'      => '',
            'wipe'           => $wipe,
            'activitiesjson' => json_encode($activities),
        ]);
        $task->execute();
    }

    /**
     * Points the AI at a payload every builder accepts and the presets at an empty catalog.
     *
     * @param array $prompts Receives every user prompt sent to the AI.
     * @return void
     */
    private function universal_ai(array &$prompts = []): void {
        preset_loader::set_directory_for_testing(make_request_directory());
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$prompts): string {
            $prompts[] = $system . "\n" . $user;
            return json_encode([
                'strategy'  => 'blocks',
                'blocks'    => [['type' => 'callout', 'html' => '<p>Universal block</p>']],
                'content'   => '<p>Universal body</p>',
                'questions' => [['type' => 'truefalse', 'question' => 'PHP is a language.', 'answer' => true]],
                'terms'     => [['term' => 'Variable', 'definition' => 'A named storage.']],
            ]);
        });
    }

    /**
     * Returns the progress record.
     *
     * @param int $progressid The progress id.
     * @return \stdClass
     */
    private function progress(int $progressid): \stdClass {
        global $DB;
        return $DB->get_record('local_studiolms_progress', ['id' => $progressid], '*', MUST_EXIST);
    }

    /**
     * With the core capability present, wipe removes existing activities before generating.
     */
    public function test_wipe_removes_existing_activities_when_authorised(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $existing = $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'section' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [['type' => 'label', 'title' => 'New']], true);

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $progressid]);
        $this->assertSame('completed', $progress->status);

        $modinfo = get_fast_modinfo($course);
        $this->assertArrayNotHasKey($existing->cmid, $modinfo->get_cms());
        $this->assertCount(1, $modinfo->get_instances_of('label'));
    }

    /**
     * Regression test: a user with local/studiolms:generate but without
     * moodle/course:manageactivities cannot wipe a section's activities through the task
     * either, even if the permission was revoked only after the web service queued it.
     */
    public function test_wipe_without_manageactivities_fails_and_deletes_nothing(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $existing = $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'section' => 1]);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:manageactivities', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $progressid = $this->seed_progress((int) $teacher->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [['type' => 'label', 'title' => 'New']], true);

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $progressid]);
        $this->assertSame('failed', $progress->status);

        $modinfo = get_fast_modinfo($course);
        $this->assertArrayHasKey($existing->cmid, $modinfo->get_cms());
        $this->assertCount(1, $modinfo->get_instances_of('label'));
    }

    /**
     * Regression test: the task re-checks mod/<type>:addinstance, so a permission revoked after queueing
     * cannot be used to create that activity type, and nothing at all is created.
     */
    public function test_activity_type_without_addinstance_fails_and_creates_nothing(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/forum:addinstance', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $progressid = $this->seed_progress((int) $teacher->id, $course->id);
        $activities = [['type' => 'label', 'title' => 'Ok'], ['type' => 'forum', 'title' => 'Denied']];
        $this->run_task($progressid, $course->id, 1, $activities, false);

        $this->assertSame('failed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $progressid]));
        $modinfo = get_fast_modinfo($course);
        $this->assertCount(0, $modinfo->get_instances_of('label'));
        $this->assertCount(0, $modinfo->get_instances_of('forum'));
    }

    /**
     * Creating a new section (sectionnum -1) is refused when moodle/course:update was revoked.
     */
    public function test_new_section_without_course_update_fails_and_creates_nothing(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:update', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $progressid = $this->seed_progress((int) $teacher->id, $course->id);
        $this->run_task($progressid, $course->id, -1, [['type' => 'label', 'title' => 'New']], false);

        $this->assertSame('failed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $progressid]));
        $this->assertSame(2, $DB->count_records('course_sections', ['course' => $course->id]));
    }

    /**
     * A task whose progress record no longer exists (for example the course was deleted while it was
     * queued) returns quietly instead of failing.
     *
     * Regression test: the record was assigned straight to a property typed stdClass, so the false
     * returned for a missing row raised a TypeError before the "not found" check could run, and the
     * task was retried with a failure every time.
     */
    public function test_missing_progress_record_returns_without_error(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $progressid = $this->seed_progress(2, $course->id);
        $DB->delete_records('local_studiolms_progress', ['id' => $progressid]);

        $this->run_task($progressid, $course->id, 1, [['type' => 'label', 'title' => 'New']], false);

        $this->assertFalse($DB->record_exists('local_studiolms_progress', ['id' => $progressid]));
        $this->assertCount(0, get_fast_modinfo($course)->get_instances_of('label'));
    }

    /**
     * Section number -1 creates a section at the end of the course and fills it.
     */
    public function test_new_section_is_created_and_filled(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $before = $DB->count_records('course_sections', ['course' => $course->id]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, -1, [['type' => 'label', 'title' => 'Divider']], false);

        $this->assertSame('completed', $this->progress($progressid)->status);
        $this->assertSame($before + 1, $DB->count_records('course_sections', ['course' => $course->id]));
        $newsection = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $before], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('course_modules', ['course' => $course->id, 'section' => $newsection->id]));
    }

    /**
     * Wiping a section that is only being created must not touch the activities of the others.
     */
    public function test_wipe_with_a_new_section_leaves_other_sections_alone(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $existing = $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'section' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, -1, [['type' => 'label', 'title' => 'New']], true);

        $this->assertSame('completed', $this->progress($progressid)->status);
        $this->assertArrayHasKey($existing->cmid, get_fast_modinfo($course)->get_cms());
    }

    /**
     * Every activity type is built and reported, with no warning when the AI works.
     */
    public function test_every_activity_type_is_built_and_reported(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $types = ['page', 'label', 'forum', 'assign', 'glossary', 'quiz'];
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $activities = array_map(static fn(string $type): array => ['type' => $type, 'title' => 'Item ' . $type], $types);
        $this->run_task($progressid, $course->id, 1, $activities, false);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $this->assertSame([], json_decode($progress->warnings, true));
        $modinfo = get_fast_modinfo($course);
        foreach ($types as $type) {
            $this->assertCount(1, $modinfo->get_instances_of($type), $type);
        }
        $this->assertSame($types, array_column(json_decode($progress->reportjson, true), 'type'));
        $this->assertSame(count($types), (int) $progress->step);
        $this->assertSame((int) $progress->total, (int) $progress->step);
    }

    /**
     * Without usable AI output the section is still built; each simplified activity is a warning, and a type
     * the task does not know is built and reported as a page.
     */
    public function test_degraded_activities_are_recorded_as_warnings(): void {
        global $USER;
        $this->setAdminUser();
        preset_loader::set_directory_for_testing(make_request_directory());
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => 'not json');
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $activities = [
            ['type' => 'forum', 'title' => 'Discuss'],
            ['type' => 'assign', 'title' => 'Essay'],
            ['type' => 'glossary', 'title' => 'Terms'],
            ['type' => 'quiz', 'title' => 'Check'],
            ['type' => 'video', 'title' => 'Clip'],
        ];
        $this->run_task($progressid, $course->id, 1, $activities, false);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $warnings = implode('|', json_decode($progress->warnings, true));
        foreach (['Discuss', 'Essay', 'Terms', 'Check', 'Clip'] as $title) {
            $this->assertStringContainsString($title, $warnings);
        }
        $types = array_column(json_decode($progress->reportjson, true), 'type');
        $this->assertNotContains('video', $types);
        $this->assertSame('page', end($types));
    }

    /**
     * The reference material and Bloom level reach the prompts of the activities written from them.
     */
    public function test_reference_and_bloom_reach_the_prompts(): void {
        global $USER;
        $this->setAdminUser();
        $prompts = [];
        $this->universal_ai($prompts);
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [['type' => 'forum', 'title' => 'Discuss']], false, [
            'reference' => 'Chapter three of the textbook',
            'bloom' => 'apply',
        ]);

        $joined = implode("\n", $prompts);
        $this->assertStringContainsString('Reference material:', $joined);
        $this->assertStringContainsString('Chapter three of the textbook', $joined);
        $this->assertStringContainsString("Cognitive level (Bloom's taxonomy): apply", $joined);
    }

    /**
     * A failure removes the section the run created and its activities, and marks the run failed.
     */
    public function test_failure_rolls_back_the_created_section(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $sectionsbefore = $DB->count_records('course_sections', ['course' => $course->id]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, -1, [
            ['type' => 'label', 'title' => 'Built first'],
            'not an activity',
        ], false);

        $progress = $this->progress($progressid);
        $this->assertSame('failed', $progress->status);
        $this->assertNotSame('', (string) $progress->errormsg);
        $this->assertSame($sectionsbefore, $DB->count_records('course_sections', ['course' => $course->id]));
        $this->assertSame(0, $DB->count_records('course_modules', ['course' => $course->id]));
    }

    /**
     * A failure in an existing section removes the activities the run added, but keeps the section.
     */
    public function test_failure_keeps_an_existing_section_but_removes_new_activities(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $sectionsbefore = $DB->count_records('course_sections', ['course' => $course->id]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [
            ['type' => 'label', 'title' => 'Built first'],
            'not an activity',
        ], false);

        $this->assertSame('failed', $this->progress($progressid)->status);
        $this->assertSame($sectionsbefore, $DB->count_records('course_sections', ['course' => $course->id]));
        $this->assertSame(0, $DB->count_records('course_modules', ['course' => $course->id]));
    }

    /**
     * A plan that is not a non-empty list fails the run without creating anything.
     */
    public function test_invalid_activities_fail_without_creating_anything(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $progressid = $this->seed_progress((int) $USER->id, $course->id);

        $this->run_task($progressid, $course->id, -1, [], false);

        $this->assertSame('failed', $this->progress($progressid)->status);
        $this->assertSame(2, $DB->count_records('course_sections', ['course' => $course->id]));
    }

    /**
     * Regression test: a long activity title used to overflow the varchar(255) progress message, so the update
     * failed, the failure could not be recorded either, and the run crashed while still "running".
     */
    public function test_long_titles_do_not_break_progress_tracking(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $long = str_repeat('é', 400);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [['type' => 'label', 'title' => $long]], false);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $this->assertLessThanOrEqual(255, \core_text::strlen($progress->message));
    }

    /**
     * Even the failure path survives a long message.
     */
    public function test_failure_with_a_long_message_is_still_recorded(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $progressid = $this->seed_progress((int) $USER->id, $course->id);
        $this->run_task($progressid, $course->id, 1, [
            ['type' => 'label', 'title' => str_repeat('x', 400)],
            'not an activity',
        ], false);

        $progress = $this->progress($progressid);
        $this->assertSame('failed', $progress->status);
        $this->assertLessThanOrEqual(255, \core_text::strlen($progress->message));
    }
}
