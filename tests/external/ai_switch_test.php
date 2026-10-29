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

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;
use local_studiolms\task\generate_course_task;
use local_studiolms\task\generate_section_task;

/**
 * Tests that every way into the generation respects the switch for AI tools of the course.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\external\generate_outline
 * @covers \local_studiolms\external\plan_section
 * @covers \local_studiolms\external\generate_activity
 * @covers \local_studiolms\external\generate_section
 * @covers \local_studiolms\external\populate_course
 * @covers \local_studiolms\task\generate_course_task
 * @covers \local_studiolms\task\generate_section_task
 */
final class ai_switch_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists(\core_ai\aiactions\generate_text::class)) {
            $this->markTestSkipped('core_ai is not available.');
        }
        // There is an AI to use, so that the only reason to refuse is the switch.
        ai_resolver::set_provider_for_testing(static fn(): string => '{"content": "<p>x</p>"}');
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Puts a manager in the container that says the AI tools are off in every course.
     *
     * @return void
     */
    private function switch_off(): void {
        \core\di::set(\core_ai\manager::class, new class {
            /**
             * What the switch of the course says.
             *
             * @param \context $context The context.
             * @param string $actionclass The action class.
             * @return bool
             */
            public function is_action_enabled_in_context(\context $context, string $actionclass): bool {
                return false;
            }
        });
    }

    /**
     * Makes a course, a teacher, a draft outline and a queued progress record.
     *
     * @return array The course, the teacher, the outline id and the progress id.
     */
    private function seed(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $now = time();
        $outlineid = $DB->insert_record('local_studiolms_outline', (object) [
            'userid' => $teacher->id, 'status' => 'draft', 'courseid' => $course->id,
            'briefingjson' => json_encode(['theme' => 'Programming', 'mode' => 'standard']),
            'outlinejson' => json_encode(['objectives' => [], 'sections' => [
                ['title' => 'Basics', 'activities' => [['type' => 'label', 'title' => 'Intro']]],
            ]]),
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $progressid = $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid' => $outlineid, 'userid' => $teacher->id, 'step' => 0, 'total' => 0, 'status' => 'queued',
            'courseid' => $course->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return [$course, $teacher, $outlineid, $progressid];
    }

    /**
     * Each web service refuses a course with the AI tools off, with the message that says why, and queues nothing.
     *
     * @return void
     */
    public function test_every_web_service_refuses_a_course_with_the_ai_tools_off(): void {
        global $DB;

        [$course, $teacher, $outlineid] = $this->seed();
        $this->setUser($teacher);
        $before = $DB->count_records('local_studiolms_progress');
        $activities = json_encode([['type' => 'page', 'title' => 'A page']]);
        $calls = [
            'outline' => fn() => generate_outline::execute($course->id, 'Theme'),
            'plan of a section' => fn() => plan_section::execute($course->id, 1, 'Theme'),
            'one activity' => fn() => generate_activity::execute($course->id, 1, 'page', 'A page', 'Theme', 'general', ''),
            'a section' => fn() => generate_section::execute($course->id, 1, 'Theme', $activities),
            'the course' => fn() => populate_course::execute(
                $course->id,
                $outlineid,
                false,
                [],
                [['title' => 'Basics', 'activities' => []]]
            ),
        ];

        $this->switch_off();
        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail($name . ' must refuse a course with the AI tools off.');
            } catch (\moodle_exception $e) {
                $this->assertSame('aidisabledincourse', $e->errorcode, $name);
            }
        }
        $this->assertSame($before, $DB->count_records('local_studiolms_progress'));
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(generate_course_task::class));
        $this->assertSame([], \core\task\manager::get_adhoc_tasks(generate_section_task::class));
    }

    /**
     * The tasks check the switch again when they run, since it can be turned off between the click and the cron,
     * and create nothing.
     *
     * @return void
     */
    public function test_the_tasks_check_the_switch_again_when_they_run(): void {
        global $DB;

        [$course, $teacher, , $progressid] = $this->seed();
        $this->setUser($teacher);
        $this->switch_off();

        $task = new generate_course_task();
        $task->set_custom_data(['progressid' => $progressid, 'wipe' => 0]);
        $task->execute();
        $this->assertSame('failed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $progressid]));

        $sectionprogress = $DB->insert_record('local_studiolms_progress', (object) [
            'outlineid' => null, 'userid' => $teacher->id, 'step' => 0, 'total' => 0, 'status' => 'queued',
            'courseid' => $course->id, 'createditems' => '[]', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $sectiontask = new generate_section_task();
        $sectiontask->set_custom_data([
            'progressid' => $sectionprogress, 'courseid' => $course->id, 'sectionnum' => 1, 'theme' => 'Theme',
            'bloom' => 'general', 'reference' => '', 'wipe' => 0,
            'activitiesjson' => json_encode([['type' => 'label', 'title' => 'New']]),
        ]);
        $sectiontask->execute();

        $this->assertSame('failed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $sectionprogress]));
        $this->assertCount(0, get_fast_modinfo($course)->get_instances_of('label'));
    }

    /**
     * A switch turned off while a long run is going stops it at the next activity and nothing more is sent to the
     * AI, instead of the run going on to the end with the text of the course.
     *
     * @return void
     */
    public function test_a_switch_turned_off_during_the_run_stops_it(): void {
        global $DB;

        [$course, $teacher, $outlineid, $progressid] = $this->seed();
        $DB->set_field('local_studiolms_outline', 'outlinejson', json_encode(['objectives' => [], 'sections' => [
            ['title' => 'Basics', 'activities' => [
                ['type' => 'page', 'title' => 'First'],
                ['type' => 'page', 'title' => 'Second'],
                ['type' => 'forum', 'title' => 'Third'],
            ]],
        ]]), ['id' => $outlineid]);
        $this->setUser($teacher);

        $calls = 0;
        ai_resolver::set_provider_for_testing(function () use (&$calls): string {
            $calls++;
            // The manager turns the tools off while the first call is being answered.
            $this->switch_off();
            return '{"content": "<p>x</p>"}';
        });

        $task = new generate_course_task();
        $task->set_custom_data(['progressid' => $progressid, 'wipe' => 0]);
        $task->execute();

        $progress = $DB->get_record('local_studiolms_progress', ['id' => $progressid], '*', MUST_EXIST);
        $this->assertSame('failed', $progress->status);
        $this->assertSame(get_string('aidisabledincourse', 'local_studiolms'), $progress->errormsg);
        $this->assertSame(1, $calls);
        $this->assertCount(0, get_fast_modinfo($course)->get_instances_of('page'));
    }
}
