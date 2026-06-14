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
 * mceNonEditable class (except table, which is editable), so generated content opens for
 * editing just like hand-placed blocks.
 */
class block_builder {
    /** @var array Block registry id mapped to its Mustache template. */
    private const TEMPLATES = [
        'stylizedHeading'       => 'tiny_studiolms/block_heading',
        'callout'               => 'tiny_studiolms/block_callout',
        'advancedCard'          => 'tiny_studiolms/block_card',
        'accordion'             => 'tiny_studiolms/block_accordion',
        'gridcards'             => 'tiny_studiolms/block_gridcards',
        'table'                 => 'tiny_studiolms/block_table',
        'infographic'           => 'tiny_studiolms/block_infographic',
        'infographicFeatures'   => 'tiny_studiolms/block_infographic_features',
        'infographicSteps'      => 'tiny_studiolms/block_infographic_steps',
        'infographicTimeline'   => 'tiny_studiolms/block_infographic_timeline',
        'infographicComparison' => 'tiny_studiolms/block_infographic_comparison',
        'mindmap'               => 'tiny_studiolms/block_mindmap',
    ];

    /**
     * @var array Config keys excluded from the state chip for each block type.
     * These are reconstructed from the live DOM by the editor's extractDOM hook.
     */
    private const EXCLUDED = [
        'callout'   => ['contentHtml'],
        'accordion' => ['title', 'content'],
        'gridcards' => ['containerTitle', 'slots'],
        'table'     => ['cellData'],
    ];

