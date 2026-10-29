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

namespace local_studiolms;

/**
 * Tests for the admin settings page of the plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class settings_test extends \advanced_testcase {
    /**
     * Makes an admin tree that holds only the category the settings page goes under, so that the page found in it
     * is the one the plugin's own file registers.
     *
     * @return \admin_root
     */
    private function empty_admin_tree(): \admin_root {
        $root = new \admin_root(true);
        $root->add('root', new \admin_category('localplugins', 'Local plugins'));
        return $root;
    }

    /**
     * An administrator gets the settings page, with the heading that explains where the AI comes from, and nobody
     * else does.
     *
     * @return void
     */
    public function test_the_settings_page_is_registered_for_those_who_can_configure_the_site(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/adminlib.php');

        $ADMIN = $this->empty_admin_tree();
        $hassiteconfig = true;
        include($CFG->dirroot . '/local/studiolms/settings.php');

        $page = $ADMIN->locate('local_studiolms');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertSame(get_string('pluginname', 'local_studiolms'), $page->visiblename);
        $this->assertCount(1, (array) $page->settings);

        $ADMIN = $this->empty_admin_tree();
        $hassiteconfig = false;
        $settings = null;
        include($CFG->dirroot . '/local/studiolms/settings.php');
        $this->assertNull($settings, 'without the capability no page is made');
        $this->assertNull($ADMIN->locate('local_studiolms'));
    }
}
