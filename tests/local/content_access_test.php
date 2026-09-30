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
 * Tests for the core capability checks applied to content creation.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for content_access and the generate capability's risk flags.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(content_access::class)]
final class content_access_test extends \advanced_testcase {
    /**
     * Unknown or malformed types map to page, which is what the generators create for them.
     */
    public function test_modules_for_maps_unknown_types_to_page(): void {
        $this->assertSame(
            ['forum', 'page', 'quiz'],
            content_access::modules_for(['forum', 'forum', 'bogus', '', 'quiz'])
        );
    }

    /**
     * New sections add moodle/course:update on top of manageactivities and one addinstance per module.
     */
    public function test_required_capabilities(): void {
        $this->assertSame(
            ['moodle/course:manageactivities', 'mod/label:addinstance'],
            content_access::required_capabilities(['label', 'label'], false)
        );
        $this->assertSame(
            ['moodle/course:manageactivities', 'moodle/course:update', 'mod/assign:addinstance'],
            content_access::required_capabilities(['assign'], true)
        );
    }

    /**
     * Only well-formed activity definitions contribute a type.
     */
    public function test_types_of_ignores_malformed_input(): void {
        $this->assertSame(['page', ''], content_access::types_of([['type' => 'page'], 'x', ['title' => 'no type']]));
        $this->assertSame([], content_access::types_of('not an array'));
        $this->assertSame([], content_access::types_of(null));
    }

    /**
     * Regression test: can_create() honours a denied core capability and a per-module addinstance denial.
     */
    public function test_can_create_follows_core_capabilities(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->assertTrue(content_access::can_create($context, ['page', 'forum'], true, (int) $teacher->id));

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/forum:addinstance', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse(content_access::can_create($context, ['page', 'forum'], true, (int) $teacher->id));
        $this->assertTrue(content_access::can_create($context, ['page'], true, (int) $teacher->id));
    }

    /**
     * The generate capability creates page content rendered without cleaning, so it must carry RISK_XSS.
     */
    public function test_generate_capability_declares_xss_risk(): void {
        $capability = get_capability_info('local/studiolms:generate');

        $this->assertNotEmpty($capability->riskbitmask & RISK_XSS);
        $this->assertNotEmpty($capability->riskbitmask & RISK_DATALOSS);
    }

    /**
     * require_can_create() lets a user with every core capability through and refuses one that lacks any.
     *
     * @return void
     */
    public function test_require_can_create_enforces_each_capability(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        content_access::require_can_create($context, ['page', 'quiz'], true);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        foreach (['mod/quiz:addinstance', 'moodle/course:update', 'moodle/course:manageactivities'] as $capability) {
            assign_capability($capability, CAP_PREVENT, $roleid, $context->id, true);
            accesslib_clear_all_caches_for_unit_testing();
            try {
                content_access::require_can_create($context, ['page', 'quiz'], true);
                $this->fail($capability . ' must be required.');
            } catch (\required_capability_exception $e) {
                $this->assertSame(get_capability_string($capability), $e->a);
            }
            unassign_capability($capability, $roleid, $context->id);
        }
    }
}
