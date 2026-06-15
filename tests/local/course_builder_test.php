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
 * Tests for the course builder.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the course builder.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_builder::class)]
final class course_builder_test extends \advanced_testcase {
    /** @var \stdClass The test course. */
    private $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecompletion', 1);
        // Start with completion off on the course to prove the builder enables it.
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
    }

    /**
     * create_section adds a named section to the course.
     *
     * @return void
     */
    public function test_create_section(): void {
        $section = course_builder::create_section($this->course, 'Unit one');
        $this->assertGreaterThan(0, $section->section);
        $this->assertEquals('Unit one', $section->name);
    }

    /**
     * A page is created with view-to-complete tracking, and the course gains
     * completion automatically.
     *
     * @return void
     */
    public function test_add_page_sets_view_completion_and_enables_course(): void {
        global $DB;
        $result = course_builder::add_page($this->course, 0, 'Lesson', '<p>Body</p>');

        $cm = $DB->get_record('course_modules', ['id' => $result->coursemodule], '*', MUST_EXIST);
        $this->assertEquals(COMPLETION_TRACKING_AUTOMATIC, $cm->completion);
        $this->assertEquals(1, $cm->completionview);

        $enabled = $DB->get_field('course', 'enablecompletion', ['id' => $this->course->id]);
        $this->assertEquals(1, $enabled);
    }

    /**
     * A label carries no completion tracking (it is a visual separator).
     *
     * @return void
     */
    public function test_add_label_has_no_completion(): void {
        global $DB;
        $result = course_builder::add_label($this->course, 0, '<h4>Sep</h4>');

        $cm = $DB->get_record('course_modules', ['id' => $result->coursemodule], '*', MUST_EXIST);
        $this->assertEquals(COMPLETION_TRACKING_NONE, $cm->completion);
    }

    /**
     * Each activity helper creates a module of the expected type.
     *
     * @return void
     */
    public function test_activity_helpers_create_expected_modules(): void {
        $forum = course_builder::add_forum($this->course, 0, 'Discuss', '<p>i</p>');
        $assign = course_builder::add_assign($this->course, 0, 'Task', '<p>i</p>');
        $quiz = course_builder::add_quiz($this->course, 0, 'Test', '<p>i</p>');
        $glossary = course_builder::add_glossary($this->course, 0, 'Terms', '<p>i</p>');

        $this->assertEquals('forum', $this->modname($forum->coursemodule));
        $this->assertEquals('assign', $this->modname($assign->coursemodule));
        $this->assertEquals('quiz', $this->modname($quiz->coursemodule));
        $this->assertEquals('glossary', $this->modname($glossary->coursemodule));
    }

    /**
     * Returns the module name for a course module id.
     *
     * @param int $cmid Course module id.
     * @return string The module name.
     */
    private function modname(int $cmid): string {
        global $DB;
        $sql = "SELECT m.name
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid";
        return (string) $DB->get_field_sql($sql, ['cmid' => $cmid], MUST_EXIST);
    }
}
