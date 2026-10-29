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
 * (heading/text/callout/card/accordion). All rendered blocks carry the editor contract
 * attributes so they open for re-editing in the tiny_studiolms plugin.
 */
class page_builder {
    /** @var int Maximum number of glossary terms shown in the pre-training block. */
    private const PRETRAINING_TERMS = 6;

    /** @var string[] Block config keys the block templates print as HTML (triple mustache). */
    private const MARKUP_KEYS = ['containerTitle', 'content', 'contentHtml', 'cellData', 'slots', 'text'];

    /**
     * Renders the full HTML for a course page.
     *
     * @param \context $context Course context the page is generated for (scopes the editor's AI call).
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $pagetitle The page title.
     * @param array $glossaryterms Glossary terms ([term, definition]) for the pre-training block.
     * @param string $reference Optional reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general' for no specific level.
     * @param array $objectives Course learning objectives from the outline.
     * @param bool $degraded Set to true when the AI body could not be generated.
     * @param string $chosenpreset Set to the preset name, 'blocks', or '' (degraded).
     * @return string The page HTML.
     */
    public static function render(
        \context $context,
        string $theme,
        string $sectiontitle,
        string $pagetitle,
        array $glossaryterms,
        string $reference = '',
        string $bloom = 'general',
        array $objectives = [],
        bool &$degraded = false,
        string &$chosenpreset = ''
    ): string {
        $html = '';

        $body = self::generate_body(
            $context,
            $theme,
            $sectiontitle,
            $pagetitle,
            $reference,
            $bloom,
            $objectives,
            $chosenpreset
        );
        if ($body === '') {
            $degraded = true;
        }
        // The key terms come before the lesson, but only those the lesson uses: the same list on every page of
        // the course says nothing about the page that holds it.
        $used = self::terms_used($glossaryterms, $body);
        if (!empty($used)) {
            $html .= self::pretraining($used);
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
     * Picks the glossary terms a page body mentions.
     *
     * A term written with its acronym ("Two-factor authentication (2FA)") counts when the page uses either one.
     *
     * @param array $glossaryterms Glossary terms ([term, definition]).
     * @param string $body The HTML of the page body.
     * @return array The terms the body uses, in glossary order.
     */
    private static function terms_used(array $glossaryterms, string $body): array {
        $text = \core_text::strtolower(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $used = [];
        foreach ($glossaryterms as $term) {
            $name = trim((string) ($term['term'] ?? ''));
            $names = [$name];
            if (preg_match('/^(.*?)\s*\(([^)]+)\)$/u', $name, $parts)) {
                $names = [trim($parts[1]), trim($parts[2])];
            }
            foreach ($names as $candidate) {
                $needle = \core_text::strtolower($candidate);
                if (\core_text::strlen($needle) >= 2 && \core_text::strpos($text, $needle) !== false) {
                    $used[] = $term;
                    break;
                }
            }
        }
        return $used;
    }

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
     * @param \context $context Course context the page is generated for.
     * @param string $theme Course theme.
     * @param string $sectiontitle Section title.
     * @param string $pagetitle Page title.
     * @param string $reference Reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param array $objectives Course learning objectives.
     * @param string $chosenpreset Set to the preset name, 'blocks', or '' on failure.
     * @return string Rendered body HTML.
     */
    private static function generate_body(
        \context $context,
        string $theme,
        string $sectiontitle,
        string $pagetitle,
        string $reference = '',
        string $bloom = 'general',
        array $objectives = [],
        string &$chosenpreset = ''
    ): string {
        try {
            return self::plan_and_build(
                $context,
                $theme,
                $sectiontitle,
                $pagetitle,
                $reference,
                $bloom,
                $objectives,
                $chosenpreset
            );
        } catch (\Throwable $e) {
            return '';
        }
    }




    /**
     * Asks the AI to plan the page, then builds it.
     *
     * @param \context $context Course context the page is generated for.
     * @param string $theme Course theme.
     * @param string $sectiontitle Section title.
     * @param string $pagetitle Page title.
     * @param string $reference Reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param array $objectives Course learning objectives.
     * @param string $chosenpreset Set to the preset name, 'blocks', or '' on failure.
     * @return string Rendered body HTML.
     */
    private static function plan_and_build(
        \context $context,
        string $theme,
        string $sectiontitle,
        string $pagetitle,
        string $reference = '',
        string $bloom = 'general',
        array $objectives = [],
        string &$chosenpreset = ''
    ): string {
        $plan = self::plan_page($theme, $sectiontitle, $pagetitle, $reference, $bloom, $objectives);
        if ($plan === null) {
            return '';
        }

        if (($plan['strategy'] ?? '') === 'preset') {
            $chosenpreset = ai_json::text($plan['preset_name'] ?? null, 'preset');
            $html = self::build_from_preset($plan);
            if ($html !== '') {
                return $html;
            }
            $chosenpreset = '';
            // A preset that cannot be used as planned (unknown, not drawable or left with placeholders) must not
            // end as an empty page: the AI is asked once more, for free blocks only.
            if (empty($plan['blocks'])) {
                $plan = self::plan_page($theme, $sectiontitle, $pagetitle, $reference, $bloom, $objectives, true);
                if ($plan === null) {
                    return '';
                }
            }
        }

        if (!empty($plan['blocks']) && is_array($plan['blocks'])) {
            $chosenpreset = 'blocks';
            $html = '';
            foreach ($plan['blocks'] as $block) {
                // An entry that is not an object (a string, a number) is no block.
                $html .= is_array($block) ? self::render_block($block) : '';
            }
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
     * @param string $reference Reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param array $objectives Course learning objectives.
     * @param bool $blocksonly Ask for free blocks only, with no catalog of templates.
     * @return array|null Decoded plan, or null when AI is unavailable.
     */
    private static function plan_page(
        string $theme,
        string $sectiontitle,
        string $pagetitle,
        string $reference = '',
        string $bloom = 'general',
        array $objectives = [],
        bool $blocksonly = false
    ): ?array {
        $lang = current_language();
        $catalog = $blocksonly ? [] : preset_loader::catalog_for_prompt($lang);
        $catalogjson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        $presetschema = '{"strategy":"preset","preset_name":"<name from catalog>","fill":{"[Placeholder]":"value"}}';
        $blockschema  = '{"strategy":"blocks","blocks":['
            . '{"type":"heading","text":"..."},'
            . '{"type":"text","html":"<p>...</p>"},'
            . '{"type":"callout","html":"<p>...</p>"},'
            . '{"type":"card","content":"<h4>...</h4><p>...</p>"},'
            . '{"type":"accordion","title":"...","content":"<p>...</p>"}'
            . ']}';

        $blocksrule = ' Use the block types heading, text, callout, card and accordion. Use text for ordinary'
            . ' explanatory prose, with paragraphs and lists and no frame, so the page reads like a lesson; keep'
            . ' callout, card and accordion for what deserves emphasis.';
        if ($blocksonly) {
            $system = 'You are a course page planner. Write the page as custom visual blocks.'
                . ' Return ONLY a valid JSON object with no markdown, using this schema: ' . $blockschema
                . $blocksrule;
        } else {
            $system = 'You are a course page planner. Given a page topic and a catalog of page templates,'
                . ' decide whether to apply a template (preset) or generate custom visual blocks.'
                . ' Return ONLY a valid JSON object with no markdown. Use one of these two schemas:'
                . ' PRESET: ' . $presetschema
                . ' BLOCKS: ' . $blockschema
                . ' Choose PRESET when the page context fits a catalog entry well, and fill EVERY [Placeholder]'
                . ' of it with real content. Choose BLOCKS otherwise.' . $blocksrule
                . ' The "fill" map replaces [Placeholder] tokens in the preset (e.g. "[Course name]" -> value).';
        }
        $system .= ' Write all generated text in the language identified by the code: ' . $lang . '.';

        if ($bloom !== 'general' && $bloom !== '') {
            $system .= " The predominant cognitive level (Bloom's taxonomy) is: {$bloom}.";
        }

        $user = "Course theme: {$theme}\nSection: {$sectiontitle}\nPage title: {$pagetitle}"
            . ($blocksonly ? '' : "\nAvailable presets: {$catalogjson}");

        if (!empty($objectives)) {
            $objlist = implode('; ', array_slice($objectives, 0, 6));
            $user .= "\nLearning objectives: {$objlist}";
        }

        if ($reference !== '') {
            $user .= "\n\nReference material:\n" . mb_substr($reference, 0, 3000);
        }

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
        $preset = preset_loader::find(ai_json::text($plan['preset_name'] ?? null));
        if ($preset === null || !preset_loader::is_renderable($preset)) {
            return '';
        }

        $fill = is_array($plan['fill'] ?? null) ? $plan['fill'] : [];
        if (!empty($fill)) {
            $preset = self::fill_preset($preset, $fill);
        }
        // A page with "[Title]" or "[date]" left in it is not finished, and a student would read the brackets.
        if (self::has_placeholder($preset['blocks'] ?? [])) {
            return '';
        }

        return self::render_preset($preset);
    }

    /**
     * Tells whether any text of a preset still holds a "[placeholder]".
     *
     * @param mixed $data A preset, a block or a value of one.
     * @return bool
     */
    private static function has_placeholder(mixed $data): bool {
        if (is_array($data)) {
            foreach ($data as $value) {
                if (self::has_placeholder($value)) {
                    return true;
                }
            }
            return false;
        }
        return is_string($data) && preg_match('/\[[^\[\]\n]{1,80}\]/u', strip_tags($data)) === 1;
    }

    /**
     * Renders a filled preset to HTML, sanitizing the fields that hold markup first.
     *
     * The preset's own static markup is authored by the tiny_studiolms catalog, but by the time this is called
     * it has been through fill_preset() with AI-generated values. The markup fields are cleaned as a second,
     * independent safety net on top of that. The rendered HTML itself must not be cleaned: it carries the
     * data-slms-* contract and the SVG the editor needs to reopen the blocks, and the HTML purifier drops both.
     *
     * @param array $preset Filled preset definition.
     * @return string Sanitized HTML.
     */
    private static function render_preset(array $preset): string {
        return preset_loader::render(self::clean_markup_fields($preset));
    }

    /**
     * Cleans every string stored under a config key that holds HTML, at any depth.
     *
     * @param array $data Preset definition, or a part of it.
     * @param bool $markup True when the data sits under a key that holds HTML.
     * @return array The same structure with the markup strings cleaned.
     */
    private static function clean_markup_fields(array $data, bool $markup = false): array {
        foreach ($data as $key => $value) {
            $ismarkup = $markup || in_array($key, self::MARKUP_KEYS, true);
            if (is_array($value)) {
                $data[$key] = self::clean_markup_fields($value, $ismarkup);
            } else if ($ismarkup && is_string($value)) {
                $data[$key] = clean_text($value, FORMAT_HTML);
            }
        }
        return $data;
    }

    /**
     * Recursively replaces placeholder tokens in all string values of a preset.
     *
     * The fill map is AI-generated and therefore untrusted: only keys shaped like a
     * bracketed placeholder (e.g. "[Tópico 1]") that actually occur in the preset are
     * honoured, and every replacement value is reduced to plain text before substitution,
     * since the preset's HTML is later rendered with format_text's noclean flag.
     *
     * @param array $preset Preset definition.
     * @param array $fill Map of [Placeholder] => replacement text, from the AI.
     * @return array Preset with placeholders replaced.
     */
    private static function fill_preset(array $preset, array $fill): array {
        $haystack = json_encode($preset, JSON_UNESCAPED_UNICODE);
        $search   = [];
        $replace  = [];
        foreach ($fill as $key => $value) {
            if (!is_string($key) || !preg_match('/^\[[^\]]{1,80}\]$/u', $key)) {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            if ($haystack === false || strpos($haystack, $key) === false) {
                continue;
            }
            $search[]  = $key;
            $replace[] = s(clean_param((string) $value, PARAM_TEXT));
        }
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
                $text = ai_json::text($block['text'] ?? null);
                if ($text === '') {
                    return '';
                }
                return block_builder::render('stylizedHeading', [
                    'text' => $text,
                    'level' => 'h3',
                    'icon' => '',
                    'bgColor' => '#e3f2fd',
                    'textColor' => '#0d47a1',
                ]);
            case 'text':
                return block_builder::render('textBox', [
                    'width' => 'full',
                    'textAlign' => 'left',
                    'content' => ai_json::html($block['html'] ?? null),
                ]);
            case 'callout':
                $calloutcontent = ai_json::html($block['html'] ?? null);
                if ($calloutcontent === '') {
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
                    'contentHtml' => $calloutcontent,
                ]);
            case 'card':
                $cardcontent = ai_json::html($block['content'] ?? null);
                if ($cardcontent === '') {
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
                    'content' => $cardcontent,
                ]);
            case 'accordion':
                $accordiontitle = ai_json::text($block['title'] ?? null);
                if ($accordiontitle === '') {
                    return '';
                }
                return block_builder::render('accordion', [
                    'state' => 'closed',
                    'bg' => '#ffffff',
                    'color' => '#0d47a1',
                    'icon' => '❯ / ❯',
                    'openSound' => 'none',
                    'hoverEffect' => 'none',
                    'title' => $accordiontitle,
                    'content' => ai_json::html($block['content'] ?? null),
                ]);
            default:
                return ai_json::html($block['html'] ?? null);
        }
    }
}
