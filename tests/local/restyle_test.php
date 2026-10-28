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
    /** @var string A page with a heading, three pieces of text, a picture and a second heading. */
    private const PAGE = '<h2>The cell</h2><p>Cells are the unit of life.</p><p>Remember: they have a membrane.</p>'
        . '<p><img src="https://example.com/cell.png" alt="A cell"></p><h3>Organelles</h3><p>The nucleus keeps the DNA.</p>';

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
        $this->assertSame('The cell', $segments[0]['text']);
        $this->assertStringContainsString('<img src="https://example.com/cell.png"', $segments[3]['html']);
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
     * The plan keeps only valid entries: known ids, a type the piece can take, in order, up to the limit.
     *
     * @return void
     */
    public function test_validate_drops_what_makes_no_sense(): void {
        $segments = segmenter::split(self::PAGE);
        $blocks = [
            ['id' => 5, 'type' => 'card'],
            ['id' => 0, 'type' => 'accordion'],
            ['id' => 1, 'type' => 'accordion'],
            ['id' => 4, 'type' => 'callout'],
            ['id' => 3, 'type' => 'callout'],
            ['id' => 99, 'type' => 'callout'],
            ['id' => 2, 'type' => 'poster'],
            'not an entry',
            ['id' => 'x'],
            ['id' => [1], 'type' => 'callout'],
            ['id' => 2, 'type' => 'callout'],
        ];

        $this->assertSame(
            [0 => 'accordion', 2 => 'callout', 5 => 'card'],
            planner::validate($blocks, $segments, 10)
        );
        $this->assertSame([0 => 'accordion', 2 => 'callout'], planner::validate($blocks, $segments, 2));
        $this->assertSame([], planner::validate('nothing', $segments, 5));
    }

    /**
     * The plan comes from the answer of the AI, and the AI is shown the text but told to change none of it.
     *
     * @return void
     */
    public function test_plan_reads_the_answer_of_the_ai(): void {
        $this->resetAfterTest();
        $seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen = ['system' => $system, 'user' => $user];
            return 'Here is the plan: {"blocks":[{"id":2,"type":"callout"},{"id":4,"type":"accordion"}]} Done.';
        });

        $plan = planner::plan(segmenter::split(self::PAGE), 5);

        $this->assertSame([2 => 'callout', 4 => 'accordion'], $plan);
        $this->assertStringContainsString('never write', $seen['system']);
        $this->assertStringContainsString('Remember: they have a membrane.', $seen['user']);
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
     * Headings become Studio headings, planned text becomes a block, and the text is the original one.
     *
     * @return void
     */
    public function test_assemble_builds_blocks_with_the_original_text(): void {
        $segments = segmenter::split(self::PAGE);

        $html = assembler::build($segments, [1 => 'card', 2 => 'callout']);

        $this->assertStringContainsString('data-slms-block-type="stylizedHeading"', $html);
        $this->assertSame(2, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertStringContainsString('data-slms-block-type="advancedCard"', $html);
        $this->assertStringContainsString('data-slms-block-type="callout"', $html);
        foreach (['The cell', 'Cells are the unit of life.', 'Remember: they have a membrane.', 'Organelles'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('<img src="https://example.com/cell.png"', $html, 'the picture is untouched');
        $this->assertStringContainsString('<p>The nucleus keeps the DNA.</p>', $html, 'text with no block is untouched');
    }

    /**
     * Nothing in the plan means the headings are styled and everything else is written back as it was.
     *
     * @return void
     */
    public function test_assemble_without_plan_only_styles_the_headings(): void {
        $segments = segmenter::split(self::PAGE);

        $html = assembler::build($segments, []);

        $this->assertStringNotContainsString('data-slms-block-type="callout"', $html);
        $this->assertStringNotContainsString('data-slms-block-type="advancedCard"', $html);
        $this->assertStringContainsString('<p>Cells are the unit of life.</p>', $html);
        $this->assertSame('', assembler::build([], []));
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

        $html = assembler::build($segments, [0 => 'accordion', 3 => 'accordion']);

        $this->assertSame(2, substr_count($html, 'data-slms-block-type="accordion"'));
        $this->assertSame(0, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $accordion = strpos($html, 'Second');
        $this->assertGreaterThan(strpos($html, 'Two.'), $accordion, 'the first accordion holds its two pieces');
        $this->assertLessThan(strpos($html, 'Mine'), strpos($html, 'Three.'));
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="callout"'), 'the block stays outside');
        $this->assertStringContainsString('<p>Four.</p>', $html);
    }

    /**
     * An accordion with nothing after its heading would be empty, so the heading is just styled.
     *
     * @return void
     */
    public function test_assemble_does_not_fold_an_empty_accordion(): void {
        $segments = segmenter::split('<p>Intro.</p><h2>The end</h2>');

        $html = assembler::build($segments, [1 => 'accordion']);

        $this->assertStringNotContainsString('data-slms-block-type="accordion"', $html);
        $this->assertStringContainsString('data-slms-block-type="stylizedHeading"', $html);
    }

    /**
     * Restyling an already restyled page changes nothing: the Studio blocks are carried over as they are.
     *
     * @return void
     */
    public function test_restyling_twice_keeps_the_blocks_it_made(): void {
        $segments = segmenter::split(self::PAGE);
        $first = assembler::build($segments, [1 => 'card', 2 => 'callout']);

        $again = assembler::build(segmenter::split($first), [0 => 'callout', 1 => 'card']);

        $this->assertSame(
            substr_count($first, 'data-slms-block-type='),
            substr_count($again, 'data-slms-block-type=')
        );
    }
}
