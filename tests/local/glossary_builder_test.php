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
 * Tests for the glossary builder.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the glossary builder.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(glossary_builder::class)]
final class glossary_builder_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * The glossary is created and the AI terms are stored as readable entries.
     *
     * @return void
     */
    public function test_create_stores_terms(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'terms' => [
                ['term' => 'Variable', 'definition' => 'A named storage.'],
                ['term' => 'Loop', 'definition' => 'A repeated block.'],
            ],
        ]));

        $course = $this->getDataGenerator()->create_course();
        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $terms = glossary_builder::get_terms($result->instance);
        $this->assertCount(2, $terms);
        $this->assertSame('Variable', $terms[0]['term']);
        $this->assertStringContainsString('named storage', $terms[0]['definition']);
    }

    /**
     * Entries are created through the glossary API: one entry_created event each, approved, with the autolink on.
     *
     * @return void
     */
    public function test_create_goes_through_the_glossary_api(): void {
        global $DB;
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'terms' => [
                ['term' => 'Variable', 'definition' => 'A named storage.'],
                ['term' => 'Loop', 'definition' => 'A repeated block.'],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();
        $sink = $this->redirectEvents();

        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $events = array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \mod_glossary\event\entry_created
        );
        $this->assertCount(2, $events);
        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $result->instance]);
        foreach ($entries as $entry) {
            $this->assertEquals(1, $entry->approved);
            $this->assertEquals(1, $entry->usedynalink);
            $this->assertEquals(1, $entry->teacherentry);
        }
    }

    /**
     * When the AI yields no terms, the glossary is still created but empty.
     *
     * @return void
     */
    public function test_create_with_no_terms_is_empty(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => '{"terms": []}');

        $course = $this->getDataGenerator()->create_course();
        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $this->assertSame([], glossary_builder::get_terms($result->instance));
    }

    /**
     * Malformed terms are skipped and markup in the ones kept is stripped.
     *
     * @return void
     */
    public function test_create_skips_malformed_terms_and_strips_markup(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'terms' => [
                ['term' => '<b>Variable</b>', 'definition' => '<script>alert(1)</script>A named storage.'],
                ['term' => 'No definition'],
                ['definition' => 'No term'],
                'not an item',
                ['term' => '', 'definition' => 'Empty term'],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();

        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $terms = glossary_builder::get_terms($result->instance);
        $this->assertCount(1, $terms);
        $this->assertSame('Variable', $terms[0]['term']);
        $this->assertStringNotContainsString('<script', $terms[0]['definition']);
        $this->assertStringContainsString('A named storage.', $terms[0]['definition']);
    }

    /**
     * A failing AI call still leaves a usable, empty glossary instead of an error.
     *
     * @return void
     */
    public function test_create_survives_an_ai_failure(): void {
        ai_resolver::set_provider_for_testing(static function (string $s, string $u): string {
            throw new \moodle_exception('noaiprovider', 'local_studiolms');
        });
        $course = $this->getDataGenerator()->create_course();

        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $this->assertGreaterThan(0, $result->coursemodule);
        $this->assertSame([], glossary_builder::get_terms($result->instance));
    }

    /**
     * Terms are read in insertion order and definitions come back as plain text.
     *
     * @return void
     */
    public function test_get_terms_returns_plain_text_in_order(): void {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => '{"terms": []}');
        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');
        foreach (['Second' => '<p>Two <b>bold</b></p>', 'First' => '<p>One</p>'] as $concept => $definition) {
            $DB->insert_record('glossary_entries', (object) [
                'glossaryid' => $result->instance, 'userid' => $USER->id, 'concept' => $concept,
                'definition' => $definition, 'definitionformat' => FORMAT_HTML, 'timecreated' => time(),
                'timemodified' => time(), 'approved' => 1,
            ]);
        }

        $terms = glossary_builder::get_terms($result->instance);

        $this->assertSame(['Second', 'First'], array_column($terms, 'term'));
        $this->assertStringNotContainsString('<', $terms[0]['definition']);
        $this->assertStringContainsString('Two', $terms[0]['definition']);
    }

    /**
     * A term or a definition that is not text drops the entry instead of failing the glossary.
     */
    public function test_create_skips_terms_with_fields_of_the_wrong_type(): void {
        ai_resolver::set_provider_for_testing(static fn(string $s, string $u): string => json_encode([
            'terms' => [
                ['term' => ['x'], 'definition' => 'List as term'],
                ['term' => 'List as definition', 'definition' => ['x']],
                ['term' => ['a' => 'b'], 'definition' => ['c' => 'd']],
                'a string instead of an entry',
                ['term' => 'Kept', 'definition' => 'Only this one.'],
            ],
        ]));
        $course = $this->getDataGenerator()->create_course();

        $result = glossary_builder::create($course, 0, 'Glossary', 'Programming');

        $this->assertSame(['Kept'], array_column(glossary_builder::get_terms($result->instance), 'term'));
    }
}
