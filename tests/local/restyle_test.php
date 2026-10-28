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

namespace local_studiolms\local;

use local_studiolms\local\restyle\assembler;
use local_studiolms\local\restyle\planner;
use local_studiolms\local\restyle\segmenter;

/**
 * Tests for the pieces that restyle the HTML of an activity: the segmenter, the planner and the assembler.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\restyle\segmenter
 * @covers \local_studiolms\local\restyle\planner
 * @covers \local_studiolms\local\restyle\assembler
 */
final class restyle_test extends \advanced_testcase {
    /** @var string A page with a heading, two pieces of text, a picture and a second heading. */
    private const PAGE = '<h2>The cell</h2><p>Cells are the unit of life.</p><p>Remember: they have a membrane.</p>'
        . '<p><img src="https://example.com/cell.png" alt="A cell"></p><h3>Organelles</h3><p>The nucleus keeps the DNA.</p>';

    /** @var string A page pasted from the web: no headings, the titles are lines of a paragraph. */
    private const PASTED = '<p>Intro title<br>First text.</p><p>Topic<br>Body one.<br>Body two.</p>'
        . '<p>Item A<br>Item B<br>Item C</p>';

    /** @var string Three items, each a title line and a text line. */
    private const ITEMS = '<p>A<br>a text<br>B<br>b text<br>C<br>c text</p>';

    /**
     * Splits the page by its top-level nodes and tells each kind.
     *
     * @return void
     */
    public function test_split_tells_headings_text_and_fixed_pieces(): void {
        $segments = segmenter::split(self::PAGE);

        $this->assertSame(
            ['heading', 'text', 'text', 'fixed', 'heading', 'text'],
            array_column($segments, 'kind')
        );
        $this->assertSame([0, 1, 2, 3, 4, 5], array_column($segments, 'id'));
        $this->assertSame([2, 0, 0, 0, 3, 0], array_column($segments, 'level'));
        $this->assertSame([false, false, false, false, false, false], array_column($segments, 'line'));
        $this->assertSame('The cell', $segments[0]['text']);
        $this->assertStringContainsString('<img src="https://example.com/cell.png"', $segments[3]['html']);
    }

    /**
     * A paragraph with line breaks is cut into one piece per line, and the lines of a paragraph share a group.
     *
     * @return void
     */
    public function test_split_cuts_a_paragraph_at_its_line_breaks(): void {
        $segments = segmenter::split(self::PASTED);

        $this->assertCount(8, $segments);
        $this->assertSame([0, 0, 1, 1, 1, 2, 2, 2], array_column($segments, 'group'));
        $this->assertSame(array_fill(0, 8, true), array_column($segments, 'line'));
        $this->assertSame('Topic', $segments[2]['text']);
        $this->assertSame('<p>Intro title</p>', $segments[0]['html']);
        $this->assertSame('Body two.', $segments[4]['inner']);
    }

    /**
     * The markup inside a line is kept, a paragraph with a single line is not cut, and empty lines are dropped.
     *
     * @return void
     */
    public function test_split_keeps_the_markup_of_a_line_and_drops_empty_lines(): void {
        $segments = segmenter::split('<p><strong>Term</strong>: text<br><br>&nbsp;<br>Other</p><p>Only line<br></p>');

        $this->assertSame(['text', 'text', 'text'], array_column($segments, 'kind'));
        $this->assertSame('<strong>Term</strong>: text', $segments[0]['inner']);
        $this->assertSame([true, true, false], array_column($segments, 'line'));
        $this->assertSame('<p>Only line<br></p>', $segments[2]['html'], 'one line is left as it was');
    }

    /**
     * Non-ASCII text survives the split without being turned into entities.
     *
     * @return void
     */
    public function test_split_keeps_accents(): void {
        $segments = segmenter::split('<p>Atenção: avaliação é essencial.</p>');

        $this->assertSame('Atenção: avaliação é essencial.', $segments[0]['text']);
        $this->assertStringContainsString('Atenção', $segments[0]['html']);
    }

