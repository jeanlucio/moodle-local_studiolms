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
 * Tests for the outline normaliser.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the outline normaliser.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(outline_generator::class)]
final class outline_generator_test extends \advanced_testcase {
    /**
     * Calls the private normalise() on the given raw outline.
     *
     * @param array $decoded Raw decoded outline.
     * @return array Normalised outline.
     */
    private function normalise(array $decoded): array {
        $method = new \ReflectionMethod(outline_generator::class, 'normalise');
        $method->setAccessible(true);
        return $method->invoke(null, $decoded);
    }

    /**
     * Unknown activity types are coerced to 'page' and untitled items dropped.
     *
     * @return void
     */
    public function test_normalise_coerces_types_and_drops_untitled(): void {
        $result = $this->normalise([
            'objectives' => ['Learn X', '', 42, '   '],
            'sections' => [
                ['title' => 'S1', 'activities' => [
                    ['type' => 'video', 'title' => 'V'],
                    ['type' => 'page', 'title' => ''],
                ]],
                ['title' => '', 'activities' => []],
            ],
        ]);

        $this->assertSame(['Learn X'], $result['objectives']);
        $this->assertCount(1, $result['sections']);
        $this->assertSame('S1', $result['sections'][0]['title']);

        $types = array_column($result['sections'][0]['activities'], 'type');
        $this->assertContains('page', $types);
        $this->assertNotContains('video', $types);
    }

    /**
     * Exactly one glossary survives and it is the first activity of the first section.
     *
     * @return void
     */
    public function test_normalise_enforces_single_glossary_first(): void {
        $result = $this->normalise([
            'objectives' => [],
            'sections' => [
                ['title' => 'S1', 'activities' => [
                    ['type' => 'page', 'title' => 'P'],
                    ['type' => 'glossary', 'title' => 'G1'],
                ]],
                ['title' => 'S2', 'activities' => [
                    ['type' => 'glossary', 'title' => 'G2'],
                    ['type' => 'quiz', 'title' => 'Q'],
                ]],
            ],
        ]);

        $glossaries = 0;
        foreach ($result['sections'] as $section) {
            foreach ($section['activities'] as $activity) {
                if ($activity['type'] === 'glossary') {
                    $glossaries++;
                }
            }
        }
        $this->assertSame(1, $glossaries);
        $this->assertSame('glossary', $result['sections'][0]['activities'][0]['type']);
        $this->assertSame('G1', $result['sections'][0]['activities'][0]['title']);
    }

    /**
     * When no glossary is supplied, a default-titled one is prepended.
     *
     * @return void
     */
    public function test_normalise_adds_default_glossary_when_absent(): void {
        $result = $this->normalise([
            'sections' => [
                ['title' => 'S1', 'activities' => [['type' => 'page', 'title' => 'P']]],
            ],
        ]);

        $first = $result['sections'][0]['activities'][0];
        $this->assertSame('glossary', $first['type']);
        $this->assertSame(get_string('glossary_default_title', 'local_studiolms'), $first['title']);
    }
}
