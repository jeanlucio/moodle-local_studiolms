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
 * Tests for the lenient AI JSON decoder.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the lenient AI JSON decoder.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ai_json::class)]
final class ai_json_test extends \advanced_testcase {
    /**
     * Plain valid JSON object decodes to an array.
     *
     * @return void
     */
    public function test_decode_plain_object(): void {
        $result = ai_json::decode('{"content":"hello","n":3}');
        $this->assertSame(['content' => 'hello', 'n' => 3], $result);
    }

    /**
     * A markdown-fenced JSON block is unwrapped before decoding.
     *
     * @return void
     */
    public function test_decode_strips_markdown_fence(): void {
        $fence = str_repeat(chr(96), 3);
        $raw = $fence . "json\n" . '{"a":1}' . "\n" . $fence;
        $this->assertSame(['a' => 1], ai_json::decode($raw));
    }

    /**
     * Surrounding prose is discarded; the first JSON structure is extracted.
     *
     * @return void
     */
    public function test_decode_extracts_from_prose(): void {
        $raw = 'Sure! Here is the data: {"ok":true} — hope it helps.';
        $this->assertSame(['ok' => true], ai_json::decode($raw));
    }

    /**
     * A trailing comma before a closing brace is tolerated.
     *
     * @return void
     */
    public function test_decode_tolerates_trailing_comma(): void {
        $this->assertSame(['a' => 1, 'b' => 2], ai_json::decode('{"a":1,"b":2,}'));
    }

    /**
     * A top-level JSON array decodes to a list.
     *
     * @return void
     */
    public function test_decode_array(): void {
        $this->assertSame([1, 2, 3], ai_json::decode('[1, 2, 3]'));
    }

    /**
     * Input with no JSON structure returns null.
     *
     * @return void
     */
    public function test_decode_without_json_returns_null(): void {
        $this->assertNull(ai_json::decode('no json here at all'));
    }

    /**
     * Irreparably malformed JSON returns null.
     *
     * @return void
     */
    public function test_decode_malformed_returns_null(): void {
        $this->assertNull(ai_json::decode('{"a": "unterminated'));
    }

    /**
     * Text from the model is cleaned, and anything that is not a scalar counts as missing.
     *
     * clean_param() throws on an array and a cast would turn it into the word "Array".
     */
    public function test_text_treats_non_scalars_as_missing(): void {
        $this->assertSame('Hello', ai_json::text('<b>Hello</b>'));
        $this->assertSame('5', ai_json::text(5));
        $this->assertSame('', ai_json::text(['x']));
        $this->assertSame('', ai_json::text(['text' => 'x']));
        $this->assertSame('', ai_json::text(null));
        $this->assertSame('fallback', ai_json::text(['x'], 'fallback'));
        $this->assertSame('fallback', ai_json::text(null, 'fallback'));
    }

    /**
     * HTML from the model is cleaned, and anything that is not a scalar is empty.
     */
    public function test_html_cleans_and_treats_non_scalars_as_empty(): void {
        $this->assertSame('<p>ok</p>', ai_json::html('<p>ok</p><script>alert(1)</script>'));
        $this->assertSame('', ai_json::html(['<p>x</p>']));
        $this->assertSame('', ai_json::html(null));
    }

    /**
     * Yes and no are read in the languages of the courses, and what is not one of them is told apart from a no.
     */
    public function test_flag_or_null_reads_yes_and_no_in_the_languages_of_the_courses(): void {
        foreach (['Sim', 'verdadeiro', 'Verdadero', 'sí', 'oui', 'vrai', 'Yes', true, 1, ' true '] as $value) {
            $this->assertTrue(ai_json::flag_or_null($value), var_export($value, true));
        }
        foreach (['Não', 'nao', 'falso', 'Falso', 'faux', 'non', 'No', false, 0, '0'] as $value) {
            $this->assertFalse(ai_json::flag_or_null($value), var_export($value, true));
        }
        foreach ([null, 'talvez', 'maybe', '', ['x'], 2, 'verdadeiro e falso'] as $value) {
            $this->assertNull(ai_json::flag_or_null($value), var_export($value, true));
        }
        $this->assertTrue(ai_json::flag('Sim'));
        $this->assertFalse(ai_json::flag('Falso', true));
    }

    /**
     * A boolean written as text keeps its meaning: the string "false" is not true.
     */
    public function test_flag_reads_booleans_written_as_text(): void {
        foreach ([true, 'true', 'TRUE', '1', 1, 'yes', 'on'] as $value) {
            $this->assertTrue(ai_json::flag($value), var_export($value, true));
        }
        foreach ([false, 'false', 'False', '0', 0, 'no', 'off'] as $value) {
            $this->assertFalse(ai_json::flag($value, true), var_export($value, true));
        }
        $this->assertTrue(ai_json::flag(null, true));
        $this->assertFalse(ai_json::flag(null));
        $this->assertTrue(ai_json::flag(['x'], true));
        $this->assertTrue(ai_json::flag('', true), 'An empty text says neither yes nor no.');
        $this->assertFalse(ai_json::flag('maybe'));
        $this->assertTrue(ai_json::flag('maybe', true));
    }

    /**
     * A list field that is not an array is an empty list, so a loop over it never fails.
     */
    public function test_items_returns_an_empty_list_for_non_arrays(): void {
        $this->assertSame([1, 2], ai_json::items([1, 2]));
        $this->assertSame([], ai_json::items('text'));
        $this->assertSame([], ai_json::items(null));
        $this->assertSame([], ai_json::items(5));
    }
}
