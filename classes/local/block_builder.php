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
 * Renders StudioLMS visual blocks as editable HTML.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Renders a StudioLMS block (registry id plus canonical config) into the exact markup
 * the tiny_studiolms editor recognises for re-editing.
 *
 * Each rendered block root carries data-slms-block-type, data-slms-state (a base64 chip
 * of the canonical config, matching the editor's btoa(encodeURIComponent(JSON))) and the
 * mceNonEditable class, so generated content opens for editing just like hand-placed blocks.
 */
class block_builder {
    /** @var array Block registry id mapped to its Mustache template. */
    private const TEMPLATES = [
        'stylizedHeading' => 'tiny_studiolms/block_heading',
        'callout' => 'tiny_studiolms/block_callout',
        'advancedCard' => 'tiny_studiolms/block_card',
    ];

    /** @var array Block registry id mapped to config keys kept out of the state chip (read from DOM). */
    private const EXCLUDED = [
        'callout' => ['contentHtml'],
    ];

    /**
     * Renders a block as editable Studio HTML.
     *
     * @param string $type The block registry id (for example 'callout').
     * @param array $config The canonical block configuration.
     * @return string The wrapped editable HTML, or an empty string when the type is unsupported.
     */
    public static function render(string $type, array $config): string {
        global $OUTPUT;

        $adapter = self::adapt($type, $config);
        if ($adapter === null) {
            return '';
        }

        $html = $OUTPUT->render_from_template($adapter['template'], $adapter['context']);
        return self::wrap($html, $type, $config);
    }

    /**
     * Builds the Mustache template name and view model for a block type.
     *
     * @param string $type The block registry id.
     * @param array $config The canonical block configuration.
     * @return array|null The template name and context, or null when the type is unsupported.
     */
    private static function adapt(string $type, array $config): ?array {
        if (!isset(self::TEMPLATES[$type])) {
            return null;
        }

        switch ($type) {
            case 'stylizedHeading':
                $level = $config['level'] ?? 'h3';
                $context = [
                    'isH3' => $level === 'h3',
                    'isH4' => $level === 'h4',
                    'bgColor' => $config['bgColor'] ?? '#e3f2fd',
                    'textColor' => $config['textColor'] ?? '#0d47a1',
                    'icon' => $config['icon'] ?? '',
                    'text' => $config['text'] ?? '',
                ];
                break;
            case 'callout':
                $context = [
                    'backgroundColor' => $config['backgroundColor'] ?? '#fef9c3',
                    'borderLeftWidth' => $config['borderLeftWidth'] ?? 4,
                    'borderColor' => $config['borderColor'] ?? '#eab308',
                    'borderRadius' => $config['borderRadius'] ?? 6,
                    'hoverEffect' => $config['hoverEffect'] ?? 'none',
                    'hasIcon' => !empty($config['icon']),
                    'icon' => $config['icon'] ?? '',
                    'textColor' => $config['textColor'] ?? '#854d0e',
                    'contentHtml' => $config['contentHtml'] ?? '',
                ];
                break;
            case 'advancedCard':
                $context = self::card_context($config);
                break;
            default:
                return null;
        }

        return ['template' => self::TEMPLATES[$type], 'context' => $context];
    }

    /**
     * Builds the advanced card view model from its canonical config.
     *
     * @param array $config The canonical card configuration.
     * @return array The Mustache context for tiny_studiolms/block_card.
     */
    private static function card_context(array $config): array {
        $shadowmap = [
            'none' => 'none',
            'sm' => '0 1px 3px rgba(0, 0, 0, 0.1)',
            'md' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'lg' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
        ];
        $shadow = $config['shadow'] ?? 'md';
        $mediatype = $config['mediaType'] ?? 'none';
        $mediaurl = trim((string) ($config['mediaUrl'] ?? ''));
        $layout = $config['layout'] ?? 'vertical';
        $btnalign = $config['btnAlign'] ?? 'left';
        $btntext = trim((string) ($config['btnText'] ?? ''));

        return [
            'bg' => $config['bg'] ?? '#ffffff',
            'text' => $config['text'] ?? '#212529',
            'border' => $config['border'] ?? '#0d47a1',
            'radius' => $config['radius'] ?? 8,
            'shadowCss' => $shadowmap[$shadow] ?? $shadowmap['md'],
            'hasMedia' => $mediatype !== 'none' && $mediaurl !== '',
            'isImage' => $mediatype === 'image',
            'mediaUrl' => $mediaurl,
            'isHorizontal' => $layout === 'horizontal',
            'content' => $config['content'] ?? '',
            'hasButton' => $btntext !== '',
            'btnText' => $btntext,
            'btnUrl' => $config['btnUrl'] ?? '#',
            'btnBg' => $config['btnBg'] ?? '#0d47a1',
            'btnTextCol' => $config['btnTextCol'] ?? '#ffffff',
            'isAlignLeft' => $btnalign === 'left',
            'isAlignCenter' => $btnalign === 'center',
            'isAlignRight' => $btnalign === 'right',
            'isAlignFull' => $btnalign === 'full',
            'isBtnFullWidth' => $btnalign === 'full',
            'btnDisplayMode' => $btnalign === 'full' ? 'flex' : 'inline-flex',
        ];
    }

    /**
     * Wraps rendered block HTML so the tiny_studiolms editor treats it as an editable block.
     *
     * @param string $html The rendered block HTML.
     * @param string $type The block registry id.
     * @param array $config The canonical block configuration.
     * @return string The wrapped HTML.
     */
    private static function wrap(string $html, string $type, array $config): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $state = $config;
        foreach (self::EXCLUDED[$type] ?? [] as $key) {
            unset($state[$key]);
        }
        $json = json_encode($state);
        if ($json === false) {
            return $html;
        }
        $chip = base64_encode(rawurlencode($json));

        $tagend = strpos($html, '>');
        if ($tagend === false) {
            return $html;
        }
        $opentag = substr($html, 0, $tagend);

        if ($type !== 'table' && strpos($opentag, 'mceNonEditable') === false) {
            if (preg_match('/\bclass="/', $opentag)) {
                $opentag = preg_replace('/\bclass="/', 'class="mceNonEditable ', $opentag, 1);
            } else {
                $opentag .= ' class="mceNonEditable"';
            }
        }

        $attrs = ' data-slms-block-type="' . $type . '" data-slms-state="' . $chip . '"';
        return $opentag . $attrs . substr($html, $tagend);
    }
}
