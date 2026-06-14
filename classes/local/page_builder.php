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
 * Builds rich page content using the StudioLMS visual blocks.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Renders a page as a pre-training callout plus AI-planned visual blocks.
 *
 * The AI first plans the page: it chooses a preset from the tiny_studiolms catalog
 * when one fits the context (hybrid strategy), or it generates custom blocks
 * (heading/callout/card/accordion). All rendered blocks carry the editor contract
 * attributes so they open for re-editing in the tiny_studiolms plugin.
 */
class page_builder {
    /** @var int Maximum number of glossary terms shown in the pre-training block. */
    private const PRETRAINING_TERMS = 6;

    /**
     * Renders the full HTML for a course page.
     *
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $pagetitle The page title.
     * @param array $glossaryterms Glossary terms ([term, definition]) for the pre-training block.
     * @param bool $degraded Set to true when the AI body could not be generated.
     * @return string The page HTML.
     */
    public static function render(
        string $theme,
        string $sectiontitle,
        string $pagetitle,
        array $glossaryterms,
        bool &$degraded = false
    ): string {
        $html = '';

        if (!empty($glossaryterms)) {
            $html .= self::pretraining($glossaryterms);
        }

        $body = self::generate_body($theme, $sectiontitle, $pagetitle);
        if ($body === '') {
            $degraded = true;
        }
        $html .= $body;

        if (trim(html_to_text($html)) === '') {
            $html = \html_writer::tag('p', s($pagetitle));
        }
        return $html;
    }

    /**
     * Renders the first page of a course using the "Plano de Disciplina" preset.
     *
     * Forces the preset regardless of AI planning — the AI only generates the fill
     * values for content placeholders (topics, objectives). Structural placeholders
     * (dates, chapter titles) are left for the teacher to complete manually.
     *
     * @param string $theme The course theme.
     * @param string $pagetitle The page title (used as the course name).
     * @param array $glossaryterms Glossary terms for the pre-training block.
     * @param bool $degraded Set to true when the AI body could not be generated.
     * @return string The page HTML.
     */
    public static function render_course_intro(
        string $theme,
        string $pagetitle,
        array $glossaryterms,
        bool &$degraded = false
    ): string {
        $html = '';
        if (!empty($glossaryterms)) {
            $html .= self::pretraining($glossaryterms);
        }
        $body = self::build_course_plan($theme, $pagetitle);
        if ($body === '') {
            $degraded = true;
        }
        $html .= $body;
        if (trim(html_to_text($html)) === '') {
            $html = \html_writer::tag('p', s($pagetitle));
        }
        return $html;
    }

    // -----------------------------------------------------------------------
    // Pre-training callout.
    // -----------------------------------------------------------------------.

    /**
     * Builds the "Key concepts" pre-training callout from the glossary terms.
     *
     * @param array $glossaryterms Glossary terms ([term, definition]).
     * @return string The rendered callout HTML.
     */
    private static function pretraining(array $glossaryterms): string {
        $items = '';
        foreach (array_slice($glossaryterms, 0, self::PRETRAINING_TERMS) as $term) {
            $items .= \html_writer::tag(
                'li',
                \html_writer::tag('strong', s($term['term'])) . ' — ' . s($term['definition'])
            );
        }
        $content = \html_writer::tag(
            'p',
            \html_writer::tag('strong', get_string('pretraining_title', 'local_studiolms'))
        ) . \html_writer::tag('ul', $items);

        return block_builder::render('callout', [
            'backgroundColor' => '#eef2ff',
            'borderLeftWidth' => 4,
            'borderColor' => '#6366f1',
            'borderRadius' => 6,
            'hoverEffect' => 'none',
            'icon' => '💡',
            'textColor' => '#3730a3',
            'contentHtml' => $content,
        ]);
    }

    // -----------------------------------------------------------------------
    // AI-driven body: plan to preset or blocks.
    // -----------------------------------------------------------------------.

