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
 * Tests for the plugin upgrade steps.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms;

/**
 * Runs the upgrade steps against a progress table put back into its pre-upgrade shape.
 *
 * Each test changes the table's structure and restores it in a finally block, so a failure never leaves the
 * shared test database with a schema that differs from install.xml.
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_local_studiolms_upgrade')]
final class upgrade_test extends \advanced_testcase {
    /** @var int A version older than every upgrade step. */
    private const OLDEST = 2026061304;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/studiolms/db/upgrade.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    /**
     * Returns the progress table definition.
     *
     * @return \xmldb_table
     */
    private function table(): \xmldb_table {
        return new \xmldb_table('local_studiolms_progress');
    }

    /**
     * The definition of a column added by an upgrade step.
     *
     * @param string $name The column name.
     * @param string $previous The column it follows.
     * @return \xmldb_field
     */
    private function text_field(string $name, string $previous): \xmldb_field {
        return new \xmldb_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null, $previous);
    }

    /**
     * The outlineid foreign key.
     *
     * @return \xmldb_key
     */
    private function outline_key(): \xmldb_key {
        return new \xmldb_key('outlineid', XMLDB_KEY_FOREIGN, ['outlineid'], 'local_studiolms_outline', ['id']);
    }

    /**
     * Whether the database has an index on outlineid, which is what a foreign key really is in XMLDB.
     *
     * @return bool
     */
    private function outline_index_exists(): bool {
        global $DB;
        $index = new \xmldb_index('outlineid', XMLDB_INDEX_NOTUNIQUE, ['outlineid']);
        return (bool) $DB->get_manager()->find_index_name($this->table(), $index);
    }

    /**
     * Sets the installed version so the savepoints of the steps that follow are accepted.
     *
     * @return void
     */
    private function pretend_installed_version_is_oldest(): void {
        set_config('version', self::OLDEST, 'local_studiolms');
    }

    /**
     * Sites installed before the warnings and report columns existed get them.
     *
     * @return void
     */
    public function test_upgrade_adds_the_warnings_and_report_columns(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $warnings = $this->text_field('warnings', 'errormsg');
        $report = $this->text_field('reportjson', 'warnings');

        try {
            $dbman->drop_field($this->table(), $report);
            $dbman->drop_field($this->table(), $warnings);
            $this->pretend_installed_version_is_oldest();

            $this->assertTrue(xmldb_local_studiolms_upgrade(self::OLDEST));

            $this->assertTrue($dbman->field_exists($this->table(), $warnings));
            $this->assertTrue($dbman->field_exists($this->table(), $report));
        } finally {
            foreach ([$warnings, $report] as $field) {
                if (!$dbman->field_exists($this->table(), $field)) {
                    $dbman->add_field($this->table(), $field);
                }
            }
        }
    }

    /**
     * Sites where outlineid is still mandatory get it made nullable, with its foreign key put back.
     *
     * @return void
     */
    public function test_upgrade_makes_outlineid_nullable(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $mandatory = new \xmldb_field('outlineid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        $nullable = new \xmldb_field('outlineid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        try {
            if ($dbman->find_key_name($this->table(), $this->outline_key())) {
                $dbman->drop_key($this->table(), $this->outline_key());
            }
            $dbman->change_field_notnull($this->table(), $mandatory);
            $dbman->add_key($this->table(), $this->outline_key());
            $this->assertTrue($DB->get_columns('local_studiolms_progress')['outlineid']->not_null);
            $this->assertTrue($this->outline_index_exists());
            $this->pretend_installed_version_is_oldest();

            $this->assertTrue(xmldb_local_studiolms_upgrade(2026061400));

            $this->assertFalse($DB->get_columns('local_studiolms_progress')['outlineid']->not_null);
            $this->assertTrue($this->outline_index_exists());
        } finally {
            if ($DB->get_columns('local_studiolms_progress')['outlineid']->not_null) {
                if ($dbman->find_key_name($this->table(), $this->outline_key())) {
                    $dbman->drop_key($this->table(), $this->outline_key());
                }
                $dbman->change_field_notnull($this->table(), $nullable);
            }
            if (!$dbman->find_key_name($this->table(), $this->outline_key())) {
                $dbman->add_key($this->table(), $this->outline_key());
            }
        }
    }

    /**
     * Running every step on a schema that is already current changes nothing and still succeeds.
     *
     * @return void
     */
    public function test_upgrade_is_harmless_on_a_current_schema(): void {
        global $DB;
        $columns = array_keys($DB->get_columns('local_studiolms_progress'));
        $this->pretend_installed_version_is_oldest();

        $this->assertTrue(xmldb_local_studiolms_upgrade(self::OLDEST));

        $this->assertSame($columns, array_keys($DB->get_columns('local_studiolms_progress')));
        $this->assertFalse($DB->get_columns('local_studiolms_progress')['outlineid']->not_null);
        $this->assertTrue($this->outline_index_exists());
    }
}
