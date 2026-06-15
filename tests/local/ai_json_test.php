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
}
