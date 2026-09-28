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
 * Tests for the generate_section web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;

/**
 * Unit tests for the section generation web service's permission checks.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_section::class)]
final class generate_section_test extends \advanced_testcase {
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
     * Regression test: wipe=true used to require only local/studiolms:generate, letting a
     * role with that capability but not moodle/course:manageactivities delete activities
     * (and any student submissions in them), unlike populate_course which already required
     * the core capability.
     */
    public function test_wipe_requires_manageactivities_capability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        // Prevent the core capability that governs deleting activities, while the plugin's
        // own generate capability (granted by the archetype) stays allowed.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:manageactivities', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $this->assertTrue(has_capability('local/studiolms:generate', $context));
        $this->assertFalse(has_capability('moodle/course:manageactivities', $context));

        $this->expectException(\required_capability_exception::class);
        generate_section::execute(
            $course->id,
            1,
            'Theme',
            json_encode([['type' => 'label', 'title' => 'New']]),
            'general',
            '',
            true
        );
    }

    /**
     * The same user, without wipe, can still queue ordinary generation.
     */
    public function test_generation_without_wipe_does_not_require_manageactivities(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('moodle/course:manageactivities', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $result = generate_section::execute(
            $course->id,
            1,
            'Theme',
            json_encode([['type' => 'label', 'title' => 'New']]),
            'general',
            '',
            false
        );

        $this->assertGreaterThan(0, $result['progressid']);
        $this->assertSame(
            'queued',
            $DB->get_field('local_studiolms_progress', 'status', ['id' => $result['progressid']])
        );
    }
}
