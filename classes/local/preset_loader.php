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
    /**
     * Returns all presets for the current (or given) language, with English fallback.
     *
     * Each entry has keys: name, description (optional), blocks (array of {type, config}).
     *
     * @param string $lang Language code. Defaults to current_language().
     * @return array Preset definitions, sorted by file name.
     */
    public static function get_all(string $lang = ''): array {
        global $CFG;

        if ($lang === '') {
            $lang = current_language();
        }

        $basedir = $CFG->dirroot . '/lib/editor/tiny/plugins/studiolms/presets';
        $langdir = $basedir . '/' . $lang;
        if (!is_dir($langdir)) {
            $langdir = $basedir . '/en';
        }
        if (!is_dir($langdir)) {
            return [];
        }

        $files = glob($langdir . '/*.json') ?: [];
        sort($files);

        $presets = [];
        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['name'])) {
                continue;
            }
            if (empty($data['blocks'])) {
                continue;
            }
            $presets[] = $data;
        }

        return $presets;
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