    /**
     * Blocks of the Studio and media are fixed, and empty nodes and bare text are handled.
     *
     * @return void
     */
    public function test_split_fixes_studio_blocks_and_media_and_drops_empty_nodes(): void {
        $html = '<div class="slms-block mceNonEditable" data-slms-block-type="callout"><p>Old</p></div>'
            . '<p>&nbsp;</p><iframe src="https://example.com/v"></iframe><table><tr><td>1</td></tr></table>'
            . 'Loose text<!-- a comment --><p><a href="https://example.com">A link</a> in text.</p>';
        $segments = segmenter::split($html);

        $this->assertSame(['fixed', 'fixed', 'fixed', 'text', 'text'], array_column($segments, 'kind'));
        $this->assertSame('<p>Loose text</p>', $segments[3]['html']);
        $this->assertStringContainsString('<a href="https://example.com">A link</a>', $segments[4]['html']);
        $this->assertSame([], segmenter::split('   '));
    }

    /**
     * Titles and lists are kept when their lines fit, and dropped when they do not.
     *
     * @return void
     */
    public function test_validate_keeps_titles_lists_and_boxes_that_fit(): void {
        $segments = segmenter::split(self::PASTED);
        $blocks = [
            ['type' => 'heading', 'line' => 0, 'level' => 2],
            ['type' => 'heading', 'line' => 1, 'level' => 7],
            ['type' => 'list', 'lines' => [5, 6, 7]],
            ['type' => 'list', 'lines' => [5, 7]],
            ['type' => 'card', 'lines' => [3, 4]],
            ['type' => 'accordion', 'heading' => 2],
            ['type' => 'callout', 'lines' => [99]],
            ['type' => 'poster', 'lines' => [1]],
            ['type' => 'callout', 'lines' => ['x']],
            ['type' => 'callout', 'lines' => [1.5]],
            ['type' => 'callout', 'lines' => ['6']],
            'not an entry',
        ];

        $plan = planner::validate($blocks, $segments, 10);

        $this->assertSame([0, 1, 2, 3, 5], array_keys($plan));
        $this->assertSame(2, $plan[0]['level']);
        $this->assertSame(3, $plan[1]['level'], 'a level that is not 2 is a subtopic');
        $this->assertSame('accordion', $plan[2]['type']);
        $this->assertSame([3, 4], $plan[3]['ids']);
        $this->assertSame([5, 6, 7], $plan[5]['ids'], 'the first block that claims a line keeps it');
        $this->assertSame([], planner::validate('nothing', $segments, 5));
    }

    /**
     * A title cannot lead into what follows (a colon) or be a long sentence, and a real heading is not named again.
     *
     * @return void
     */
    public function test_validate_rejects_lines_that_are_not_titles(): void {
        $segments = segmenter::split('<h2>Real</h2><p>Objectives include:<br>' . str_repeat('word ', 40) . '<br>Fine title</p>');
        $blocks = [
            ['type' => 'heading', 'line' => 0],
            ['type' => 'heading', 'line' => 1],
            ['type' => 'heading', 'line' => 2],
            ['type' => 'accordion', 'heading' => 1],
            ['type' => 'heading', 'line' => 3],
        ];

        $plan = planner::validate($blocks, $segments, 5);

        $this->assertSame([3], array_keys($plan), '"Fine title" is the only line that is a valid title');
        $this->assertSame(
            [0],
            array_keys(planner::validate([['type' => 'accordion', 'heading' => 0]], $segments, 5)),
            'a real heading may head an accordion'
        );
    }

