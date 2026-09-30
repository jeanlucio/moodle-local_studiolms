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
use local_studiolms\local\preset_loader;

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
        preset_loader::set_directory_for_testing(null);
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
     * @param bool $wipe Whether to wipe the course first.
     * @return void
     */
    private function run_task(int $progressid, bool $wipe = false): void {
        $task = new generate_course_task();
        $task->set_custom_data(['progressid' => $progressid, 'wipe' => $wipe ? 1 : 0]);
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

    /**
     * Regression test: a teacher whose role is denied an activity type through core capabilities cannot
     * have the task create it (the permission may be revoked after the web service queued the run).
     *
     * @return void
     */
    public function test_activity_type_without_addinstance_fails_and_creates_nothing(): void {
        global $DB;

        $teacher = $this->getDataGenerator()->create_user();
        [$course, $progressid] = $this->seed((int) $teacher->id, [
            'objectives' => [],
            'sections' => [
                ['title' => 'Basics', 'activities' => [
                    ['type' => 'label', 'title' => 'Intro'],
                    ['type' => 'forum', 'title' => 'Discuss'],
                ]],
            ],
        ]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability(
            'mod/forum:addinstance',
            CAP_PREVENT,
            $roleid,
            \context_course::instance($course->id)->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $this->run_task($progressid);

        $this->assertSame('failed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $progressid]));
        $modinfo = get_fast_modinfo($course);
        $this->assertCount(0, $modinfo->get_instances_of('label'));
        $this->assertCount(0, $modinfo->get_instances_of('forum'));
    }

    /**
     * Wiping removes the existing activities but always keeps the course's news forum.
     *
     * @return void
     */
    public function test_wipe_removes_activities_but_keeps_the_news_forum(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->setAdminUser();

        [$course, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [['title' => 'Fresh', 'activities' => [['type' => 'label', 'title' => 'New']]]],
        ]);
        forum_get_course_forum($course->id, 'news');
        for ($i = 1; $i <= 3; $i++) {
            $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 1, 'type' => 'general']);
        }
        $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'section' => 1]);

        $this->run_task($progressid, true);

        $this->assertSame('completed', $DB->get_field('local_studiolms_progress', 'status', ['id' => $progressid]));
        $this->assertSame(0, $DB->count_records('forum', ['course' => $course->id, 'type' => 'general']));
        $this->assertSame(1, $DB->count_records('forum', ['course' => $course->id, 'type' => 'news']));
        $this->assertCount(1, get_fast_modinfo($course)->get_instances_of('label'));
    }

    /**
     * Points the AI at a payload every builder accepts and the presets at an empty catalog.
     *
     * @return void
     */
    private function universal_ai(): void {
        $empty = make_request_directory();
        preset_loader::set_directory_for_testing($empty);
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => json_encode([
            'strategy'  => 'blocks',
            'blocks'    => [['type' => 'callout', 'html' => '<p>Universal block</p>']],
            'content'   => '<p>Universal body</p>',
            'questions' => [['type' => 'truefalse', 'question' => 'PHP is a language.', 'answer' => true]],
            'terms'     => [['term' => 'Variable', 'definition' => 'A named storage.']],
        ]));
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
     * Regression guard for the first page: the course gets a plan page in section 0 built from the intro
     * renderer, once, before the first content page.
     *
     * @return void
     */
    public function test_first_page_also_creates_the_course_plan_page(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();

        [$course, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [['title' => 'Basics', 'activities' => [
                ['type' => 'page', 'title' => 'Intro'],
                ['type' => 'page', 'title' => 'Second'],
            ]]],
        ]);
        $this->run_task($progressid);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $plantitle = get_string('courseplantitle', 'local_studiolms');
        $this->assertSame(1, $DB->count_records('page', ['course' => $course->id, 'name' => $plantitle]));
        $this->assertSame(3, $DB->count_records('page', ['course' => $course->id]));
        $plancm = $DB->get_record_sql(
            "SELECT cm.id, cs.section
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'page'
               JOIN {page} p ON p.id = cm.instance
               JOIN {course_sections} cs ON cs.id = cm.section
              WHERE cm.course = :courseid AND p.name = :name",
            ['courseid' => $course->id, 'name' => $plantitle]
        );
        $this->assertEquals(0, $plancm->section);
        $report = json_decode($progress->reportjson, true);
        $this->assertSame('plan', $report[0]['preset']);
        $this->assertSame($plantitle, $report[0]['title']);
        $this->assertCount(3, $report);
        $this->assertSame(4, (int) $progress->total);
        $this->assertSame((int) $progress->total, (int) $progress->step);
    }

    /**
     * Every activity type is built in its section, with a report entry each and no warnings when the AI works.
     *
     * @return void
     */
    public function test_every_activity_type_is_built_and_reported(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $types = ['page', 'label', 'forum', 'assign', 'glossary', 'quiz'];

        [$course, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => ['Learn'],
            'sections' => [['title' => 'All', 'activities' => array_map(
                static fn(string $type): array => ['type' => $type, 'title' => 'Item ' . $type],
                $types
            )]],
        ]);
        $this->run_task($progressid);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $this->assertSame([], json_decode($progress->warnings, true));
        $modinfo = get_fast_modinfo($course);
        foreach ($types as $type) {
            $this->assertCount($type === 'page' ? 2 : 1, $modinfo->get_instances_of($type), $type);
        }
        $report = json_decode($progress->reportjson, true);
        $this->assertSame($types, array_slice(array_column($report, 'type'), 1));
        $this->assertNotContains(true, array_column($report, 'degraded'));
        $glossary = $DB->get_record('glossary', ['course' => $course->id], '*', MUST_EXIST);
        $this->assertCount(1, \local_studiolms\local\glossary_builder::get_terms((int) $glossary->id));
    }

    /**
     * When the AI gives nothing usable the course is still built, and each simplified activity is a warning.
     *
     * @return void
     */
    public function test_degraded_activities_are_recorded_as_warnings(): void {
        global $USER;
        $this->setAdminUser();
        preset_loader::set_directory_for_testing(make_request_directory());
        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => 'not json');

        [, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [['title' => 'Basics', 'activities' => [
                ['type' => 'forum', 'title' => 'Discuss'],
                ['type' => 'assign', 'title' => 'Essay'],
                ['type' => 'glossary', 'title' => 'Terms'],
                ['type' => 'quiz', 'title' => 'Check'],
                ['type' => 'video', 'title' => 'Clip'],
            ]]],
        ]);
        $this->run_task($progressid);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $warnings = implode('|', json_decode($progress->warnings, true));
        foreach (['Discuss', 'Essay', 'Terms', 'Check', 'Clip'] as $title) {
            $this->assertStringContainsString($title, $warnings);
        }
        $this->assertContains('page', array_column(json_decode($progress->reportjson, true), 'type'));
        $this->assertNotContains('video', array_column(json_decode($progress->reportjson, true), 'type'));
        $this->assertNotContains(false, array_column(json_decode($progress->reportjson, true), 'degraded'));
    }

    /**
     * A failure part-way through removes everything the run created, marks it failed and fires the event.
     *
     * The second section holds a malformed activity, which fails after the first section was fully built. The
     * first page also creates the plan page in section 0, which only the per-module cleanup can remove.
     *
     * @return void
     */
    public function test_failure_rolls_back_everything_created(): void {
        global $DB, $USER;
        $this->setAdminUser();
        $this->universal_ai();

        [$course, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [
                ['title' => 'Built first', 'activities' => [
                    ['type' => 'page', 'title' => 'Intro'],
                    ['type' => 'label', 'title' => 'Divider'],
                ]],
                ['title' => 'Broken', 'activities' => ['not an activity']],
            ],
        ]);
        $sectionsbefore = $DB->count_records('course_sections', ['course' => $course->id]);

        $sink = $this->redirectEvents();
        $this->run_task($progressid);
        $events = $sink->get_events();
        $sink->close();

        $progress = $this->progress($progressid);
        $this->assertSame('failed', $progress->status);
        $this->assertNotSame('', (string) $progress->errormsg);
        $this->assertSame(0, $DB->count_records('course_modules', ['course' => $course->id]));
        $this->assertSame($sectionsbefore, $DB->count_records('course_sections', ['course' => $course->id]));
        $failed = array_filter($events, static fn($e) => $e instanceof \local_studiolms\event\generation_failed);
        $this->assertCount(1, $failed);
        $this->assertSame(0, $DB->count_records('local_studiolms_generation_log', ['courseid' => $course->id]));
    }

    /**
     * Regression test: a long section or activity title used to overflow the varchar(255) progress message,
     * which made every progress update fail, including the one recording the failure, so the run crashed and
     * stayed "running" forever.
     *
     * @return void
     */
    public function test_long_titles_do_not_break_progress_tracking(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $long = str_repeat('é', 400);

        [, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [['title' => $long, 'activities' => [['type' => 'label', 'title' => $long]]]],
        ]);
        $this->run_task($progressid);

        $progress = $this->progress($progressid);
        $this->assertSame('completed', $progress->status);
        $this->assertLessThanOrEqual(255, \core_text::strlen($progress->message));
    }

    /**
     * Even the failure path survives a long title: the run is marked failed instead of crashing.
     *
     * @return void
     */
    public function test_failure_with_a_long_message_is_still_recorded(): void {
        global $USER;
        $this->setAdminUser();
        $this->universal_ai();
        $long = str_repeat('x', 400);

        [, $progressid] = $this->seed((int) $USER->id, [
            'objectives' => [],
            'sections' => [['title' => $long, 'activities' => ['not an activity']]],
        ]);
        $this->run_task($progressid);

        $progress = $this->progress($progressid);
        $this->assertSame('failed', $progress->status);
        $this->assertLessThanOrEqual(255, \core_text::strlen($progress->message));
    }
}
