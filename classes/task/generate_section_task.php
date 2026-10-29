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
 * Adhoc task that populates one section of a course with AI-generated activities.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\task;

use context_course;
use local_studiolms\local\activity_content;
use local_studiolms\local\ai_resolver;
use local_studiolms\local\content_access;
use local_studiolms\local\course_builder;
use local_studiolms\local\glossary_builder;
use local_studiolms\local\page_builder;
use local_studiolms\local\quiz_builder;
use local_studiolms\local\section_names;
use stdClass;

/**
 * Generates and inserts a teacher-approved list of activities into a course section.
 *
 * Activity planning is done synchronously by the plan_section web service before this
 * task is queued. The task receives the approved plan via custom_data and only executes
 * the content generation for each activity.
 */
class generate_section_task extends \core\task\adhoc_task {
    /** @var stdClass The progress record being updated. */
    private stdClass $progress;

    /** @var stdClass The target course. */
    private stdClass $course;

    /** @var int[] Course-module ids created, used for rollback. */
    private array $createdcmids = [];

    /** @var int|null Section number created by this task; non-null triggers section rollback. */
    private ?int $createdsectionnum = null;

    /** @var int Completed activity steps. */
    private int $step = 0;

    /** @var string Reference material provided by the teacher. */
    private string $reference = '';

    /** @var string Bloom's taxonomy level ('general' means no specific level). */
    private string $bloom = 'general';

    /** @var string[] Activities that used simplified content because the AI was unavailable. */
    private array $warnings = [];

    /** @var array Per-activity generation report saved to reportjson. */
    private array $report = [];

    #[\Override]
    public function get_name(): string {
        return get_string('task_generate_section', 'local_studiolms');
    }

    #[\Override]
    public function execute(): void {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        $data = $this->get_custom_data();
        // Checked before assigning: $progress is typed stdClass, so it cannot hold the false of a missing row.
        $progress = $DB->get_record('local_studiolms_progress', ['id' => $data->progressid]);
        if ($progress === false) {
            return;
        }
        $this->progress = $progress;

        $this->course = get_course($data->courseid);
        $context = context_course::instance($this->course->id);

        if (!has_capability('local/studiolms:generate', $context, $USER->id)) {
            $this->fail(get_string('error_populate', 'local_studiolms'));
            return;
        }

        if (!ai_resolver::is_enabled_in_context($context)) {
            $this->fail(get_string('aidisabledincourse', 'local_studiolms'));
            return;
        }

        $sectionnum      = (int) $data->sectionnum;
        $this->reference = (string) ($data->reference ?? '');
        $this->bloom     = (string) ($data->bloom ?? 'general');
        $wipe            = !empty($data->wipe);
        $theme           = (string) $data->theme;

        $activities = json_decode($data->activitiesjson, true);
        if (!is_array($activities) || empty($activities)) {
            $this->fail(get_string('error_populate', 'local_studiolms'));
            return;
        }

        // Re-checked here, not just in the web service: the permission can have been
        // revoked between enqueueing and execution. Also covers wipe (manageactivities).
        $types = content_access::types_of($activities);
        if (!content_access::can_create($context, $types, $sectionnum < 0, (int) $USER->id)) {
            $this->fail(get_string('error_populate', 'local_studiolms'));
            return;
        }

        $this->progress->status = 'running';
        $this->update();

        try {
            if ($sectionnum < 0) {
                $this->progress->message = get_string('section_planning', 'local_studiolms');
                $this->update();
                $newsection = course_create_section($this->course);
                $sectionnum = (int) $newsection->section;
                $this->createdsectionnum = $sectionnum;
            }

            $secrecord = $DB->get_record(
                'course_sections',
                ['course' => $this->course->id, 'section' => $sectionnum],
                '*',
                MUST_EXIST
            );
            $sectionname = section_names::of_record($secrecord, $context);

            if ($wipe && $sectionnum >= 0) {
                $this->progress->message = get_string('section_wiping', 'local_studiolms');
                $this->update();
                $cmids = $DB->get_fieldset_select(
                    'course_modules',
                    'id',
                    'course = :cid AND section = :sid',
                    ['cid' => $this->course->id, 'sid' => $secrecord->id]
                );
                // The news forum (Announcements) is never wiped, as in the wipe of the whole course.
                $cmids = array_diff(array_map('intval', $cmids), course_builder::news_forum_cmids((int) $this->course->id));
                foreach ($cmids as $cmid) {
                    course_builder::delete_module($this->course, (int) $cmid);
                }
            }

            $this->progress->total = count($activities);
            $this->update();

            foreach ($activities as $activity) {
                $this->add_activity($sectionnum, $sectionname, $activity, $theme, $activities);
            }

            $this->progress->status  = 'completed';
            $this->progress->message = get_string('section_done', 'local_studiolms');
            $this->update();
        } catch (\Throwable $e) {
            $this->rollback();
            $this->fail($e->getMessage());
        }
    }

