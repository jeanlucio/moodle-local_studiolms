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
 * Loads StudioLMS page presets from the tiny_studiolms preset catalog.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Reads the tiny_studiolms preset catalog and renders preset blocks as editable HTML.
 *
 * The catalog lives in tiny_studiolms/presets/<lang>/*.json. Each file holds a named
 * preset with an array of {type, config} block definitions that match the registry ids
 * and canonical configs used by the editor.
 */
class preset_loader {
    /** @var string|null Preset catalog directory injected by tests, replacing the tiny_studiolms one. */
    private static ?string $testingdir = null;

    /**
     * Injects a preset catalog directory for tests, so they do not depend on the installed editor's presets.
     *
     * Ignored outside PHPUnit and Behat.
     *
     * @param string|null $directory Directory holding one sub-directory per language, or null to reset.
     * @return void
     */
    public static function set_directory_for_testing(?string $directory): void {
        if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
            return;
        }
        self::$testingdir = $directory;
    }

    /**
     * Returns all presets for the current (or given) language, with English fallback.
     *
     * Each entry has keys: name, description (optional), blocks (array of {type, config}).
     *
     * @param string $lang Language code. Defaults to current_language().
     * @return array Preset definitions, sorted by file name.
     */
    public static function get_all(string $lang = ''): array {
        $langdir = self::language_directory($lang);
        if ($langdir === null) {
            return [];
        }

        $files = glob($langdir . '/*.json') ?: [];
        sort($files);

        $presets = [];
        foreach ($files as $file) {
            $data = self::read_file($file);
            if ($data !== null) {
                $presets[] = $data;
            }
        }

        return $presets;
    }

    /**
     * Finds a preset by the base name of its catalog file, which unlike the display name does not change per language.
     *
     * @param string $stem File name without the .json extension (for example "course_syllabus").
     * @param string $lang Language code. Defaults to current_language(); falls back to English like get_all().
     * @return array|null The preset definition, or null if the file is missing or not a valid preset.
     */
    public static function find_by_file(string $stem, string $lang = ''): ?array {
        $langdir = self::language_directory($lang);
        if ($langdir === null || !preg_match('/^[a-z0-9_]+$/', $stem)) {
            return null;
        }
        $file = $langdir . '/' . $stem . '.json';
        return is_file($file) ? self::read_file($file) : null;
    }

    /**
     * Returns the catalog directory for a language, falling back to English when it has none.
     *
     * @param string $lang Language code, or '' for current_language().
     * @return string|null The directory, or null when the catalog is not available.
     */
    private static function language_directory(string $lang): ?string {
        global $CFG;

        if ($lang === '') {
            $lang = current_language();
        }

        $basedir = self::$testingdir ?? $CFG->dirroot . '/lib/editor/tiny/plugins/studiolms/presets';
        $langdir = $basedir . '/' . $lang;
        if (!is_dir($langdir)) {
            $langdir = $basedir . '/en';
        }
        return is_dir($langdir) ? $langdir : null;
    }

    /**
     * Reads one preset file, accepting only a named preset that has blocks.
     *
     * @param string $file Absolute path of the JSON file.
     * @return array|null The preset definition, or null when the file is unreadable or not a valid preset.
     */
    private static function read_file(string $file): ?array {
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['name']) || empty($data['blocks'])) {
            return null;
        }
        return $data;
    }

    /**
     * Finds a single preset by (case-insensitive) name.
     *
     * @param string $name Preset name to look for.
     * @param string $lang Language code. Defaults to current_language().
     * @return array|null The preset definition, or null if not found.
     */
    public static function find(string $name, string $lang = ''): ?array {
        $needle = mb_strtolower(trim($name));
        foreach (self::get_all($lang) as $preset) {
            if (mb_strtolower(trim($preset['name'])) === $needle) {
                return $preset;
            }
        }
        return null;
    }

    /**
     * Returns a compact catalog for use in AI prompts: name + description only.
     *
     * @param string $lang Language code. Defaults to current_language().
     * @return array Array of {name, description} maps.
     */
    public static function catalog_for_prompt(string $lang = ''): array {
        return array_map(static fn($p) => [
            'name'        => $p['name'],
            'description' => $p['description'] ?? '',
        ], self::get_all($lang));
    }

    /**
     * Renders all blocks of a preset as editable Studio HTML.
     *
     * @param array $preset Preset definition (from get_all() or find()).
     * @return string Concatenated HTML of all blocks.
     */
    public static function render(array $preset): string {
        $html = '';
        foreach ($preset['blocks'] ?? [] as $block) {
            $type   = $block['type'] ?? '';
            $config = $block['config'] ?? [];
            if ($type === '' || !is_array($config)) {
                continue;
            }
            $html .= block_builder::render($type, $config);
        }
        return $html;
    }
}
