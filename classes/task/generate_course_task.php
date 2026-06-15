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
 * Adhoc task that populates a course from a reviewed outline.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\task;

use context_course;
use local_studiolms\local\ai_json;
use local_studiolms\local\ai_resolver;
use local_studiolms\local\course_builder;
use local_studiolms\local\gamification_setup;
use local_studiolms\local\glossary_builder;
use local_studiolms\local\page_builder;
use local_studiolms\local\quiz_builder;
use stdClass;

/**
 * Builds the sections and activities of a course in the background, tracking progress.
 */
class generate_course_task extends \core\task\adhoc_task {
    /** @var stdClass The progress record being updated. */
    private $progress;

    /** @var stdClass The target course. */
    private $course;

    /** @var array Created item ids for surgical rollback. */
    private $created = ['sectionids' => [], 'cmids' => []];

    /** @var int Steps completed so far. */
    private $step = 0;

    /** @var array Glossary terms used for the page pre-training block. */
    private $glossaryterms = [];

    /** @var string[] Activities that used simplified content because the AI was unavailable. */
    private $warnings = [];

    /** @var bool Whether the first course-intro page has already been created. */
    private bool $firstpagecreated = false;

    /** @var string Reference material from the briefing, passed to page content generation. */
    private string $reference = '';

    /** @var string Bloom's taxonomy level ('general' means no specific level). */
    private string $bloom = 'general';

    /** @var array Course learning objectives from the outline. */
    private array $objectives = [];

    /** @var array Per-activity generation report saved to reportjson. */
    private array $report = [];

    /** @var array Created course module ids grouped by module name (quiz, assign, forum). */
    private array $cmidsbytype = ['quiz' => [], 'assign' => [], 'forum' => []];

    /** @var string[] Section titles, used by the gamified narrative profile. */
    private array $sectiontitles = [];

    #[\Override]
    public function get_name(): string {
        return get_string('task_generate_course', 'local_studiolms');
    }

    #[\Override]
    public function execute(): void {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        $data = $this->get_custom_data();
        $this->progress = $DB->get_record('local_studiolms_progress', ['id' => $data->progressid]);
        if ($this->progress === false) {
            return;
        }

        $outline = $DB->get_record('local_studiolms_outline', ['id' => $this->progress->outlineid], '*', MUST_EXIST);
        $this->course = get_course($this->progress->courseid);
        $context = context_course::instance($this->course->id);

        if (!has_capability('local/studiolms:generate', $context, $USER->id)) {
            $this->fail(get_string('error_populate', 'local_studiolms'));
            return;
        }

        $briefing = json_decode($outline->briefingjson, true) ?: [];
        $structure = json_decode($outline->outlinejson, true) ?: [];
        $sections = $structure['sections'] ?? [];
        $theme = $briefing['theme'] ?? '';
        $this->reference = $briefing['reference'] ?? '';
        $this->bloom = $briefing['bloom'] ?? 'general';
        $this->objectives = $structure['objectives'] ?? [];

        $this->progress->status = 'running';
        $this->progress->total = self::count_steps($sections);
        $this->update();

        try {
            if (!empty($data->wipe)) {
                require_capability('local/studiolms:generate', $context);
                $this->wipe_course();
            }
            $this->build($sections, $theme);
            $this->gamify($briefing, $theme);
            $this->complete($outline, $briefing);
        } catch (\Throwable $e) {
            $this->rollback();
            $this->fail($e->getMessage());
        }
    }

    /**
     * Counts the total number of progress steps for the outline.
     *
     * @param array $sections The outline sections.
     * @return int Total steps (sections plus activities).
     */
    private static function count_steps(array $sections): int {
        $total = count($sections);
        foreach ($sections as $section) {
            $total += count($section['activities'] ?? []);
        }
        return $total;
    }

    /**
     * Creates the sections and their activities.
     *
     * @param array $sections The outline sections.
     * @param string $theme The course theme.
     * @return void
     */
    private function build(array $sections, string $theme): void {
        foreach ($sections as $section) {
            $title = $section['title'];
            $this->sectiontitles[] = $title;
            $record = course_builder::create_section($this->course, $title);
            $this->created['sectionids'][] = $record->id;
            $this->advance(get_string('progress_section', 'local_studiolms', $title));

            foreach ($section['activities'] ?? [] as $activity) {
                $this->add_activity($record->section, $title, $activity, $theme);
            }
        }
    }

