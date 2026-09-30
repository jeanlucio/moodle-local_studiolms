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
 * Tests for the StudioLMS privacy provider.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the StudioLMS privacy provider.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        writer::reset();
    }

    /**
     * Inserts one record per StudioLMS table for the given user and course.
     *
     * @param int $userid The user id.
     * @param int $courseid The course id.
     * @return void
     */
    private function seed_records(int $userid, int $courseid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_studiolms_generation_log', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'mode' => 'standard',
            'prompt' => 'Theme', 'status' => 'completed', 'timecreated' => $now,
        ]);
        $DB->insert_record('local_studiolms_outline', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'status' => 'completed',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_studiolms_progress', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'step' => 1, 'total' => 1,
            'status' => 'completed', 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * The metadata describes the three teacher-owned tables.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_studiolms'));
        $this->assertCount(3, $collection->get_collection());
    }

    /**
     * Every declared table lists exactly the columns it really has, each with an existing lang string.
     *
     * Regression test: columns added by upgrade steps (warnings, reportjson) were left out of the
     * declaration, so the privacy registry under-reported what the progress table stores. Comparing
     * key sets, rather than asserting individual keys, fails when any future column is added silently.
     *
     * @return void
     */
    public function test_metadata_declares_every_column_of_its_tables(): void {
        global $DB;

        $collection = provider::get_metadata(new collection('local_studiolms'));
        foreach ($collection->get_collection() as $table) {
            $columns = array_keys($DB->get_columns($table->get_name()));
            $columns = array_values(array_diff($columns, ['id']));
            $declared = array_keys($table->get_privacy_fields());

            $this->assertEqualsCanonicalizing($columns, $declared, $table->get_name());
            foreach ($table->get_privacy_fields() as $stringkey) {
                $this->assertTrue(get_string_manager()->string_exists($stringkey, 'local_studiolms'), $stringkey);
            }
        }
    }

    /**
     * The user's course context is discovered and the user is listed in it.
     *
     * @return void
     */
    public function test_contexts_and_users(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertEqualsCanonicalizing([$context->id], $contextlist->get_contextids());

        $userlist = new userlist($context, 'local_studiolms');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id], $userlist->get_userids());
    }

    /**
     * Exporting writes the user's data into the course context.
     *
     * @return void
     */
    public function test_export(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);

        $contextlist = new approved_contextlist($user, 'local_studiolms', [$context->id]);
        provider::export_user_data($contextlist);

        $this->assertTrue(writer::with_context($context)->has_any_data());
    }

    /**
     * Deleting for a single user removes only their records in the context.
     *
     * @return void
     */
    public function test_delete_for_user(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);
        $this->seed_records((int) $other->id, (int) $course->id);

        $contextlist = new approved_contextlist($user, 'local_studiolms', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('local_studiolms_outline', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_studiolms_outline', ['userid' => $other->id]));
    }

    /**
     * Deleting a user list removes the listed users' records in the context.
     *
     * @return void
     */
    public function test_delete_for_users(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);

        $approved = new approved_userlist($context, 'local_studiolms', [$user->id]);
        provider::delete_data_for_users($approved);

        $this->assertSame(0, $DB->count_records('local_studiolms_progress', ['userid' => $user->id]));
    }

    /**
     * Deleting for the whole context removes every user's records.
     *
     * @return void
     */
    public function test_delete_for_all_in_context(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('local_studiolms_generation_log', ['courseid' => $course->id]));
    }

    /**
     * Only course contexts hold this plugin's data: every other context is ignored by every entry point.
     *
     * @return void
     */
    public function test_non_course_contexts_are_ignored(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $course->id);
        $system = \context_system::instance();

        $userlist = new userlist($system, 'local_studiolms');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($user, 'local_studiolms', [$system->id]));
        $this->assertFalse(writer::with_context($system)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($user, 'local_studiolms', [$system->id]));
        provider::delete_data_for_users(new approved_userlist($system, 'local_studiolms', [$user->id]));
        provider::delete_data_for_all_users_in_context($system);

        foreach (['local_studiolms_generation_log', 'local_studiolms_outline', 'local_studiolms_progress'] as $table) {
            $this->assertSame(1, $DB->count_records($table, ['userid' => $user->id]), $table);
        }
    }

    /**
     * Deleting in one course leaves the same user's data in another course alone, and an empty user list is a no-op.
     *
     * @return void
     */
    public function test_deletion_is_scoped_to_the_course(): void {
        global $DB;
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $first->id);
        $this->seed_records((int) $user->id, (int) $second->id);
        $firstcontext = \context_course::instance($first->id);

        provider::delete_data_for_users(new approved_userlist($firstcontext, 'local_studiolms', []));
        $this->assertSame(2, $DB->count_records('local_studiolms_outline', ['userid' => $user->id]));

        provider::delete_data_for_user(new approved_contextlist($user, 'local_studiolms', [$firstcontext->id]));

        $this->assertSame(0, $DB->count_records('local_studiolms_outline', ['courseid' => $first->id]));
        $this->assertSame(1, $DB->count_records('local_studiolms_outline', ['courseid' => $second->id]));
    }

    /**
     * Exporting a user who has no data in a context writes nothing there.
     *
     * @return void
     */
    public function test_export_writes_nothing_for_a_context_without_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        provider::export_user_data(new approved_contextlist($user, 'local_studiolms', [$context->id]));

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * A context of another level whose instance id equals a course id must not be mistaken for that course.
     *
     * The tables are keyed by course id, so treating any context's instance id as a course id would let a
     * deletion or export aimed at, say, a user context touch the data of the course with the same number.
     *
     * @return void
     */
    public function test_instance_id_of_another_context_level_is_not_a_course_id(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $this->seed_records((int) $user->id, (int) $bystander->id);
        $foreign = \context_user::instance($bystander->id);

        $userlist = new userlist($foreign, 'local_studiolms');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($user, 'local_studiolms', [$foreign->id]));
        $this->assertFalse(writer::with_context($foreign)->has_any_data());

        provider::delete_data_for_user(new approved_contextlist($user, 'local_studiolms', [$foreign->id]));
        provider::delete_data_for_users(new approved_userlist($foreign, 'local_studiolms', [$user->id]));
        provider::delete_data_for_all_users_in_context($foreign);

        foreach (['local_studiolms_generation_log', 'local_studiolms_outline', 'local_studiolms_progress'] as $table) {
            $this->assertSame(1, $DB->count_records($table, ['courseid' => $bystander->id]), $table);
        }
    }
}
