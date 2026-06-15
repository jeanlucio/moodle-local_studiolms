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
}
