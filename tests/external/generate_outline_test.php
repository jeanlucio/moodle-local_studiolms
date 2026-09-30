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
 * Tests for the generate_outline web service.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\external;

use local_studiolms\local\ai_resolver;

/**
 * Unit tests for the outline web service: permission, persistence and the returned payload.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(generate_outline::class)]
final class generate_outline_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * A permitted teacher gets the outline back and it is stored as a reviewed draft they own.
     */
    public function test_outline_is_generated_and_persisted(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => json_encode([
            'objectives' => ['Understand loops'],
            'sections' => [
                ['title' => 'Basics', 'activities' => [
                    ['type' => 'page', 'title' => 'Intro'],
                    ['type' => 'quiz', 'title' => 'Check'],
                ]],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = generate_outline::execute($course->id, 'Programming');

        $this->assertSame(['Understand loops'], $result['objectives']);
        $activities = $result['sections'][0]['activities'];
        $this->assertSame(['glossary', 'page', 'quiz'], array_column($activities, 'type'));
        $this->assertSame(get_string('activity_page', 'local_studiolms'), $activities[1]['typelabel']);

        $record = $DB->get_record('local_studiolms_outline', ['id' => $result['outlineid']], '*', MUST_EXIST);
        $this->assertEquals($teacher->id, $record->userid);
        $this->assertEquals($course->id, $record->courseid);
        $this->assertSame('reviewed', $record->status);
        $this->assertSame('Programming', json_decode($record->briefingjson, true)['theme']);
        $this->assertSame('Basics', json_decode($record->outlinejson, true)['sections'][0]['title']);
    }

    /**
     * A user without the capability is refused before the AI is called or anything is stored.
     */
    public function test_requires_generate_capability(): void {
        global $DB;

        $calls = 0;
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$calls): string {
            $calls++;
            return '{}';
        });
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        try {
            generate_outline::execute($course->id, 'Programming');
            $this->fail('A student must not be able to generate an outline.');
        } catch (\required_capability_exception $e) {
            $this->assertSame(0, $calls);
            $this->assertSame(0, $DB->count_records('local_studiolms_outline'));
        }
    }

    /**
     * When the AI never returns a usable outline the service fails and stores nothing.
     */
    public function test_invalid_ai_response_fails_without_storing(): void {
        global $DB;

        ai_resolver::set_provider_for_testing(static fn(string $system, string $user): string => 'not json at all');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        try {
            generate_outline::execute($course->id, 'Programming');
            $this->fail('An unusable AI response must raise an error.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidairesponse', $e->errorcode);
            $this->assertSame(0, $DB->count_records('local_studiolms_outline'));
        }
    }
}
