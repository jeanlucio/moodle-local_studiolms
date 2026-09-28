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
 * Core capability checks for creating course content.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use context_course;

/**
 * Makes local/studiolms:generate subordinate to the core capabilities that govern creating content.
 *
 * The course builders call add_moduleinfo() and course_create_section(), which check no capability at
 * all: the core UI enforces them upstream. Without these checks the plugin's own capability would
 * silently grant everything the core ones (and any override on them) are meant to control.
 */
class content_access {
    /** @var string[] Module names the generators can create. */
    private const MODULES = ['page', 'label', 'forum', 'assign', 'glossary', 'quiz'];

    /**
     * Maps activity types from a generation plan to the modules that will be created.
     *
     * Unknown types are created as pages by the generators, so they map to 'page' here too.
     *
     * @param array $types Activity type strings.
     * @return string[] Distinct module names.
     */
    public static function modules_for(array $types): array {
        $modules = [];
        foreach ($types as $type) {
            $modules[] = in_array($type, self::MODULES, true) ? $type : 'page';
        }
        return array_values(array_unique($modules));
    }

    /**
     * Lists the core capabilities needed to create the given content.
     *
     * @param array $types Activity type strings that will be created.
     * @param bool $newsections Whether new sections will be created.
     * @return string[] Capability names.
     */
    public static function required_capabilities(array $types, bool $newsections): array {
        $capabilities = ['moodle/course:manageactivities'];
        if ($newsections) {
            $capabilities[] = 'moodle/course:update';
        }
        foreach (self::modules_for($types) as $module) {
            $capabilities[] = 'mod/' . $module . ':addinstance';
        }
        return $capabilities;
    }

    /**
     * Requires the current user to hold every core capability needed to create the content.
     *
     * @param context_course $context Course context.
     * @param array $types Activity type strings that will be created.
     * @param bool $newsections Whether new sections will be created.
     * @return void
     * @throws \required_capability_exception When a capability is missing.
     */
    public static function require_can_create(context_course $context, array $types, bool $newsections): void {
        foreach (self::required_capabilities($types, $newsections) as $capability) {
            require_capability($capability, $context);
        }
    }

    /**
     * Tells whether a user holds every core capability needed to create the content.
     *
     * Used by the background tasks, which re-check because the permission can be revoked between
     * enqueueing and execution.
     *
     * @param context_course $context Course context.
     * @param array $types Activity type strings that will be created.
     * @param bool $newsections Whether new sections will be created.
     * @param int $userid The user the task runs as.
     * @return bool
     */
    public static function can_create(context_course $context, array $types, bool $newsections, int $userid): bool {
        foreach (self::required_capabilities($types, $newsections) as $capability) {
            if (!has_capability($capability, $context, $userid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extracts the activity types from a list of {type, title} activity definitions.
     *
     * @param mixed $activities Decoded activities; anything that is not a list of arrays is ignored.
     * @return string[] Activity types, one per well-formed activity.
     */
    public static function types_of(mixed $activities): array {
        $types = [];
        if (!is_array($activities)) {
            return $types;
        }
        foreach ($activities as $activity) {
            if (is_array($activity)) {
                $types[] = (string) ($activity['type'] ?? '');
            }
        }
        return $types;
    }
}
