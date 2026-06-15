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
 * Event fired when a course is successfully populated by StudioLMS.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\event;

/**
 * Triggered after a background generation finishes and writes its audit log.
 *
 * The other['mode'] field carries the generation mode (standard or gamified);
 * other['profile'] carries the gamification profile when applicable.
 */
class course_generated extends \core\event\base {
    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_studiolms_generation_log';
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_course_generated', 'local_studiolms');
    }

    /**
     * Returns a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $mode = $this->other['mode'] ?? 'standard';
        return "The user with id '{$this->userid}' populated the course with id '{$this->courseid}' " .
            "using StudioLMS (mode: {$mode}).";
    }

    /**
     * Returns the URL of the populated course.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }
}
