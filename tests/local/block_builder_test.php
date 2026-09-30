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
 * Unit tests for the StudioLMS block renderer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(block_builder::class)]
final class block_builder_test extends \advanced_testcase {
    /**
     * Calls the private render_plain() for the given type and config.
     *
     * @param string $type Block registry id.
     * @param array $config Canonical block config.
     * @return string Rendered plain HTML.
     */
    private function render_plain(string $type, array $config): string {
        $method = new \ReflectionMethod(block_builder::class, 'render_plain');
        $method->setAccessible(true);
        return $method->invoke(null, $type, $config);
    }

    /**
     * The plain heading renders the requested heading level with the text.
     *
     * @return void
     */
    public function test_plain_heading(): void {
        $html = $this->render_plain('stylizedHeading', ['level' => 'h4', 'text' => 'Intro', 'icon' => '']);
        $this->assertStringContainsString('<h4>', $html);
        $this->assertStringContainsString('Intro', $html);
    }

    /**
     * The plain callout renders a Bootstrap alert and keeps the rich content.
     *
     * @return void
     */
    public function test_plain_callout(): void {
        $html = $this->render_plain('callout', ['contentHtml' => '<p>Note</p>', 'icon' => '📌']);
        $this->assertStringContainsString('alert', $html);
        $this->assertStringContainsString('<p>Note</p>', $html);
    }

    /**
     * The plain card renders an image, the body and a button when supplied.
     *
     * @return void
     */
    public function test_plain_card(): void {
        $html = $this->render_plain('advancedCard', [
            'content' => '<p>Body</p>',
            'mediaType' => 'image',
            'mediaUrl' => 'https://example.test/i.png',
            'btnText' => 'Go',
            'btnUrl' => 'https://example.test',
        ]);
        $this->assertStringContainsString('card-img-top', $html);
        $this->assertStringContainsString('card-body', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('Go', $html);
    }

    /**
     * The plain accordion renders a native details/summary, open when requested.
     *
     * @return void
     */
    public function test_plain_accordion_open(): void {
        $html = $this->render_plain('accordion', ['title' => 'Sec', 'content' => '<p>x</p>', 'state' => 'open']);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('open="open"', $html);
        $this->assertStringContainsString('<summary>', $html);
    }

    /**
     * The plain table renders the first row as a scoped header.
     *
     * @return void
     */
    public function test_plain_table_header(): void {
        $html = $this->render_plain('table', [
            'rows' => 2, 'cols' => 2, 'style' => 'striped',
            'cellData' => [['H1', 'H2'], ['a', 'b']],
        ]);
        $this->assertStringContainsString('<table class="table table-bordered table-striped"', $html);
        $this->assertStringContainsString('<th scope="col">H1</th>', $html);
        $this->assertStringContainsString('<td>a</td>', $html);
    }

    /**
     * The plain comparison renders accessible yes/no markers.
     *
     * @return void
     */
    public function test_plain_comparison_accessible_markers(): void {
        $html = $this->render_plain('infographicComparison', [
            'col1' => 'A', 'col2' => 'B',
            'items' => [['label' => 'price', 'col1' => 1, 'col2' => 0]],
        ]);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('aria-label="' . get_string('yes') . '"', $html);
        $this->assertStringContainsString('aria-label="' . get_string('no') . '"', $html);
    }

    /**
     * The plain mind map renders a nested list of branches and children.
     *
     * @return void
     */
    public function test_plain_mindmap_nested_list(): void {
        $html = $this->render_plain('mindmap', [
            'topic' => 'Root',
            'branches' => [['label' => 'B1', 'children' => ['c1', 'c2']]],
        ]);
        $this->assertStringContainsString('Root', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('c1', $html);
    }

    /**
     * An unsupported type renders to an empty string in plain mode.
     *
     * @return void
     */
    public function test_plain_unknown_type_empty(): void {
        $this->assertSame('', $this->render_plain('nope', []));
    }

    /**
     * With the editor present, render() wraps the block with the editor contract
     * attributes (data-slms-block-type / data-slms-state) for re-editing.
     *
     * @return void
     */
    public function test_rich_render_wraps_with_editor_attributes(): void {
        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }
        $html = block_builder::render('callout', ['contentHtml' => '<p>Note</p>', 'icon' => '📌']);
        $this->assertStringContainsString('data-slms-block-type="callout"', $html);
        $this->assertStringContainsString('data-slms-state="', $html);
    }

    /**
     * Skips the test when the editor's templates, which the rich rendering needs, are not installed.
     *
     * @return void
     */
    private function require_editor(): void {
        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }
    }

    /**
     * Decodes the data-slms-state chip of a rendered block back to its config.
     *
     * @param string $html Rendered block HTML.
     * @return array The stored config.
     */
    private function chip(string $html): array {
        $this->assertSame(1, preg_match('/data-slms-state="([^"]+)"/', $html, $matches));
        return json_decode(rawurldecode(base64_decode($matches[1])), true);
    }

