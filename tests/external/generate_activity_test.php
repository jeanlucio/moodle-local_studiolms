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
 * Tests for the generate_activity web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;

/**
 * Unit tests for the single-activity web service's core capability checks.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_activity::class)]
final class generate_activity_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(
            static fn(string $system, string $user): string => '{"content": "<p>x</p>"}'
        );
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Creates a course with an editing teacher whose role is denied one capability.
     *
     * @param string $capability The capability to prevent for the editing teacher role.
     * @return array [course, teacher].
     */
    private function teacher_denied(string $capability): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability($capability, CAP_PREVENT, $roleid, \context_course::instance($course->id)->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        return [$course, $teacher];
    }

    /**
     * Regression test: local/studiolms:generate used to create any activity type regardless of the
     * core mod/<type>:addinstance capability, so a role denied forums could still create them.
     */
    public function test_activity_requires_addinstance_for_its_type(): void {
        global $DB;

        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        try {
            generate_activity::execute($course->id, 1, 'forum', 'Discuss', 'Theme', 'general', '');
            $this->fail('Creating a forum without mod/forum:addinstance must be refused.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(0, $DB->count_records('forum', ['course' => $course->id]));
        }
    }

    /**
     * Activity creation is gated by moodle/course:manageactivities.
     */
    public function test_activity_requires_manageactivities(): void {
        [$course, $teacher] = $this->teacher_denied('moodle/course:manageactivities');
        $this->setUser($teacher);

        $this->expectException(\required_capability_exception::class);
        generate_activity::execute($course->id, 1, 'label', 'Intro', 'Theme', 'general', '');
    }

    /**
     * A type the user may add is still created.
     */
    public function test_activity_is_created_when_core_capabilities_are_held(): void {
        [$course, $teacher] = $this->teacher_denied('mod/forum:addinstance');
        $this->setUser($teacher);

        $result = generate_activity::execute($course->id, 1, 'label', 'Intro', 'Theme', 'general', '');

        $this->assertGreaterThan(0, $result['cmid']);
    }
}
