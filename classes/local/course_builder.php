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
 * Adds sections and activities to an existing course.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');
require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

/**
 * Creates course sections and native Moodle activities through the standard course APIs.
 */
class course_builder {
    /**
     * Creates a new section at the end of the course and names it.
     *
     * @param stdClass $course The target course.
     * @param string $name The section name.
     * @return stdClass The created section record (id and section number).
     */
    public static function create_section(stdClass $course, string $name): stdClass {
        $section = course_create_section($course);
        course_update_section($course, $section, ['name' => $name]);
        return $section;
    }

    /**
     * Adds a page activity with rich HTML content.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $content The page HTML content.
     * @return stdClass The add_moduleinfo result (coursemodule and instance).
     */
    public static function add_page(stdClass $course, int $sectionnum, string $name, string $content): stdClass {
        $moduleinfo = self::base($course, 'page', $sectionnum, $name, '');
        $moduleinfo->page = ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0];
        $moduleinfo->content = $content;
        $moduleinfo->contentformat = FORMAT_HTML;
        $moduleinfo->display = 0;
        $moduleinfo->printheading = 1;
        $moduleinfo->printintro = 0;
        $moduleinfo->printlastmodified = 1;
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Adds a label used as a visual separator inside a section.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $content The label HTML content.
     * @return stdClass The add_moduleinfo result.
     */
    public static function add_label(stdClass $course, int $sectionnum, string $content): stdClass {
        // Labels are visual separators: view completion is not meaningful for them.
        $moduleinfo = self::base($course, 'label', $sectionnum, '', $content, false);
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Adds a general discussion forum.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $intro The forum introduction HTML.
     * @return stdClass The add_moduleinfo result.
     */
    public static function add_forum(stdClass $course, int $sectionnum, string $name, string $intro): stdClass {
        $moduleinfo = self::base($course, 'forum', $sectionnum, $name, $intro);
        $moduleinfo->type = 'general';
        $moduleinfo->forcesubscribe = 0;
        $moduleinfo->assessed = 0;
        $moduleinfo->scale = 0;
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Adds an assignment with online text submission.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $intro The assignment instructions HTML.
     * @return stdClass The add_moduleinfo result.
     */
    public static function add_assign(stdClass $course, int $sectionnum, string $name, string $intro): stdClass {
        $moduleinfo = self::base($course, 'assign', $sectionnum, $name, $intro);
        $moduleinfo->introattachments_editor = ['itemid' => 0];
        $moduleinfo->activityeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        $moduleinfo->submissiondrafts = 0;
        $moduleinfo->requiresubmissionstatement = 0;
        $moduleinfo->sendnotifications = 0;
        $moduleinfo->sendlatenotifications = 0;
        $moduleinfo->sendstudentnotifications = 1;
        $moduleinfo->duedate = 0;
        $moduleinfo->allowsubmissionsfromdate = 0;
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->gradingduedate = 0;
        $moduleinfo->timelimit = 0;
        $moduleinfo->teamsubmission = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->preventsubmissionnotingroup = 0;
        $moduleinfo->blindmarking = 0;
        $moduleinfo->markinganonymous = 0;
        $moduleinfo->attemptreopenmethod = 'none';
        $moduleinfo->maxattempts = -1;
        $moduleinfo->markingworkflow = 0;
        $moduleinfo->markingallocation = 0;
        $moduleinfo->hidegrader = 0;
        $moduleinfo->grade = 100;
        $moduleinfo->assignsubmission_onlinetext_enabled = 1;
        $moduleinfo->assignsubmission_onlinetext_wordlimit = 0;
        $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled = 0;
        $moduleinfo->assignsubmission_file_enabled = 0;
        $moduleinfo->assignsubmission_file_maxfiles = 1;
        $moduleinfo->assignsubmission_file_maxsizebytes = 0;
        $moduleinfo->assignsubmission_comments_enabled = 0;
        $moduleinfo->assignfeedback_comments_enabled = 0;
        $moduleinfo->assignfeedback_file_enabled = 0;
        $moduleinfo->assignfeedback_editpdf_enabled = 0;
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Adds a single continuous glossary.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $intro The glossary introduction HTML.
     * @return stdClass The add_moduleinfo result.
     */
    public static function add_glossary(stdClass $course, int $sectionnum, string $name, string $intro): stdClass {
        $moduleinfo = self::base($course, 'glossary', $sectionnum, $name, $intro);
        $moduleinfo->displayformat = 'dictionary';
        $moduleinfo->mainglossary = 0;
        $moduleinfo->globalglossary = 0;
        $moduleinfo->allowduplicatedentries = 0;
        $moduleinfo->allowcomments = 0;
        $moduleinfo->usedynalink = 1;
        $moduleinfo->defaultapproval = 1;
        $moduleinfo->entbypage = 10;
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Adds an empty quiz. Questions are attached later by the quiz builder.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $intro The quiz introduction HTML.
     * @return stdClass The add_moduleinfo result.
     */
    public static function add_quiz(stdClass $course, int $sectionnum, string $name, string $intro): stdClass {
        $moduleinfo = self::base($course, 'quiz', $sectionnum, $name, $intro);
        $moduleinfo->timeopen = 0;
        $moduleinfo->timeclose = 0;
        $moduleinfo->timelimit = 0;
        $moduleinfo->overduehandling = 'autosubmit';
        $moduleinfo->graceperiod = 0;
        $moduleinfo->preferredbehaviour = 'deferredfeedback';
        $moduleinfo->grade = 100;
        $moduleinfo->grademethod = 1;
        $moduleinfo->questionsperpage = 1;
        $moduleinfo->navmethod = 'free';
        $moduleinfo->shuffleanswers = 1;
        $moduleinfo->attempts = 0;
        $moduleinfo->attemptonlast = 0;
        $moduleinfo->decimalpoints = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->sumgrades = 0;
        $moduleinfo->quizpassword = '';
        $moduleinfo->subnet = '';
        $moduleinfo->browsersecurity = '-';
        $moduleinfo->delay1 = 0;
        $moduleinfo->delay2 = 0;
        $moduleinfo->showuserpicture = 0;
        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Builds the common module info shared by every activity type.
     *
     * @param stdClass $course The target course.
     * @param string $modulename The module name (page, forum, ...).
     * @param int $sectionnum The section number.
     * @param string $name The activity name.
     * @param string $intro The activity introduction HTML.
     * @param bool $trackcompletion Whether to enable "view to complete" tracking.
     * @return stdClass The base module info.
     */
    private static function base(
        stdClass $course,
        string $modulename,
        int $sectionnum,
        string $name,
        string $intro,
        bool $trackcompletion = true
    ): stdClass {
        global $DB;

        $moduleinfo = new stdClass();
        $moduleinfo->modulename = $modulename;
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $modulename], MUST_EXIST);
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->visible = 1;
        $moduleinfo->cmidnumber = '';
        if ($name !== '') {
            $moduleinfo->name = $name;
        }
        $moduleinfo->introeditor = ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0];

        if ($trackcompletion) {
            self::ensure_completion_enabled($course);
            if (!empty($course->enablecompletion)) {
                // View-to-complete is the simplest standard rule, which the teacher can refine later.
                $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
                $moduleinfo->completionview = 1;
                $moduleinfo->completionusegrade = 0;
                $moduleinfo->completionexpected = 0;
            }
        }

        return $moduleinfo;
    }

    /**
     * Enables completion tracking on the course when the site allows it.
     *
     * Idempotent: updates the course only once, then flips the in-memory flag so
     * repeated calls during a single generation are no-ops.
     *
     * @param stdClass $course The target course (updated in place).
     * @return void
     */
    private static function ensure_completion_enabled(stdClass $course): void {
        global $CFG;

        if (empty($CFG->enablecompletion) || !empty($course->enablecompletion)) {
            return;
        }

        update_course((object) ['id' => $course->id, 'enablecompletion' => 1]);
        $course->enablecompletion = 1;
    }
}