    /**
     * A grid and a sequence of steps need two or more items whose lines are consecutive.
     *
     * @return void
     */
    public function test_validate_needs_consecutive_items(): void {
        $segments = segmenter::split(self::ITEMS);
        $good = [
            ['title' => 0, 'text' => [1]],
            ['title' => 2, 'text' => [3]],
            ['title' => 4, 'text' => [5]],
        ];

        $plan = planner::validate([['type' => 'grid', 'items' => $good]], $segments, 5);
        $this->assertSame([0, 1, 2, 3, 4, 5], $plan[0]['ids']);
        $this->assertSame($good, $plan[0]['items']);

        $gap = [['title' => 0, 'text' => [1]], ['title' => 3, 'text' => [4]]];
        $this->assertSame([], planner::validate([['type' => 'steps', 'items' => $gap]], $segments, 5));
        $this->assertSame([], planner::validate([['type' => 'grid', 'items' => [$good[0]]]], $segments, 5), 'one item');
        $this->assertSame([], planner::validate([['type' => 'grid', 'items' => [['text' => [1]], $good[1]]]], $segments, 5));
        $this->assertSame([], planner::validate([['type' => 'grid', 'items' => 'x']], $segments, 5));
    }

    /**
     * The visual blocks are limited, in the order of the page, and headings and lists do not count.
     *
     * @return void
     */
    public function test_validate_limits_the_visual_blocks(): void {
        $segments = segmenter::split('<p>One<br>Two<br>Three<br>Four<br>Five<br>Six</p>');
        $blocks = [
            ['type' => 'callout', 'lines' => [4]],
            ['type' => 'callout', 'lines' => [0]],
            ['type' => 'list', 'lines' => [1, 2]],
            ['type' => 'heading', 'line' => 3],
            ['type' => 'callout', 'lines' => [5]],
        ];

        $this->assertSame([0, 1, 3, 4], array_keys(planner::validate($blocks, $segments, 2)));
    }

