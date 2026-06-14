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
 * English language strings for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['activity_assign'] = 'Assignment';
$string['activity_forum'] = 'Forum';
$string['activity_glossary'] = 'Glossary';
$string['activity_label'] = 'Label';
$string['activity_page'] = 'Page';
$string['activity_quiz'] = 'Quiz';
$string['add_activity'] = 'Add activity';
$string['add_objective'] = 'Add objective';
$string['add_section'] = 'Add section';
$string['aiheading'] = 'Artificial intelligence';
$string['aikeys_configure'] = 'Configure';
$string['aikeys_info'] = 'AI keys are managed by the StudioLMS editor (tiny_studiolms). StudioLMS reuses that configuration, so there is nothing to set up here.';
$string['aria_activity_title'] = 'Activity title';
$string['aria_activity_type'] = 'Activity type';
$string['aria_objective'] = 'Learning objective';
$string['aria_remove_activity'] = 'Remove activity';
$string['aria_remove_objective'] = 'Remove objective';
$string['aria_remove_section'] = 'Remove section';
$string['aria_section_title'] = 'Section title';
$string['back_to_course'] = 'Back to the course';
$string['bloom_analyze'] = 'Analyse';
$string['bloom_apply'] = 'Apply';
$string['bloom_create'] = 'Create';
$string['bloom_evaluate'] = 'Evaluate';
$string['bloom_general'] = 'General';
$string['bloom_remember'] = 'Remember';
$string['bloom_taxonomy'] = "Bloom's Taxonomy";
$string['bloom_understand'] = 'Understand';
$string['briefing_bloom'] = 'Predominant cognitive level';
$string['briefing_mode'] = 'Mode';
$string['briefing_reference'] = 'Reference material (optional — paste text)';
$string['briefing_reference_placeholder'] = 'Paste any syllabus, notes or reference text the AI should take into account.';
$string['briefing_structure'] = 'Structure';
$string['briefing_theme'] = 'Theme / content focus';
$string['briefing_theme_placeholder'] = 'For example: Introduction to Python';
$string['briefing_wipe'] = 'Wipe the course first (deletes current sections and activities)';
$string['briefing_wipe_warning'] = 'This permanently removes the existing sections and activities of this course before generating. Use with care.';
$string['btn_back'] = 'Back';
$string['btn_cancel'] = 'Cancel';
$string['btn_generate_outline'] = 'Generate course';
$string['btn_populate'] = 'Populate course';
$string['courseplantitle'] = 'Course plan';
$string['error_outline_generation'] = 'The outline could not be generated. Please try again.';
$string['error_populate'] = 'The course could not be populated. Please try again.';
$string['error_theme_required'] = 'Please enter the theme or content focus.';
$string['estimate_time'] = 'Estimated time: ~{$a} min';
$string['estimate_time_short'] = 'Estimated time: < 1 min';
$string['fillwithai'] = 'StudioLMS';
$string['gamification_profile'] = 'Gamification profile';
$string['generate_heading'] = 'Generate course with StudioLMS';
$string['generating'] = 'Generating course...';
$string['glossary_default_title'] = 'Course glossary';
$string['glossary_intro'] = 'Key terms for this course.';
$string['invalidairesponse'] = 'The AI did not return a valid course outline. Please try again.';
$string['mode_gamified'] = 'Gamified';
$string['mode_gamified_detected'] = 'PlayerHUD detected';
$string['mode_gamified_disabled'] = 'Install PlayerHUD to enable this mode';
$string['mode_standard'] = 'Standard';
$string['objectives_heading'] = 'Learning objectives';
$string['outline_review_heading'] = 'Review course';
$string['pluginname'] = 'StudioLMS course builder';
$string['populate_heading'] = 'Populating the course';
$string['preferredprovider'] = 'Preferred AI provider';
$string['preferredprovider_desc'] = 'Provider used to generate course content. StudioLMS uses the tiny_studiolms AI layer by default; PlayerGames is offered only when local_playergames is installed.';
$string['pretraining_title'] = 'Key concepts';
$string['profile_conquest'] = 'Conquest';
$string['profile_narrative'] = 'Narrative';
$string['profile_social'] = 'Social';
$string['progress_activity'] = 'Activity created: {$a}';
$string['progress_done'] = 'Course populated.';
$string['progress_section'] = 'Section added: {$a}';
$string['provider_playergames'] = 'PlayerGames (local_playergames)';
$string['provider_studio'] = 'StudioLMS (default)';
$string['report_blocks'] = 'Custom blocks';
$string['report_degraded'] = 'simplified';
$string['report_duration'] = 'Total time: {$a}';
$string['report_fallback'] = 'Simplified (AI unavailable)';
$string['report_heading'] = 'Generation report';
$string['report_pages'] = 'Pages generated:';
$string['report_plan'] = 'Course plan (preset)';
$string['report_success'] = 'successful';
$string['section_number'] = 'Section {$a}';
$string['step_of'] = 'Step {$a->current} of {$a->total}';
$string['structure_abc'] = 'ABC Learning Design';
$string['structure_free'] = 'Free (AI decides)';
$string['studiolms:generate'] = 'Fill a course with AI using StudioLMS';
$string['studiolms:viewlog'] = 'View the StudioLMS generation log';
$string['task_generate_course'] = 'Populate a course with StudioLMS';
$string['warnings_heading'] = 'Some activities used simplified content because the AI was unavailable:';
