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
 * Tolerant JSON decoding for AI responses.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Decodes JSON returned by language models, tolerating markdown fences and trailing commas.
 */
class ai_json {
    /**
     * Decodes an AI response into an array.
     *
     * @param string $raw Raw AI response.
     * @return array|null The decoded array, or null when no valid JSON is found.
     */
    public static function decode(string $raw): ?array {
        $text = trim($raw);
        $fence = str_repeat(chr(96), 3);
        $text = preg_replace('/^' . $fence . '(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*' . $fence . '$/', '', $text);

        $starts = array_filter([strpos($text, '{'), strpos($text, '[')], fn($value) => $value !== false);
        $ends = array_filter([strrpos($text, '}'), strrpos($text, ']')], fn($value) => $value !== false);
        if (empty($starts) || empty($ends)) {
            return null;
        }

        $start = min($starts);
        $end = max($ends);
        $text = substr($text, $start, $end - $start + 1);
        $text = preg_replace('/,(\s*[}\]])/', '$1', $text);

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
}
