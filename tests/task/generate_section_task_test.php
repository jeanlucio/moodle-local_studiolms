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
     * @return void
     */
    private function run_task(int $progressid, int $courseid, int $sectionnum, array $activities, bool $wipe): void {
        $task = new generate_section_task();
        $task->set_custom_data([
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
}