    /**
     * @var array Colour palettes indexed by theme name, matching tiny_studiolms JS THEMES.
     */
    private const THEMES = [
        'blue'   => [
            'bg' => '#eff6ff', 'iconBg' => '#dbeafe', 'iconColor' => '#1d4ed8',
            'valuColor' => '#1e3a8a', 'labelColor' => '#3b5280',
            'titleColor' => '#1e3a8a', 'borderColor' => '#bfdbfe',
        ],
        'green'  => [
            'bg' => '#f0fdf4', 'iconBg' => '#dcfce7', 'iconColor' => '#15803d',
            'valuColor' => '#14532d', 'labelColor' => '#2d6a4f',
            'titleColor' => '#14532d', 'borderColor' => '#bbf7d0',
        ],
        'purple' => [
            'bg' => '#faf5ff', 'iconBg' => '#ede9fe', 'iconColor' => '#7c3aed',
            'valuColor' => '#4c1d95', 'labelColor' => '#5b3aa5',
            'titleColor' => '#4c1d95', 'borderColor' => '#ddd6fe',
        ],
        'orange' => [
            'bg' => '#fff7ed', 'iconBg' => '#ffedd5', 'iconColor' => '#c2410c',
            'valuColor' => '#7c2d12', 'labelColor' => '#9a3412',
            'titleColor' => '#7c2d12', 'borderColor' => '#fed7aa',
        ],
        'red'    => [
            'bg' => '#fff1f2', 'iconBg' => '#ffe4e6', 'iconColor' => '#be123c',
            'valuColor' => '#881337', 'labelColor' => '#9f1239',
            'titleColor' => '#881337', 'borderColor' => '#fecdd3',
        ],
        'black'  => [
            'bg' => '#f8fafc', 'iconBg' => '#1e293b', 'iconColor' => '#f1f5f9',
            'valuColor' => '#0f172a', 'labelColor' => '#334155',
            'titleColor' => '#0f172a', 'borderColor' => '#cbd5e1',
        ],
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
     * Resolves a block type to its Mustache template name and view-model context.
     *
     * @param string $type The block registry id.
     * @param array $config The canonical block configuration.
     * @return array|null Template name and context array, or null when unsupported.
     */
    private static function adapt(string $type, array $config): ?array {
        if (!isset(self::TEMPLATES[$type])) {
            return null;
        }

        switch ($type) {
            case 'stylizedHeading':
                $context = self::heading_context($config);
                break;
            case 'callout':
                $context = self::callout_context($config);
                break;
            case 'advancedCard':
                $context = self::card_context($config);
                break;
            case 'accordion':
                $context = self::accordion_context($config);
                break;
            case 'gridcards':
                $context = self::gridcards_context($config);
                break;
            case 'table':
                $context = self::table_context($config);
                break;
            case 'infographic':
                $context = self::infographic_context($config);
                break;
            case 'infographicFeatures':
                $context = self::infographic_features_context($config);
                break;
            case 'infographicSteps':
                $context = self::infographic_steps_context($config);
                break;
            case 'infographicTimeline':
                $context = self::infographic_timeline_context($config);
                break;
            case 'infographicComparison':
                $context = self::infographic_comparison_context($config);
                break;
            case 'mindmap':
                $context = self::mindmap_context($config);
                break;
            default:
                return null;
        }

        return ['template' => self::TEMPLATES[$type], 'context' => $context];
    }

    // -------------------------------------------------------------------------
    // Block-specific context builders — mirror the JS renderHtml() functions.
    // -------------------------------------------------------------------------.

    /**
     * Builds the view model for block_heading.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function heading_context(array $config): array {
        $level = $config['level'] ?? 'h3';
        return [
            'isH3' => $level === 'h3',
            'isH4' => $level === 'h4',
            'bgColor' => $config['bgColor'] ?? '#e3f2fd',
            'textColor' => $config['textColor'] ?? '#0d47a1',
            'icon' => $config['icon'] ?? '',
            'text' => $config['text'] ?? '',
        ];
    }

    /**
     * Builds the view model for block_callout.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function callout_context(array $config): array {
        return [
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
    }

    /**
     * Builds the view model for block_card.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function card_context(array $config): array {
        $shadowmap = [
            'none' => 'none',
            'sm'   => '0 1px 3px rgba(0, 0, 0, 0.1)',
            'md'   => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'lg'   => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
        ];
        $shadow   = $config['shadow'] ?? 'md';
        $mediatype = $config['mediaType'] ?? 'none';
        $mediaurl  = trim((string) ($config['mediaUrl'] ?? ''));
        $layout    = $config['layout'] ?? 'vertical';
        $btnalign  = $config['btnAlign'] ?? 'left';
        $btntext   = trim((string) ($config['btnText'] ?? ''));

        return [
            'bg'           => $config['bg'] ?? '#ffffff',
            'text'         => $config['text'] ?? '#212529',
            'border'       => $config['border'] ?? '#0d47a1',
            'radius'       => $config['radius'] ?? 8,
            'shadowCss'    => $shadowmap[$shadow] ?? $shadowmap['md'],
            'hasMedia'     => $mediatype !== 'none' && $mediaurl !== '',
            'isImage'      => $mediatype === 'image',
            'mediaUrl'     => $mediaurl,
            'isHorizontal' => $layout === 'horizontal',
            'content'      => $config['content'] ?? '',
            'hasButton'    => $btntext !== '',
            'btnText'      => $btntext,
            'btnUrl'       => $config['btnUrl'] ?? '#',
            'btnBg'        => $config['btnBg'] ?? '#0d47a1',
            'btnTextCol'   => $config['btnTextCol'] ?? '#ffffff',
            'isAlignLeft'  => $btnalign === 'left',
            'isAlignCenter' => $btnalign === 'center',
            'isAlignRight' => $btnalign === 'right',
            'isAlignFull'  => $btnalign === 'full',
            'isBtnFullWidth' => $btnalign === 'full',
            'btnDisplayMode' => $btnalign === 'full' ? 'flex' : 'inline-flex',
        ];
    }

    /**
     * Builds the view model for block_accordion.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function accordion_context(array $config): array {
        $icon = $config['icon'] ?? '▼ / ▲';
        return [
            'state'       => $config['state'] ?? 'closed',
            'isOpen'      => ($config['state'] ?? 'closed') === 'open',
            'bg'          => $config['bg'] ?? '#ffffff',
            'color'       => $config['color'] ?? '#3b82f6',
            'title'       => $config['title'] ?? '',
            'content'     => $config['content'] ?? '',
            'openSound'   => $config['openSound'] ?? 'none',
            'hoverEffect' => $config['hoverEffect'] ?? 'none',
            'iconFirst'   => explode(' / ', $icon)[0],
        ];
    }

    /**
     * Builds the view model for block_gridcards.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function gridcards_context(array $config): array {
        $pdmap = ['none' => '0px', 'sm' => '10px', 'md' => '20px', 'lg' => '40px'];
        $bg = ($config['background'] ?? 'transparent') === 'transparent'
            ? 'transparent'
            : ($config['background'] ?? 'transparent');
        $pad = $pdmap[$config['padding'] ?? 'none'] ?? '0px';
        $bdw = (int) ($config['borderWidth'] ?? 0);
        $bdcolor = $config['borderColor'] ?? '#e2e8f0';
        $borderstyle = $bdw > 0 ? "{$bdw}px solid {$bdcolor}" : 'none';
        $bradius = (int) ($config['borderRadius'] ?? 0);
        $style = "background: {$bg}; border: {$borderstyle}; border-radius: {$bradius}px;"
            . " padding: {$pad}; width: 100%; box-sizing: border-box; margin-bottom: 1.5rem;";

        $colcount = max(1, (int) ($config['columns'] ?? 2));
        $slots = $config['slots'] ?? [];
        $slotshtml = '';
        for ($i = 0; $i < $colcount; $i++) {
            $content = !empty($slots[$i]) ? $slots[$i] : '<p><br></p>';
            $slotshtml .= '<div class="slms-grid-slot mceEditable"'
                . ' style="min-width: 0; height: 100%;">' . $content . '</div>';
        }

        return [
            'containerStyle'     => $style,
            'containerTitle'     => $config['containerTitle'] ?? '',
            'containerTitleSize' => $config['containerTitleSize'] ?? '24px',
            'titleColor'         => $config['titleColor'] ?? '#333333',
            'columns'            => (string) $colcount,
            'gap'                => $config['gap'] ?? 20,
            'slotsHtml'          => $slotshtml,
        ];
    }

    /**
     * Builds the view model for block_table.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context.
     */
    private static function table_context(array $config): array {
        $numrows  = max(2, (int) ($config['rows'] ?? 4));
        $numcols  = max(1, (int) ($config['cols'] ?? 3));
        $headerbg = $config['headerBg'] ?? '#0f172a';
        $headertext = $config['headerText'] ?? '#ffffff';
        $celldata = $config['cellData'] ?? [];

        $headercells = [];
        $bodyrows = [];
        for ($r = 0; $r < $numrows; $r++) {
            $rowdata = $celldata[$r] ?? [];
            $cells = [];
            for ($c = 0; $c < $numcols; $c++) {
                $content = !empty($rowdata[$c]) ? $rowdata[$c] : '&nbsp;';
                $cells[] = ['content' => $content, 'headerBg' => $headerbg, 'headerText' => $headertext];
            }
            if ($r === 0) {
                $headercells = $cells;
            } else {
                $bodyrows[] = ['cells' => $cells];
            }
        }

        return [
            'isStriped'   => ($config['style'] ?? 'striped') === 'striped',
            'headerCells' => $headercells,
            'bodyRows'    => $bodyrows,
        ];
    }

    /**
     * Builds the view model for block_infographic (stats layout).
     *
     * @param array $config Canonical block config.
     * @return array Mustache context with pre-rendered content HTML.
     */
    private static function infographic_context(array $config): array {
        $theme = $config['theme'] ?? 'blue';
        $palette = self::palette($theme);
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items = array_filter($items, static fn($i) => !empty($i['value']) || !empty($i['label']));

        $parts = [];
        if (!empty(trim((string) ($config['title'] ?? '')))) {
            $t = htmlspecialchars(trim((string) $config['title']), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-infographic__title" style="color:' . $palette['titleColor'] . ';">'
                . $t . '</div>';
        }

        $parts[] = '<div class="slms-infographic__grid">';
        foreach ($items as $item) {
            $icon = self::icon_html(self::normalise_icon((string) ($item['icon'] ?? '')));
            $val  = htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $lbl  = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-infographic__stat"'
                . ' style="background:' . $palette['bg'] . ';border-color:' . $palette['borderColor'] . ';">'
                . '<div class="slms-infographic__icon"'
                . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
                . $icon . '</div>'
                . '<div class="slms-infographic__value" style="color:' . $palette['valuColor'] . ';">'
                . $val . '</div>'
                . '<div class="slms-infographic__label" style="color:' . $palette['labelColor'] . ';">'
                . $lbl . '</div>'
                . '</div>';
        }
        $parts[] = '</div>';

        return ['layout' => $config['layout'] ?? 'stats', 'theme' => $theme, 'content' => implode('', $parts)];
    }

    /**
     * Builds the view model for block_infographic_features.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context with pre-rendered content HTML.
     */
    private static function infographic_features_context(array $config): array {
        $theme = $config['theme'] ?? 'blue';
        $palette = self::palette($theme);
        $cols = ($config['columns'] ?? 3) === 2 ? 2 : 3;
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items = array_filter($items, static fn($i) => !empty($i['title']));

        $parts = [];
        if (!empty(trim((string) ($config['title'] ?? '')))) {
            $t = htmlspecialchars(trim((string) $config['title']), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-features__title" style="color:' . $palette['titleColor'] . ';">'
                . $t . '</div>';
        }

        $parts[] = '<div class="slms-features__grid slms-features__grid--' . $cols . '">';
        foreach ($items as $item) {
            $icon  = self::icon_html(self::normalise_icon((string) ($item['icon'] ?? '')));
            $name  = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc  = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-features__item"'
                . ' style="background:' . $palette['bg'] . ';border-color:' . $palette['borderColor'] . ';">'
                . '<div class="slms-features__icon"'
                . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
                . $icon . '</div>'
                . '<div class="slms-features__name" style="color:' . $palette['valuColor'] . ';">'
                . $name . '</div>'
                . ($desc !== ''
                    ? '<div class="slms-features__desc" style="color:' . $palette['labelColor'] . ';">'
                    . $desc . '</div>'
                    : '')
                . '</div>';
        }
        $parts[] = '</div>';

        return ['theme' => $theme, 'columns' => $cols, 'content' => implode('', $parts)];
    }

    /**
     * Builds the view model for block_infographic_steps.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context with pre-rendered content HTML.
     */
    private static function infographic_steps_context(array $config): array {
        $theme = $config['theme'] ?? 'blue';
        $palette = self::palette($theme);
        $layout = $config['layout'] ?? 'vertical';
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items = array_values(array_filter($items, static fn($i) => !empty($i['title'])));

        $parts = [];
        if (!empty(trim((string) ($config['title'] ?? '')))) {
            $t = htmlspecialchars(trim((string) $config['title']), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-steps__title" style="color:' . $palette['titleColor'] . ';">'
                . $t . '</div>';
        }

        $parts[] = '<div class="slms-steps__list">';
        $count = count($items);
        foreach ($items as $idx => $item) {
            $islast = $idx === $count - 1;
            $stepnum = $idx + 1;
            $iconclass = self::normalise_icon((string) ($item['icon'] ?? ''));
            $badge = '<div class="slms-steps__badge"'
                . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
                . ($iconclass !== '' ? self::icon_html($iconclass) : '<span aria-hidden="true">' . $stepnum . '</span>')
                . '</div>';
            $connector = $islast ? '' : '<div class="slms-steps__connector"'
                . ' style="background:' . $palette['borderColor'] . ';"></div>';
            $steptitle = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $stepdesc  = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $pbbottom  = $islast ? '0' : '1.25rem';
            $parts[] = '<div class="slms-steps__item">'
                . '<div class="slms-steps__left">' . $badge . $connector . '</div>'
                . '<div class="slms-steps__content" style="padding-bottom:' . $pbbottom . ';">'
                . '<div class="slms-steps__step-title" style="color:' . $palette['valuColor'] . ';">'
                . $steptitle . '</div>'
                . ($stepdesc !== ''
                    ? '<div class="slms-steps__step-desc" style="color:' . $palette['labelColor'] . ';">'
                    . $stepdesc . '</div>'
                    : '')
                . '</div></div>';
        }
        $parts[] = '</div>';

        return ['theme' => $theme, 'layout' => $layout, 'content' => implode('', $parts)];
    }

    /**
     * Builds the view model for block_infographic_timeline.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context with pre-rendered content HTML.
     */
    private static function infographic_timeline_context(array $config): array {
        $theme = $config['theme'] ?? 'blue';
        $palette = self::palette($theme);
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items = array_values(array_filter($items, static fn($i) => !empty($i['title'])));

        $parts = [];
        if (!empty(trim((string) ($config['title'] ?? '')))) {
            $t = htmlspecialchars(trim((string) $config['title']), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-timeline__title" style="color:' . $palette['titleColor'] . ';">'
                . $t . '</div>';
        }

        $parts[] = '<div class="slms-timeline__list">';
        $count = count($items);
        foreach ($items as $idx => $item) {
            $islast = $idx === $count - 1;
            $date  = htmlspecialchars((string) ($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $desc  = htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8');
            $connector = $islast ? '' : '<div class="slms-timeline__connector"'
                . ' style="background:' . $palette['borderColor'] . ';"></div>';
            $parts[] = '<div class="slms-timeline__item">'
                . '<div class="slms-timeline__marker">'
                . '<div class="slms-timeline__dot" style="background:' . $palette['iconBg'] . ';"></div>'
                . $connector
                . '</div>'
                . '<div class="slms-timeline__body"'
                . ' style="background:' . $palette['bg'] . ';border-color:' . $palette['borderColor'] . ';">'
                . ($date !== ''
                    ? '<div class="slms-timeline__date"'
                    . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
                    . $date . '</div>'
                    : '')
                . '<div class="slms-timeline__event-title" style="color:' . $palette['valuColor'] . ';">'
                . $title . '</div>'
                . ($desc !== ''
                    ? '<div class="slms-timeline__event-desc" style="color:' . $palette['labelColor'] . ';">'
                    . $desc . '</div>'
                    : '')
                . '</div></div>';
        }
        $parts[] = '</div>';

        return ['theme' => $theme, 'content' => implode('', $parts)];
    }

    /**
     * Builds the view model for block_infographic_comparison.
     *
     * @param array $config Canonical block config.
     * @return array Mustache context with pre-rendered content HTML.
     */
    private static function infographic_comparison_context(array $config): array {
        $theme = $config['theme'] ?? 'blue';
        $palette = self::palette($theme);
        $items = is_array($config['items'] ?? null) ? $config['items'] : [];
        $items = array_filter($items, static fn($i) => !empty($i['label']));
        $col1 = htmlspecialchars((string) ($config['col1'] ?? 'A'), ENT_QUOTES, 'UTF-8');
        $col2 = htmlspecialchars((string) ($config['col2'] ?? 'B'), ENT_QUOTES, 'UTF-8');

        $parts = [];
        if (!empty(trim((string) ($config['title'] ?? '')))) {
            $t = htmlspecialchars(trim((string) $config['title']), ENT_QUOTES, 'UTF-8');
            $parts[] = '<div class="slms-comparison__title" style="color:' . $palette['titleColor'] . ';">'
                . $t . '</div>';
        }

        $parts[] = '<div class="slms-comparison__table">';
        $parts[] = '<div class="slms-comparison__header">'
            . '<div class="slms-comparison__header-label"></div>'
            . '<div class="slms-comparison__header-col"'
            . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
            . $col1 . '</div>'
            . '<div class="slms-comparison__header-col"'
            . ' style="background:' . $palette['iconBg'] . ';color:' . $palette['iconColor'] . ';">'
            . $col2 . '</div>'
            . '</div>';
        foreach ($items as $item) {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $yes1 = !empty($item['col1']);
            $yes2 = !empty($item['col2']);
            $parts[] = '<div class="slms-comparison__row"'
                . ' style="border-color:' . $palette['borderColor'] . ';background:' . $palette['bg'] . ';">'
                . '<div class="slms-comparison__row-label" style="color:' . $palette['labelColor'] . ';">'
                . $label . '</div>'
                . '<div class="slms-comparison__row-cell">'
                . ($yes1
                    ? '<span class="slms-comparison__yes" role="img" aria-label="&#x2713;"'
                    . ' style="color:' . $palette['iconColor'] . ';">&#x2713;</span>'
                    : '<span class="slms-comparison__no" role="img" aria-label="&#x2717;">&#x2717;</span>')
                . '</div>'
                . '<div class="slms-comparison__row-cell">'
                . ($yes2
                    ? '<span class="slms-comparison__yes" role="img" aria-label="&#x2713;"'
                    . ' style="color:' . $palette['iconColor'] . ';">&#x2713;</span>'
                    : '<span class="slms-comparison__no" role="img" aria-label="&#x2717;">&#x2717;</span>')
                . '</div>'
                . '</div>';
        }
        $parts[] = '</div>';

        return ['theme' => $theme, 'content' => implode('', $parts)];
    }

    // -------------------------------------------------------------------------
    // Wrap: inject editor contract attributes into the block root.
    // -------------------------------------------------------------------------.

    /**
     * Injects data-slms-block-type, data-slms-state and (for non-table blocks)
     * mceNonEditable into the root element of the rendered HTML.
     *
     * @param string $html Rendered block HTML.
     * @param string $type Block registry id.
     * @param array $config Canonical block config (source of the state chip).
     * @return string Wrapped HTML.
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

    // -------------------------------------------------------------------------
    // Mind map block.
    // --------------------------------------------------------------------------.

    /**
     * Builds the view model for block_mindmap.
     *
     * Generates the SVG diagram in PHP, mirroring the buildSvg() function
     * from tiny_studiolms/amd/src/blocks/mindmap.js.
     *
     * @param array $config Canonical block config (topic, theme, branches).
     * @return array Mustache context with svgContent and textAlt.
     */
    private static function mindmap_context(array $config): array {
        $colors   = self::mindmap_colors();
        $theme    = $config['theme'] ?? 'blue';
        $c        = $colors[$theme] ?? $colors['blue'];
        $topic    = clean_param((string)($config['topic'] ?? 'Topic'), PARAM_TEXT);
        $branches = is_array($config['branches'] ?? null) ? array_slice($config['branches'], 0, 8) : [];

        $svgcontent = self::build_mindmap_svg($topic, $branches, $c);

        $branchparts = [];
        foreach ($branches as $b) {
            $label = (string)($b['label'] ?? '');
            $kids  = is_array($b['children'] ?? null)
                ? array_filter(array_map('strval', $b['children']))
                : [];
            $branchparts[] = !empty($kids) ? $label . ' (' . implode(', ', $kids) . ')' : $label;
        }
        $textalt = !empty($branchparts) ? $topic . ': ' . implode('; ', $branchparts) : $topic;

        return ['svgContent' => $svgcontent, 'textAlt' => $textalt];
    }

    /**
     * Returns the colour palette definitions for the mind map block.
     *
     * Mirrors the COLORS constant in tiny_studiolms/amd/src/blocks/mindmap.js.
     *
     * @return array Palette definitions indexed by theme name.
     */
    private static function mindmap_colors(): array {
        return [
            'blue' => [
                'bg' => '#f0f9ff',
                'centerFill' => '#1e3a8a', 'centerText' => '#ffffff',
                'branchFills' => ['#2563eb', '#1d4ed8', '#3b82f6', '#1e40af', '#60a5fa', '#1e40af'],
                'branchText' => '#ffffff',
                'childFill' => '#dbeafe', 'childText' => '#1e40af',
                'lineColor' => '#93c5fd',
            ],
            'green' => [
                'bg' => '#f0fdf4',
                'centerFill' => '#14532d', 'centerText' => '#ffffff',
                'branchFills' => ['#16a34a', '#15803d', '#22c55e', '#166534', '#4ade80', '#15803d'],
                'branchText' => '#ffffff',
                'childFill' => '#dcfce7', 'childText' => '#14532d',
                'lineColor' => '#86efac',
            ],
            'purple' => [
                'bg' => '#faf5ff',
                'centerFill' => '#581c87', 'centerText' => '#ffffff',
                'branchFills' => ['#9333ea', '#7c3aed', '#a855f7', '#6d28d9', '#c084fc', '#7c3aed'],
                'branchText' => '#ffffff',
                'childFill' => '#f3e8ff', 'childText' => '#581c87',
                'lineColor' => '#c084fc',
            ],
            'orange' => [
                'bg' => '#fff7ed',
                'centerFill' => '#7c2d12', 'centerText' => '#ffffff',
                'branchFills' => ['#ea580c', '#f97316', '#c2410c', '#fb923c', '#f97316', '#c2410c'],
                'branchText' => '#ffffff',
                'childFill' => '#ffedd5', 'childText' => '#7c2d12',
                'lineColor' => '#fdba74',
            ],
            'red' => [
                'bg' => '#fff1f2',
                'centerFill' => '#881337', 'centerText' => '#ffffff',
                'branchFills' => ['#be123c', '#e11d48', '#f43f5e', '#9f1239', '#fb7185', '#be123c'],
                'branchText' => '#ffffff',
                'childFill' => '#ffe4e6', 'childText' => '#881337',
                'lineColor' => '#fda4af',
            ],
            'black' => [
                'bg' => '#f8fafc',
                'centerFill' => '#0f172a', 'centerText' => '#f1f5f9',
                'branchFills' => ['#1e293b', '#334155', '#475569', '#1e293b', '#334155', '#475569'],
                'branchText' => '#f1f5f9',
                'childFill' => '#e2e8f0', 'childText' => '#0f172a',
                'lineColor' => '#94a3b8',
            ],
        ];
    }

    /**
     * Builds the SVG for a mind map, mirroring buildSvg() from mindmap.js.
     *
     * @param string $topic Central node label.
     * @param array $branches Array of {label, children: string[]}.
     * @param array $c Colour palette from mindmap_colors().
     * @return string Complete SVG markup string.
     */
    private static function build_mindmap_svg(string $topic, array $branches, array $c): string {
        $w  = 900;
        $h  = 620;
        $cx = (float)($w / 2);
        $cy = (float)($h / 2);
        $n  = count($branches);
        if ($n === 0) {
            return '';
        }

        $r1 = 145;
        if ($n <= 4) {
            $r1 = 180;
        } else if ($n <= 6) {
            $r1 = 162;
        }
        $r2           = 124;
        $manybranches = $n >= 7;
        $bw           = $manybranches ? 110 : 128;
        $fontbranch   = $manybranches ? 12 : 13;
        $bh1          = $manybranches ? 38 : 42;
        $bh2          = $manybranches ? 48 : 54;
        $kw           = 112;
        $fontchild    = 12;
        $kh1          = 34;
        $kh2          = 44;
        $linemax      = $manybranches ? 14 : 15;
        $childspacing = 122;

        $parts   = [];
        $parts[] = '<svg xmlns="http://www.w3.org/2000/svg"'
            . ' viewBox="-10 -10 ' . ($w + 20) . ' ' . ($h + 20) . '"'
            . ' style="width:100%;max-width:' . $w . 'px;height:auto;display:block;"'
            . ' aria-hidden="true">';
        $parts[] = '<rect x="-10" y="-10" width="' . ($w + 20) . '" height="' . ($h + 20) . '"'
            . ' rx="10" fill="' . $c['bg'] . '"/>';

        $branchpos = [];
        foreach ($branches as $i => $b) {
            $angle       = ($i / $n) * M_PI * 2 - M_PI / 2;
            $branchpos[] = [
                'x'        => $cx + $r1 * cos($angle),
                'y'        => $cy + $r1 * sin($angle),
                'angle'    => $angle,
                'b'        => $b,
                'coloridx' => $i % count($c['branchFills']),
            ];
        }

        // Connector lines drawn first (below nodes).
        foreach ($branchpos as $bp) {
            [$bx, $by, $angle, $b, $coloridx] = [
                $bp['x'], $bp['y'], $bp['angle'], $bp['b'], $bp['coloridx'],
            ];
            $bfill   = $c['branchFills'][$coloridx];
            $cpos    = self::svg_child_positions($bx, $by, $angle, $b['children'] ?? [], $r2, $childspacing);
            $mx      = ($cx + $bx) / 2;
            $parts[] = '<path d="M' . $cx . ',' . $cy . ' Q' . round($mx, 1) . ',' . $cy
                . ' ' . round($bx, 1) . ',' . round($by, 1) . '"'
                . ' stroke="' . $c['lineColor'] . '" stroke-width="2.5" fill="none" opacity="0.8"/>';
            foreach ($cpos as $cp) {
                $parts[] = '<line x1="' . round($bx, 1) . '" y1="' . round($by, 1) . '"'
                    . ' x2="' . round($cp['x'], 1) . '" y2="' . round($cp['y'], 1) . '"'
                    . ' stroke="' . $bfill . '" stroke-width="1.5" opacity="0.5"/>';
            }
        }

        // Branch and child nodes drawn above connectors.
        foreach ($branchpos as $bp) {
            [$bx, $by, $angle, $b, $coloridx] = [
                $bp['x'], $bp['y'], $bp['angle'], $bp['b'], $bp['coloridx'],
            ];
            $bfill   = $c['branchFills'][$coloridx];
            $cpos    = self::svg_child_positions($bx, $by, $angle, $b['children'] ?? [], $r2, $childspacing);
            $blines  = self::svg_wrap_text((string)($b['label'] ?? ''), $linemax);
            $bh      = count($blines) > 1 ? $bh2 : $bh1;
            $parts[] = '<rect x="' . round($bx - $bw / 2, 1) . '" y="' . round($by - $bh / 2, 1) . '"'
                . ' width="' . $bw . '" height="' . $bh . '" rx="' . round($bh / 2) . '"'
                . ' fill="' . $bfill . '"/>';
            if (count($blines) === 1) {
                $parts[] = '<text x="' . round($bx, 1) . '" y="' . round($by + 5, 1) . '"'
                    . ' text-anchor="middle" fill="' . $c['branchText'] . '"'
                    . ' font-size="' . $fontbranch . '" font-weight="600"'
                    . ' font-family="system-ui,sans-serif">'
                    . self::svg_esc($blines[0]) . '</text>';
            } else {
                $yoff    = $fontbranch <= 12 ? 6 : 7;
                $parts[] = '<text x="' . round($bx, 1) . '" y="' . round($by - $yoff, 1) . '"'
                    . ' text-anchor="middle" fill="' . $c['branchText'] . '"'
                    . ' font-size="' . $fontbranch . '" font-weight="600"'
                    . ' font-family="system-ui,sans-serif">'
                    . self::svg_esc($blines[0]) . '</text>';
                $parts[] = '<text x="' . round($bx, 1) . '" y="' . round($by + $yoff + 2, 1) . '"'
                    . ' text-anchor="middle" fill="' . $c['branchText'] . '"'
                    . ' font-size="' . $fontbranch . '" font-weight="600"'
                    . ' font-family="system-ui,sans-serif">'
                    . self::svg_esc($blines[1]) . '</text>';
            }
            foreach ($cpos as $cp) {
                $klines  = self::svg_wrap_text((string)$cp['label'], 14);
                $kh      = count($klines) > 1 ? $kh2 : $kh1;
                $kx      = $cp['x'];
                $ky      = $cp['y'];
                $parts[] = '<rect x="' . round($kx - $kw / 2, 1) . '" y="' . round($ky - $kh / 2, 1) . '"'
                    . ' width="' . $kw . '" height="' . $kh . '" rx="' . round($kh / 2) . '"'
                    . ' fill="' . $c['childFill'] . '" stroke="' . $bfill . '" stroke-width="1.5"/>';
                if (count($klines) === 1) {
                    $parts[] = '<text x="' . round($kx, 1) . '" y="' . round($ky + 5, 1) . '"'
                        . ' text-anchor="middle" fill="' . $c['childText'] . '"'
                        . ' font-size="' . $fontchild . '" font-family="system-ui,sans-serif">'
                        . self::svg_esc($klines[0]) . '</text>';
                } else {
                    $parts[] = '<text x="' . round($kx, 1) . '" y="' . round($ky - 5, 1) . '"'
                        . ' text-anchor="middle" fill="' . $c['childText'] . '"'
                        . ' font-size="' . $fontchild . '" font-family="system-ui,sans-serif">'
                        . self::svg_esc($klines[0]) . '</text>';
                    $parts[] = '<text x="' . round($kx, 1) . '" y="' . round($ky + 9, 1) . '"'
                        . ' text-anchor="middle" fill="' . $c['childText'] . '"'
                        . ' font-size="' . $fontchild . '" font-family="system-ui,sans-serif">'
                        . self::svg_esc($klines[1]) . '</text>';
                }
            }
        }

        // Center node — drawn last (always on top).
        $cw      = 158;
        $ch      = 60;
        $parts[] = '<rect x="' . round($cx - $cw / 2 + 2, 1) . '" y="' . round($cy - $ch / 2 + 3, 1) . '"'
            . ' width="' . $cw . '" height="' . $ch . '" rx="' . ($ch / 2) . '"'
            . ' fill="rgba(0,0,0,0.15)"/>';
        $parts[] = '<rect x="' . round($cx - $cw / 2, 1) . '" y="' . round($cy - $ch / 2, 1) . '"'
            . ' width="' . $cw . '" height="' . $ch . '" rx="' . ($ch / 2) . '"'
            . ' fill="' . $c['centerFill'] . '"/>';

        $maxtopic = 14;
        if (mb_strlen($topic) <= $maxtopic) {
            $parts[] = '<text x="' . $cx . '" y="' . round($cy + 6, 1) . '"'
                . ' text-anchor="middle" fill="' . $c['centerText'] . '"'
                . ' font-size="15" font-weight="700" font-family="system-ui,sans-serif">'
                . self::svg_esc($topic) . '</text>';
        } else {
            $mid    = (int)floor(mb_strlen($topic) / 2);
            $rawspl = mb_strrpos(mb_substr($topic, 0, $mid + 6), ' ');
            $split  = ($rawspl === false || $rawspl < 3) ? $maxtopic - 1 : $rawspl;
            $l1     = mb_substr(mb_substr($topic, 0, $split), 0, $maxtopic);
            $l2     = mb_substr(trim(mb_substr($topic, $split)), 0, $maxtopic);
            $parts[] = '<text x="' . $cx . '" y="' . round($cy - 6, 1) . '"'
                . ' text-anchor="middle" fill="' . $c['centerText'] . '"'
                . ' font-size="14" font-weight="700" font-family="system-ui,sans-serif">'
                . self::svg_esc($l1) . '</text>';
            $parts[] = '<text x="' . $cx . '" y="' . round($cy + 12, 1) . '"'
                . ' text-anchor="middle" fill="' . $c['centerText'] . '"'
                . ' font-size="14" font-weight="700" font-family="system-ui,sans-serif">'
                . self::svg_esc($l2) . '</text>';
        }

        $parts[] = '</svg>';
        return implode('', $parts);
    }

    /**
     * Computes child node positions perpendicular to the branch direction.
     *
     * Mirrors the childPositions() helper in tiny_studiolms/amd/src/blocks/mindmap.js.
     *
     * @param float $bx Branch node X coordinate.
     * @param float $by Branch node Y coordinate.
     * @param float $angle Branch angle in radians.
     * @param array $children Child label strings.
     * @param int $r2 Distance from branch to child row centre.
     * @param int $childspacing Spacing in px between sibling child nodes.
     * @return array Child position records, each with keys x, y, label.
     */
    private static function svg_child_positions(
        float $bx,
        float $by,
        float $angle,
        array $children,
        int $r2,
        int $childspacing
    ): array {
        $m = count($children);
        if ($m === 0) {
            return [];
        }
        $perpangle = $angle + M_PI / 2;
        $outx      = $r2 * cos($angle);
        $outy      = $r2 * sin($angle);
        $px        = cos($perpangle);
        $py        = sin($perpangle);
        $pos       = [];
        foreach ($children as $j => $child) {
            $offset = ($j - ($m - 1) / 2) * $childspacing;
            $pos[]  = [
                'x'     => $bx + $outx + $offset * $px,
                'y'     => $by + $outy + $offset * $py,
                'label' => (string)$child,
            ];
        }
        return $pos;
    }

    /**
     * Splits a string into at most two lines for SVG text rendering.
     *
     * Mirrors the wrapText() helper in tiny_studiolms/amd/src/blocks/mindmap.js.
     *
     * @param string $text Text to wrap.
     * @param int $linemax Maximum characters per line before attempting a split.
     * @return string[] Array of 1 or 2 lines.
     */
    private static function svg_wrap_text(string $text, int $linemax): array {
        if (mb_strlen($text) <= $linemax) {
            return [$text];
        }
        $mid   = (int)ceil(mb_strlen($text) / 2);
        $rawsp = mb_strrpos(mb_substr($text, 0, $mid + 5), ' ');
        if ($rawsp === false || $rawsp < 1) {
            $alt = mb_strpos($text, ' ', max(0, $mid - 5));
            if ($alt === false || $alt < 1) {
                return [mb_substr($text, 0, $linemax), mb_substr($text, $linemax, $linemax * 2)];
            }
            $rawsp = $alt;
        }
        $l1 = trim(mb_substr($text, 0, $rawsp));
        $l2 = trim(mb_substr($text, $rawsp));
        if (mb_strlen($l2) > $linemax + 3) {
            $l2 = mb_substr($l2, 0, $linemax + 2) . '…';
        }
        return [$l1, $l2];
    }

    /**
     * Escapes a string for safe embedding inside an SVG text node.
     *
     * @param string $s Raw string.
     * @return string XML-escaped string.
     */
    private static function svg_esc(string $s): string {
        return htmlspecialchars($s, ENT_XML1, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Helpers — shared across infographic builders.
    // -------------------------------------------------------------------------.

    /**
     * Returns the colour palette for the given theme name.
     *
     * @param string $theme One of blue, green, purple, orange, red, black.
     * @return array Palette with keys bg, iconBg, iconColor, valuColor, labelColor, titleColor, borderColor.
     */
    private static function palette(string $theme): array {
        return self::THEMES[$theme] ?? self::THEMES['blue'];
    }

    /**
     * Normalises a Font Awesome icon class string to the FA6 format.
     *
     * Mirrors the JavaScript normaliseIcon() in infographic_shared.js.
     *
     * @param string $raw Raw icon class (e.g. 'fa-users', 'fas fa-users', 'fa-solid fa-users').
     * @return string Normalised class, or 'fa-solid fa-circle-info' as fallback.
     */
    private static function normalise_icon(string $raw): string {
        $s = trim($raw);
        if ($s === '') {
            return 'fa-solid fa-circle-info';
        }
        if (preg_match('/^(fa-solid|fa-regular|fa-brands|fas|far|fab)\s/', $s)) {
            return $s;
        }
        return 'fa-solid ' . (str_starts_with($s, 'fa-') ? $s : 'fa-' . $s);
    }

    /**
     * Returns an accessible icon HTML element from an FA6 class string.
     *
     * @param string $iconclass Normalised FA6 class string.
     * @return string HTML string.
     */
    private static function icon_html(string $iconclass): string {
        $cls = htmlspecialchars($iconclass, ENT_QUOTES, 'UTF-8');
        return '<i class="' . $cls . '" aria-hidden="true"></i>';
    }
}
