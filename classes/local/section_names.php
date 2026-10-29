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
 * The names of the sections of a course, as plain text.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Gives the name of a section as plain text.
 *
 * format_string() and the section names of the course formats return HTML: an ampersand is already an entity. A
 * template that prints the name escapes it again, and a prompt for the AI would carry the entity, so the name is
 * handed on as plain text, to be escaped once by whatever shows it.
 */
class section_names {
    /**
     * Turns a formatted name (HTML) into plain text.
     *
     * @param string $formatted The name as format_string() or get_section_name() returned it.
     * @return string
     */
    public static function from_formatted(string $formatted): string {
        return trim(html_entity_decode(strip_tags($formatted), ENT_QUOTES));
    }

    /**
     * Returns the name of a section record, or its position when it has no name.
     *
     * @param \stdClass $section The record of course_sections: name and section.
     * @param \context $context The context of the course.
     * @return string
     */
    public static function of_record(\stdClass $section, \context $context): string {
        if (trim((string) $section->name) === '') {
            return get_string('section_number', 'local_studiolms', (int) $section->section);
        }
        return self::from_formatted(format_string($section->name, true, ['context' => $context]));
    }
}