    /**
     * Generates the page body by asking the AI to plan a preset or custom blocks.
     *
     * Returns an empty string (degraded) when the AI is unavailable.
     *
     * @param string $theme Course theme.
     * @param string $sectiontitle Section title.
     * @param string $pagetitle Page title.
     * @return string Rendered body HTML.
     */
    private static function generate_body(
        string $theme,
        string $sectiontitle,
        string $pagetitle
    ): string {
        try {
            return self::plan_and_build($theme, $sectiontitle, $pagetitle);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Loads and renders the "Plano de Disciplina" preset with AI-generated fill values.
     *
     * Uses the course name and fixed structural values as a base fill, then enriches
     * with AI-generated topics and objectives. Falls back gracefully when the preset
     * is unavailable or the AI cannot be reached.
     *
     * @param string $theme Course theme.
     * @param string $pagetitle Page title (used as the course name placeholder).
     * @return string Rendered preset HTML, or empty string on failure.
     */
    private static function build_course_plan(string $theme, string $pagetitle): string {
        try {
            $lang   = current_language();
            $preset = preset_loader::find('Plano de Disciplina', $lang);
            if ($preset === null) {
                return '';
            }
            $fill = [
                '[Nome da Disciplina]' => $pagetitle,
                '[N]'                  => '4',
                '[X]'                  => '10',
                '[Y]'                  => '60',
            ];
            try {
                $aifill = self::ai_fill_for_course_plan($theme, $pagetitle);
                if (!empty($aifill)) {
                    $fill = array_merge($fill, $aifill);
                }
            } catch (\Throwable $e) {
                debugging('StudioLMS: course plan fill skipped — ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            $preset = self::fill_preset($preset, $fill);
            return preset_loader::render($preset);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Asks the AI to generate educational content fill values for the course plan preset.
     *
     * Returns a map of [Placeholder] => replacement for topics and learning objectives.
     * Structural placeholders (dates, chapter titles) are excluded; the teacher fills them.
     *
     * @param string $theme Course theme.
     * @param string $pagetitle Page title.
     * @return array Map of placeholder string => replacement text.
     */
    private static function ai_fill_for_course_plan(string $theme, string $pagetitle): array {
        $lang = current_language();
        $keys = '[Tópico 1], [Tópico 2], [Tópico 3], [Tópico 4], [Tópico 5], [Tópico 6],'
            . ' [descreva o objetivo geral da disciplina], [resultado esperado],'
            . ' [Objetivo específico 1], [Objetivo específico 2], [Objetivo específico 3],'
            . ' [Objetivo específico 4], [Objetivo específico 5], [Objetivo específico 6]';
        $system = 'You are a course syllabus generator. Given a course theme and title,'
            . ' generate short fill values for a course plan template.'
            . ' Return ONLY a valid JSON object mapping each key (with square brackets)'
            . ' to its replacement value (plain text, not HTML, max 80 chars each).'
            . ' Keys to fill: ' . $keys . '.'
            . ' Write all text in the language identified by the code: ' . $lang . '.';
        $user    = "Course theme: {$theme}\nCourse title: {$pagetitle}";
        $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Asks the AI to plan the page, then builds it.
     *
     * @param string $theme Course theme.
     * @param string $sectiontitle Section title.
     * @param string $pagetitle Page title.
     * @return string Rendered body HTML.
     */
    private static function plan_and_build(
        string $theme,
        string $sectiontitle,
        string $pagetitle
    ): string {
        $plan = self::plan_page($theme, $sectiontitle, $pagetitle);
        if ($plan === null) {
            return '';
        }

        if (($plan['strategy'] ?? '') === 'preset') {
            $html = self::build_from_preset($plan);
            if ($html !== '') {
                return $html;
            }
        }

        if (!empty($plan['blocks']) && is_array($plan['blocks'])) {
            $html = '';
            foreach ($plan['blocks'] as $block) {
                $html .= self::render_block($block);
            }
            $html .= self::generate_mindmap_block($pagetitle);
            return $html;
        }

        return '';
    }

    /**
     * Asks the AI to decide: use a preset or generate custom blocks.
     *
     * Returns the decoded plan array, or null on failure.
     *
     * @param string $theme Course theme.
     * @param string $sectiontitle Section title.
     * @param string $pagetitle Page title.
     * @return array|null Decoded plan, or null when AI is unavailable.
     */
    private static function plan_page(
        string $theme,
        string $sectiontitle,
        string $pagetitle
    ): ?array {
        $lang = current_language();
        $catalog = preset_loader::catalog_for_prompt($lang);
        $catalogjson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        $presetschema = '{"strategy":"preset","preset_name":"<name from catalog>","fill":{"[Placeholder]":"value"}}';
        $blockschema  = '{"strategy":"blocks","blocks":['
            . '{"type":"heading","text":"..."},'
            . '{"type":"callout","html":"<p>...</p>"},'
            . '{"type":"card","content":"<h4>...</h4><p>...</p>"},'
            . '{"type":"accordion","title":"...","content":"<p>...</p>"}'
            . ']}';

        $system = 'You are a course page planner. Given a page topic and a catalog of page templates,'
            . ' decide whether to apply a template (preset) or generate custom visual blocks.'
            . ' Return ONLY a valid JSON object with no markdown. Use one of these two schemas:'
            . ' PRESET: ' . $presetschema
            . ' BLOCKS: ' . $blockschema
            . ' Choose PRESET when the page context fits a catalog entry well.'
            . ' Choose BLOCKS otherwise, using types: heading, callout, card, accordion.'
            . ' The "fill" map replaces [Placeholder] tokens in the preset (e.g. "[Course name]" -> value).'
            . ' Write all generated text in the language identified by the code: ' . $lang . '.';

        $user = "Course theme: {$theme}\nSection: {$sectiontitle}\nPage title: {$pagetitle}"
            . "\nAvailable presets: {$catalogjson}";

        $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        if ($decoded === null || empty($decoded['strategy'])) {
            return null;
        }
        return $decoded;
    }

    /**
     * Loads and renders a preset from the AI plan, applying placeholder fill values.
     *
     * @param array $plan AI plan with preset_name and fill keys.
     * @return string Rendered HTML, or empty string when the preset is not found.
     */
    private static function build_from_preset(array $plan): string {
        $preset = preset_loader::find((string) ($plan['preset_name'] ?? ''));
        if ($preset === null) {
            return '';
        }

        $fill = is_array($plan['fill'] ?? null) ? $plan['fill'] : [];
        if (!empty($fill)) {
            $preset = self::fill_preset($preset, $fill);
        }

        return preset_loader::render($preset);
    }

    /**
     * Recursively replaces placeholder tokens in all string values of a preset.
     *
     * @param array $preset Preset definition.
     * @param array $fill Map of [Placeholder] => replacement text.
     * @return array Preset with placeholders replaced.
     */
    private static function fill_preset(array $preset, array $fill): array {
        $search  = array_keys($fill);
        $replace = array_values($fill);
        return self::fill_recursive($preset, $search, $replace);
    }

    /**
     * Recursively replaces strings inside an array.
     *
     * @param array $data The data to process.
     * @param array $search Strings to search for.
     * @param array $replace Replacement strings.
     * @return array Data with replacements applied.
     */
    private static function fill_recursive(array $data, array $search, array $replace): array {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = str_replace($search, $replace, $v);
            } else if (is_array($v)) {
                $data[$k] = self::fill_recursive($v, $search, $replace);
            }
        }
        return $data;
    }

    // -----------------------------------------------------------------------
    // Rich visual blocks.
    // ------------------------------------------------------------------------.

    /**
     * Generates a mind map block as a visual summary for a content page.
     *
     * Calls the tiny_studiolms AI generator directly for the mind map structure
     * (topic + branches). Silently returns empty string on AI failure so the
     * page degrades gracefully without losing the text blocks.
     *
     * @param string $topic Page topic used as the mind map central node and prompt.
     * @return string Rendered mindmap block HTML, or empty string on failure.
     */
    private static function generate_mindmap_block(string $topic): string {
        try {
            $data     = \tiny_studiolms\ai\generator::generate_mindmap($topic);
            $branches = json_decode((string)($data['branches'] ?? '[]'), true);
            if (!is_array($branches) || empty($branches)) {
                return '';
            }
            return block_builder::render('mindmap', [
                'topic'    => $data['topic'] ?? $topic,
                'theme'    => 'blue',
                'branches' => $branches,
            ]);
        } catch (\Throwable $e) {
            return '';
        }
    }

    // -----------------------------------------------------------------------
    // Custom block rendering (blocks strategy).
    // -----------------------------------------------------------------------.

    /**
     * Renders a single AI-generated block as editable StudioLMS HTML.
     *
     * @param array $block Block definition from the AI plan.
     * @return string The rendered block HTML.
     */
    private static function render_block(array $block): string {
        switch ($block['type'] ?? '') {
            case 'heading':
                if (empty($block['text'])) {
                    return '';
                }
                return block_builder::render('stylizedHeading', [
                    'text' => clean_param($block['text'], PARAM_TEXT),
                    'level' => 'h3',
                    'icon' => '',
                    'bgColor' => '#e3f2fd',
                    'textColor' => '#0d47a1',
                ]);
            case 'callout':
                if (empty($block['html'])) {
                    return '';
                }
                return block_builder::render('callout', [
                    'backgroundColor' => '#fef9c3',
                    'borderLeftWidth' => 4,
                    'borderColor' => '#eab308',
                    'borderRadius' => 6,
                    'hoverEffect' => 'none',
                    'icon' => '📌',
                    'textColor' => '#854d0e',
                    'contentHtml' => clean_text($block['html'], FORMAT_HTML),
                ]);
            case 'card':
                if (empty($block['content'])) {
                    return '';
                }
                return block_builder::render('advancedCard', [
                    'bg' => '#ffffff',
                    'text' => '#212529',
                    'border' => '#0d47a1',
                    'radius' => 8,
                    'shadow' => 'sm',
                    'mediaType' => 'none',
                    'mediaUrl' => '',
                    'layout' => 'vertical',
                    'btnText' => '',
                    'btnUrl' => '#',
                    'btnBg' => '#0d47a1',
                    'btnTextCol' => '#ffffff',
                    'btnAlign' => 'left',
                    'hoverEffect' => 'none',
                    'content' => clean_text($block['content'], FORMAT_HTML),
                ]);
            case 'accordion':
                if (empty($block['title'])) {
                    return '';
                }
                return block_builder::render('accordion', [
                    'state' => 'closed',
                    'bg' => '#ffffff',
                    'color' => '#3b82f6',
                    'icon' => '▼ / ▲',
                    'openSound' => 'none',
                    'hoverEffect' => 'none',
                    'title' => clean_param($block['title'], PARAM_TEXT),
                    'content' => clean_text($block['content'] ?? '', FORMAT_HTML),
                ]);
            default:
                return empty($block['html']) ? '' : clean_text($block['html'], FORMAT_HTML);
        }
    }
}
