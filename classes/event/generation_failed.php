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
 * Event fired when a StudioLMS course generation fails and rolls back.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\event;

/**
 * Triggered when a background generation fails. The other['error'] field carries
 * the failure message. No object table is set: the run produced no persistent
 * record (its created items were rolled back).
 */
class generation_failed extends \core\event\base {
    /**
     * Initialises the event data.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_generation_failed', 'local_studiolms');
    }

    /**
     * Returns a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $error = $this->other['error'] ?? '';
        return "A StudioLMS generation for the course with id '{$this->courseid}' failed: {$error}";
    }

    /**
     * Returns the URL of the target course.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }
}
