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
}