    /**
     * Creates a single activity of the given type.
     *
     * @param int $sectionnum The section number.
     * @param string $sectiontitle The section title.
     * @param array $activity The activity definition (type, title).
     * @param string $theme The course theme.
     * @return void
     */
    private function add_activity(int $sectionnum, string $sectiontitle, array $activity, string $theme): void {
        $type = $activity['type'];
        $title = $activity['title'];
        $degraded = false;
        $chosenpreset = '';

        switch ($type) {
            case 'page':
                if (!$this->firstpagecreated) {
                    $this->firstpagecreated = true;
                    $plandegraded = false;
                    $planhtml = page_builder::render_course_intro($theme, $this->course->fullname, [], $plandegraded);
                    $plantitle = get_string('courseplantitle', 'local_studiolms');
                    $planresult = course_builder::add_page($this->course, 0, $plantitle, $planhtml);
                    $this->created['cmids'][] = $planresult->coursemodule;
                    $this->progress->total++;
                    $this->report[] = [
                        'title' => $plantitle,
                        'type' => 'page',
                        'preset' => 'plan',
                        'degraded' => $plandegraded,
                    ];
                    if ($plandegraded) {
                        $this->warnings[] = $plantitle . ': ' . $this->course->fullname;
                    }
                }
                $html = page_builder::render(
                    $theme,
                    $sectiontitle,
                    $title,
                    $this->glossaryterms,
                    $this->reference,
                    $this->bloom,
                    $this->objectives,
                    $degraded,
                    $chosenpreset
                );
                $result = course_builder::add_page($this->course, $sectionnum, $title, $html);
                break;
            case 'label':
                $result = course_builder::add_label($this->course, $sectionnum, \html_writer::tag('h4', s($title)));
                break;
            case 'forum':
                $intro = $this->generate_html(
                    'forum',
                    $theme,
                    $sectiontitle,
                    $title,
                    $this->reference,
                    $this->bloom,
                    $degraded
                );
                $result = course_builder::add_forum($this->course, $sectionnum, $title, $intro);
                break;
            case 'assign':
                $intro = $this->generate_html(
                    'assign',
                    $theme,
                    $sectiontitle,
                    $title,
                    $this->reference,
                    $this->bloom,
                    $degraded
                );
                $result = course_builder::add_assign($this->course, $sectionnum, $title, $intro);
                break;
            case 'glossary':
                $result = glossary_builder::create($this->course, $sectionnum, $title, $theme);
                $this->glossaryterms = glossary_builder::get_terms($result->instance);
                $degraded = empty($this->glossaryterms);
                break;
            case 'quiz':
                $intro = \html_writer::tag('p', s($title));
                $result = course_builder::add_quiz($this->course, $sectionnum, $title, $intro);
                try {
                    $degraded = quiz_builder::create($result->coursemodule, $result->instance, $theme, $title, 5) === 0;
                } catch (\Throwable $e) {
                    debugging('StudioLMS quiz questions failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $degraded = true;
                }
                break;
            default:
                $html = $this->generate_html(
                    'page',
                    $theme,
                    $sectiontitle,
                    $title,
                    $this->reference,
                    $this->bloom,
                    $degraded
                );
                $result = course_builder::add_page($this->course, $sectionnum, $title, $html);
                break;
        }

        if ($degraded) {
            $this->warnings[] = get_string('activity_' . $type, 'local_studiolms') . ': ' . $title;
        }

        $this->report[] = [
            'title' => $title,
            'type' => $type,
            'preset' => $chosenpreset,
            'degraded' => $degraded,
        ];

        $this->created['cmids'][] = $result->coursemodule;
        if (isset($this->cmidsbytype[$type])) {
            $this->cmidsbytype[$type][] = $result->coursemodule;
        }
        $this->advance(get_string('progress_activity', 'local_studiolms', $title));
    }

    /**
     * Configures PlayerHUD gamification when the gamified mode is selected.
     *
     * Runs after the course content is built. Failures here are recorded as
     * warnings and never roll back the already-created course content.
     *
     * @param array $briefing The briefing data (mode, profile).
     * @param string $theme The course theme.
     * @return void
     */
    private function gamify(array $briefing, string $theme): void {
        $mode = $briefing['mode'] ?? 'standard';
        if ($mode !== 'gamified' || !gamification_setup::is_available()) {
            return;
        }

        $profile = $briefing['profile'] ?? gamification_setup::PROFILE_CONQUEST;
        $this->progress->total += gamification_setup::step_count($profile);
        $this->update();

        try {
            $warnings = gamification_setup::run(
                $this->course,
                $profile,
                $theme,
                $this->cmidsbytype,
                $this->sectiontitles,
                fn(string $message) => $this->advance($message)
            );
            $this->warnings = array_merge($this->warnings, $warnings);
        } catch (\Throwable $e) {
            debugging('StudioLMS gamification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->warnings[] = get_string('progress_playerhud', 'local_studiolms');
        }
    }

    /**
     * Generates cleaned HTML content for an activity, falling back to a simple paragraph.
     *
     * @param string $kind The content kind (page, forum, assign).
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $title The activity title.
     * @param string $reference Reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param bool $degraded Set to true when the AI content could not be generated.
     * @return string The cleaned HTML content.
     */
    private function generate_html(
        string $kind,
        string $theme,
        string $sectiontitle,
        string $title,
        string $reference = '',
        string $bloom = 'general',
        bool &$degraded = false
    ): string {
        $language = current_language();
        $instructions = [
            'page' => 'Write the body of a course page with headings, paragraphs and lists.',
            'forum' => 'Write a short forum introduction with a guiding discussion question.',
            'assign' => 'Write clear assignment instructions and the expected deliverables.',
        ];
        $instruction = $instructions[$kind] ?? $instructions['page'];

        try {
            $system = $instruction . ' Return ONLY a valid JSON object shaped like {"content": "..."} '
                . 'where content is clean HTML (headings, paragraphs, lists) with no markdown fences. '
                . "Write in the language identified by the code: {$language}.";
            if ($bloom !== 'general' && $bloom !== '') {
                $system .= " Cognitive level (Bloom's taxonomy): {$bloom}.";
            }
            $user = "Course theme: {$theme}\nSection: {$sectiontitle}\nTitle: {$title}";
            if ($reference !== '') {
                $user .= "\n\nReference material:\n" . mb_substr($reference, 0, 3000);
            }
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
            $html = is_array($decoded) ? trim((string) ($decoded['content'] ?? '')) : '';
            if ($html === '' || trim(html_to_text($html)) === '') {
                throw new \moodle_exception('invalidairesponse', 'local_studiolms');
            }
            return clean_text($html, FORMAT_HTML);
        } catch (\Throwable $e) {
            $degraded = true;
            return \html_writer::tag('p', s($title));
        }
    }

    /**
     * Removes the existing sections and activities of the course.
     * The Moodle default news forum (Announcements) is always preserved.
     *
     * @return void
     */
    private function wipe_course(): void {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        foreach ($modinfo->get_cms() as $cm) {
            $isnewsforum = $cm->modname === 'forum'
                && $DB->record_exists('forum', ['id' => $cm->instance, 'course' => $this->course->id, 'type' => 'news']);
            if ($isnewsforum) {
                continue;
            }
            course_delete_module($cm->id);
        }

        $sections = $DB->get_records('course_sections', ['course' => $this->course->id], 'section DESC');
        foreach ($sections as $section) {
            if ($section->section > 0) {
                course_delete_section($this->course, $section, true);
            }
        }
    }

    /**
     * Deletes everything created so far after a failure.
     *
     * @return void
     */
    private function rollback(): void {
        global $DB;

        foreach ($this->created['cmids'] as $cmid) {
            try {
                course_delete_module($cmid);
            } catch (\Throwable $e) {
                continue;
            }
        }
        if (!empty($this->created['sectionids'])) {
            $sections = $DB->get_records_list('course_sections', 'id', $this->created['sectionids'], 'section DESC');
            foreach ($sections as $section) {
                try {
                    course_delete_section($this->course, $section, true);
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
    }

    /**
     * Records a completed step and updates progress.
     *
     * @param string $message The step message.
     * @return void
     */
    private function advance(string $message): void {
        $this->step++;
        $this->progress->step = $this->step;
        $this->progress->message = $message;
        $this->update();
    }

    /**
     * Persists the current progress record.
     *
     * @return void
     */
    private function update(): void {
        global $DB;
        $this->progress->createditems = json_encode($this->created);
        $this->progress->warnings = json_encode($this->warnings);
        $this->progress->reportjson = json_encode($this->report);
        $this->progress->timemodified = time();
        $DB->update_record('local_studiolms_progress', $this->progress);
    }

    /**
     * Marks the generation as completed and writes the audit log.
     *
     * @param stdClass $outline The outline record.
     * @param array $briefing The briefing data.
     * @return void
     */
    private function complete(stdClass $outline, array $briefing): void {
        global $DB;

        $this->progress->status = 'completed';
        $this->progress->message = get_string('progress_done', 'local_studiolms');
        $this->update();

        $outline->status = 'completed';
        $outline->timemodified = time();
        $DB->update_record('local_studiolms_outline', $outline);

        $DB->insert_record('local_studiolms_generation_log', (object) [
            'userid' => $this->progress->userid,
            'courseid' => $this->course->id,
            'mode' => $briefing['mode'] ?? 'standard',
            'bloomlevel' => $briefing['bloom'] ?? null,
            'gamificationprofile' => $briefing['profile'] ?? null,
            'prompt' => $briefing['theme'] ?? '',
            'outlinejson' => $outline->outlinejson,
            'status' => 'completed',
            'timecreated' => time(),
            'timecompleted' => time(),
        ]);
    }

    /**
     * Marks the generation as failed.
     *
     * @param string $message The error message.
     * @return void
     */
    private function fail(string $message): void {
        global $DB;
        if ($this->progress === null) {
            return;
        }
        $this->progress->status = 'failed';
        $this->progress->errormsg = $message;
        $this->progress->timemodified = time();
        $DB->update_record('local_studiolms_progress', $this->progress);
    }
}
