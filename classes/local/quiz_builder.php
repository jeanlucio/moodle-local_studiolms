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
 * Generates quiz questions with the AI and adds them to a quiz.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use context_module;
use core_question\local\bank\question_version_status;
use question_bank;
use stdClass;

/**
 * Creates AI-generated questions in the course question bank and links them to a quiz.
 */
class quiz_builder {
    /** @var string[] Question types supported in v1. */
    private const SUPPORTED = ['multichoice', 'truefalse', 'shortanswer'];

    /**
     * Generates questions for a quiz and attaches them.
     *
     * @param int $cmid The quiz course module id.
     * @param int $quizinstanceid The quiz instance id.
     * @param string $theme The course theme.
     * @param string $quiztitle The quiz title.
     * @param int $count Number of questions to request.
     * @return int Number of questions added.
     */
    public static function create(
        int $cmid,
        int $quizinstanceid,
        string $theme,
        string $quiztitle,
        int $count = 5
    ): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $questions = self::generate_questions($theme, $quiztitle, $count);
        if (empty($questions)) {
            return 0;
        }

        $context = context_module::instance($cmid);
        $category = question_get_default_category($context->id, true);
        $quiz = $DB->get_record('quiz', ['id' => $quizinstanceid], '*', MUST_EXIST);

        $added = 0;
        foreach ($questions as $data) {
            $form = self::build_form($data, (int) $category->id);
            if ($form === null) {
                continue;
            }
            $stub = new stdClass();
            $stub->qtype = $data['type'];
            $stub->createdby = $GLOBALS['USER']->id;
            $stub->modifiedby = $GLOBALS['USER']->id;
            $stub->idnumber = null;
            $stub->status = question_version_status::QUESTION_STATUS_READY;

            $saved = question_bank::get_qtype($data['type'])->save_question($stub, $form);
            quiz_add_quiz_question($saved->id, $quiz, 0);
            $added++;
        }

        if ($added > 0) {
            \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        }
        return $added;
    }

    /**
     * Builds the save_question form data for a question definition.
     *
     * @param array $data The question definition from the AI.
     * @param int $categoryid The question category id.
     * @return stdClass|null The form data, or null when the definition is unusable.
     */
    private static function build_form(array $data, int $categoryid): ?stdClass {
        $type = $data['type'] ?? '';
        if (!in_array($type, self::SUPPORTED, true) || empty($data['question'])) {
            return null;
        }

        $form = new stdClass();
        $form->category = (string) $categoryid;
        $form->questiontext = ['text' => clean_text($data['question'], FORMAT_HTML), 'format' => FORMAT_HTML];
        $form->name = shorten_text(strip_tags($data['question']), 80);
        $form->generalfeedback = ['text' => clean_text($data['generalfeedback'] ?? '', FORMAT_HTML), 'format' => FORMAT_HTML];
        $form->defaultmark = 1;
        $form->status = question_version_status::QUESTION_STATUS_READY;

        switch ($type) {
            case 'truefalse':
                return self::build_truefalse($form, $data);
            case 'shortanswer':
                return self::build_shortanswer($form, $data);
            case 'multichoice':
                return self::build_multichoice($form, $data);
            default:
                return null;
        }
    }

    /**
     * Completes the form data for a true/false question.
     *
     * @param stdClass $form The base form data.
     * @param array $data The question definition.
     * @return stdClass The completed form data.
     */
    private static function build_truefalse(stdClass $form, array $data): stdClass {
        $form->penalty = 1;
        $form->correctanswer = !empty($data['answer']) ? '1' : '0';
        $form->feedbacktrue = ['text' => '', 'format' => FORMAT_HTML];
        $form->feedbackfalse = ['text' => '', 'format' => FORMAT_HTML];
        return $form;
    }

    /**
     * Completes the form data for a short answer question.
     *
     * @param stdClass $form The base form data.
     * @param array $data The question definition.
     * @return stdClass|null The completed form data, or null without answers.
     */
    private static function build_shortanswer(stdClass $form, array $data): ?stdClass {
        $answers = array_values(array_filter(array_map(
            fn($answer) => is_string($answer) ? trim($answer) : '',
            $data['answers'] ?? []
        )));
        if (empty($answers)) {
            return null;
        }

        $form->penalty = 0.3333333;
        $form->usecase = 0;
        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($answers as $answer) {
            $form->answer[] = clean_param($answer, PARAM_TEXT);
            $form->fraction[] = '1.0';
            $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML];
        }
        return $form;
    }

    /**
     * Completes the form data for a multiple choice question.
     *
     * @param stdClass $form The base form data.
     * @param array $data The question definition.
     * @return stdClass|null The completed form data, or null without valid options.
     */
    private static function build_multichoice(stdClass $form, array $data): ?stdClass {
        $options = array_values(array_filter(
            $data['options'] ?? [],
            fn($option) => is_array($option) && !empty($option['text'])
        ));
        $correct = array_values(array_filter($options, fn($option) => !empty($option['correct'])));
        if (count($options) < 2 || empty($correct)) {
            return null;
        }

        $numcorrect = count($correct);
        $form->penalty = 0.3333333;
        $form->single = $numcorrect === 1 ? '1' : '0';
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->shownumcorrect = 1;
        $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];

        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($options as $option) {
            $iscorrect = !empty($option['correct']);
            $form->answer[] = ['text' => clean_text($option['text'], FORMAT_HTML), 'format' => FORMAT_HTML];
            $form->fraction[] = $iscorrect ? (string) round(1 / $numcorrect, 7) : '0.0';
            $form->feedback[] = ['text' => clean_text($option['feedback'] ?? '', FORMAT_HTML), 'format' => FORMAT_HTML];
        }
        return $form;
    }

    /**
     * Generates quiz questions from the AI for the given theme and quiz.
     *
     * @param string $theme The course theme.
     * @param string $quiztitle The quiz title.
     * @param int $count Number of questions to request.
     * @return array List of question definitions.
     */
    private static function generate_questions(string $theme, string $quiztitle, int $count): array {
        $language = current_language();
        $system = 'You are an assessment designer creating quiz questions. '
            . 'Return ONLY a valid JSON object, no markdown or commentary, shaped like '
            . '{"questions": [{"type": "multichoice", "question": "...", '
            . '"options": [{"text": "...", "correct": true, "feedback": "..."}], "generalfeedback": "..."}, '
            . '{"type": "truefalse", "question": "...", "answer": true, "generalfeedback": "..."}, '
            . '{"type": "shortanswer", "question": "...", "answers": ["..."], "generalfeedback": "..."}]}. '
            . 'Use only these types: multichoice, truefalse, shortanswer. Each multichoice has 4 options '
            . 'with at least one correct. Use double quotes and no trailing commas. '
            . "Write everything in the language identified by the code: {$language}.";
        $user = "Course theme: {$theme}\nQuiz title: {$quiztitle}\nProduce exactly {$count} questions.";

        $decoded = ai_json::decode(ai_resolver::generate_text($system, $user));
        if ($decoded === null || empty($decoded['questions']) || !is_array($decoded['questions'])) {
            return [];
        }
        return $decoded['questions'];
    }
}
