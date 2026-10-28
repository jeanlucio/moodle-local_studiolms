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
 * Builds the restyled HTML of an activity from its pieces and the plan.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

use local_studiolms\local\block_builder;

/**
 * Puts the original pieces back together, giving the planned ones a Studio block.
 *
 * Headings become Studio headings, and so do the lines the plan names as titles. Whatever the plan does not mention,
 * and every fixed piece, is written back as it was: the lines of one paragraph are joined again with their line
 * breaks. The words of a block are always the original ones.
 */
class assembler {
    /**
     * Builds the HTML.
     *
     * @param array[] $segments The pieces, from segmenter::split().
     * @param array $plan The blocks by the id of their first line, from planner::plan().
     * @return string The restyled HTML.
     */
    public static function build(array $segments, array $plan): string {
        return self::range($segments, $plan, 0, count($segments));
    }

    /**
     * Builds the HTML of a range of pieces.
     *
     * @param array[] $segments The pieces.
     * @param array $plan The blocks of the plan.
     * @param int $from The first piece.
     * @param int $to The position after the last piece.
     * @return string The HTML.
     */
    private static function range(array $segments, array $plan, int $from, int $to): string {
        $html = '';
        $i = $from;
        while ($i < $to) {
            $segment = $segments[$i];
            $block = $plan[$i] ?? null;
            if ($block !== null && end($block['ids']) < $to) {
                [$out, $i] = self::block($segments, $plan, $block, $to);
                $html .= $out;
                continue;
            }
            if ($segment['kind'] === segmenter::KIND_HEADING) {
                $html .= self::heading($segment['text'], $segment['level'] <= 2 ? 2 : ($segment['level'] === 3 ? 3 : 4));
                $i++;
            } else if ($segment['kind'] === segmenter::KIND_TEXT && $segment['line']) {
                $end = $i + 1;
                while ($end < $to && self::continues_paragraph($segments, $plan, $end, $segment['group'])) {
                    $end++;
                }
                $html .= '<p>' . implode('<br>', array_column(array_slice($segments, $i, $end - $i), 'inner')) . '</p>';
                $i = $end;
            } else {
                $html .= $segment['html'];
                $i++;
            }
        }
        return $html;
    }

    /**
     * Tells whether a piece is one more line of the paragraph that is being written back as it was.
     *
     * @param array[] $segments The pieces.
     * @param array $plan The blocks of the plan.
     * @param int $position The piece.
     * @param int $group The paragraph.
     * @return bool
     */
    private static function continues_paragraph(array $segments, array $plan, int $position, int $group): bool {
        return $segments[$position]['line'] && $segments[$position]['group'] === $group && !isset($plan[$position]);
    }

    /**
     * Builds the block of the plan that starts at a piece.
     *
     * @param array[] $segments The pieces.
     * @param array $plan The blocks of the plan.
     * @param array $block The block.
     * @param int $to The position after the last piece of the range being built.
     * @return array The HTML and the position of the piece after the block.
     */
    private static function block(array $segments, array $plan, array $block, int $to): array {
        $first = $block['ids'][0];
        $next = end($block['ids']) + 1;
        switch ($block['type']) {
            case planner::BLOCK_HEADING:
                return [self::heading($segments[$first]['text'], $block['level']), $next];
            case planner::BLOCK_LIST:
                return [self::list_block($segments, $block['ids']), $next];
            case planner::BLOCK_CALLOUT:
                return [self::callout(self::paragraphs($segments, $block['ids'])), $next];
            case planner::BLOCK_CARD:
                return [self::card(self::paragraphs($segments, $block['ids'])), $next];
            case planner::BLOCK_GRID:
                return [self::grid($segments, $block['items']), $next];
            case planner::BLOCK_STEPS:
                return [self::steps($segments, $block['items']), $next];
            default:
                $end = self::accordion_end($segments, $plan, $first, $to);
                if ($end === $first + 1) {
                    return [self::heading($segments[$first]['text'], 3), $end];
                }
                $content = self::range($segments, $plan, $first + 1, $end);
                return [self::accordion($segments[$first]['text'], $content), $end];
        }
    }

    /**
     * Finds where the content folded under a heading ends.
     *
     * It runs up to the next heading (of the page or of the plan), the next accordion, or the next piece that holds
     * a Studio block, which is never nested in another.
     *
     * @param array[] $segments The pieces.
     * @param array $plan The blocks of the plan.
     * @param int $position The position of the heading.
     * @param int $to The position after the last piece of the range being built.
     * @return int The position of the first piece that is not folded.
     */
    private static function accordion_end(array $segments, array $plan, int $position, int $to): int {
        for ($i = $position + 1; $i < $to; $i++) {
            $isheading = $segments[$i]['kind'] === segmenter::KIND_HEADING;
            $planned = isset($plan[$i]) && in_array($plan[$i]['type'], [planner::BLOCK_HEADING, planner::BLOCK_ACCORDION], true);
            $isblock = $segments[$i]['kind'] === segmenter::KIND_FIXED
                && strpos($segments[$i]['html'], 'data-slms-block-type') !== false;
            if ($isheading || $planned || $isblock) {
                return $i;
            }
        }
        return $to;
    }

