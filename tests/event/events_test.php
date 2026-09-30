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
 * Tests for the plugin's events.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\event;

/**
 * Unit tests for the generation events: name, description, URL and required data.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_generated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(generation_failed::class)]
final class events_test extends \advanced_testcase {
    /**
     * A finished generation event names the mode and links to the course.
     */
    public function test_course_generated(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();

        course_generated::create([
            'context' => \context_course::instance($course->id),
            'objectid' => 7,
            'userid' => $user->id,
            'courseid' => $course->id,
            'other' => ['mode' => 'gamified', 'profile' => 'social'],
        ])->trigger();

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(get_string('event_course_generated', 'local_studiolms'), course_generated::get_name());
        $this->assertStringContainsString("'$user->id'", $event->get_description());
        $this->assertStringContainsString("'$course->id'", $event->get_description());
        $this->assertStringContainsString('mode: gamified', $event->get_description());
        $this->assertSame('/course/view.php?id=' . $course->id, $event->get_url()->out_as_local_url(false));
        $this->assertSame('local_studiolms_generation_log', $event->objecttable);
        $this->assertSame(\core\event\base::LEVEL_TEACHING, $event->edulevel);
    }

    /**
     * Without a mode in the payload the description falls back to the standard mode.
     */
    public function test_course_generated_defaults_to_the_standard_mode(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $event = course_generated::create([
            'context' => \context_course::instance($course->id),
            'objectid' => 1,
            'courseid' => $course->id,
        ]);

        $this->assertStringContainsString('mode: standard', $event->get_description());
    }

    /**
     * A failure event carries the error and links to the course.
     */
    public function test_generation_failed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $sink = $this->redirectEvents();

        generation_failed::create([
            'context' => \context_course::instance($course->id),
            'courseid' => $course->id,
            'other' => ['error' => 'Something broke'],
        ])->trigger();

        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(get_string('event_generation_failed', 'local_studiolms'), generation_failed::get_name());
        $this->assertStringContainsString('Something broke', $event->get_description());
        $this->assertStringContainsString("'$course->id'", $event->get_description());
        $this->assertSame('/course/view.php?id=' . $course->id, $event->get_url()->out_as_local_url(false));
        $this->assertSame(\core\event\base::LEVEL_OTHER, $event->edulevel);
    }
}
