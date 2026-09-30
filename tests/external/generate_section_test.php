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
 * Tests for the generate_section web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;
use local_studiolms\task\generate_section_task;

/**
 * Unit tests for the section generation web service's permission checks.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_section::class)]
final class generate_section_test extends \advanced_testcase {
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
     * Regression test: wipe=true used to require only local/studiolms:generate, letting a
     * role with that capability but not moodle/course:manageactivities delete activities
     * (and any student submissions in them), unlike populate_course which already required
     * the core capability.
     */
    public function test_wipe_requires_manageactivities_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        // Prevent the core capability that governs deleting activities, while the plugin's
        // own generate capability (granted by the archetype) stays allowed.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:manageactivities', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $this->assertTrue(has_capability('local/studiolms:generate', $context));
        $this->assertFalse(has_capability('moodle/course:manageactivities', $context));

        $this->expectException(\required_capability_exception::class);
        generate_section::execute(
            $course->id,
            1,
            'Theme',
            json_encode([['type' => 'label', 'title' => 'New']]),
            'general',
            '',
            true
        );
    }

    /**
     * Ordinary generation (no wipe) is gated by manageactivities too: add_moduleinfo() checks nothing itself.
     */
    public function test_generation_requires_manageactivities_capability(): void {
        [$course, $teacher] = $this->teacher_denied('moodle/course:manageactivities');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        $this->queue($course, 1, [['type' => 'label', 'title' => 'New']]);
    }

    /**
     * Regression test: a role denied mod/forum:addinstance could still create forums through the plugin.
     */
    public function test_generation_requires_addinstance_for_each_activity_type(): void {
        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        $this->queue($course, 1, [['type' => 'label', 'title' => 'A'], ['type' => 'forum', 'title' => 'B']]);
    }

    /**
     * Creating a new section (sectionnum -1) needs moodle/course:update.
     */
    public function test_new_section_requires_course_update_capability(): void {
        [$course, $teacher] = $this->teacher_denied('moodle/course:update');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        $this->queue($course, -1, [['type' => 'label', 'title' => 'New']]);
    }

    /**
     * A user holding every core capability for the requested types can still queue generation.
     */
    public function test_generation_is_queued_when_core_capabilities_are_held(): void {
        global $DB;

        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        $result = $this->queue($course, 1, [['type' => 'label', 'title' => 'New']]);

        $this->assertGreaterThan(0, $result['progressid']);
        $this->assertSame(
            'queued',
            $DB->get_field('local_studiolms_progress', 'status', ['id' => $result['progressid']])
        );
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
     * Calls the web service for the given activities, without wipe.
     *
     * @param \stdClass $course Target course.
     * @param int $sectionnum Target section number.
     * @param array $activities Activity definitions.
     * @return array The web service result.
     */
    private function queue(\stdClass $course, int $sectionnum, array $activities): array {
        return generate_section::execute($course->id, $sectionnum, 'Theme', json_encode($activities));
    }

    /**
     * Queuing needs an AI source: without one the caller is told so, and nothing is stored or queued.
     */
    public function test_refuses_when_no_ai_is_available(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(null);
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        try {
            $this->queue($course, 1, [['type' => 'label', 'title' => 'New']]);
            $this->fail('Queuing without AI must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noaiprovider', $e->errorcode);
            $this->assertSame(0, $DB->count_records('local_studiolms_progress'));
            $this->assertSame([], \core\task\manager::get_adhoc_tasks(generate_section_task::class));
        }
    }

    /**
     * A plan that is not a non-empty JSON list is rejected before anything is stored or queued.
     */
    public function test_rejects_an_invalid_activity_list(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        foreach (['not json', '[]', '{}', '"text"'] as $json) {
            try {
                generate_section::execute($course->id, 1, 'Theme', $json);
                $this->fail('Rejected input expected for ' . $json);
            } catch (\coding_exception $e) {
                $this->assertSame(0, $DB->count_records('local_studiolms_progress'), $json);
            }
        }
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(generate_section_task::class));
    }

    /**
     * Regression guard for the section scope: a section that exists only in another course is refused.
     */
    public function test_section_of_another_course_is_refused(): void {
        $this->getDataGenerator()->create_course(['numsections' => 4]);
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\dml_missing_record_exception::class);
        $this->queue($course, 3, [['type' => 'label', 'title' => 'New']]);
    }

    /**
     * The queued task carries the teacher, the progress record and every option the task needs.
     */
    public function test_queued_task_carries_the_request(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $activities = [['type' => 'label', 'title' => 'New']];

        $result = generate_section::execute(
            $course->id,
            1,
            'Botany',
            json_encode($activities),
            'apply',
            'Chapter three'
        );

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $result['progressid']], '*', MUST_EXIST);
        $this->assertEquals($teacher->id, $progress->userid);
        $this->assertEquals($course->id, $progress->courseid);
        $this->assertNull($progress->outlineid);
        $tasks = \core\task\manager::get_adhoc_tasks(generate_section_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals($teacher->id, $task->get_userid());
        $data = $task->get_custom_data();
        $this->assertEquals($result['progressid'], $data->progressid);
        $this->assertEquals($course->id, $data->courseid);
        $this->assertEquals(1, $data->sectionnum);
        $this->assertSame('Botany', $data->theme);
        $this->assertSame('apply', $data->bloom);
        $this->assertSame('Chapter three', $data->reference);
        $this->assertFalse($data->wipe);
        $this->assertSame(json_encode($activities), $data->activitiesjson);
    }

    /**
     * The wipe choice reaches the queued task, which is what makes it delete the section's activities.
     */
    public function test_queued_task_carries_the_wipe_choice(): void {
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        generate_section::execute(
            $course->id,
            1,
            'Botany',
            json_encode([['type' => 'label', 'title' => 'New']]),
            'general',
            '',
            true
        );

        $tasks = \core\task\manager::get_adhoc_tasks(generate_section_task::class);
        $task = reset($tasks);
        $this->assertTrue($task->get_custom_data()->wipe);
    }
}
