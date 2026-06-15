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
use local_studiolms\local\ai_json;
use local_studiolms\local\ai_resolver;
use local_studiolms\local\course_builder;
use local_studiolms\local\glossary_builder;
use local_studiolms\local\page_builder;
use local_studiolms\local\quiz_builder;
use stdClass;

/**
 * Generates and inserts activities into an existing course section.
 */
class generate_section_task extends \core\task\adhoc_task {
    /** @var stdClass The progress record being updated. */
    private stdClass $progress;

    /** @var stdClass The target course. */
    private stdClass $course;

    /** @var array Course-module ids created, used for rollback. */
    private array $created = ['cmids' => []];

    /** @var int Completed activity steps. */
    private int $step = 0;

    /** @var string Reference material provided by the teacher. */
    private string $reference = '';

    /** @var string Bloom's taxonomy level ('general' means no specific level). */
    private string $bloom = 'general';

    #[\Override]
    public function get_name(): string {
        return get_string('task_generate_section', 'local_studiolms');
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

        $this->course = get_course($data->courseid);
        $context = context_course::instance($this->course->id);

        if (!has_capability('local/studiolms:generate', $context, $USER->id)) {
            $this->fail(get_string('error_populate', 'local_studiolms'));
            return;
        }

        $sectionnum       = (int) $data->sectionnum;
        $theme            = (string) $data->theme;
        $this->reference  = (string) ($data->reference ?? '');
        $this->bloom      = (string) ($data->bloom ?? 'general');

        $this->progress->status = 'running';
        $this->update();

        try {
            $section = $DB->get_record(
                'course_sections',
                ['course' => $this->course->id, 'section' => $sectionnum],
                '*',
                MUST_EXIST
            );
            $sectionname = ($section->name !== '' && $section->name !== null)
                ? format_string($section->name)
                : get_string('section_number', 'local_studiolms', $sectionnum);

            $this->progress->message = get_string('section_planning', 'local_studiolms');
            $this->update();

            $activities = $this->plan_activities($sectionname, $theme);

            $this->progress->total = count($activities);
            $this->update();

            foreach ($activities as $activity) {
                $this->add_activity($sectionnum, $sectionname, $activity, $theme);
            }

            $this->progress->status  = 'completed';
            $this->progress->message = get_string('progress_done', 'local_studiolms');
            $this->update();
        } catch (\Throwable $e) {
            $this->rollback();
            $this->fail($e->getMessage());
        }
    }

    /**
     * Calls the AI provider to get a list of activities for the section.
     *
     * Falls back to a single page if the AI is unavailable or returns invalid JSON.
     *
     * @param string $sectionname The section display name.
     * @param string $theme The course theme.
     * @return array Array of {type, title} activity definitions.
     */
    private function plan_activities(string $sectionname, string $theme): array {
        $language = current_language();
        $system = 'Return ONLY a valid JSON array of learning activities for a course section. '
            . 'Example: [{"type":"page","title":"Introduction"},{"type":"quiz","title":"Check"}]. '
            . 'Allowed types: page, quiz, forum, assign, label, glossary. '
            . 'Return between 3 and 6 activities. Titles must be under 80 characters. '
            . "Write all titles in the language identified by the code: {$language}.";

        if ($this->bloom !== 'general' && $this->bloom !== '') {
            $system .= " Cognitive level (Bloom's taxonomy): {$this->bloom}.";
        }

        $user = "Section: {$sectionname}\nCourse theme: {$theme}";
        if ($this->reference !== '') {
            $user .= "\n\nReference material:\n" . mb_substr($this->reference, 0, 3000);
        }

        try {
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
            if (!is_array($decoded) || empty($decoded)) {
                throw new \moodle_exception('invalidairesponse', 'local_studiolms');
            }
            $activities = [];
            foreach ($decoded as $item) {
                if (isset($item['type'], $item['title'])) {
                    $activities[] = [
                        'type'  => (string) $item['type'],
                        'title' => (string) $item['title'],
                    ];
                }
            }
            if (empty($activities)) {
                throw new \moodle_exception('invalidairesponse', 'local_studiolms');
            }
            return $activities;
        } catch (\Throwable $e) {
            return [['type' => 'page', 'title' => $sectionname]];
        }
    }

