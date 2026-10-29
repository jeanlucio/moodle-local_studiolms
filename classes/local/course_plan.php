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
 * Builds the plan of a generated course (the "Plano de Disciplina" page).
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * The plan is written from the outline the teacher approved, so it lists what the course really holds: its sections,
 * its objectives and its graded activities. The AI only adds a sentence for the general objective and one for each
 * section; without it the plan is still complete.
 */
class course_plan {
    /** @var string[] Activity types that carry a grade, and so belong in the assessment table. */
    private const GRADED = ['quiz', 'assign'];

    /** @var int Longest sentence the AI is asked for, in characters. */
    private const SENTENCE_LENGTH = 160;

    /**
     * Renders the plan page of a course.
     *
     * @param \context $context Course context the plan is for (scopes the AI call).
     * @param string $theme The course theme.
     * @param string $coursename The course name.
     * @param array $sections The outline sections, each with 'title' and 'activities' ([type, title]).
     * @param string[] $objectives The learning objectives of the outline.
     * @param bool $degraded Set to true when the AI wrote none of its sentences (the rest of the plan is complete).
     * @return string The page HTML.
     */
    public static function render(
        \context $context,
        string $theme,
        string $coursename,
        array $sections,
        array $objectives,
        bool &$degraded = false
    ): string {
        $sections = self::known_types($sections);
        $written = self::ask_ai($context, $theme, $coursename, $sections, $objectives);
        $degraded = $written['general'] === '' && array_filter($written['sections']) === [];

        $html = block_builder::render('stylizedHeading', [
            'text' => s(get_string('plan_heading', 'local_studiolms', $coursename)),
            'level' => 'h2',
            'icon' => '📚',
            'bgColor' => '#1F4E79',
            'textColor' => '#ffffff',
        ]);
        $html .= self::syllabus($sections, $written['sections']);
        $html .= self::objectives($objectives, $written['general']);
        $html .= self::method($sections);
        $html .= self::organisation($sections, $written['sections']);
        $html .= self::assessment($sections);
        $html .= self::callout(
            '⚠️',
            ['#fff8e1', '#5d4037', '#ffc107'],
            \html_writer::tag('strong', s(get_string('plan_note_title', 'local_studiolms')) . ':') . ' '
                . s(get_string('plan_note_text', 'local_studiolms'))
        );
        return $html;
    }

    /**
     * Turns the activity types the course builder does not know into pages, as the builder itself does.
     *
     * @param array $sections The outline sections.
     * @return array The sections with a type the builder knows on every activity.
     */
    private static function known_types(array $sections): array {
        foreach ($sections as $i => $section) {
            foreach ($section['activities'] ?? [] as $j => $activity) {
                $sections[$i]['activities'][$j]['type'] = content_access::modules_for([$activity['type'] ?? 'page'])[0];
            }
        }
        return $sections;
    }

