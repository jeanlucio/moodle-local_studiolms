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
}
