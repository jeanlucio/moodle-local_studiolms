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

use local_studiolms\local\restyle\polisher;
use local_studiolms\local\restyle\segmenter;

/**
 * Tests for the mode that lets the AI reorganise and polish the text of an activity.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_studiolms\local\restyle\polisher
 * @covers \local_studiolms\local\restyle\blocks
 */
final class restyle_polisher_test extends \advanced_testcase {
    /** @var string A page with a repeated sentence, a link, a picture and a number. */
    private const PAGE = '<h2>The cell</h2><p>Cells are the unit of life. Cells are the unit of life.</p>'
        . '<p>Visit <a href="https://example.com/cells">this page</a> to learn more.</p>'
        . '<p><img src="https://example.com/cell.png" alt="A cell"></p><p>The nucleus keeps the DNA in 23 pairs.</p>';

    /**
     * Makes the AI answer with the given JSON, and keeps what it was asked.
     *
     * @param string $answer The answer.
     * @return \stdClass Receives system and user as the AI is called.
     */
    private function ai_answers(string $answer): \stdClass {
        $seen = new \stdClass();
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use ($answer, $seen): string {
            $seen->system = $system;
            $seen->user = $user;
            return $answer;
        });
        return $seen;
    }

    /**
     * Polishes the test page.
     *
     * @param int $maxblocks The most visual blocks.
     * @return string|null The HTML.
     */
    private function polish(int $maxblocks = 10): ?string {
        return polisher::polish(segmenter::split(self::PAGE), $maxblocks);
    }

    /**
     * The AI writes the new page: titles, paragraphs and boxes, with the repetition gone.
     *
     * @return void
     */
    public function test_the_page_is_rewritten_from_the_blocks_of_the_answer(): void {
        $this->resetAfterTest();
        $seen = $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'heading', 'text' => 'The cell', 'level' => 2],
            ['type' => 'paragraph', 'html' => '<p>Cells are the unit of life.</p>'],
            ['type' => 'callout', 'html' => 'Visit <a href="https://example.com/cells">this page</a> to learn more.'],
            ['type' => 'keep', 'id' => 3],
            ['type' => 'list', 'items' => ['The nucleus keeps the DNA', 'in 23 pairs']],
        ]]));

        $html = $this->polish();

        $this->assertSame(1, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="callout"'));
        $this->assertSame(1, substr_count($html, 'Cells are the unit of life.'), 'the repetition is gone');
        $this->assertStringContainsString('<ul><li>The nucleus keeps the DNA</li><li>in 23 pairs</li></ul>', $html);
        $this->assertSame(1, substr_count($html, '<img src="https://example.com/cell.png"'), 'the picture is carried over');
        $this->assertStringContainsString('must not add', $seen->system);
        $this->assertStringContainsString('{"id":3,"keep":true}', $seen->user);
        $this->assertStringNotContainsString('cell.png', $seen->user, 'the AI is not shown a picture');
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A picture the AI left out is put back at the end, one it named twice or does not exist is not repeated, and
     * nothing in the original is lost.
     *
     * @return void
     */
    public function test_a_fixed_piece_is_never_lost_or_repeated(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'paragraph', 'html' => 'Cells are the unit of life. Visit this page to learn more.'],
            ['type' => 'paragraph', 'html' => 'The nucleus keeps the DNA in 23 pairs.'],
            ['type' => 'keep', 'id' => 99],
        ]]));
        $left = $this->polish();
        $this->assertSame(1, substr_count($left, '<img src="https://example.com/cell.png"'));
        $this->assertStringEndsWith('alt="A cell"></p>', $left);

        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'keep', 'id' => 3],
            ['type' => 'paragraph', 'html' => 'Cells are the unit of life. Visit this page to learn more.'],
            ['type' => 'paragraph', 'html' => 'The nucleus keeps the DNA in 23 pairs.'],
            ['type' => 'keep', 'id' => 3],
        ]]));
        $this->assertSame(1, substr_count($this->polish(), '<img src="https://example.com/cell.png"'));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * The text of the AI is untrusted: scripts and pictures it writes are removed, and a link is kept only if the
     * original had it, the text of any other link staying.
     *
     * @return void
     */
    public function test_what_the_ai_writes_is_cleaned(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'paragraph', 'html' => '<script>alert(2)</script>Cells are the unit of life.<img src=x onerror=alert(3)>'],
            ['type' => 'paragraph', 'html' => 'Visit <a href="https://example.com/cells" onclick="x()">this page</a> and '
                . '<a href="https://evil.example/x">that one</a> to learn more.'],
            ['type' => 'paragraph', 'html' => '<b>The nucleus</b> keeps the <em>DNA</em> in 23 pairs.'
                . '<iframe src="https://evil"></iframe>'],
            ['type' => 'keep', 'id' => 3],
        ]]));

        $html = $this->polish();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('<a href="https://example.com/cells">this page</a>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('that one', $html);
        $this->assertStringContainsString('<b>The nucleus</b> keeps the <em>DNA</em>', $html);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A result with a number the original does not have is refused, and so is a length far from the original.
     *
     * @return void
     */
    public function test_a_result_that_strays_from_the_original_is_refused(): void {
        $this->resetAfterTest();
        $text = 'Cells are the unit of life. Visit this page to learn more. The nucleus keeps the DNA in ';

        $this->ai_answers(json_encode(['blocks' => [['type' => 'paragraph', 'html' => $text . '37 pairs.']]]));
        try {
            $this->polish();
            $this->fail('A number that is not in the text must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_polish_numbers', $e->errorcode);
        }

        $this->ai_answers(json_encode(['blocks' => [['type' => 'paragraph', 'html' => 'Cells are life.']]]));
        try {
            $this->polish();
            $this->fail('A text much shorter than the original must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_polish_length', $e->errorcode);
        }

        $this->ai_answers(json_encode(['blocks' => [['type' => 'paragraph', 'html' => str_repeat($text . '23 pairs. ', 3)]]]));
        try {
            $this->polish();
            $this->fail('A text much longer than the original must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_polish_length', $e->errorcode);
        }
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * The numbers a block draws itself (the steps are numbered) are not counted as written by the AI.
     *
     * @return void
     */
    public function test_the_numbers_drawn_by_a_block_are_not_the_ones_of_the_ai(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'steps', 'items' => [
                ['title' => 'Cells are the unit of life', 'text' => 'Visit this page to learn more'],
                ['title' => 'The nucleus', 'text' => 'keeps the DNA in 23 pairs'],
            ]],
            ['type' => 'keep', 'id' => 3],
        ]]));

        $html = $this->polish();

        $this->assertSame(1, substr_count($html, 'data-slms-block-type="infographicSteps"'));
        $this->assertStringContainsString('keeps the DNA in 23 pairs', $html);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Grids, steps, cards and accordions are built, and over the limit or with too few items they become plain text.
     *
     * @return void
     */
    public function test_visual_blocks_over_the_limit_or_malformed_keep_their_text(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'card', 'title' => 'Cells', 'html' => 'Cells are the unit of life.'],
            ['type' => 'grid', 'items' => [['title' => 'Page', 'html' => 'Visit this page to learn more.']]],
            ['type' => 'accordion', 'title' => 'More', 'blocks' => [
                ['type' => 'paragraph', 'html' => 'The nucleus keeps the DNA in 23 pairs.'],
            ]],
            ['type' => 'keep', 'id' => 3],
        ]]));

        $html = $this->polish(1);

        $this->assertSame(1, substr_count($html, 'data-slms-block-type="advancedCard"'), 'the first fits');
        $this->assertSame(0, substr_count($html, 'data-slms-block-type="gridcards"'), 'one item is no grid');
        $this->assertSame(0, substr_count($html, 'data-slms-block-type="accordion"'), 'over the limit');
        $this->assertStringContainsString('<h4>Page</h4>', $html);
        $this->assertStringContainsString('The nucleus keeps the DNA in 23 pairs.', $html);

        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'grid', 'items' => [
                ['title' => 'Cells', 'html' => 'Cells are the unit of life.'],
                ['title' => 'Page', 'html' => 'Visit this page to learn more.'],
            ]],
            ['type' => 'accordion', 'title' => 'More', 'blocks' => [
                ['type' => 'paragraph', 'html' => 'The nucleus keeps the DNA in 23 pairs.'],
            ]],
            ['type' => 'keep', 'id' => 3],
        ]]));
        $both = $this->polish();
        $this->assertSame(1, substr_count($both, 'data-slms-block-type="gridcards"'));
        $this->assertSame(1, substr_count($both, 'data-slms-block-type="accordion"'));
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Without a usable answer there is nothing to write (null); a message of the AI layer reaches the teacher.
     *
     * @return void
     */
    public function test_there_is_no_result_without_a_usable_answer(): void {
        $this->resetAfterTest();
        $this->ai_answers('sorry, no');
        $this->assertNull($this->polish());
        $this->ai_answers('{"blocks":"x"}');
        $this->assertNull($this->polish());

        ai_resolver::set_provider_for_testing(function (): string {
            throw new \RuntimeException('No provider.');
        });
        $this->assertNull($this->polish());
        $this->assertDebuggingCalled();

        ai_resolver::set_provider_for_testing(function (): string {
            throw new \moodle_exception('aidisabledincourse', 'local_studiolms');
        });
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aidisabledincourse', 'local_studiolms'));
        try {
            $this->polish();
        } finally {
            ai_resolver::set_provider_for_testing(null);
        }
    }

    /**
     * A text too long to be sent whole is refused: the page is rewritten from what the AI is shown, so a text cut at
     * the limit would lose its end without a word.
     *
     * @return void
     */
    public function test_a_text_too_long_to_send_is_refused_and_the_ai_is_not_asked(): void {
        $this->resetAfterTest();
        $seen = $this->ai_answers('{"blocks":[]}');
        $page = str_repeat('<p>' . str_repeat('word ', 100) . '</p>', 60);

        try {
            polisher::polish(segmenter::split($page), 5);
            $this->fail('A text over the limit must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('restyle_polish_toolong', $e->errorcode);
        }
        $this->assertObjectNotHasProperty('system', $seen);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * Blocks the AI wrote with no text, or with text that is not text, are left out and nothing breaks.
     *
     * @return void
     */
    public function test_empty_and_malformed_blocks_are_left_out(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'heading', 'text' => '   '],
            ['type' => 'paragraph', 'html' => ['not', 'text']],
            ['type' => 'list', 'items' => [['x'], '']],
            ['type' => 'accordion', 'title' => '', 'blocks' => [
                ['type' => 'paragraph', 'html' => 'Cells are the unit of life.'],
            ]],
            ['type' => 'accordion', 'title' => 'Empty', 'blocks' => []],
            ['type' => 'callout', 'html' => ''],
            ['type' => 'poster', 'html' => 'A type the plugin does not know.'],
            'not a block',
            ['type' => 'paragraph', 'html' => 'Visit this page to learn more. The nucleus keeps the DNA in 23 pairs.'],
            ['type' => 'keep', 'id' => 3],
        ]]));

        $html = $this->polish();

        $this->assertSame(0, substr_count($html, 'data-slms-block-type="stylizedHeading"'));
        $this->assertSame(0, substr_count($html, 'data-slms-block-type="accordion"'));
        $this->assertStringContainsString('<p>Cells are the unit of life.</p>', $html, 'an untitled accordion keeps its text');
        $this->assertStringContainsString('<p>Visit this page to learn more.', $html);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A page with nothing but a picture has nothing to polish, and the AI is not asked.
     *
     * @return void
     */
    public function test_a_page_with_no_text_is_not_sent_to_the_ai(): void {
        $this->resetAfterTest();
        $seen = $this->ai_answers('{"blocks":[]}');

        $this->assertNull(polisher::polish(segmenter::split('<p><img src="a.png" alt=""></p>'), 5));
        $this->assertObjectNotHasProperty('system', $seen);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A number of the original that the result does not have is told, and does not refuse the result: the AI may have
     * cut a sentence on purpose, and the teacher decides.
     *
     * @return void
     */
    public function test_a_number_that_goes_missing_is_told_and_does_not_refuse(): void {
        $this->resetAfterTest();
        $segments = segmenter::split(
            '<p>Lipovetsky wrote the book (2006) and said it again in 2010.</p><p>It sold 24.000 copies.</p>'
        );
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'paragraph', 'html' => 'Lipovetsky wrote the book and said it again in 2010. It sold 24.000 copies.'],
        ]]));

        $missing = [];
        $html = polisher::polish($segments, 5, null, $missing);

        $this->assertNotNull($html);
        $this->assertSame(['2006'], $missing);
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * A number kept anywhere in the result is not missing, one told twice is told once, the markers of a typed list are
     * not numbers of the text, and a number of a picture or table is not asked of the AI.
     *
     * @return void
     */
    public function test_numbers_kept_or_typed_as_a_list_marker_are_not_missing(): void {
        $this->resetAfterTest();
        $segments = segmenter::split(
            '<p>1. First step in 2002.<br>2. Second step, again in 2002.<br>Total of 3,5 hours in 2002.</p>'
            . '<table><tr><td>Year 1999</td></tr></table>'
        );
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'list', 'items' => ['First step', 'Second step']],
            ['type' => 'paragraph', 'html' => 'Total of 3,5 hours.'],
            ['type' => 'keep', 'id' => 3],
        ]]));

        $missing = [];
        polisher::polish($segments, 5, null, $missing);

        $this->assertSame(['2002'], $missing, '2002 is told once, and 1 and 2 are list markers; 3,5 and 1999 are there');
        ai_resolver::set_provider_for_testing(null);
    }

    /**
     * With every number kept, nothing is missing.
     *
     * @return void
     */
    public function test_nothing_is_missing_when_every_number_is_kept(): void {
        $this->resetAfterTest();
        $this->ai_answers(json_encode(['blocks' => [
            ['type' => 'paragraph', 'html' => 'Cells are the unit of life. Visit this page to learn more.'],
            ['type' => 'paragraph', 'html' => 'The nucleus keeps the DNA in 23 pairs.'],
            ['type' => 'keep', 'id' => 3],
        ]]));
        $missing = ['stale'];

        polisher::polish(segmenter::split(self::PAGE), 10, null, $missing);

        $this->assertSame([], $missing);
        ai_resolver::set_provider_for_testing(null);
    }
}
