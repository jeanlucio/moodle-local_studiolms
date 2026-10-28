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
 * Lists the pages and labels of a course that can be restyled.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

/**
 * Builds the list shown on the restyle page: the activities that can be restyled, by section.
 */
class overview {
    /** @var int How many characters of the name of an activity are shown; a label's name is its own text. */
    private const NAME_LENGTH = 90;

    /**
     * Builds the sections that hold pages or labels, with those activities.
     *
     * @param \stdClass $course The course.
     * @param int $highlightcmid The activity to point out, or 0 for none.
     * @return array[] The sections, each with name and activities (cmid, name, buttonlabel, type, icon, hidden,
     *                 highlight).
     */
    public static function sections(\stdClass $course, int $highlightcmid = 0): array {
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $activities = [];
            foreach ($modinfo->get_cms() as $cm) {
                if ((int) $cm->sectionnum !== (int) $section->section || !self::is_listed($cm)) {
                    continue;
                }
                $name = self::plain_name($cm);
                $activities[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $name,
                    'buttonlabel' => get_string('restyle_button_aria', 'local_studiolms', $name),
                    'type' => get_string('modulename', $cm->modname),
                    'icon' => $cm->get_icon_url()->out(false),
                    'hidden' => !$cm->visible,
                    'highlight' => (int) $cm->id === $highlightcmid,
                ];
            }
            if (!empty($activities)) {
                $sections[] = ['name' => get_section_name($course, $section), 'activities' => $activities];
            }
        }
        return $sections;
    }

    /**
     * Returns the name of an activity as plain text, for a template that escapes it once.
     *
     * @param \cm_info $cm The activity.
     * @return string
     */
    private static function plain_name(\cm_info $cm): string {
        $name = html_entity_decode(strip_tags(format_string($cm->name, true, ['context' => $cm->context])), ENT_QUOTES);
        return \core_text::strlen($name) > self::NAME_LENGTH
            ? \core_text::substr($name, 0, self::NAME_LENGTH - 1) . '…'
            : $name;
    }

    /**
     * Tells whether an activity is one that can be restyled and that is still there.
     *
     * @param \cm_info $cm The activity.
     * @return bool
     */
    private static function is_listed(\cm_info $cm): bool {
        return adapter::supports($cm->modname) && !$cm->deletioninprogress && $cm->uservisible;
    }
}
