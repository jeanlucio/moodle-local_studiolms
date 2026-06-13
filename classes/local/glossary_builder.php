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
 * Creates the course glossary and its AI-generated entries.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use stdClass;

/**
 * Builds a single continuous glossary and fills it with key terms from the AI.
 */
class glossary_builder {
    /**
     * Creates the glossary activity and inserts the generated terms as entries.
     *
     * @param stdClass $course The target course.
     * @param int $sectionnum The section number.
     * @param string $name The glossary name.
     * @param string $theme The course theme used to generate the terms.
     * @return stdClass The add_moduleinfo result (coursemodule and instance).
     */
    public static function create(stdClass $course, int $sectionnum, string $name, string $theme): stdClass {
        global $DB, $USER;

        $intro = get_string('glossary_intro', 'local_studiolms');
        $result = course_builder::add_glossary($course, $sectionnum, $name, $intro);

        $now = time();
        foreach (self::generate_terms($theme) as $term) {
            $entry = (object) [
                'glossaryid' => $result->instance,
                'userid' => $USER->id,
                'concept' => $term['term'],
                'definition' => $term['definition'],
                'definitionformat' => FORMAT_HTML,
                'definitiontrust' => 0,
                'attachment' => '',
                'timecreated' => $now,
                'timemodified' => $now,
                'teacherentry' => 1,
                'sourceglossaryid' => 0,
                'usedynalink' => 1,
                'casesensitive' => 0,
                'fullmatch' => 1,
                'approved' => 1,
            ];
            $DB->insert_record('glossary_entries', $entry);
        }

        return $result;
    }

    /**
     * Generates key glossary terms for the given theme.
     *
     * @param string $theme The course theme.
     * @return array List of ['term' => string, 'definition' => string].
     */
    private static function generate_terms(string $theme): array {
        $language = current_language();
        $system = 'You are an instructional designer creating a course glossary. '
            . 'Return ONLY a valid JSON object, no markdown or commentary, shaped like '
            . '{"terms": [{"term": "...", "definition": "..."}]}. Use double quotes and no trailing commas. '
            . "Write the terms and definitions in the language identified by the code: {$language}. "
            . 'Produce between 8 and 12 concise, essential terms.';
        $user = "Course theme or focus: {$theme}";

        $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        if ($decoded === null || empty($decoded['terms']) || !is_array($decoded['terms'])) {
            return [];
        }

        $terms = [];
        foreach ($decoded['terms'] as $item) {
            if (!is_array($item) || empty($item['term']) || empty($item['definition'])) {
                continue;
            }
            $terms[] = [
                'term' => clean_param($item['term'], PARAM_TEXT),
                'definition' => clean_param($item['definition'], PARAM_TEXT),
            ];
        }
        return $terms;
    }
}