    /**
     * The AI is shown all the text, with the headings told, and its answer becomes the plan.
     *
     * @return void
     */
    public function test_plan_reads_the_answer_of_the_ai(): void {
        $this->resetAfterTest();
        $seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen = ['system' => $system, 'user' => $user];
            return 'Here is the plan: {"blocks":[{"type":"callout","lines":[2]},{"type":"accordion","heading":4}]} Done.';
        });

        $plan = planner::plan(segmenter::split(self::PAGE), 5);

        $this->assertSame([2, 4], array_keys($plan));
        $this->assertSame('callout', $plan[2]['type']);
        $this->assertStringContainsString('never write', $seen['system']);
        $this->assertStringContainsString('ends with a colon', $seen['system']);
        $this->assertStringContainsString('"text":"Remember: they have a membrane."', $seen['user']);
        $this->assertStringContainsString('"heading":true', $seen['user']);
        $this->assertStringNotContainsString('cell.png', $seen['user'], 'fixed pieces are not sent');
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Without a usable answer there is no plan (null), and a page with nothing to plan needs no AI.
     *
     * @return void
     */
    public function test_plan_is_null_without_a_usable_answer(): void {
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => 'sorry, no');
        $this->assertNull(planner::plan(segmenter::split(self::PAGE), 5));

        ai_resolver::set_provider_for_testing(function (): string {
            throw new \RuntimeException('No provider.');
        });
        $this->assertNull(planner::plan(segmenter::split(self::PAGE), 5));
        $this->assertDebuggingCalled();

        $called = false;
        ai_resolver::set_provider_for_testing(function () use (&$called): string {
            $called = true;
            return '{}';
        });
        $this->assertSame([], planner::plan(segmenter::split('<p><img src="a.png"></p>'), 5));
        $this->assertSame([], planner::plan(segmenter::split(self::PAGE), 0));
        $this->assertFalse($called);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Headings, lists and callouts are built from the lines, and the lines left alone are joined as they were.
     *
     * @return void
     */
    public function test_assemble_builds_titles_lists_and_callouts_from_lines(): void {
        $segments = segmenter::split(self::PASTED);
        $plan = planner::validate([
            ['type' => 'heading', 'line' => 0, 'level' => 2],
            ['type' => 'callout', 'lines' => [1]],
            ['type' => 'heading', 'line' => 2],
            ['type' => 'list', 'lines' => [5, 6, 7]],
        ], $segments, 5);

        $html = assembler::build($segments, $plan);

        $this->assertSame(2, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="callout"'));
        $this->assertStringContainsString('<ul><li>Item A</li><li>Item B</li><li>Item C</li></ul>', $html);
        $this->assertStringContainsString('<p>Body one.<br>Body two.</p>', $html, 'the lines left alone are joined again');
    }

    /**
     * A bullet typed at the start of a line is not kept in the list, which draws its own.
     *
     * @return void
     */
    public function test_assemble_drops_the_typed_bullet_of_a_list_item(): void {
        $segments = segmenter::split('<p>• one<br>- two<br>– three</p>');
        $plan = planner::validate([['type' => 'list', 'lines' => [0, 1, 2]]], $segments, 5);

        $this->assertStringContainsString('<ul><li>one</li><li>two</li><li>three</li></ul>', assembler::build($segments, $plan));
    }

    /**
     * A grid has one card for each item, with its title, and the steps keep the title and the text.
     *
     * @return void
     */
    public function test_assemble_builds_a_grid_and_steps(): void {
        $segments = segmenter::split(self::ITEMS);
        $items = [['title' => 0, 'text' => [1]], ['title' => 2, 'text' => [3]], ['title' => 4, 'text' => [5]]];

        $grid = assembler::build($segments, planner::validate([['type' => 'grid', 'items' => $items]], $segments, 5));
        $this->assertSame(1, substr_count($grid, 'data-slms-block-type="gridcards"'));
        $this->assertSame(3, substr_count($grid, 'data-slms-block-type="advancedCard"'));
        $this->assertStringContainsString('<h4>B</h4>', $grid);
        $this->assertStringContainsString('b text', $grid);

        $steps = assembler::build($segments, planner::validate([['type' => 'steps', 'items' => $items]], $segments, 5));
        $this->assertSame(1, substr_count($steps, 'data-slms-block-type="infographicSteps"'));
        $this->assertStringContainsString('c text', $steps);
    }

    /**
     * Nothing in the plan means the real headings are styled and everything else is written back as it was.
     *
     * @return void
     */
    public function test_assemble_without_plan_only_styles_the_real_headings(): void {
        $html = assembler::build(segmenter::split(self::PAGE), []);

        $this->assertSame(2, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertStringNotContainsString('data-slms-block-type="callout"', $html);
        $this->assertStringContainsString('<p>Cells are the unit of life.</p>', $html);
        $this->assertStringContainsString('<img src="https://example.com/cell.png"', $html, 'the picture is untouched');
        $this->assertSame('', assembler::build([], []));
        $this->assertSame(
            '<p>Intro title<br>First text.</p><p>Topic<br>Body one.<br>Body two.</p><p>Item A<br>Item B<br>Item C</p>',
            assembler::build(segmenter::split(self::PASTED), []),
            'a pasted page with no plan comes back as it was'
        );
    }

    /**
     * An accordion folds the pieces after its heading up to the next heading, and a Studio block is not nested.
     *
     * @return void
     */
    public function test_assemble_folds_the_content_of_an_accordion(): void {
        $page = '<h2>First</h2><p>One.</p><p>Two.</p><h2>Second</h2><p>Three.</p>'
            . '<div class="mceNonEditable" data-slms-block-type="callout"><p>Mine</p></div><p>Four.</p>';
        $segments = segmenter::split($page);
        $plan = planner::validate(
            [['type' => 'accordion', 'heading' => 0], ['type' => 'accordion', 'heading' => 3]],
            $segments,
            5
        );

        $html = assembler::build($segments, $plan);

        $this->assertSame(2, substr_count($html, 'data-slms-block-type="accordion"'));
        $this->assertSame(0, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertGreaterThan(strpos($html, 'Two.'), strpos($html, 'Second'), 'the first accordion holds its two pieces');
        $this->assertLessThan(strpos($html, 'Mine'), strpos($html, 'Three.'));
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="callout"'), 'the block stays outside');
        $this->assertStringContainsString('<p>Four.</p>', $html);
    }

    /**
     * A block planned inside the folded content is built there, and a title named in the plan ends the folding.
     *
     * @return void
     */
    public function test_assemble_builds_blocks_inside_an_accordion_and_stops_at_a_planned_title(): void {
        $segments = segmenter::split('<p>Topic<br>Body.<br>Key idea.<br>Next topic<br>More.</p>');
        $plan = planner::validate([
            ['type' => 'accordion', 'heading' => 0],
            ['type' => 'callout', 'lines' => [2]],
            ['type' => 'heading', 'line' => 3],
        ], $segments, 5);

        $html = assembler::build($segments, $plan);

        $accordion = strpos($html, 'data-slms-block-type="accordion"');
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="accordion"'));
        $this->assertGreaterThan($accordion, strpos($html, 'data-slms-block-type="callout"'), 'the callout is inside');
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertGreaterThan(strpos($html, 'Key idea.'), strpos($html, 'Next topic'));
    }

    /**
     * An accordion with nothing after its heading would be empty, so the heading is just styled.
     *
     * @return void
     */
    public function test_assemble_does_not_fold_an_empty_accordion(): void {
        $segments = segmenter::split('<p>Intro.</p><h2>The end</h2>');

        $html = assembler::build($segments, planner::validate([['type' => 'accordion', 'heading' => 1]], $segments, 5));

        $this->assertStringNotContainsString('data-slms-block-type="accordion"', $html);
        $this->assertStringContainsString('data-slms-block-type="stylizedHeading"', $html);
    }

    /**
     * Whatever the plan does, every word of the original is in the result, in the same order.
     *
     * @return void
     */
    public function test_no_word_is_lost_or_moved(): void {
        $page = self::PASTED . self::ITEMS . self::PAGE;
        $segments = segmenter::split($page);
        $plan = planner::validate([
            ['type' => 'heading', 'line' => 0, 'level' => 2],
            ['type' => 'callout', 'lines' => [1]],
            ['type' => 'accordion', 'heading' => 2],
            ['type' => 'card', 'lines' => [3, 4]],
            ['type' => 'list', 'lines' => [5, 6, 7]],
            ['type' => 'grid', 'items' => [['title' => 8, 'text' => [9]], ['title' => 10, 'text' => [11]]]],
            ['type' => 'steps', 'items' => [['title' => 12, 'text' => [13]], ['title' => 14, 'text' => [15]]]],
        ], $segments, 10);

        $words = function (string $html): array {
            $spaced = preg_replace('/<[^>]*>/', ' ', $html);
            return preg_match_all('/[\p{L}\p{N}]+/u', html_entity_decode($spaced), $matches) ? $matches[0] : [];
        };
        $original = $words($page);
        $result = $words(assembler::build($segments, $plan));

        $position = 0;
        foreach ($original as $word) {
            $position = array_search($word, array_slice($result, $position), true) === false
                ? -1
                : $position + array_search($word, array_slice($result, $position), true) + 1;
            $this->assertNotSame(-1, $position, 'Lost or moved: ' . $word);
        }
    }

    /**
     * Restyling an already restyled page changes nothing: the Studio blocks are carried over as they are.
     *
     * @return void
     */
    public function test_restyling_twice_keeps_the_blocks_it_made(): void {
        $segments = segmenter::split(self::PAGE);
        $first = assembler::build($segments, planner::validate([['type' => 'callout', 'lines' => [1]]], $segments, 5));

        $again = assembler::build(segmenter::split($first), []);

        $this->assertSame(
            substr_count($first, 'data-slms-block-type='),
            substr_count($again, 'data-slms-block-type=')
        );
    }
}
