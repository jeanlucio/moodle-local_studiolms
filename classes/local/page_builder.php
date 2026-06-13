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
 * Renders a page as a pre-training callout plus AI-generated visual blocks.
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

        $blocks = self::generate_blocks($theme, $sectiontitle, $pagetitle);
        if (empty($blocks)) {
            $degraded = true;
        }
        foreach ($blocks as $block) {
            $html .= self::render_block($block);
        }

        if (trim(html_to_text($html)) === '') {
            $html = \html_writer::tag('p', s($pagetitle));
        }
        return $html;
    }

    /**
     * Builds the "Key concepts" pre-training callout from the glossary terms.
     *
     * @param array $glossaryterms Glossary terms ([term, definition]).
     * @return string The rendered callout HTML.
     */
    private static function pretraining(array $glossaryterms): string {
        global $OUTPUT;

        $items = '';
        foreach (array_slice($glossaryterms, 0, self::PRETRAINING_TERMS) as $term) {
            $items .= \html_writer::tag(
                'li',
                \html_writer::tag('strong', s($term['term'])) . ' — ' . s($term['definition'])
            );
        }
        $content = \html_writer::tag('p', \html_writer::tag('strong', get_string('pretraining_title', 'local_studiolms')))
            . \html_writer::tag('ul', $items);

        return $OUTPUT->render_from_template('tiny_studiolms/block_callout', [
            'backgroundColor' => '#eef2ff',
            'borderLeftWidth' => 4,
            'borderColor' => '#6366f1',
            'borderRadius' => 6,
            'hasIcon' => true,
            'icon' => '💡',
            'textColor' => '#3730a3',
            'contentHtml' => $content,
        ]);
    }

    /**
     * Renders a single content block through the matching StudioLMS template.
     *
     * @param array $block The block definition (type and fields).
     * @return string The rendered block HTML.
     */
    private static function render_block(array $block): string {
        global $OUTPUT;

        switch ($block['type'] ?? '') {
            case 'heading':
                if (empty($block['text'])) {
                    return '';
                }
                return $OUTPUT->render_from_template('tiny_studiolms/block_heading', [
                    'isH3' => true,
                    'isH4' => false,
                    'bgColor' => '#e3f2fd',
                    'textColor' => '#0d47a1',
                    'icon' => '',
                    'text' => clean_param($block['text'], PARAM_TEXT),
                ]);
            case 'callout':
                if (empty($block['html'])) {
                    return '';
                }
                return $OUTPUT->render_from_template('tiny_studiolms/block_callout', [
                    'backgroundColor' => '#fef9c3',
                    'borderLeftWidth' => 4,
                    'borderColor' => '#eab308',
                    'borderRadius' => 6,
                    'hasIcon' => true,
                    'icon' => '📌',
                    'textColor' => '#854d0e',
                    'contentHtml' => clean_text($block['html'], FORMAT_HTML),
                ]);
            case 'card':
                if (empty($block['content'])) {
                    return '';
                }
                return $OUTPUT->render_from_template('tiny_studiolms/block_card', [
                    'bg' => '#ffffff',
                    'text' => '#333333',
                    'border' => '#0d47a1',
                    'radius' => '8',
                    'shadowCss' => '0 1px 3px rgba(0,0,0,.1)',
                    'hasMedia' => false,
                    'hasButton' => false,
                    'isAlignLeft' => true,
                    'content' => clean_text($block['content'], FORMAT_HTML),
                ]);
            default:
                return empty($block['html']) ? '' : clean_text($block['html'], FORMAT_HTML);
        }
    }

    /**
     * Asks the AI for the page body as a list of visual blocks.
     *
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $pagetitle The page title.
     * @return array List of block definitions.
     */
    private static function generate_blocks(string $theme, string $sectiontitle, string $pagetitle): array {
        $language = current_language();
        $system = 'You are an instructional designer writing a course page with rich visual blocks. '
            . 'Return ONLY a valid JSON object, no markdown or commentary, shaped like '
            . '{"blocks": [{"type": "heading", "text": "..."}, {"type": "paragraph", "html": "<p>...</p>"}, '
            . '{"type": "callout", "html": "<p>...</p>"}, {"type": "card", "content": "<h5>...</h5><p>...</p>"}]}. '
            . 'Use only these block types: heading, paragraph, callout, card. Use callouts to highlight key '
            . 'information and cards to group related ideas. Use double quotes and no trailing commas. '
            . "Write everything in the language identified by the code: {$language}.";
        $user = "Course theme: {$theme}\nSection: {$sectiontitle}\nPage title: {$pagetitle}";

        try {
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        } catch (\Throwable $e) {
            return [];
        }
        if ($decoded === null || empty($decoded['blocks']) || !is_array($decoded['blocks'])) {
            return [];
        }
        return $decoded['blocks'];
    }
}