    /**
     * Asks the AI for the general objective and a sentence for each section.
     *
     * @param \context $context Course context.
     * @param string $theme The course theme.
     * @param string $coursename The course name.
     * @param array $sections The outline sections.
     * @param string[] $objectives The learning objectives.
     * @return array 'general' (string) and 'sections' (string[] by position); empty when the AI gave nothing usable.
     */
    private static function ask_ai(
        \context $context,
        string $theme,
        string $coursename,
        array $sections,
        array $objectives
    ): array {
        $none = ['general' => '', 'sections' => []];
        $outline = '';
        foreach (array_values($sections) as $i => $section) {
            $titles = implode('; ', array_column($section['activities'] ?? [], 'title'));
            $outline .= "\n" . ($i + 1) . '. ' . $section['title'] . ' (' . $titles . ')';
        }
        $system = 'You write the syllabus of a Moodle course. Return ONLY a JSON object with no markdown, shaped as '
            . '{"general":"<one sentence with the general objective of the course>",'
            . '"sections":["<one sentence for each section, in the same order, saying what it covers>"]}. '
            . 'Plain text only, at most ' . self::SENTENCE_LENGTH . ' characters per sentence. '
            . 'Write in the language identified by the code: ' . current_language() . '.';
        $user = "Course theme: {$theme}\nCourse title: {$coursename}\nObjectives: " . implode('; ', $objectives)
            . "\nSections:" . $outline;

        try {
            $decoded = ai_json::decode(ai_resolver::generate_text($system, $user, $context));
        } catch (\Throwable $e) {
            debugging('StudioLMS course plan sentences skipped — ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $none;
        }
        if ($decoded === null) {
            return $none;
        }

        $sentences = [];
        foreach (array_values(ai_json::items($decoded['sections'] ?? null)) as $sentence) {
            $sentences[] = ai_json::text($sentence);
        }
        return ['general' => ai_json::text($decoded['general'] ?? null), 'sections' => $sentences];
    }

    /**
     * Builds a callout in the colours of the plan.
     *
     * @param string $icon The emoji of the box.
     * @param string[] $colours Background, text and border colour.
     * @param string $content The HTML of the box (already escaped).
     * @return string The block HTML.
     */
    private static function callout(string $icon, array $colours, string $content): string {
        return block_builder::render('callout', [
            'backgroundColor' => $colours[0],
            'textColor' => $colours[1],
            'borderColor' => $colours[2],
            'borderLeftWidth' => 6,
            'borderRadius' => 6,
            'hoverEffect' => 'none',
            'icon' => $icon,
            'contentHtml' => $content,
        ]);
    }

    /**
     * Builds a section heading of the plan.
     *
     * @param string $icon The emoji of the heading.
     * @param string $text The heading, as plain text.
     * @return string The block HTML.
     */
    private static function heading(string $icon, string $text): string {
        return block_builder::render('stylizedHeading', [
            'text' => s($text),
            'level' => 'h3',
            'icon' => $icon,
            'bgColor' => '#e3f2fd',
            'textColor' => '#0d47a1',
        ]);
    }

    /**
     * The syllabus: one line for each section, with its sentence or, without one, what it holds.
     *
     * @param array $sections The outline sections.
     * @param string[] $sentences The sentence of each section, by position.
     * @return string The block HTML.
     */
    private static function syllabus(array $sections, array $sentences): string {
        $items = '';
        foreach (array_values($sections) as $i => $section) {
            $detail = ($sentences[$i] ?? '') !== ''
                ? $sentences[$i]
                : implode(', ', array_column($section['activities'] ?? [], 'title'));
            $items .= \html_writer::tag(
                'li',
                \html_writer::tag('strong', s($section['title'])) . ($detail !== '' ? ' — ' . s($detail) : '')
            );
        }
        if ($items === '') {
            return '';
        }
        return self::callout(
            '📋',
            ['#e3f2fd', '#0d47a1', '#1F4E79'],
            \html_writer::tag('strong', s(get_string('plan_syllabus', 'local_studiolms')) . ':')
                . \html_writer::tag('ol', $items)
        );
    }

    /**
     * The objectives: the general one the AI wrote and the ones of the outline.
     *
     * @param string[] $objectives The learning objectives.
     * @param string $general The general objective, or '' when the AI wrote none.
     * @return string The block HTML, or '' when there is nothing to say.
     */
    private static function objectives(array $objectives, string $general): string {
        $html = '';
        if ($general !== '') {
            $html .= \html_writer::tag('p', \html_writer::tag('strong', s(get_string('plan_general', 'local_studiolms')))
                . ': ' . s($general));
        }
        if (!empty($objectives)) {
            $items = '';
            foreach ($objectives as $objective) {
                $items .= \html_writer::tag('li', s($objective));
            }
            $html .= \html_writer::tag('p', \html_writer::tag('strong', s(get_string('plan_specific', 'local_studiolms'))))
                . \html_writer::tag('ol', $items);
        }
        if ($html === '') {
            return '';
        }
        return self::callout(
            '🎯',
            ['#f0f4ff', '#1a237e', '#3949ab'],
            \html_writer::tag('strong', s(get_string('plan_objectives', 'local_studiolms')) . ':') . $html
        );
    }

    /**
     * How the course is organised, in numbers: sections, activities and how many of each kind.
     *
     * @param array $sections The outline sections.
     * @return string The block HTML.
     */
    private static function method(array $sections): string {
        $kinds = [];
        $total = 0;
        foreach ($sections as $section) {
            foreach ($section['activities'] ?? [] as $activity) {
                $kinds[$activity['type']] = ($kinds[$activity['type']] ?? 0) + 1;
                $total++;
            }
        }
        $items = '';
        foreach ($kinds as $type => $count) {
            $name = get_string('activity_' . $type, 'local_studiolms');
            $items .= \html_writer::tag('li', s($name) . ': ' . $count);
        }
        $counts = (object) ['sections' => count($sections), 'activities' => $total];
        return self::callout(
            '⚙️',
            ['#f3f4f6', '#1f2937', '#6b7280'],
            \html_writer::tag('strong', s(get_string('plan_method', 'local_studiolms')) . ':')
                . \html_writer::tag('p', s(get_string('plan_method_text', 'local_studiolms', $counts)))
                . \html_writer::tag('ul', $items)
        );
    }

    /**
     * The sections as the steps of a path, each with its sentence or what it holds.
     *
     * @param array $sections The outline sections.
     * @param string[] $sentences The sentence of each section, by position.
     * @return string The blocks HTML.
     */
    private static function organisation(array $sections, array $sentences): string {
        $items = [];
        foreach (array_values($sections) as $i => $section) {
            $description = ($sentences[$i] ?? '') !== ''
                ? $sentences[$i]
                : implode(', ', array_column($section['activities'] ?? [], 'title'));
            $items[] = ['title' => $section['title'], 'description' => $description, 'icon' => ''];
        }
        if (empty($items)) {
            return '';
        }
        return self::heading('🗓️', get_string('plan_organisation', 'local_studiolms'))
            . block_builder::render('infographicSteps', [
                'theme' => 'blue',
                'layout' => 'vertical',
                'title' => '',
                'items' => $items,
            ]);
    }

    /**
     * The graded activities of the course, in a table.
     *
     * @param array $sections The outline sections.
     * @return string The blocks HTML, or '' when nothing in the course is graded.
     */
    private static function assessment(array $sections): string {
        $rows = [[
            s(get_string('plan_col_activity', 'local_studiolms')),
            s(get_string('plan_col_type', 'local_studiolms')),
            s(get_string('plan_col_section', 'local_studiolms')),
        ]];
        foreach ($sections as $section) {
            foreach ($section['activities'] ?? [] as $activity) {
                if (in_array($activity['type'], self::GRADED, true)) {
                    $rows[] = [
                        s($activity['title']),
                        s(get_string('activity_' . $activity['type'], 'local_studiolms')),
                        s($section['title']),
                    ];
                }
            }
        }
        if (count($rows) === 1) {
            return '';
        }
        return self::heading('📊', get_string('plan_assessment', 'local_studiolms'))
            . block_builder::render('table', [
                'cols' => 3,
                'rows' => count($rows),
                'style' => 'striped',
                'headerBg' => '#1F4E79',
                'headerText' => '#ffffff',
                'cellData' => $rows,
            ]);
    }
}
