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
 * Splits the HTML of an activity into the pieces the restyle plan works on.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local\restyle;

/**
 * Cuts the HTML of a page or label into its pieces, one per line of text.
 *
 * Text pasted from the web often has no real headings: a title is a line of a paragraph, set apart only by a line
 * break. So a paragraph that holds line breaks is cut at them, and each line becomes a piece the plan can name. Only
 * the markup around the words is touched: no word is ever rewritten here or anywhere later.
 *
 * A piece is a heading (h1 to h6), a piece of text that may be given a visual block, or a fixed piece (a picture, a
 * video, a table, a Studio block) that is carried over exactly as it is.
 */
class segmenter {
    /** @var string A heading, from h1 to h6. */
    public const KIND_HEADING = 'heading';

    /** @var string A piece of text that may be given a visual block. */
    public const KIND_TEXT = 'text';

    /** @var string A piece that is carried over as it is. */
    public const KIND_FIXED = 'fixed';

    /**
     * Splits the HTML into pieces.
     *
     * @param string $html The HTML of the activity.
     * @return array[] The pieces in order. Each has id, kind, html (its own HTML), inner (what goes inside a list
     *                 item or a card), text (the plain text, one line), level (1 to 6 for a heading, 0 for the
     *                 others), group (the node it came from, shared by the lines of one paragraph) and line (true
     *                 when it is one line cut from a paragraph).
     */
    public static function split(string $html): array {
        if (trim($html) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return [];
        }
        $xpath = new \DOMXPath($dom);

        $segments = [];
        $group = 0;
        foreach ($body->childNodes as $node) {
            $pieces = self::pieces($dom, $xpath, $node);
            foreach ($pieces as $piece) {
                $piece['id'] = count($segments);
                $piece['group'] = $group;
                $segments[] = $piece;
            }
            if (!empty($pieces)) {
                $group++;
            }
        }
        return $segments;
    }

    /**
     * Describes one top-level node as one piece, or as one per line when it is a paragraph with line breaks.
     *
     * @param \DOMDocument $dom The document.
     * @param \DOMXPath $xpath The query object of the document.
     * @param \DOMNode $node The node.
     * @return array[] The pieces without id and group; none for a comment or empty space.
     */
    private static function pieces(\DOMDocument $dom, \DOMXPath $xpath, \DOMNode $node): array {
        if ($node instanceof \DOMText) {
            $text = self::plain($node->textContent);
            if ($text === '') {
                return [];
            }
            return [self::text_piece('<p>' . s($text) . '</p>', s($text), $text, false)];
        }
        if (!($node instanceof \DOMElement)) {
            return [];
        }

        $html = trim($dom->saveHTML($node));
        $text = self::plain($node->textContent);
        $isfixed = self::is_fixed($xpath, $node);

        if (!$isfixed && $text === '') {
            return [];
        }
        if ($isfixed) {
            return [['kind' => self::KIND_FIXED, 'html' => $html, 'inner' => $html, 'text' => $text, 'level' => 0,
                'line' => false]];
        }
        if (preg_match('/^h([1-6])$/', $node->nodeName, $matches)) {
            return [['kind' => self::KIND_HEADING, 'html' => $html, 'inner' => $html, 'text' => $text,
                'level' => (int) $matches[1], 'line' => false]];
        }
        if ($node->nodeName === 'p') {
            $lines = self::lines($dom, $node);
            if (count($lines) > 1) {
                return array_map(
                    fn(array $line): array => self::text_piece('<p>' . $line[0] . '</p>', $line[0], $line[1], true),
                    $lines
                );
            }
            $inner = '';
            foreach ($node->childNodes as $child) {
                $inner .= $dom->saveHTML($child);
            }
            return [self::text_piece($html, trim($inner), $text, false)];
        }
        return [self::text_piece($html, $html, $text, false)];
    }

    /**
     * Builds a piece of text.
     *
     * @param string $html Its own HTML.
     * @param string $inner What goes inside a list item or a card.
     * @param string $text The plain text.
     * @param bool $line True when it is one line cut from a paragraph.
     * @return array The piece without id and group.
     */
    private static function text_piece(string $html, string $inner, string $text, bool $line): array {
        return ['kind' => self::KIND_TEXT, 'html' => $html, 'inner' => $inner, 'text' => $text, 'level' => 0,
            'line' => $line];
    }

    /**
     * Cuts a paragraph at its line breaks.
     *
     * @param \DOMDocument $dom The document.
     * @param \DOMElement $paragraph The paragraph.
     * @return array[] The lines that hold text, each as [html, plain text].
     */
    private static function lines(\DOMDocument $dom, \DOMElement $paragraph): array {
        $lines = [];
        $html = '';
        $text = '';
        $flush = function () use (&$lines, &$html, &$text): void {
            $plain = self::plain($text);
            if ($plain !== '') {
                $lines[] = [trim($html), $plain];
            }
            $html = '';
            $text = '';
        };
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'br') {
                $flush();
                continue;
            }
            $html .= $dom->saveHTML($child);
            $text .= $child->textContent;
        }
        $flush();
        return $lines;
    }

    /**
     * Tells whether a node must be carried over as it is.
     *
     * That is anything that holds a picture, a sound, a video, an embedded page, a table or a form, and any block of
     * the Studio that is already there: restyling must never touch what the teacher placed with the editor.
     *
     * @param \DOMXPath $xpath The query object of the document.
     * @param \DOMElement $node The node.
     * @return bool
     */
    private static function is_fixed(\DOMXPath $xpath, \DOMElement $node): bool {
        $query = 'descendant-or-self::*[self::img or self::video or self::audio or self::iframe or self::object'
            . ' or self::embed or self::svg or self::canvas or self::table or self::form or self::script'
            . ' or self::style or self::hr or @data-slms-block-type'
            . " or contains(concat(' ', normalize-space(@class), ' '), ' mceNonEditable ')]";
        return $xpath->query($query, $node)->length > 0;
    }

    /**
     * Reduces text to one line, with no repeated space.
     *
     * @param string $text The text.
     * @return string
     */
    private static function plain(string $text): string {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
    }
}
