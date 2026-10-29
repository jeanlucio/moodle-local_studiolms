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

namespace local_studiolms\local;

/**
 * Tests for the names of the sections as plain text.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\section_names
 */
final class section_names_test extends \advanced_testcase {
    /**
     * A formatted name comes back as the text it was written with.
     *
     * @return void
     */
    public function test_a_formatted_name_becomes_plain_text(): void {
        $this->assertSame('P&D', section_names::from_formatted('P&amp;D'));
        $this->assertSame('A > B "quoted"', section_names::from_formatted('A &gt; B &quot;quoted&quot;'));
        $this->assertSame('Bold', section_names::from_formatted(' <b>Bold</b> '));
    }

    /**
     * The name of a section record is plain whatever formatstringstriptags says, and a section with no name is
     * known by its position.
     *
     * @return void
     */
    public function test_a_section_record_gives_its_plain_name_or_its_position(): void {
        global $CFG;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        foreach ([1, 0] as $striptags) {
            $CFG->formatstringstriptags = $striptags;
            \core\di::reset_container();
            $this->assertSame(
                'P&D > Q',
                section_names::of_record((object) ['name' => 'P&D > Q', 'section' => 1], $context),
                "formatstringstriptags $striptags"
            );
        }
        // A section with no name is called as the course page calls it: General for the first, Topic N for the rest.
        $this->assertSame(
            get_string('section0name', 'format_topics'),
            section_names::of_record((object) ['name' => '', 'section' => 0], $context)
        );
        $this->assertSame(
            get_section_name($course, 1),
            section_names::of_record((object) ['name' => '  ', 'section' => 1], $context)
        );
        $this->assertSame(
            get_section_name($course, 2),
            section_names::of_record((object) ['name' => null, 'section' => 2], $context)
        );
        // A section that does not exist has only its position to go by.
        $this->assertSame(
            get_string('section_number', 'local_studiolms', 99),
            section_names::of_record((object) ['name' => '', 'section' => 99], $context)
        );
    }
}