    /**
     * Every infographic escapes teacher and AI text, so markup in a title, label or description is inert.
     *
     * Regression guard next to the stylizedHeading and callout blocks, which trust their caller instead.
     *
     * @return void
     */
    public function test_infographics_escape_user_text(): void {
        $this->require_editor();
        $evil = '<script>alert(1)</script>';
        $blocks = [
            'infographic' => ['title' => $evil, 'items' => [['value' => $evil, 'label' => $evil]]],
            'infographicFeatures' => ['title' => $evil, 'items' => [['title' => $evil, 'description' => $evil]]],
            'infographicSteps' => ['title' => $evil, 'items' => [['title' => $evil, 'description' => $evil]]],
            'infographicTimeline' => ['title' => $evil, 'items' => [['date' => $evil, 'title' => $evil, 'description' => $evil]]],
            'infographicComparison' => ['title' => $evil, 'col1' => $evil, 'col2' => $evil, 'items' => [['label' => $evil]]],
        ];

        foreach ($blocks as $type => $config) {
            $html = block_builder::render($type, $config);
            $this->assertStringNotContainsString('<script', $html, $type);
            $this->assertStringContainsString('&lt;script&gt;', $html, $type);
        }
    }

    /**
     * The mind map escapes labels both in the SVG and in the accessible text alternative.
     *
     * @return void
     */
    public function test_mindmap_escapes_labels_and_caps_branches(): void {
        $this->require_editor();
        $branches = [['label' => 'Bad <script>x</script>', 'children' => ['Kid <b>k</b>']]];
        for ($i = 2; $i <= 10; $i++) {
            $branches[] = ['label' => 'Branch ' . $i];
        }

        $html = block_builder::render('mindmap', ['topic' => 'Topic', 'branches' => $branches]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Branch 8', $html);
        $this->assertStringNotContainsString('Branch 9', $html);
    }

    /**
     * A mind map without branches renders no diagram.
     *
     * @return void
     */
    public function test_mindmap_without_branches_has_no_svg(): void {
        $this->require_editor();

        $html = block_builder::render('mindmap', ['topic' => 'Topic', 'branches' => []]);

        $this->assertStringNotContainsString('<svg', $html);
    }

    /**
     * The state chip omits the keys the editor rebuilds from the live DOM, and keeps the rest.
     *
     * @return void
     */
    public function test_state_chip_excludes_dom_owned_keys(): void {
        $this->require_editor();

        $callout = $this->chip(block_builder::render('callout', ['icon' => '📌', 'contentHtml' => '<p>Note</p>']));
        $accordion = $this->chip(block_builder::render('accordion', ['state' => 'open', 'title' => 'Q', 'content' => '<p>A</p>']));
        $grid = $this->chip(block_builder::render('gridcards', [
            'columns' => 2, 'containerTitle' => 'Grid', 'slots' => ['<p>one</p>'],
        ]));
        $table = $this->chip(block_builder::render('table', ['rows' => 2, 'cols' => 2, 'cellData' => [['H1', 'H2']]]));

        $this->assertSame(['icon' => '📌'], $callout);
        $this->assertSame(['state' => 'open'], $accordion);
        $this->assertSame(['columns' => 2], $grid);
        $this->assertSame(['rows' => 2, 'cols' => 2], $table);
    }

    /**
     * Blocks are marked non-editable so the editor treats them as units, except tables, which stay editable.
     *
     * @return void
     */
    public function test_wrap_marks_blocks_non_editable_except_tables(): void {
        $this->require_editor();

        $heading = block_builder::render('stylizedHeading', ['text' => 'Intro', 'level' => 'h3']);
        $table = block_builder::render('table', ['rows' => 2, 'cols' => 1, 'cellData' => [['H'], ['a']]]);

        $this->assertStringContainsString('mceNonEditable', $heading);
        $this->assertStringContainsString('data-slms-block-type="stylizedHeading"', $heading);
        $this->assertStringNotContainsString('mceNonEditable', $table);
        $this->assertStringContainsString('data-slms-block-type="table"', $table);
    }

    /**
     * The layout blocks render their content: card media and button, open accordion, grid slots and table cells.
     *
     * @return void
     */
    public function test_rich_layout_blocks_render_their_content(): void {
        $this->require_editor();

        $card = block_builder::render('advancedCard', [
            'content' => '<p>Body</p>', 'mediaType' => 'image', 'mediaUrl' => 'https://example.test/i.png',
            'btnText' => 'Go', 'btnUrl' => 'https://example.test',
        ]);
        $this->assertStringContainsString('https://example.test/i.png', $card);
        $this->assertStringContainsString('Body', $card);
        $this->assertStringContainsString('Go', $card);

        $open = block_builder::render('accordion', ['state' => 'open', 'title' => 'Q', 'content' => '<p>A</p>']);
        $closed = block_builder::render('accordion', ['state' => 'closed', 'title' => 'Q', 'content' => '<p>A</p>']);
        $this->assertStringNotContainsString('slms-closed', $open);
        $this->assertStringContainsString('slms-closed', $closed);

        $grid = block_builder::render('gridcards', [
            'containerTitle' => 'Grid', 'columns' => 2, 'slots' => ['<p>one</p>', '<p>two</p>'],
        ]);
        $this->assertStringContainsString('slms-cols-2', $grid);
        $this->assertSame(2, substr_count($grid, 'slms-grid-slot'));
        $this->assertStringContainsString('one', $grid);
        $this->assertStringContainsString('two', $grid);

        $table = block_builder::render('table', ['rows' => 3, 'cols' => 2, 'cellData' => [['H1', 'H2'], ['a', 'b']]]);
        $this->assertSame(2, substr_count($table, '<th scope="col"'));
        $this->assertStringContainsString('table-striped', $table);
        $this->assertStringContainsString('H1', $table);
        $this->assertStringContainsString('b', $table);
    }

    /**
     * A type the renderer does not know produces nothing.
     *
     * @return void
     */
    public function test_rich_unknown_type_renders_nothing(): void {
        $this->require_editor();

        $this->assertSame('', block_builder::render('doesNotExist', ['x' => 1]));
    }
}
