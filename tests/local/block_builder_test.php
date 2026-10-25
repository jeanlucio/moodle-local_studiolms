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
     * The card carries the hover and sound classes of its design, for the card and for its button.
     *
     * Regression test: the context did not pass them, so the card template rendered empty class suffixes
     * and a preset with a hover effect or a click sound never got either from the server.
     *
     * @return void
     */
    public function test_rich_card_carries_hover_and_sound_classes(): void {
        $this->require_editor();

        $card = block_builder::render('advancedCard', [
            'btnText' => 'Go', 'hoverEffect' => 'lift', 'btnHoverEffect' => 'glow', 'btnClickSound' => 'pop',
        ]);
        $this->assertMatchesRegularExpression('/class="studiolms-card slms-hov-lift\b/', $card);
        $this->assertMatchesRegularExpression('/class="studiolms-btn slms-hov-glow slms-snd-pop"/', $card);

        $plain = block_builder::render('advancedCard', ['btnText' => 'Go']);
        $this->assertStringContainsString('slms-hov-none', $plain);
        $this->assertStringContainsString('slms-snd-none', $plain);
        $this->assertStringNotContainsString('slms-hov- ', $plain);
    }

    /**
     * The card shadow reaches the markup as a class, because the purifier strips an inline box-shadow.
     *
     * Regression test: the shadow was computed and never printed, so the Shadow setting changed nothing.
     *
     * @return void
     */
    public function test_rich_card_carries_shadow_class(): void {
        $this->require_editor();

        foreach (['sm', 'md', 'lg'] as $size) {
            $card = block_builder::render('advancedCard', ['shadow' => $size]);
            $this->assertStringContainsString("slms-shadow-{$size}", $card);
        }
        $this->assertStringNotContainsString('slms-shadow-', block_builder::render('advancedCard', ['shadow' => 'none']));
        $this->assertStringContainsString('slms-shadow-md', block_builder::render('advancedCard', ['shadow' => 'bogus']));
    }

    /**
     * The card says where its media goes and whether the side accent shows, by class.
     *
     * Regression test for the card layout options: a card saved before them has neither key and must come out
     * as it always did, and the value of the accent can arrive as a string from a stored configuration.
     *
     * @return void
     */
    public function test_rich_card_carries_media_position_and_accent_classes(): void {
        $this->require_editor();

        $old = block_builder::render('advancedCard', ['layout' => 'horizontal']);
        $this->assertStringContainsString('slms-horizontal', $old);
        $this->assertStringNotContainsString('slms-media-end', $old);
        $this->assertStringNotContainsString('slms-no-accent', $old);
        $this->assertStringContainsString('border-left-color', $old);

        $right = block_builder::render('advancedCard', ['layout' => 'horizontal_end']);
        $this->assertStringContainsString('slms-horizontal slms-media-end', $right);

        $bottom = block_builder::render('advancedCard', ['layout' => 'vertical_end']);
        $this->assertStringContainsString('slms-media-end', $bottom);
        $this->assertStringNotContainsString('slms-horizontal', $bottom);

        foreach ([false, 0, '0', 'false'] as $off) {
            $plain = block_builder::render('advancedCard', ['accent' => $off]);
            $this->assertStringContainsString('slms-no-accent', $plain);
            $this->assertStringNotContainsString('border-left-color', $plain);
        }
        $this->assertStringNotContainsString('slms-no-accent', block_builder::render('advancedCard', ['accent' => true]));
    }

    /**
     * The chevron is one icon that turns, the arrows pair keeps the look it had, and the content has a body.
     *
     * Regression test for the animation of the accordion: a pair of one icon twice is drawn once with the class the
     * stylesheet turns, the arrows pair (down, up) that was dropped from the options is drawn as the triangles it
     * looked like in the pages, and the content sits in a body whose height the stylesheet animates.
     *
     * @return void
     */
    public function test_rich_accordion_chevron_turns_and_the_dropped_arrows_pair_keeps_its_look(): void {
        $this->require_editor();

        $chevron = block_builder::render('accordion', ['icon' => "\u{276F} / \u{276F}"]);
        $this->assertStringContainsString('slms-acc-icon slms-acc-icon-turns', $chevron);
        $this->assertSame(1, substr_count($chevron, "\u{276F}"));
        $this->assertStringNotContainsString('slms-acc-icon-open', $chevron);
        $this->assertStringContainsString('studiolms-accordion-body', $chevron);

        $default = block_builder::render('accordion', []);
        $this->assertStringContainsString('slms-acc-icon-turns', $default);

        $arrows = block_builder::render('accordion', ['icon' => "\u{25BC} / \u{25B2}"]);
        $this->assertStringContainsString("slms-acc-icon-closed\">\u{25B6}\u{FE0E}<", $arrows);
        $this->assertStringContainsString("slms-acc-icon-open\">\u{25BC}<", $arrows);
        $this->assertStringNotContainsString("\u{25B2}", $arrows);
    }

    /**
     * The card writes its title colour on the headings of its text, over the one they have, and only a hex colour.
     *
     * Regression test for the title colour of the card: the title is only the first heading of the free text, so the
     * colour goes on the headings in a style attribute. A card without it, or with a colour that is not hexadecimal,
     * keeps its text as it was, since the colour is written into markup.
     *
     * @return void
     */
    public function test_rich_card_colours_the_headings_of_its_text(): void {
        $this->require_editor();

        $content = '<h4 style="margin-top: 0; color: inherit; font-weight: bold;">Title</h4><p>Text</p>';
        $html = block_builder::render('advancedCard', ['content' => $content, 'titleColor' => '#123456']);
        $this->assertStringContainsString(
            '<h4 style="margin-top: 0; font-weight: bold; color: #123456;">Title</h4>',
            $html
        );
        $this->assertStringContainsString('<p>Text</p>', $html);

        $plain = block_builder::render('advancedCard', ['content' => $content]);
        $this->assertStringContainsString($content, $plain);

        foreach (['red', '#12', '#fff"><script>', 'rgb(1,2,3)'] as $notcolour) {
            $this->assertStringContainsString(
                $content,
                block_builder::render('advancedCard', ['content' => $content, 'titleColor' => $notcolour])
            );
        }

        $bare = block_builder::render('advancedCard', ['content' => '<H2>One</H2>', 'titleColor' => '#abc']);
        $this->assertStringContainsString('<H2 style="color: #abc;">One</H2>', $bare);

        $background = '<h4 style="background-color: #eee; color: blue">T</h4>';
        $kept = block_builder::render('advancedCard', ['content' => $background, 'titleColor' => '#abc']);
        $this->assertStringContainsString('<h4 style="background-color: #eee; color: #abc;">T</h4>', $kept);

        $pattern = block_builder::render('advancedCard', [
            'content' => '<h4 style="content: \'$0\'">T</h4>', 'titleColor' => '#abc',
        ]);
        $this->assertStringContainsString("content: '\$0'; color: #abc;", $pattern);
    }

    /**
     * The accordion animates unless told not to, and carries the text colour of its content when it has one.
     *
     * Regression test for the animation option and the text colour of the content: a block saved before them has
     * neither key and must come out animated and with the page's own text colour, and the flag can arrive as text.
     *
     * @return void
     */
    public function test_rich_accordion_animates_by_default_and_carries_the_content_colour(): void {
        $this->require_editor();

        $plain = block_builder::render('accordion', []);
        $this->assertStringNotContainsString('slms-static', $plain);
        $this->assertDoesNotMatchRegularExpression('/studiolms-accordion-content"[^>]*style=/', $plain);

        foreach ([false, 0, '0', 'false'] as $off) {
            $static = block_builder::render('accordion', ['animate' => $off]);
            $this->assertStringContainsString('slms-static', $static);
        }
        $this->assertStringNotContainsString('slms-static', block_builder::render('accordion', ['animate' => true]));

        $coloured = block_builder::render('accordion', ['contentColor' => '#112233']);
        $this->assertMatchesRegularExpression('/studiolms-accordion-content"[^>]*style="color: #112233;"/', $coloured);
    }

    /**
     * The right-pointing triangle of the "Triangles" pair is asked to be text, like the down one.
     *
     * Regression test: it was drawn as a coloured emoji box while the accordion was closed and as a plain triangle
     * while open, since many fonts draw U+25B6 as an emoji unless told otherwise.
     *
     * @return void
     */
    public function test_rich_accordion_triangle_is_text_not_emoji(): void {
        $this->require_editor();

        $html = block_builder::render('accordion', ['icon' => "\u{25B6} / \u{25BC}"]);
        $this->assertStringContainsString("slms-acc-icon-closed\">\u{25B6}\u{FE0E}<", $html);
        $this->assertStringContainsString("slms-acc-icon-open\">\u{25BC}<", $html);

        $written = block_builder::render('accordion', ['icon' => "\u{25B6}\u{FE0F} / \u{25BC}"]);
        $this->assertStringContainsString("slms-acc-icon-closed\">\u{25B6}\u{FE0F}<", $written);
    }

    /**
     * A card whose media is a video draws no media box, since only an image is drawn here.
     *
     * Regression test: the media box was drawn for any type but none, and a video left it empty.
     *
     * @return void
     */
    public function test_rich_card_draws_no_empty_media_box_for_a_video(): void {
        $this->require_editor();

        $video = block_builder::render('advancedCard', ['mediaType' => 'video', 'mediaUrl' => 'https://youtu.be/abc']);
        $this->assertStringNotContainsString('studiolms-card-media', $video);

        $image = block_builder::render('advancedCard', ['mediaType' => 'image', 'mediaUrl' => 'https://example.com/a.png']);
        $this->assertStringContainsString('studiolms-card-media', $image);
        $this->assertStringContainsString('https://example.com/a.png', $image);
    }

    /**
     * The accordion carries both icons of its pair and the background of its title bar.
     *
     * Regression test: only the first icon was passed, turned a quarter by the stylesheet when closed, so a
     * folder lay on its side. An accordion saved before the title bar had a background of its own has none.
     *
     * @return void
     */
    public function test_rich_accordion_carries_both_icons_and_the_header_background(): void {
        $this->require_editor();

        $html = block_builder::render('accordion', ['icon' => '📁 / 📂', 'headerBg' => '#e3f2fd']);
        $this->assertStringContainsString('slms-acc-icon-closed">📁<', $html);
        $this->assertStringContainsString('slms-acc-icon-open">📂<', $html);
        $this->assertStringContainsString('background-color: #e3f2fd', $html);

        $plain = block_builder::render('accordion', []);
        $this->assertDoesNotMatchRegularExpression('/studiolms-accordion-header" style="[^"]*background-color/', $plain);
    }

    /**
     * Every card of a grid is rendered, even when there are more than the columns.
     *
     * Regression test: only as many slots as columns were drawn, and the AI puts all the cards of a list in
     * one block, so the rest were dropped for good.
     *
     * @return void
     */
    public function test_rich_grid_keeps_every_card(): void {
        $this->require_editor();

        $slots = ['<p>1</p>', '<p>2</p>', '<p>3</p>', '<p>4</p>', '<p>5</p>', '<p>6</p>'];
        $grid = block_builder::render('gridcards', ['columns' => '3', 'slots' => $slots]);
        $this->assertSame(6, substr_count($grid, 'slms-grid-slot mceEditable'));

        $empty = block_builder::render('gridcards', ['columns' => 2, 'slots' => ['<p>a</p>', '', '', '<p><br></p>']]);
        $this->assertSame(2, substr_count($empty, 'slms-grid-slot mceEditable'));

        $fresh = block_builder::render('gridcards', ['columns' => 3]);
        $this->assertSame(3, substr_count($fresh, 'slms-grid-slot mceEditable'));
    }

    /**
     * The grid spacing moves to the closest one that has a class, and the border thickness stays in range.
     *
     * Regression test: the value went into the class name as it came, and only some values have a rule.
     *
     * @return void
     */
    public function test_rich_grid_spacing_and_border_are_normalised(): void {
        $this->require_editor();

        foreach ([[10, 10], ['40', 40], [17, 16], [70, 60], ['wide', 20], [null, 20]] as [$given, $expected]) {
            $grid = block_builder::render('gridcards', ['gap' => $given]);
            $this->assertStringContainsString('slms-gap-' . $expected . '"', $grid, var_export($given, true));
        }

        $thick = block_builder::render('gridcards', ['borderWidth' => 99, 'borderColor' => '#ff0000']);
        $this->assertStringContainsString('border: 4px solid #ff0000', $thick);
        $none = block_builder::render('gridcards', ['borderWidth' => -3]);
        $this->assertStringContainsString('border: none', $none);
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
