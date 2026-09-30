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
 * Tests for the populate_course web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;
use local_studiolms\task\generate_course_task;

/**
 * Unit tests for the course population web service's core capability checks.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(populate_course::class)]
final class populate_course_test extends \advanced_testcase {
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
     * Seeds a course with an enrolled editing teacher, a reviewed outline and a denied capability.
     *
     * @param string $capability The capability to prevent for the editing teacher role.
     * @return array [course, teacher, outlineid].
     */
    private function seed(string $capability): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability($capability, CAP_PREVENT, $roleid, \context_course::instance($course->id)->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $now = time();
        $outlineid = $DB->insert_record('local_studiolms_outline', (object) [
            'userid'       => $teacher->id,
            'status'       => 'draft',
            'courseid'     => $course->id,
            'briefingjson' => json_encode(['theme' => 'Programming']),
            'outlinejson'  => '{}',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        return [$course, $teacher, $outlineid];
    }

    /**
     * Calls the web service with a one-section outline of the given activity type.
     *
     * @param \stdClass $course Target course.
     * @param int $outlineid Outline id.
     * @param string $type Activity type.
     * @return array The web service result.
     */
    private function populate(\stdClass $course, int $outlineid, string $type): array {
        return populate_course::execute($course->id, $outlineid, false, ['Objective'], [
            ['title' => 'Basics', 'activities' => [['type' => $type, 'title' => 'Item']]],
        ]);
    }

    /**
     * Regression test: the outline's activity types are checked against mod/<type>:addinstance.
     */
    public function test_outline_requires_addinstance_for_its_activity_types(): void {
        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        $this->populate($course, $outlineid, 'quiz');
    }

    /**
     * Populating always creates sections, so it needs moodle/course:update.
     */
    public function test_population_requires_course_update(): void {
        [$course, $teacher, $outlineid] = $this->seed('moodle/course:update');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        $this->populate($course, $outlineid, 'label');
    }

    /**
     * An outline whose types the user may all add is queued.
     */
    public function test_population_is_queued_when_core_capabilities_are_held(): void {
        global $DB;

        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $this->setUser($teacher);

        $result = $this->populate($course, $outlineid, 'label');

        $this->assertSame(
            'queued',
            $DB->get_field('local_studiolms_progress', 'status', ['id' => $result['progressid']])
        );
    }

    /**
     * Queuing needs an AI source: without one the caller is told so, and nothing is queued.
     */
    public function test_refuses_when_no_ai_is_available(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(null);
        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $this->setUser($teacher);

        try {
            $this->populate($course, $outlineid, 'label');
            $this->fail('Populating without AI must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noaiprovider', $e->errorcode);
            $this->assertSame(0, $DB->count_records('local_studiolms_progress'));
            $this->assertSame([], \core\task\manager::get_adhoc_tasks(generate_course_task::class));
        }
    }

    /**
     * Regression guard for the outline scope: an outline owned by another teacher, or belonging to another
     * course, cannot be populated, and nothing is changed or queued.
     */
    public function test_outline_of_another_user_or_course_is_refused(): void {
        global $DB;

        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $original = $DB->get_field('local_studiolms_outline', 'outlinejson', ['id' => $outlineid]);

        $this->setUser($other);
        try {
            $this->populate($course, $outlineid, 'label');
            $this->fail('Another teacher must not populate this outline.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame($original, $DB->get_field('local_studiolms_outline', 'outlinejson', ['id' => $outlineid]));
        }

        $this->setUser($otherteacher);
        try {
            $this->populate($othercourse, $outlineid, 'label');
            $this->fail('A teacher of another course must not populate this outline.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame($original, $DB->get_field('local_studiolms_outline', 'outlinejson', ['id' => $outlineid]));
        }
    }

    /**
     * Regression guard for the course scope alone: the owner of an outline cannot apply it to a different course
     * they also teach.
     */
    public function test_outline_cannot_be_applied_to_another_course_of_its_owner(): void {
        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $othercourse->id, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\dml_missing_record_exception::class);
        $this->populate($othercourse, $outlineid, 'label');
    }

    /**
     * The reviewed structure replaces the stored draft, and the queued task carries the wipe choice.
     */
    public function test_reviewed_outline_is_stored_and_the_task_queued(): void {
        global $DB;

        [$course, $teacher, $outlineid] = $this->seed('mod/quiz:addinstance');
        $this->setUser($teacher);

        $result = populate_course::execute($course->id, $outlineid, true, ['Objective'], [
            ['title' => 'Basics', 'activities' => [['type' => 'label', 'title' => 'Item']]],
        ]);

        $stored = json_decode($DB->get_field('local_studiolms_outline', 'outlinejson', ['id' => $outlineid]), true);
        $this->assertSame(['Objective'], $stored['objectives']);
        $this->assertSame('Basics', $stored['sections'][0]['title']);
        $progress = $DB->get_record('local_studiolms_progress', ['id' => $result['progressid']], '*', MUST_EXIST);
        $this->assertEquals($outlineid, $progress->outlineid);
        $this->assertEquals($teacher->id, $progress->userid);
        $tasks = \core\task\manager::get_adhoc_tasks(generate_course_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals($teacher->id, $task->get_userid());
        $this->assertEquals($result['progressid'], $task->get_custom_data()->progressid);
        $this->assertEquals(1, $task->get_custom_data()->wipe);
    }
}
