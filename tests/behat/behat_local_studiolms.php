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
 * Behat page resolvers for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL guard, as this file is required by Behat before Moodle is bootstrapped.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Resolves StudioLMS page URLs for the "I am on the ... page" Behat steps.
 */
class behat_local_studiolms extends behat_base {
    /**
     * Resolves a global StudioLMS page to its URL.
     *
     * Recognised page: "settings" (the plugin admin settings page).
     *
     * @param string $page The page identifier.
     * @return moodle_url The resolved URL.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'settings':
                return new moodle_url('/admin/settings.php', ['section' => 'local_studiolms']);
            default:
                throw new Exception("Unrecognised local_studiolms page type '{$page}'.");
        }
    }

    /**
     * Resolves a course-scoped StudioLMS page to its URL.
     *
     * Recognised type: "wizard" (the in-course generation wizard), identified by
     * the course short name.
     *
     * @param string $type The page type.
     * @param string $identifier The course short name.
     * @return moodle_url The resolved URL.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'wizard':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/local/studiolms/generate.php', ['courseid' => $courseid]);
            default:
                throw new Exception("Unrecognised local_studiolms page type '{$type}'.");
        }
    }
}
