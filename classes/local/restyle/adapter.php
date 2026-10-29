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
 * Reads and writes the HTML that can be restyled in each kind of activity.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

/**
 * The two operations the restyle needs from an activity type: which field holds the HTML, and how to write a new
 * HTML into it. Supporting another type means adding it here.
 */
class adapter {
    /** @var int[] The most blocks the plan may hold, by module name; the label is short, so it gets fewer. */
    private const MAX_BLOCKS = ['page' => 12, 'label' => 4];

    /**
     * Tells whether the module can be restyled.
     *
     * @param string $modname The module name.
     * @return bool
     */
    public static function supports(string $modname): bool {
        return isset(self::MAX_BLOCKS[$modname]);
    }

    /**
     * Returns the most blocks the plan of a module may hold.
     *
     * @param string $modname The module name.
     * @return int
     */
    public static function max_blocks(string $modname): int {
        return self::MAX_BLOCKS[$modname] ?? 0;
    }

    /**
     * Returns the HTML of an activity.
     *
     * @param string $modname The module name.
     * @param int $instanceid The id of the activity in its own table.
     * @return string|null The HTML, or null when the text is not written in HTML.
     */
    public static function read(string $modname, int $instanceid): ?string {
        global $DB;

        if ($modname === 'page') {
            $record = $DB->get_record('page', ['id' => $instanceid], 'id, content, contentformat', MUST_EXIST);
            return (int) $record->contentformat === (int) FORMAT_HTML ? (string) $record->content : null;
        }
        $record = $DB->get_record($modname, ['id' => $instanceid], 'id, intro, introformat', MUST_EXIST);
        return (int) $record->introformat === (int) FORMAT_HTML ? (string) $record->intro : null;
    }
}
