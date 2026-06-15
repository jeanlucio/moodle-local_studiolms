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
 * Tests for the gamification setup helper.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the gamification setup helper.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gamification_setup::class)]
final class gamification_setup_test extends \advanced_testcase {
    /**
     * The social profile reports one extra step (the hourly forum collectible).
     *
     * @return void
     */
    public function test_step_count_per_profile(): void {
        $this->assertSame(8, gamification_setup::step_count(gamification_setup::PROFILE_SOCIAL));
        $this->assertSame(7, gamification_setup::step_count(gamification_setup::PROFILE_CONQUEST));
        $this->assertSame(7, gamification_setup::step_count(gamification_setup::PROFILE_NARRATIVE));
    }

    /**
     * Availability reflects whether the PlayerHUD block plugin is installed.
     *
     * @return void
     */
    public function test_is_available_matches_plugin_list(): void {
        $expected = array_key_exists('playerhud', \core_component::get_plugin_list('block'));
        $this->assertSame($expected, gamification_setup::is_available());
    }
}