    /**
     * Creates a single activity of the given type in the section.
     *
     * @param int $sectionnum The section number.
     * @param string $sectionname The section display name.
     * @param array $activity The activity definition {type, title}.
     * @param string $theme The course theme.
     * @param array $sectionactivities The activity definitions of the same section, for the label.
     * @return void
     */
    private function add_activity(
        int $sectionnum,
        string $sectionname,
        array $activity,
        string $theme,
        array $sectionactivities = []
    ): void {
        // Unknown types are built as pages, so they are reported as pages too (there is no string for them).
        $type        = content_access::modules_for([$activity['type']])[0];
        $title       = $activity['title'];
        $degraded    = false;
        $chosenpreset = '';

        switch ($type) {
            // Any other type was already turned into a page by content_access::modules_for().
            default:
            case 'page':
                $html = page_builder::render(
                    context_course::instance($this->course->id),
                    $theme,
                    $sectionname,
                    $title,
                    [],
                    $this->reference,
                    $this->bloom,
                    [],
                    $degraded,
                    $chosenpreset
                );
                $result = course_builder::add_page($this->course, $sectionnum, $title, $html);
                break;

            case 'label':
                $result = course_builder::add_label(
                    $this->course,
                    $sectionnum,
                    activity_content::label($title, array_column($sectionactivities, 'title'))
                );
                break;

            case 'forum':
                $intro  = activity_content::forum_intro($theme, $sectionname, $title, $this->reference, $this->bloom, $degraded);
                $result = course_builder::add_forum($this->course, $sectionnum, $title, $intro);
                break;

            case 'assign':
                $intro  = activity_content::assign_intro($theme, $sectionname, $title, $this->reference, $this->bloom, $degraded);
                $result = course_builder::add_assign($this->course, $sectionnum, $title, $intro);
                break;

            case 'glossary':
                $result   = glossary_builder::create($this->course, $sectionnum, $title, $theme);
                $degraded = empty(glossary_builder::get_terms($result->instance));
                break;

            case 'quiz':
                $intro  = \html_writer::tag('p', s($title));
                $result = course_builder::add_quiz($this->course, $sectionnum, $title, $intro);
                try {
                    $created  = quiz_builder::create(
                        $result->coursemodule,
                        $result->instance,
                        $theme,
                        $title,
                        5
                    );
                    $degraded = $created === 0;
                    activity_content::write_quiz_intro($result, $title, $created);
                } catch (\Throwable $e) {
                    debugging('StudioLMS quiz questions failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $degraded = true;
                }
                break;
        }

        if ($degraded) {
            $this->warnings[] = get_string('activity_' . $type, 'local_studiolms') . ': ' . $title;
        }

        $this->report[] = [
            'title'    => $title,
            'type'     => $type,
            'preset'   => $chosenpreset,
            'degraded' => $degraded,
        ];

        $this->createdcmids[] = $result->coursemodule;
        $this->step++;
        $this->progress->step    = $this->step;
        $this->progress->message = get_string('progress_activity', 'local_studiolms', $title);
        $this->update();
    }

    /**
     * Deletes all activities and the section created during this run.
     *
     * @return void
     */
    private function rollback(): void {
        foreach ($this->createdcmids as $cmid) {
            try {
                course_builder::delete_module($this->course, (int) $cmid);
            } catch (\Throwable $e) {
                continue;
            }
        }
        if ($this->createdsectionnum !== null) {
            try {
                course_delete_section($this->course, $this->createdsectionnum, true);
            } catch (\Throwable $e) {
                debugging('StudioLMS section rollback failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Persists the current progress record.
     *
     * @return void
     */
    private function update(): void {
        global $DB;
        $this->progress->createditems = json_encode(['cmids' => $this->createdcmids]);
        $this->progress->warnings     = json_encode($this->warnings);
        $this->progress->reportjson   = json_encode($this->report);
        // The message column is varchar(255) and can carry a teacher-written title; overflowing it would
        // make every update fail, including the one that records the failure.
        $this->progress->message = \core_text::substr((string) $this->progress->message, 0, 255);
        $this->progress->timemodified = time();
        $DB->update_record('local_studiolms_progress', $this->progress);
    }

    /**
     * Marks the generation as failed.
     *
     * @param string $message The error message.
     * @return void
     */
    private function fail(string $message): void {
        global $DB;
        $this->progress->status       = 'failed';
        $this->progress->errormsg     = $message;
        // The message column is varchar(255) and can carry a teacher-written title; overflowing it would
        // make every update fail, including the one that records the failure.
        $this->progress->message = \core_text::substr((string) $this->progress->message, 0, 255);
        $this->progress->timemodified = time();
        $DB->update_record('local_studiolms_progress', $this->progress);
    }
}
