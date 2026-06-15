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
 * Upgrade steps for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the local_studiolms upgrade steps.
 *
 * @param int $oldversion The currently installed plugin version.
 * @return bool Always true on success.
 */
function xmldb_local_studiolms_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061305) {
        $table = new xmldb_table('local_studiolms_progress');
        $field = new xmldb_field('warnings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'errormsg');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061305, 'local', 'studiolms');
    }

    if ($oldversion < 2026061400) {
        $table = new xmldb_table('local_studiolms_progress');
        $field = new xmldb_field('reportjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'warnings');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026061400, 'local', 'studiolms');
    }

    if ($oldversion < 2026061500) {
        $table = new xmldb_table('local_studiolms_progress');

        // The FK on outlineid creates a dependent index that blocks field modification; drop it first.
        $key = new xmldb_key('outlineid', XMLDB_KEY_FOREIGN, ['outlineid'], 'local_studiolms_outline', ['id']);
        if ($dbman->find_key_name($table, $key)) {
            $dbman->drop_key($table, $key);
        }

        // Make outlineid nullable so section-only generation rows need no outline record.
        $field = new xmldb_field('outlineid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        $dbman->change_field_notnull($table, $field);

        // Restore the FK (nullable FKs are valid — NULL means no outline linked).
        $dbman->add_key($table, $key);

        upgrade_plugin_savepoint(true, 2026061500, 'local', 'studiolms');
    }

    return true;
}
