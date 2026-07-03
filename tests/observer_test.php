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
 * Tests for the course_deleted observer.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms;

/**
 * Unit tests for the local_studiolms course_deleted observer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
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
     * Deleting a course through the standard Moodle flow must remove every
     * StudioLMS row tied to it and leave other courses' rows untouched.
     *
     * @return void
     */
    public function test_course_deletion_removes_only_that_courses_rows(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();

        $this->seed_records((int) $user->id, (int) $course->id);
        $this->seed_records((int) $user->id, (int) $othercourse->id);

        delete_course($course->id, false);

        foreach (['local_studiolms_generation_log', 'local_studiolms_outline', 'local_studiolms_progress'] as $table) {
            $this->assertEquals(0, $DB->count_records($table, ['courseid' => $course->id]));
            $this->assertEquals(1, $DB->count_records($table, ['courseid' => $othercourse->id]));
        }
    }
}
