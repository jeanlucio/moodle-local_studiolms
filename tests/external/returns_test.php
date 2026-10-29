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

namespace local_studiolms\external;

use core_external\external_description;

/**
 * Tests that every web service of the plugin describes what it returns.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\external\generate_activity
 * @covers \local_studiolms\external\generate_outline
 * @covers \local_studiolms\external\generate_section
 * @covers \local_studiolms\external\get_progress
 * @covers \local_studiolms\external\plan_section
 * @covers \local_studiolms\external\populate_course
 * @covers \local_studiolms\external\restyle_activity
 */
final class returns_test extends \advanced_testcase {
    /**
     * Every web service declared by the plugin has parameters and a return description that Moodle can read.
     *
     * @return void
     */
    public function test_every_function_describes_its_parameters_and_its_return(): void {
        global $CFG;

        $functions = [];
        require($CFG->dirroot . '/local/studiolms/db/services.php');
        $this->assertNotEmpty($functions);

        foreach ($functions as $name => $definition) {
            $class = $definition['classname'];
            $this->assertInstanceOf(
                external_description::class,
                $class::execute_parameters(),
                $name . ' describes its parameters'
            );
            $this->assertInstanceOf(
                external_description::class,
                $class::execute_returns(),
                $name . ' describes what it returns'
            );
        }
    }
}
