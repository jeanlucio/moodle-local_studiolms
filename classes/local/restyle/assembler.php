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
 * Headings become Studio headings unless the plan folds the text after them in an accordion. Whatever the plan does
 * not mention, and every fixed piece, is written back exactly as it was; the text of a block is always the original.
 */
class assembler {
    /**
     * Builds the HTML.
     *
     * @param array[] $segments The pieces, from segmenter::split().
     * @param array $plan Block type by piece id, from planner::plan().
     * @return string The restyled HTML.
     */
    public static function build(array $segments, array $plan): string {
        $html = '';
        $count = count($segments);
        for ($i = 0; $i < $count; $i++) {
            $segment = $segments[$i];
            switch ($segment['kind']) {
                case segmenter::KIND_HEADING:
                    $end = self::accordion_end($segments, $i);
                    $folded = ($plan[$segment['id']] ?? '') === planner::BLOCK_ACCORDION && $end > $i + 1;
                    if ($folded) {
                        $html .= self::accordion($segment, array_slice($segments, $i + 1, $end - $i - 1));
                        $i = $end - 1;
                    } else {
                        $html .= self::heading($segment);
                    }
                    break;
                case segmenter::KIND_TEXT:
                    $html .= self::text_block($segment, $plan[$segment['id']] ?? '');
                    break;
                default:
                    $html .= $segment['html'];
            }
        }
        return $html;
    }

    /**
     * Finds where the content folded under a heading ends.
     *
     * It runs up to the next heading or the next piece that holds a Studio block, which is never nested in another.
     *
     * @param array[] $segments The pieces.
     * @param int $position The position of the heading.
     * @return int The position of the first piece that is not folded.
     */
    private static function accordion_end(array $segments, int $position): int {
        $count = count($segments);
        for ($i = $position + 1; $i < $count; $i++) {
            $isheading = $segments[$i]['kind'] === segmenter::KIND_HEADING;
            $isblock = $segments[$i]['kind'] === segmenter::KIND_FIXED
                && strpos($segments[$i]['html'], 'data-slms-block-type') !== false;
            if ($isheading || $isblock) {
                return $i;
            }
        }
        return $count;
    }

    /**
     * Builds the Studio heading of a heading piece.
     *
     * @param array $segment The heading piece.
     * @return string The block HTML.
     */
    private static function heading(array $segment): string {
        $level = 'h4';
        if ($segment['level'] <= 2) {
            $level = 'h2';
        } else if ($segment['level'] === 3) {
            $level = 'h3';
        }
        return block_builder::render('stylizedHeading', [
            'text' => s($segment['text']),
            'level' => $level,
            'icon' => '',
            'bgColor' => '#e3f2fd',
            'textColor' => '#0d47a1',
        ]);
    }

    /**
     * Builds the block of a piece of text, or writes the piece back as it was.
     *
     * @param array $segment The text piece.
     * @param string $type The block type of the plan, or '' for none.
     * @return string The HTML.
     */
    private static function text_block(array $segment, string $type): string {
        $content = clean_text($segment['html'], FORMAT_HTML);
        if ($type === planner::BLOCK_CALLOUT) {
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
        if ($type === planner::BLOCK_CARD) {
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
        return $segment['html'];
    }

    /**
     * Builds an accordion: the heading is its title and the pieces after it are its content, unchanged.
     *
     * @param array $heading The heading piece.
     * @param array[] $pieces The pieces folded under it.
     * @return string The block HTML.
     */
    private static function accordion(array $heading, array $pieces): string {
        $content = '';
        foreach ($pieces as $piece) {
            $content .= $piece['html'];
        }
        return block_builder::render('accordion', [
            'state' => 'closed',
            'bg' => '#ffffff',
            'color' => '#0d47a1',
            'icon' => '❯ / ❯',
            'openSound' => 'none',
            'hoverEffect' => 'none',
            'title' => s($heading['text']),
            'content' => clean_text($content, FORMAT_HTML),
        ]);
    }
}
