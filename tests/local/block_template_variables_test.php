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
 * Tests for the StudioLMS block renderer.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Checks that block_builder hands each tiny_studiolms template the variables the template reads.
 *
 * The blocks are rendered here in PHP, from the templates of the editor plugin, and the view model of each block is
 * written by hand in block_builder. A variable a template starts to read that the view model does not pass is an
 * empty value on every page built here (an icon missing, a colour left out), and nothing else reports it: the
 * template renders without a word. The test reads the templates and the view models and lists what is missing.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(block_builder::class)]
final class block_template_variables_test extends \advanced_testcase {
    /**
     * Variables a template reads that block_builder leaves out on purpose, by template, each with its reason.
     *
     * An absent variable is falsy, so these are options this renderer does not offer (a video in a card, a
     * placeholder for an image not filled in), not values it forgot.
     *
     * @return array Template name mapped to the variables left out.
     */
    private static function left_out_on_purpose(): array {
        return [
            // The card media is an image here, and only with an address: a video, which needs its address turned into
            // an embed, and the stand-in for an image with none belong to the editor, where the author types it.
            'tiny_studiolms/block_card' => ['isVideo', 'isImagePlaceholder'],
        ];
    }

    /**
     * The names a Mustache template reads from its context.
     *
     * Helpers ({{#str}} and the like) and what they wrap are not variables. A section that names a list is the
     * context of its items, so what is inside it is not read from the top level; a section that names a flag is
     * read from the same context, so what is inside it is.
     *
     * @param string $template The template source.
     * @param array $context The view model, to tell a list from a flag.
     * @return string[] The top-level names read.
     */
    private static function names_read(string $template, array $context): array {
        $template = preg_replace('/\{\{!.*?\}\}/s', '', $template);
        $template = preg_replace(
            '/\{\{#(str|cleanstr|pix|js|quote|userdate|shortentext)\}\}.*?\{\{\/\1\}\}/s',
            '',
            $template
        );

        $names = [];
        $skipping = [];
        preg_match_all('/\{\{\{?\s*([#^\/>&]?)\s*([^\s}]+)\s*\}?\}\}/', $template, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $kind, $name]) {
            $name = explode('.', $name)[0];
            if ($name === '' || $name === '.') {
                continue;
            }
            if ($kind === '/') {
                if (end($skipping) === $name) {
                    array_pop($skipping);
                }
                continue;
            }
            if ($kind === '>') {
                continue;
            }
            if (!empty($skipping)) {
                // Inside the items of a list: their own fields.
                if ($kind === '#' || $kind === '^') {
                    $skipping[] = $name;
                }
                continue;
            }
            $names[$name] = true;
            $islist = is_array($context[$name] ?? null) && array_is_list($context[$name]);
            if (($kind === '#' || $kind === '^') && $islist) {
                $skipping[] = $name;
            }
        }
        return array_keys($names);
    }

    /**
     * Every variable a block template reads is passed by block_builder, or left out on purpose.
     *
     * Regression test: a template that gained a variable (the open icon of the accordion, the media position of the
     * card) rendered it empty in every page built here until block_builder passed it.
     *
     * @return void
     */
    public function test_every_variable_a_template_reads_is_passed(): void {
        global $CFG;

        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }

        $templates = (new \ReflectionClassConstant(block_builder::class, 'TEMPLATES'))->getValue();
        $adapt = new \ReflectionMethod(block_builder::class, 'adapt');
        $adapt->setAccessible(true);
        $dir = \core_component::get_component_directory('tiny_studiolms') . '/templates/';
        $leftout = self::left_out_on_purpose();

        $missing = [];
        foreach ($templates as $type => $name) {
            $context = $adapt->invoke(null, $type, [])['context'];
            $source = file_get_contents($dir . substr($name, strlen('tiny_studiolms/')) . '.mustache');
            $unpassed = array_diff(self::names_read($source, $context), array_keys($context), $leftout[$name] ?? []);
            if (!empty($unpassed)) {
                $missing[$type] = array_values($unpassed);
            }
        }

        $this->assertSame([], $missing, 'Variables read by a template and not passed by block_builder, by block type.');
    }

    /**
     * The reader tells a list from a flag, and ignores helpers and comments.
     *
     * @return void
     */
    public function test_names_read_follows_the_context_of_sections(): void {
        $source = '{{! {{commented}} }}{{#str}} some_key, tiny_studiolms {{/str}}'
            . '{{#flag}}{{inside}}{{/flag}}{{#rows}}{{field}}{{#more}}{{deeper}}{{/more}}{{/rows}}'
            . '{{^other}}x{{/other}}{{{raw}}}{{dotted.value}}';

        $names = self::names_read($source, ['flag' => true, 'rows' => [['field' => 1]]]);

        $this->assertEqualsCanonicalizing(['flag', 'inside', 'rows', 'other', 'raw', 'dotted'], $names);
    }
}
