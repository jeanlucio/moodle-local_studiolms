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
 * Tests for the background course generation task.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\task;

use local_studiolms\local\ai_resolver;

/**
 * Integration tests for the generation pipeline and its events.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_course_task::class)]
final class generate_course_task_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest();
        // Deterministic AI: every call yields a small content payload.
        ai_resolver::set_provider_for_testing(
            static fn(string $s, string $u): string => '{"content": "<p>Generated body</p>"}'
        );
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Seeds a course plus its outline and progress records.
     *
     * @param int $userid The owner user id.
     * @param array $structure The outline structure (objectives, sections).
     * @return array [course, progressid].
     */
    private function seed(int $userid, array $structure): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $now = time();
        $outlineid = $DB->insert_record('local_studiolms_outline', (object) [
            'userid'       => $userid,
            'status'       => 'reviewed',
            'courseid'     => $course->id,
            'briefingjson' => json_encode(['theme' => 'Programming', 'mode' => 'standard', 'bloom' => 'general']),
            'outlinejson'  => json_encode($structure),
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $progressid = $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid'    => $outlineid,
            'userid'       => $userid,
            'step'         => 0,
            'total'        => 0,
            'status'       => 'queued',
            'courseid'     => $course->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        return [$course, $progressid];
    }

    /**
     * Runs the configured task synchronously.
     *
     * @param int $progressid The progress record id.
     * @return void
     */
    private function run_task(int $progressid): void {
        $task = new generate_course_task();
        $task->set_custom_data(['progressid' => $progressid, 'wipe' => 0]);
        $task->execute();
    }

    /**
     * A successful run builds the content, writes the log and fires course_generated.
     *
     * @return void
     */
    public function test_successful_generation_logs_and_fires_event(): void {
        global $DB, $USER;
        $this->setAdminUser();

        [$course, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => ['Understand loops'],
            'sections' => [
                ['title' => 'Basics', 'activities' => [
                    ['type' => 'label', 'title' => 'Intro'],
                    ['type' => 'forum', 'title' => 'Discuss'],
                ]],
            ],
        ]);

        $sink = $this->redirectEvents();
        $this->run_task($progressid);
        $events = $sink->get_events();
        $sink->close();

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $progressid]);
        $this->assertSame('completed', $progress->status);

        $this->assertSame(1, $DB->count_records('local_studiolms_generation_log', ['courseid' => $course->id]));

        $modinfo = get_fast_modinfo($course);
        $this->assertCount(1, $modinfo->get_instances_of('label'));
        $this->assertCount(1, $modinfo->get_instances_of('forum'));

        $generated = array_filter(
            $events,
            static fn($e) => $e instanceof \local_studiolms\event\course_generated
        );
        $this->assertCount(1, $generated);
        $event = reset($generated);
        $this->assertSame($course->id, $event->courseid);
        $this->assertSame('standard', $event->other['mode']);
    }

    /**
     * A user without the capability fails the run, fires generation_failed and
     * creates nothing.
     *
     * @return void
     */
    public function test_missing_capability_fails_and_fires_event(): void {
        global $DB;
        $student = $this->getDataGenerator()->create_user();

        [$course, $progressid] = $this->seed((int) $student->id, [
            'objectives' => [],
            'sections' => [
                ['title' => 'Basics', 'activities' => [['type' => 'label', 'title' => 'Intro']]],
            ],
        ]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $this->run_task($progressid);
        $events = $sink->get_events();
        $sink->close();

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $progressid]);
        $this->assertSame('failed', $progress->status);

        $modinfo = get_fast_modinfo($course);
        $this->assertCount(0, $modinfo->get_instances_of('label'));

        $failed = array_filter(
            $events,
            static fn($e) => $e instanceof \local_studiolms\event\generation_failed
        );
        $this->assertCount(1, $failed);
        $this->assertSame($course->id, reset($failed)->courseid);
    }
}
