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
 * Builds the Studio blocks the restyle uses.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

use local_studiolms\local\block_builder;

/**
 * The blocks of a restyled page, built from plain values: the assembler gives them the original lines and the
 * polisher gives them the text the AI wrote.
 */
class blocks {
    /**
     * Builds a Studio heading.
     *
     * @param string $text The plain text of the heading.
     * @param int $level 2, 3 or 4.
     * @return string The block HTML.
     */
    public static function heading(string $text, int $level): string {
        return block_builder::render('stylizedHeading', [
            'text' => s($text),
            'level' => 'h' . min(4, max(2, $level)),
            'icon' => '',
            'bgColor' => '#e3f2fd',
            'textColor' => '#0d47a1',
        ]);
    }

    /**
     * Builds a list.
     *
     * A bullet typed at the start of an item is the marker the list now draws, so it is not kept.
     *
     * @param string[] $items The HTML inside each item.
     * @return string The HTML.
     */
    public static function list(array $items): string {
        $html = '';
        foreach ($items as $inner) {
            $html .= '<li>' . preg_replace('/^[\s\x{00A0}•·▪●◦\-–—*]+/u', '', $inner) . '</li>';
        }
        return clean_text('<ul>' . $html . '</ul>', FORMAT_HTML);
    }

    /**
     * Builds a callout.
     *
     * @param string $content The HTML of the content.
     * @return string The block HTML.
     */
    public static function callout(string $content): string {
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
     * @param string $content The HTML of the content.
     * @return string The block HTML.
     */
    public static function card(string $content): string {
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
     * Builds a grid with one card for each item.
     *
     * @param array[] $items The items, each with title (plain text) and html (the text under it).
     * @return string The block HTML.
     */
    public static function grid(array $items): string {
        $slots = [];
        foreach ($items as $item) {
            $slots[] = self::card(\html_writer::tag('h4', s($item['title'])) . $item['html']);
        }
        $count = count($items);
        return block_builder::render('gridcards', [
            'columns' => $count === 3 || $count > 4 ? 3 : 2,
            'gap' => 20,
            'slots' => $slots,
        ]);
    }

    /**
     * Builds a sequence of steps.
     *
     * @param array[] $items The steps, each with title and description (both plain text).
     * @return string The block HTML.
     */
    public static function steps(array $items): string {
        $steps = [];
        foreach ($items as $item) {
            $steps[] = ['icon' => '', 'title' => $item['title'], 'description' => $item['description']];
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
    public static function accordion(string $title, string $content): string {
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
