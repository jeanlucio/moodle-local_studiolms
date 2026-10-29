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
 * Builds the rich StudioLMS content of the activities other than the page.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Gives a forum, an assignment, a quiz, a label and a glossary the same visual blocks as a page.
 *
 * Each activity has a fixed recipe of blocks. For the forum and the assignment the AI only fills the text of the
 * recipe (one call, as before); what can be known without it (the label's section, the quiz settings, the glossary)
 * is built without a call. The blocks go through block_builder, so they reopen for editing in tiny_studiolms, and
 * fall back to plain semantic HTML when that editor is not installed.
 */
class activity_content {
    /** @var int Longest text of the introduction that the AI writes, in characters. */
    private const INTRO_LENGTH = 400;

    /** @var int Longest of the other texts that the AI writes, in characters. */
    private const SHORT_LENGTH = 180;

    /** @var string[] Icons of the steps of a forum, from the set the editor offers. */
    private const FORUM_ICONS = ['fa-solid fa-pen', 'fa-solid fa-users', 'fa-solid fa-check'];

    /** @var string[] Icons of the steps of an assignment, from the set the editor offers. */
    private const ASSIGN_ICONS = [
        'fa-solid fa-book-open', 'fa-solid fa-pen', 'fa-solid fa-magnifying-glass', 'fa-solid fa-upload',
    ];

    /**
     * Builds the introduction of a forum.
     *
     * @param \context $context The context of the course the text is written for.
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $title The forum title.
     * @param string $reference Optional reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param bool $degraded Set to true when the AI text could not be had and only the title is shown.
     * @return string The HTML of the introduction.
     */
    public static function forum_intro(
        \context $context,
        string $theme,
        string $sectiontitle,
        string $title,
        string $reference = '',
        string $bloom = 'general',
        bool &$degraded = false
    ): string {
        $schema = '{"intro":"2 or 3 sentences that frame the discussion","question":"the guiding question the students '
            . 'answer","steps":[{"title":"short step name","description":"one sentence"}],'
            . '"tip":"one short sentence of advice for a good post"}';
        $instructions = 'You write the introduction of a course discussion forum. Give exactly 3 steps that tell the '
            . 'students how to take part (write a first post, answer colleagues, and so on).';
        $plan = self::ask($context, $schema, $instructions, $theme, $sectiontitle, $title, $reference, $bloom);

        $intro = self::plain($plan['intro'] ?? null, self::INTRO_LENGTH);
        $question = self::plain($plan['question'] ?? null, self::SHORT_LENGTH);
        $steps = self::steps($plan['steps'] ?? null, self::FORUM_ICONS, 3);
        if ($intro === '' && $question === '' && empty($steps)) {
            $degraded = true;
            return self::heading($title, '💬', 'h2');
        }

        $html = self::heading($title, '💬', 'h2');
        $html .= self::paragraph($intro);
        if ($question !== '') {
            $html .= self::callout(
                \html_writer::tag('p', \html_writer::tag('strong', s(get_string('rich_forum_question', 'local_studiolms')))
                    . ' ' . s($question), ['class' => 'm-0']),
                '❓',
                'info'
            );
        }
        if (!empty($steps)) {
            $html .= self::steps_block(get_string('rich_forum_steps', 'local_studiolms'), $steps);
        }
        $tip = self::plain($plan['tip'] ?? null, self::SHORT_LENGTH);
        if ($tip !== '') {
            $html .= self::callout(\html_writer::tag('p', s($tip), ['class' => 'm-0']), '💡', 'tip');
        }
        return $html;
    }

    /**
     * Builds the instructions of an assignment.
     *
     * @param \context $context The context of the course the text is written for.
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $title The assignment title.
     * @param string $reference Optional reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @param bool $degraded Set to true when the AI text could not be had and only the title is shown.
     * @return string The HTML of the instructions.
     */
    public static function assign_intro(
        \context $context,
        string $theme,
        string $sectiontitle,
        string $title,
        string $reference = '',
        string $bloom = 'general',
        bool &$degraded = false
    ): string {
        $schema = '{"intro":"2 or 3 sentences that say what the students do","deliverables":["what they hand in"],'
            . '"steps":[{"title":"short step name","description":"one sentence"}],'
            . '"criteria":[{"criterion":"short name","expectation":"what is expected"}],'
            . '"tip":"one short sentence of advice"}';
        $instructions = 'You write the instructions of a course assignment. Give 2 to 4 deliverables, 3 or 4 steps and '
            . '3 to 5 evaluation criteria. Do not invent dates or points.';
        $plan = self::ask($context, $schema, $instructions, $theme, $sectiontitle, $title, $reference, $bloom);

        $intro = self::plain($plan['intro'] ?? null, self::INTRO_LENGTH);
        $deliverables = [];
        foreach (array_slice(ai_json::items($plan['deliverables'] ?? null), 0, 4) as $item) {
            $text = self::plain($item, self::SHORT_LENGTH);
            if ($text !== '') {
                $deliverables[] = $text;
            }
        }
        $steps = self::steps($plan['steps'] ?? null, self::ASSIGN_ICONS, 4);
        $criteria = [];
        foreach (array_slice(ai_json::items($plan['criteria'] ?? null), 0, 5) as $item) {
            $name = is_array($item) ? self::plain($item['criterion'] ?? null, self::SHORT_LENGTH) : '';
            if ($name !== '') {
                $criteria[] = [$name, self::plain($item['expectation'] ?? null, self::SHORT_LENGTH)];
            }
        }
        if ($intro === '' && empty($deliverables) && empty($steps) && empty($criteria)) {
            $degraded = true;
            return self::heading($title, '📝', 'h2');
        }

        $html = self::heading($title, '📝', 'h2');
        $html .= self::paragraph($intro);
        if (!empty($deliverables)) {
            $items = '';
            foreach ($deliverables as $text) {
                $items .= \html_writer::tag('li', s($text));
            }
            $html .= self::callout(
                \html_writer::tag('p', \html_writer::tag('strong', s(get_string('rich_assign_deliver', 'local_studiolms'))))
                    . \html_writer::tag('ul', $items),
                '📦',
                'info'
            );
        }
        if (!empty($steps)) {
            $html .= self::steps_block(get_string('rich_assign_steps', 'local_studiolms'), $steps);
        }
        if (!empty($criteria)) {
            $html .= self::criteria_table($criteria);
        }
        $tip = self::plain($plan['tip'] ?? null, self::SHORT_LENGTH);
        if ($tip !== '') {
            $html .= self::callout(\html_writer::tag('p', s($tip), ['class' => 'm-0']), '⚠️', 'warn');
        }
        return $html;
    }

    /**
     * Builds the content of a label: the title as a heading, and the activities of its section when there are any.
     *
     * @param string $title The label title.
     * @param string[] $siblings Titles of the other activities of the same section.
     * @return string The HTML of the label.
     */
    public static function label(string $title, array $siblings = []): string {
        $html = self::heading($title, '📌', 'h3');

        $items = '';
        foreach ($siblings as $sibling) {
            $text = trim((string) $sibling);
            if ($text !== '' && $text !== $title) {
                $items .= \html_writer::tag('li', s($text));
            }
        }
        if ($items !== '') {
            $html .= self::callout(
                \html_writer::tag('p', \html_writer::tag('strong', s(get_string('rich_label_in_section', 'local_studiolms'))))
                    . \html_writer::tag('ul', $items),
                '🧭',
                'info'
            );
        }
        return $html;
    }

    /**
     * Builds the introduction of a quiz from its real settings.
     *
     * @param string $title The quiz title.
     * @param int $questions How many questions the quiz holds.
     * @param int $attempts The attempts allowed, 0 for no limit.
     * @param int $timelimit The time limit in seconds, 0 for no limit.
     * @param bool $live True to leave the rules to the filter_studiolms widget, which reads them when the page is shown.
     * @return string The HTML of the introduction.
     */
    public static function quiz_intro(
        string $title,
        int $questions,
        int $attempts,
        int $timelimit,
        bool $live = false
    ): string {
        $html = self::heading($title, '✅', 'h2');
        $html .= self::paragraph(get_string('rich_quiz_intro', 'local_studiolms'));
        $html .= $live
            ? block_builder::render('widgetQuizInfo', [])
            : self::quiz_numbers($questions, $attempts, $timelimit);
        $html .= self::callout(
            \html_writer::tag('p', s(get_string('rich_quiz_tip', 'local_studiolms')), ['class' => 'm-0']),
            '💡',
            'tip'
        );
        return $html;
    }

    /**
     * Builds the cards with the numbers of a quiz, written once and not kept up to date.
     *
     * @param int $questions How many questions the quiz holds.
     * @param int $attempts The attempts allowed, 0 for no limit.
     * @param int $timelimit The time limit in seconds, 0 for no limit.
     * @return string The HTML of the cards.
     */
    private static function quiz_numbers(int $questions, int $attempts, int $timelimit): string {
        $items = [];
        if ($questions > 0) {
            $items[] = [
                'icon' => 'fa-solid fa-circle-check', 'value' => (string) $questions,
                'label' => get_string('rich_quiz_questions', 'local_studiolms'),
            ];
        }
        $items[] = [
            'icon' => 'fa-solid fa-key',
            'value' => $attempts > 0 ? (string) $attempts : get_string('rich_unlimited', 'local_studiolms'),
            'label' => get_string('rich_quiz_attempts', 'local_studiolms'),
        ];
        $items[] = [
            'icon' => 'fa-solid fa-clock',
            'value' => $timelimit > 0 ? format_time($timelimit) : get_string('rich_no_limit', 'local_studiolms'),
            'label' => get_string('rich_quiz_time', 'local_studiolms'),
        ];

        return block_builder::render('infographic', ['layout' => 'stats', 'theme' => 'blue', 'title' => '', 'items' => $items]);
    }

    /**
     * Tells whether the rules of a quiz can be left to the widget of the filter_studiolms plugin.
     *
     * The filter is optional: it must be installed, have the widget turned on in the site and be active in the course
     * of the quiz. Otherwise the introduction keeps the numbers written at the time of the generation.
     *
     * @param int $cmid The course module id of the quiz.
     * @return bool
     */
    public static function quiz_rules_are_live(int $cmid): bool {
        global $CFG;

        if (!class_exists(\filter_studiolms\widgets::class) || !\filter_studiolms\widgets::is_enabled('quizinfo')) {
            return false;
        }
        require_once($CFG->libdir . '/filterlib.php');
        $filters = filter_get_active_in_context(\context_module::instance($cmid));
        return isset($filters['studiolms']);
    }

    /**
     * Writes the introduction of a quiz that was created with only its title.
     *
     * The numbers (how many questions were really created, the attempts and the time of the quiz) are known only
     * after the questions are in, so the introduction is written then.
     *
     * @param \stdClass $result The add_moduleinfo result of the quiz (coursemodule and instance).
     * @param string $title The quiz title.
     * @param int $questions How many questions were created.
     * @return void
     */
    public static function write_quiz_intro(\stdClass $result, string $title, int $questions): void {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $result->instance], 'id, attempts, timelimit', MUST_EXIST);
        $intro = self::quiz_intro(
            $title,
            $questions,
            (int) $quiz->attempts,
            (int) $quiz->timelimit,
            self::quiz_rules_are_live((int) $result->coursemodule)
        );
        course_builder::write_module_text((int) $result->coursemodule, $intro);
    }

    /**
     * Builds the introduction of a glossary.
     *
     * @param string $title The glossary title.
     * @return string The HTML of the introduction.
     */
    public static function glossary_intro(string $title): string {
        return self::heading($title, '📖', 'h2')
            . self::paragraph(get_string('glossary_intro', 'local_studiolms'))
            . self::callout(
                \html_writer::tag('p', s(get_string('rich_glossary_howto', 'local_studiolms')), ['class' => 'm-0']),
                '🔎',
                'info'
            );
    }

    // -------------------------------------------------------------------------
    // The AI call and the reading of its answer.
    // -------------------------------------------------------------------------.

    /**
     * Asks the AI to fill the text of a recipe.
     *
     * @param \context $context The context of the course the text is written for.
     * @param string $schema The JSON shape the answer must have.
     * @param string $instructions What the AI writes.
     * @param string $theme The course theme.
     * @param string $sectiontitle The section title.
     * @param string $title The activity title.
     * @param string $reference Optional reference material from the briefing.
     * @param string $bloom Bloom's taxonomy level, or 'general'.
     * @return array The decoded answer, empty when the AI is unavailable or answered nothing usable.
     */
    private static function ask(
        \context $context,
        string $schema,
        string $instructions,
        string $theme,
        string $sectiontitle,
        string $title,
        string $reference,
        string $bloom
    ): array {
        try {
            $system = $instructions . ' Return ONLY a valid JSON object with no markdown, shaped like ' . $schema
                . ' Every value is plain text: no HTML and no markdown. Write in the language identified by the code: '
                . current_language() . '.';
            if ($bloom !== 'general' && $bloom !== '') {
                $system .= " Cognitive level (Bloom's taxonomy): {$bloom}.";
            }
            $user = "Course theme: {$theme}\nSection: {$sectiontitle}\nTitle: {$title}";
            if ($reference !== '') {
                $user .= "\n\nReference material:\n" . mb_substr($reference, 0, 3000);
            }
            return ai_json::decode(ai_resolver::generate_text($system, $user, $context)) ?? [];
        } catch (\Throwable $e) {
            debugging('StudioLMS: the text of an activity could not be generated — ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Reads a field of the answer as plain text of at most the given length.
     *
     * @param mixed $value The field.
     * @param int $max The longest text, in characters.
     * @return string The text, empty when the field is not one.
     */
    private static function plain(mixed $value, int $max): string {
        return mb_substr(trim(ai_json::text($value)), 0, $max);
    }

    /**
     * Reads the steps of the answer, dropping any entry that is not a step with a title.
     *
     * @param mixed $value The steps field.
     * @param string[] $icons The icon of each step, by position.
     * @param int $max How many steps to keep.
     * @return array The steps as the block takes them: icon, title and description.
     */
    private static function steps(mixed $value, array $icons, int $max): array {
        $steps = [];
        foreach (ai_json::items($value) as $step) {
            $title = is_array($step) ? self::plain($step['title'] ?? null, self::SHORT_LENGTH) : '';
            if ($title === '') {
                continue;
            }
            $steps[] = [
                'icon' => $icons[count($steps)] ?? '',
                'title' => $title,
                'description' => self::plain($step['description'] ?? null, self::SHORT_LENGTH),
            ];
            if (count($steps) >= $max) {
                break;
            }
        }
        return $steps;
    }

    // -------------------------------------------------------------------------
    // The blocks.
    // -------------------------------------------------------------------------.

    /**
     * Builds a heading block.
     *
     * @param string $text The plain text of the heading.
     * @param string $icon The emoji before the text.
     * @param string $level The level: h2 (the title of the page), h3 (a section) or h4.
     * @return string The block HTML.
     */
    private static function heading(string $text, string $icon, string $level): string {
        return block_builder::render('stylizedHeading', [
            'text' => s($text),
            'level' => $level,
            'icon' => $icon,
            'bgColor' => '#e3f2fd',
            'textColor' => '#0d47a1',
        ]);
    }

    /**
     * Builds a paragraph of plain text, or nothing when there is none.
     *
     * @param string $text The plain text.
     * @return string The paragraph HTML.
     */
    private static function paragraph(string $text): string {
        return $text === '' ? '' : \html_writer::tag('p', s($text));
    }

    /**
     * Builds a callout block.
     *
     * @param string $html The content, already escaped.
     * @param string $icon The emoji at the start of the callout.
     * @param string $kind One of info, tip or warn, which sets the colours.
     * @return string The block HTML.
     */
    private static function callout(string $html, string $icon, string $kind): string {
        $palettes = [
            'info' => ['#eff6ff', '#1e3a8a', '#3b82f6'],
            'tip' => ['#f0fdf4', '#14532d', '#22c55e'],
            'warn' => ['#fef9c3', '#854d0e', '#eab308'],
        ];
        [$background, $text, $border] = $palettes[$kind] ?? $palettes['info'];
        return block_builder::render('callout', [
            'icon' => $icon,
            'backgroundColor' => $background,
            'textColor' => $text,
            'borderColor' => $border,
            'borderLeftWidth' => 4,
            'borderRadius' => 6,
            'hoverEffect' => 'none',
            'contentHtml' => $html,
        ]);
    }

    /**
     * Builds a steps block.
     *
     * @param string $title The title above the steps.
     * @param array $steps The steps: icon, title and description.
     * @return string The block HTML.
     */
    private static function steps_block(string $title, array $steps): string {
        return block_builder::render('infographicSteps', [
            'title' => $title,
            'theme' => 'blue',
            'layout' => 'vertical',
            'current' => 0,
            'items' => $steps,
        ]);
    }

    /**
     * Builds the table of evaluation criteria.
     *
     * @param array $criteria Rows of [criterion, expectation].
     * @return string The block HTML.
     */
    private static function criteria_table(array $criteria): string {
        $rows = [[
            s(get_string('rich_assign_criterion', 'local_studiolms')),
            s(get_string('rich_assign_expectation', 'local_studiolms')),
        ]];
        foreach ($criteria as [$name, $expectation]) {
            $rows[] = [\html_writer::tag('strong', s($name)), s($expectation)];
        }
        return self::heading(get_string('rich_assign_criteria', 'local_studiolms'), '🎯', 'h3')
            . block_builder::render('table', [
                'cols' => 2,
                'rows' => count($rows),
                'style' => 'striped',
                'headerBg' => '#0d47a1',
                'headerText' => '#ffffff',
                'cellData' => $rows,
            ]);
    }
}
