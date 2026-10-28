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
 * Cuts the HTML of a page or label into its top-level pieces.
 *
 * Each piece is a heading, a piece of text that may be given a visual block, or a fixed piece (a picture, a video, a
 * table, a Studio block) that is carried over exactly as it is. The text is never rewritten here or anywhere later:
 * the pieces keep their own HTML.
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
     * @return array[] The pieces in order, each with id, kind, html, text (the plain text, one line) and level
     *                 (1 to 6 for a heading, 0 for the others).
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
        foreach ($body->childNodes as $node) {
            $piece = self::piece($dom, $xpath, $node);
            if ($piece === null) {
                continue;
            }
            $piece['id'] = count($segments);
            $segments[] = $piece;
        }
        return $segments;
    }

    /**
     * Describes one top-level node, or nothing when it holds no content.
     *
     * @param \DOMDocument $dom The document.
     * @param \DOMXPath $xpath The query object of the document.
     * @param \DOMNode $node The node.
     * @return array|null The piece without its id, or null for a comment or empty space.
     */
    private static function piece(\DOMDocument $dom, \DOMXPath $xpath, \DOMNode $node): ?array {
        if ($node instanceof \DOMText) {
            $text = self::plain($node->textContent);
            if ($text === '') {
                return null;
            }
            return ['kind' => self::KIND_TEXT, 'html' => '<p>' . s($text) . '</p>', 'text' => $text, 'level' => 0];
        }
        if (!($node instanceof \DOMElement)) {
            return null;
        }

        $html = trim($dom->saveHTML($node));
        $text = self::plain($node->textContent);
        $isfixed = self::is_fixed($xpath, $node);

        if (!$isfixed && $text === '') {
            return null;
        }
        $level = 0;
        if ($isfixed) {
            $kind = self::KIND_FIXED;
        } else if (preg_match('/^h([1-6])$/', $node->nodeName, $matches)) {
            $kind = self::KIND_HEADING;
            $level = (int) $matches[1];
        } else {
            $kind = self::KIND_TEXT;
        }
        return ['kind' => $kind, 'html' => $html, 'text' => $text, 'level' => $level];
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
     * Reduces text to one line, with entities decoded and no repeated space.
     *
     * @param string $text The text.
     * @return string
     */
    private static function plain(string $text): string {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
    }
}