    /**
     * Builds a Studio heading.
     *
     * @param string $text The plain text of the heading.
     * @param int $level 2, 3 or 4.
     * @return string The block HTML.
     */
    private static function heading(string $text, int $level): string {
        return block_builder::render('stylizedHeading', [
            'text' => s($text),
            'level' => 'h' . $level,
            'icon' => '',
            'bgColor' => '#e3f2fd',
            'textColor' => '#0d47a1',
        ]);
    }

    /**
     * Writes the pieces as paragraphs, each as it was.
     *
     * @param array[] $segments The pieces.
     * @param int[] $ids The pieces.
     * @return string The HTML.
     */
    private static function paragraphs(array $segments, array $ids): string {
        $html = '';
        foreach ($ids as $id) {
            $html .= $segments[$id]['line'] ? '<p>' . $segments[$id]['inner'] . '</p>' : $segments[$id]['html'];
        }
        return clean_text($html, FORMAT_HTML);
    }

    /**
     * Builds a list from consecutive lines, each as one item.
     *
     * A bullet typed at the start of a line is the marker the list now draws, so it is not kept.
     *
     * @param array[] $segments The pieces.
     * @param int[] $ids The lines.
     * @return string The HTML.
     */
    private static function list_block(array $segments, array $ids): string {
        $items = '';
        foreach ($ids as $id) {
            $inner = preg_replace('/^[\s\x{00A0}•·▪●◦\-–—*]+/u', '', $segments[$id]['inner']);
            $items .= '<li>' . $inner . '</li>';
        }
        return clean_text('<ul>' . $items . '</ul>', FORMAT_HTML);
    }

    /**
     * Builds a callout.
     *
     * @param string $content The content.
     * @return string The block HTML.
     */
    private static function callout(string $content): string {
        return block_builder::render('callout', [
            'backgroundColor' => '#eff6ff',
            'borderLeftWidth' => 4,
            'borderColor' => '#3b82f6',
            'borderRadius' => 6,
            'hoverEffect' => 'none',
            'icon' => '💡',
            'textColor' => '#1e3a8a',
            'contentHtml' => $content,
        ]);
    }

    /**
     * Builds a card.
     *
     * @param string $content The content.
     * @return string The block HTML.
     */
    private static function card(string $content): string {
        return block_builder::render('advancedCard', [
            'bg' => '#ffffff',
            'text' => '#212529',
            'border' => '#0d47a1',
            'radius' => 8,
            'shadow' => 'sm',
            'mediaType' => 'none',
            'mediaUrl' => '',
            'layout' => 'vertical',
            'btnText' => '',
            'btnUrl' => '#',
            'btnBg' => '#0d47a1',
            'btnTextCol' => '#ffffff',
            'btnAlign' => 'left',
            'hoverEffect' => 'none',
            'content' => $content,
        ]);
    }

    /**
     * Builds a grid with one card for each item: its title and its text.
     *
     * @param array[] $segments The pieces.
     * @param array[] $items The items, each with the id of its title and the ids of its text.
     * @return string The block HTML.
     */
    private static function grid(array $segments, array $items): string {
        $slots = [];
        foreach ($items as $item) {
            $slots[] = self::card(
                \html_writer::tag('h4', s($segments[$item['title']]['text']))
                . self::paragraphs($segments, $item['text'])
            );
        }
        $count = count($items);
        return block_builder::render('gridcards', [
            'columns' => $count === 3 || $count > 4 ? 3 : 2,
            'gap' => 20,
            'slots' => $slots,
        ]);
    }

    /**
     * Builds a sequence of steps, each with its title and its text.
     *
     * @param array[] $segments The pieces.
     * @param array[] $items The items, each with the id of its title and the ids of its text.
     * @return string The block HTML.
     */
    private static function steps(array $segments, array $items): string {
        $steps = [];
        foreach ($items as $item) {
            $text = [];
            foreach ($item['text'] as $id) {
                $text[] = $segments[$id]['text'];
            }
            $steps[] = ['icon' => '', 'title' => $segments[$item['title']]['text'], 'description' => implode(' ', $text)];
        }
        return block_builder::render('infographicSteps', [
            'title' => '',
            'theme' => 'blue',
            'layout' => 'vertical',
            'current' => 0,
            'items' => $steps,
        ]);
    }

    /**
     * Builds an accordion: the heading is its title and the content is what was folded under it.
     *
     * @param string $title The plain text of the heading.
     * @param string $content The HTML of the content.
     * @return string The block HTML.
     */
    private static function accordion(string $title, string $content): string {
        return block_builder::render('accordion', [
            'state' => 'closed',
            'bg' => '#ffffff',
            'color' => '#0d47a1',
            'icon' => '❯ / ❯',
            'openSound' => 'none',
            'hoverEffect' => 'none',
            'title' => s($title),
            'content' => $content,
        ]);
    }
}
