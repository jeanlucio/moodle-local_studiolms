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

    /**
     * Restores the AI provider seam after each test.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        parent::tearDown();
    }

    /**
     * Calls the private decode_json() on a raw AI response.
     *
     * @param string $raw The raw response.
     * @return array|null The decoded array, or null.
     */
    private function decode(string $raw): ?array {
        $method = new \ReflectionMethod(outline_generator::class, 'decode_json');
        $method->setAccessible(true);
        return $method->invoke(null, $raw);
    }

    /**
     * Fences, surrounding prose and trailing commas, the usual LLM mistakes, are tolerated.
     *
     * @return void
     */
    public function test_decode_json_tolerates_common_llm_mistakes(): void {
        $fence = str_repeat(chr(96), 3);
        $expected = ['objectives' => ['A'], 'sections' => []];

        $this->assertSame($expected, $this->decode($fence . "json\n{\"objectives\": [\"A\"], \"sections\": [],}\n" . $fence));
        $this->assertSame($expected, $this->decode('Here you go: {"objectives": ["A"], "sections": []} Hope it helps!'));
        $this->assertSame($expected, $this->decode('{"objectives": ["A",], "sections": [],}'));
        $this->assertNull($this->decode('no json here'));
        $this->assertNull($this->decode('{broken'));
        $this->assertNull($this->decode('} before {'));
    }

    /**
     * A valid response is normalised and returned on the first attempt, with the briefing in the prompts.
     *
     * @return void
     */
    public function test_generate_builds_prompts_from_the_briefing(): void {
        $prompts = [];
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$prompts): string {
            $prompts[] = [$system, $user];
            return json_encode(['objectives' => ['Learn'], 'sections' => [
                ['title' => 'S1', 'activities' => [['type' => 'page', 'title' => 'P']]],
            ]]);
        });

        $outline = outline_generator::generate([
            'theme' => 'Botany', 'reference' => 'Chapter three', 'bloom' => 'apply', 'structure' => 'abc',
        ]);

        $this->assertCount(1, $prompts);
        [$system, $user] = $prompts[0];
        $this->assertStringContainsString("Bloom's taxonomy) for this course is: apply", $system);
        $this->assertStringContainsString('ABC Learning Design', $system);
        $this->assertStringContainsString('Backward Design', $system);
        $this->assertStringContainsString('Course theme or focus: Botany', $user);
        $this->assertStringContainsString('Chapter three', $user);
        $this->assertStringNotContainsString('Return only valid JSON', $user);
        $this->assertSame('glossary', $outline['sections'][0]['activities'][0]['type']);
    }

    /**
     * Without a level or a structure preset the prompt does not mention them.
     *
     * @return void
     */
    public function test_generate_omits_optional_prompt_parts(): void {
        $system = '';
        ai_resolver::set_provider_for_testing(static function (string $s, string $u) use (&$system): string {
            $system = $s;
            return json_encode(['sections' => [['title' => 'S1', 'activities' => [['type' => 'page', 'title' => 'P']]]]]);
        });

        outline_generator::generate(['theme' => 'Botany', 'bloom' => 'general', 'structure' => 'free']);

        $this->assertStringNotContainsString('Bloom', $system);
        $this->assertStringNotContainsString('ABC Learning Design', $system);
    }

    /**
     * A first response that is not usable is retried with a stricter reminder, and the retry's outline is used.
     *
     * @return void
     */
    public function test_generate_retries_with_a_stricter_prompt(): void {
        $users = [];
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$users): string {
            $users[] = $user;
            if (count($users) === 1) {
                return 'sorry, I cannot';
            }
            return json_encode(['sections' => [['title' => 'S1', 'activities' => [['type' => 'page', 'title' => 'P']]]]]);
        });

        $outline = outline_generator::generate(['theme' => 'Botany']);

        $this->assertCount(2, $users);
        $this->assertStringNotContainsString('Return only valid JSON', $users[0]);
        $this->assertStringContainsString('Return only valid JSON', $users[1]);
        $this->assertSame('S1', $outline['sections'][0]['title']);
    }

    /**
     * When every attempt is unusable the generator gives up with a clear error after three tries.
     *
     * @return void
     */
    public function test_generate_gives_up_after_three_attempts(): void {
        $calls = 0;
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$calls): string {
            $calls++;
            return '{"objectives": [], "sections": []}';
        });

        try {
            outline_generator::generate(['theme' => 'Botany']);
            $this->fail('An outline without sections must not be accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidairesponse', $e->errorcode);
            $this->assertSame(3, $calls);
        }
    }
}
