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
 * Tests for the preset catalog loader.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for reading, finding and rendering presets, against a fixture catalog.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(preset_loader::class)]
final class preset_loader_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    #[\Override]
    protected function tearDown(): void {
        preset_loader::set_directory_for_testing(null);
        parent::tearDown();
    }

    /**
     * Builds a fixture catalog and points the loader at it.
     *
     * @param array $files Map of "lang/file.json" to raw file content.
     * @return void
     */
    private function catalog(array $files): void {
        $base = make_request_directory();
        foreach ($files as $path => $content) {
            $dir = $base . '/' . dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($base . '/' . $path, $content);
        }
        preset_loader::set_directory_for_testing($base);
    }

    /**
     * Encodes a one-block preset.
     *
     * @param string $name Preset name.
     * @param string $text Heading text of its only block.
     * @return string JSON.
     */
    private function preset(string $name, string $text = 'Hello'): string {
        return json_encode([
            'name' => $name,
            'description' => 'About ' . $name,
            'blocks' => [['type' => 'stylizedHeading', 'config' => ['text' => $text, 'level' => 'h3']]],
        ]);
    }

    /**
     * Presets are read in file-name order; files that are not valid presets are skipped.
     */
    public function test_get_all_reads_valid_presets_in_file_order(): void {
        $this->catalog([
            'en/b_second.json' => $this->preset('Second'),
            'en/a_first.json' => $this->preset('First'),
            'en/broken.json' => '{not json',
            'en/noname.json' => json_encode(['blocks' => [['type' => 'callout', 'config' => []]]]),
            'en/noblocks.json' => json_encode(['name' => 'Empty', 'blocks' => []]),
            'en/notes.txt' => 'ignored',
        ]);

        $names = array_column(preset_loader::get_all('en'), 'name');

        $this->assertSame(['First', 'Second'], $names);
    }

    /**
     * A language without its own directory falls back to English; one with its own directory uses it.
     */
    public function test_language_directory_with_english_fallback(): void {
        $this->catalog([
            'en/one.json' => $this->preset('English'),
            'pt_br/one.json' => $this->preset('Português'),
        ]);

        $this->assertSame(['Português'], array_column(preset_loader::get_all('pt_br'), 'name'));
        $this->assertSame(['English'], array_column(preset_loader::get_all('de'), 'name'));
    }

    /**
     * Without any usable directory the catalog is empty rather than an error.
     */
    public function test_missing_catalog_is_empty(): void {
        $this->catalog(['pt_br/one.json' => $this->preset('Só português')]);

        $this->assertSame([], preset_loader::get_all('de'));
        $this->assertNull(preset_loader::find('Só português', 'de'));
    }

    /**
     * Names match regardless of case and surrounding spaces; an unknown name finds nothing.
     */
    public function test_find_is_case_insensitive(): void {
        $this->catalog(['en/one.json' => $this->preset('Study Tip')]);

        $this->assertSame('Study Tip', preset_loader::find('  study TIP ', 'en')['name']);
        $this->assertNull(preset_loader::find('Other', 'en'));
    }

    /**
     * The prompt catalog carries only name and description, with an empty default description.
     */
    public function test_catalog_for_prompt_lists_name_and_description(): void {
        $this->catalog([
            'en/a.json' => $this->preset('With description'),
            'en/b.json' => json_encode(['name' => 'Bare', 'blocks' => [['type' => 'callout', 'config' => []]]]),
        ]);

        $this->assertSame([
            ['name' => 'With description', 'description' => 'About With description'],
            ['name' => 'Bare', 'description' => ''],
        ], preset_loader::catalog_for_prompt('en'));
    }

    /**
     * A preset with a block the builder cannot draw is not offered to the AI: the block would be dropped silently.
     */
    public function test_catalog_for_prompt_leaves_out_presets_the_builder_cannot_draw(): void {
        $this->catalog([
            'en/a.json' => $this->preset('Drawable'),
            'en/b.json' => json_encode(['name' => 'With tabs', 'blocks' => [
                ['type' => 'stylizedHeading', 'config' => ['text' => 'Lesson', 'level' => 'h2']],
                ['type' => 'tabs', 'config' => ['tabs' => []]],
            ]]),
        ]);

        $this->assertSame(['Drawable'], array_column(preset_loader::catalog_for_prompt('en'), 'name'));
        $this->assertTrue(preset_loader::is_renderable(preset_loader::find('Drawable', 'en')));
        $this->assertFalse(preset_loader::is_renderable(preset_loader::find('With tabs', 'en')));
    }

    /**
     * Rendering concatenates the blocks and skips entries without a type or with a malformed config.
     */
    public function test_render_skips_malformed_blocks(): void {
        $html = preset_loader::render(['blocks' => [
            ['type' => 'stylizedHeading', 'config' => ['text' => 'First', 'level' => 'h3']],
            ['type' => '', 'config' => ['text' => 'No type']],
            ['type' => 'stylizedHeading', 'config' => 'not an array'],
            ['type' => 'stylizedHeading', 'config' => ['text' => 'Last', 'level' => 'h3']],
        ]]);

        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Last', $html);
        $this->assertStringNotContainsString('No type', $html);
        $this->assertSame('', preset_loader::render([]));
    }

    /**
     * A preset is found by its file whatever its display name, using the English catalog for a language without one.
     */
    public function test_find_by_file_ignores_the_display_name_and_falls_back_to_english(): void {
        $this->catalog([
            'en/course_syllabus.json' => $this->preset('Course Syllabus'),
            'pt_br/plano_de_disciplina.json' => $this->preset('Plano de Disciplina'),
            'en/broken.json' => '{"name": "No blocks"}',
        ]);

        $this->assertSame('Plano de Disciplina', preset_loader::find_by_file('plano_de_disciplina', 'pt_br')['name']);
        $this->assertSame('Course Syllabus', preset_loader::find_by_file('course_syllabus', 'en')['name']);
        $this->assertSame('Course Syllabus', preset_loader::find_by_file('course_syllabus', 'fr')['name']);
        $this->assertNull(preset_loader::find_by_file('plano_de_disciplina', 'en'));
        $this->assertNull(preset_loader::find_by_file('broken', 'en'));
        $this->assertNull(preset_loader::find_by_file('../en/course_syllabus', 'en'));
    }

    /**
     * Without a language, the presets of the language of the user are read.
     *
     * @return void
     */
    public function test_the_current_language_is_used_when_none_is_given(): void {
        $this->catalog([
            'en/one.json' => json_encode(['name' => 'One', 'blocks' => [['type' => 'callout', 'config' => []]]]),
        ]);

        $names = array_column(preset_loader::get_all(), 'name');

        $this->assertContains('One', $names);
    }
}
