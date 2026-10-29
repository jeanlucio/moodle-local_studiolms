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
 * Restyles a page or label into a hidden copy.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

use cm_info;
use context_course;
use context_module;
use core_courseformat\formatactions;
use local_studiolms\local\ai_resolver;
use local_studiolms\local\course_builder;
use moodle_exception;
use moodle_url;

/**
 * Restyles an existing activity without ever rewriting it.
 *
 * The original is never touched: the activity is duplicated, and only the copy, which is born hidden, receives the
 * new HTML. The plan is made before the copy exists, so a failure leaves nothing behind.
 */
class service {
    /**
     * Restyles an activity into a hidden copy.
     *
     * @param int $cmid The course module id of the activity.
     * @param bool $polish True to let the AI reorganise and polish the text; false to keep every word.
     * @return \stdClass The copy: cmid, name, editurl, viewurl and missingnumbers (the numbers of the original text
     *                   that a polished copy does not have, empty otherwise).
     * @throws moodle_exception When the user cannot restyle it, the activity cannot be restyled, or the AI fails.
     */
    public static function restyle(int $cmid, bool $polish = false): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        self::require_can_restyle($course, $cm);

        $html = adapter::read($cm->modname, (int) $cm->instance);
        if ($html === null) {
            throw new moodle_exception('restyle_notsupportedformat', 'local_studiolms');
        }

        $segments = segmenter::split($html);
        if (empty(array_filter($segments, fn(array $segment): bool => $segment['kind'] !== segmenter::KIND_FIXED))) {
            throw new moodle_exception('restyle_nochange', 'local_studiolms');
        }
        $context = context_module::instance($cm->id);
        $missing = [];
        if ($polish) {
            $restyled = polisher::polish($segments, adapter::max_blocks($cm->modname), $context, $missing);
        } else {
            $plan = planner::plan($segments, adapter::max_blocks($cm->modname), $context);
            $restyled = $plan === null ? null : assembler::build($segments, $plan);
        }
        if ($restyled === null) {
            throw new moodle_exception('restyle_noplan', 'local_studiolms');
        }

        $copy = self::duplicate($course, $cm);
        $name = get_string('restyle_copyname', 'local_studiolms', $cm->name);
        self::hide_and_rename($course, $copy, $name);
        adapter::write($copy->modname, (int) $copy->instance, $restyled);
        course_builder::mark_module_updated((int) $copy->id);
        rebuild_course_cache($course->id, true);

        return (object) [
            'cmid' => (int) $copy->id,
            'name' => $name,
            'missingnumbers' => $missing,
            'editurl' => (new moodle_url('/course/modedit.php', ['update' => $copy->id, 'return' => 1]))->out(false),
            'viewurl' => $copy->modname === 'label'
                ? (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false)
                : (new moodle_url('/mod/' . $copy->modname . '/view.php', ['id' => $copy->id]))->out(false),
        ];
    }

    /**
     * Requires everything the user needs to restyle the activity.
     *
     * The plugin's own capability does not stand alone: duplicating an activity is governed by the core capabilities
     * of managing activities, backing up and restoring them, and the copy must be one the user could add.
     *
     * @param \stdClass $course The course.
     * @param cm_info $cm The activity.
     * @return void
     * @throws moodle_exception When the module is not one that can be restyled.
     */
    private static function require_can_restyle(\stdClass $course, cm_info $cm): void {
        if (!adapter::supports($cm->modname)) {
            throw new moodle_exception('restyle_notsupported', 'local_studiolms');
        }
        $coursecontext = context_course::instance($course->id);
        $modcontext = context_module::instance($cm->id);
        require_capability('local/studiolms:restyle', $coursecontext);
        require_capability('moodle/course:manageactivities', $modcontext);
        require_capability('moodle/backup:backupactivity', $modcontext);
        require_capability('moodle/restore:restoreactivity', $coursecontext);
        require_capability('mod/' . $cm->modname . ':addinstance', $coursecontext);
        if (!course_allowed_module($course, $cm->modname)) {
            throw new moodle_exception('restyle_notsupported', 'local_studiolms');
        }
    }

    /**
     * Duplicates an activity, right after the original.
     *
     * @param \stdClass $course The course.
     * @param cm_info $cm The activity.
     * @return cm_info The copy.
     * @throws moodle_exception When Moodle cannot duplicate the activity.
     */
    private static function duplicate(\stdClass $course, cm_info $cm): cm_info {
        $actions = class_exists(formatactions::class) ? formatactions::cm($course->id) : null;
        // Moodle 5.3 moved the duplication into the course format actions; before it, duplicate_module() is the API.
        if ($actions !== null && method_exists($actions, 'duplicate')) {
            $copy = $actions->duplicate($cm->id);
        } else {
            $copy = duplicate_module($course, $cm);
        }
        if ($copy === null) {
            throw new moodle_exception('restyle_noduplicate', 'local_studiolms');
        }
        return $copy;
    }

    /**
     * Hides the copy and gives it its name.
     *
     * @param \stdClass $course The course.
     * @param cm_info $copy The copy.
     * @param string $name The name of the copy.
     * @return void
     */
    private static function hide_and_rename(\stdClass $course, cm_info $copy, string $name): void {
        $actions = class_exists(formatactions::class) ? formatactions::cm($course->id) : null;
        if ($actions !== null && method_exists($actions, 'rename') && method_exists($actions, 'set_visibility')) {
            $actions->rename($copy->id, $name);
            $actions->set_visibility($copy->id, 0);
            return;
        }
        set_coursemodule_name($copy->id, $name);
        set_coursemodule_visible($copy->id, 0);
    }
}
