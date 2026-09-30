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
 * Tests for the get_progress web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

/**
 * Unit tests for the progress polling web service: payload, ownership and permission.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_progress::class)]
final class get_progress_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Inserts a progress record.
     *
     * @param int $userid Owner user id.
     * @param int $courseid Course id.
     * @param array $extra Column overrides.
     * @return int The progress id.
     */
    private function seed_progress(int $userid, int $courseid, array $extra = []): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_studiolms_progress', (object) ($extra + [
            'outlineid'    => null,
            'userid'       => $userid,
            'step'         => 2,
            'total'        => 5,
            'message'      => 'Working',
            'status'       => 'running',
            'courseid'     => $courseid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]));
    }

    /**
     * The owner reads their own progress, including decoded warnings and the raw report.
     */
    public function test_owner_reads_progress_payload(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $progressid = $this->seed_progress((int) $teacher->id, $course->id, [
            'warnings'   => json_encode(['Forum: Discuss']),
            'reportjson' => '[{"title":"Intro"}]',
            'errormsg'   => 'Boom',
        ]);
        $this->setUser($teacher);

        $result = get_progress::execute($progressid);

        $this->assertSame(2, $result['step']);
        $this->assertSame(5, $result['total']);
        $this->assertSame('Working', $result['message']);
        $this->assertSame('running', $result['status']);
        $this->assertSame('Boom', $result['errormsg']);
        $this->assertSame((int) $course->id, $result['courseid']);
        $this->assertSame(['Forum: Discuss'], $result['warnings']);
        $this->assertSame('[{"title":"Intro"}]', $result['report']);
    }

    /**
     * Columns that were never written come back as empty defaults rather than null.
     */
    public function test_missing_optional_columns_return_defaults(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $progressid = $this->seed_progress((int) $teacher->id, $course->id);
        $this->setUser($teacher);

        $result = get_progress::execute($progressid);

        $this->assertSame([], $result['warnings']);
        $this->assertSame('', $result['report']);
        $this->assertSame('', $result['errormsg']);
    }

    /**
     * Regression guard for the ownership scope: another teacher who also holds the generate capability
     * in the same course still cannot poll a run that is not theirs.
     */
    public function test_other_users_progress_is_not_readable(): void {
        $course = $this->getDataGenerator()->create_course();
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $progressid = $this->seed_progress((int) $owner->id, $course->id);
        $this->setUser($other);

        $this->assertTrue(has_capability('local/studiolms:generate', \context_course::instance($course->id)));
        $this->expectException(\dml_missing_record_exception::class);
        get_progress::execute($progressid);
    }

    /**
     * An owner who has lost the capability in the course can no longer poll.
     */
    public function test_owner_without_capability_is_refused(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $progressid = $this->seed_progress((int) $student->id, $course->id);
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_progress::execute($progressid);
    }
}