    /**
     * Creates a single activity of the given type in the section.
     *
     * @param int $sectionnum The section number.
     * @param string $sectionname The section display name.
     * @param array $activity The activity definition (type, title).
     * @param string $theme The course theme.
     * @return void
     */
    private function add_activity(
        int $sectionnum,
        string $sectionname,
        array $activity,
        string $theme
    ): void {
        $type        = $activity['type'];
        $title       = $activity['title'];
        $degraded    = false;
        $chosenpreset = '';

        switch ($type) {
            case 'page':
                $html = page_builder::render(
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
                    \html_writer::tag('h4', s($title))
                );
                break;

            case 'forum':
                $intro  = $this->generate_html('forum', $theme, $sectionname, $title, $degraded);
                $result = course_builder::add_forum($this->course, $sectionnum, $title, $intro);
                break;

            case 'assign':
                $intro  = $this->generate_html('assign', $theme, $sectionname, $title, $degraded);
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
                } catch (\Throwable $e) {
                    debugging('StudioLMS quiz questions failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $degraded = true;
                }
                break;

            default:
                $html = $this->generate_html('page', $theme, $sectionname, $title, $degraded);
                $result = course_builder::add_page($this->course, $sectionnum, $title, $html);
                break;
        }

        $this->created['cmids'][] = $result->coursemodule;
        $this->step++;
        $this->progress->step    = $this->step;
        $this->progress->message = get_string('progress_activity', 'local_studiolms', $title);
        $this->update();
    }

    /**
     * Generates clean HTML content for an activity using the AI provider.
     *
     * Falls back to a plain paragraph when the AI is unavailable.
     *
     * @param string $kind Content kind: 'page', 'forum', or 'assign'.
     * @param string $theme The course theme.
     * @param string $sectionname The section display name.
     * @param string $title The activity title.
     * @param bool $degraded Set to true when AI content could not be generated.
     * @return string Clean HTML content.
     */
    private function generate_html(
        string $kind,
        string $theme,
        string $sectionname,
        string $title,
        bool &$degraded
    ): string {
        $language = current_language();
        $instructions = [
            'page'   => 'Write the body of a course page with headings, paragraphs and lists.',
            'forum'  => 'Write a short forum introduction with a guiding discussion question.',
            'assign' => 'Write clear assignment instructions and the expected deliverables.',
        ];
        $instruction = $instructions[$kind] ?? $instructions['page'];

        try {
            $system = $instruction
                . ' Return ONLY a valid JSON object shaped like {"content": "..."}'
                . ' where content is clean HTML (headings, paragraphs, lists) with no markdown fences.'
                . " Write in the language identified by the code: {$language}.";
            if ($this->bloom !== 'general' && $this->bloom !== '') {
                $system .= " Cognitive level (Bloom's taxonomy): {$this->bloom}.";
            }
            $user = "Course theme: {$theme}\nSection: {$sectionname}\nTitle: {$title}";
            if ($this->reference !== '') {
                $user .= "\n\nReference material:\n" . mb_substr($this->reference, 0, 3000);
            }
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
            $html    = is_array($decoded) ? trim((string) ($decoded['content'] ?? '')) : '';
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
     * Deletes all activities created so far after a failure.
     *
     * @return void
     */
    private function rollback(): void {
        foreach ($this->created['cmids'] as $cmid) {
            try {
                course_delete_module($cmid);
            } catch (\Throwable $e) {
                continue;
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
        $this->progress->createditems = json_encode($this->created);
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
        $this->progress->status      = 'failed';
        $this->progress->errormsg    = $message;
        $this->progress->timemodified = time();
        $DB->update_record('local_studiolms_progress', $this->progress);
    }
}
