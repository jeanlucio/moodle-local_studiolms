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

    /**
     * Returns a field of a decoded response as plain text, or $default when it is not a scalar.
     *
     * A model answers in whatever shape it likes: a field asked for as text may come back as an object or
     * a list. clean_param() throws on an array and a (string) cast turns it into the word "Array", so
     * anything that is not a string, number or boolean is treated as missing.
     *
     * @param mixed $value Field of the decoded response.
     * @param string $default Returned when the value is not a scalar.
     * @return string
     */
    public static function text(mixed $value, string $default = ''): string {
        return is_scalar($value) ? clean_param((string) $value, PARAM_TEXT) : $default;
    }

    /**
     * Returns a field of a decoded response as cleaned HTML, or '' when it is not a scalar.
     *
     * @param mixed $value Field of the decoded response.
     * @return string
     */
    public static function html(mixed $value): string {
        return is_scalar($value) ? clean_text((string) $value, FORMAT_HTML) : '';
    }

    /**
     * Returns a field of a decoded response as a boolean, or null when it says neither yes nor no.
     *
     * A truthiness test or a cast would make the string "false" true, which in a quiz marks a wrong answer as the
     * right one. The words are read in the languages the courses are written in, since the AI answers in the
     * language it is asked to write: a "Sim" or a "Falso" is an answer, and something else ("talvez") is not.
     *
     * @param mixed $value Field of the decoded response.
     * @return bool|null True, false, or null when the value cannot be read as either.
     */
    public static function flag_or_null(mixed $value): ?bool {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $word = \core_text::strtolower(trim((string) $value));
        $yes = ['true', '1', 'yes', 'on', 'sim', 'verdadeiro', 'verdadera', 'verdadero', 'si', 'sí', 'oui', 'vrai'];
        $no = ['false', '0', 'no', 'off', 'não', 'nao', 'falso', 'falsa', 'non', 'faux'];
        if (in_array($word, $yes, true)) {
            return true;
        }
        return in_array($word, $no, true) ? false : null;
    }

    /**
     * Returns a field of a decoded response as a boolean.
     *
     * A value that cannot be read as a boolean is $default.
     *
     * @param mixed $value Field of the decoded response.
     * @param bool $default Returned when the value is missing or not a boolean.
     * @return bool
     */
    public static function flag(mixed $value, bool $default = false): bool {
        return self::flag_or_null($value) ?? $default;
    }

    /**
     * Returns a field of a decoded response as a list, or an empty one when it is not an array.
     *
     * @param mixed $value Field of the decoded response.
     * @return array
     */
    public static function items(mixed $value): array {
        return is_array($value) ? $value : [];
    }
}
