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
$string['activity_heading'] = 'Generate a single activity';
$string['activity_label'] = 'Label';
$string['activity_page'] = 'Page';
$string['activity_quiz'] = 'Quiz';
$string['activity_section'] = 'Target section';
$string['activity_success'] = 'Activity created successfully.';
$string['activity_view'] = 'View activity';
$string['add_activity'] = 'Add activity';
$string['add_objective'] = 'Add objective';
$string['add_section'] = 'Add section';
$string['aiheading'] = 'Artificial intelligence';
$string['aikeys_info'] = 'StudioLMS generates content through the AI Hub (local_aihub) when installed, the StudioLMS editor (tiny_studiolms) keys when present, or Moodle core_ai. There is no API key to configure here — set up an AI provider under Site administration → AI, or in the AI Hub settings.';
$string['aiusage'] = 'Course content generation';
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
$string['btn_generate_activity'] = 'Generate activity';
$string['btn_generate_outline'] = 'Generate course';
$string['btn_generate_section'] = 'Generate section';
$string['btn_plan_section'] = 'Plan section';
$string['btn_populate'] = 'Populate course';
$string['courseplantitle'] = 'Course plan';
$string['error_outline_generation'] = 'The outline could not be generated. Please try again.';
$string['error_populate'] = 'The course could not be populated. Please try again.';
$string['error_section_plan'] = 'The section plan could not be generated. Please try again.';
$string['error_theme_required'] = 'Please enter the theme or content focus.';
$string['estimate_time'] = 'Estimated time: ~{$a} min';
$string['estimate_time_short'] = 'Estimated time: < 1 min';
$string['event_course_generated'] = 'Course populated with StudioLMS';
$string['event_generation_failed'] = 'StudioLMS generation failed';
$string['fillwithai'] = 'StudioLMS';
$string['gamification_profile'] = 'Gamification profile';
$string['generate_heading'] = 'Generate course with StudioLMS';
$string['generating'] = 'Generating course...';
$string['glossary_default_title'] = 'Course glossary';
$string['glossary_intro'] = 'Key terms for this course.';
$string['invalidairesponse'] = 'The AI did not return a valid course outline. Please try again.';
$string['landing_activity_desc'] = 'Generate one activity in an existing section of this course.';
$string['landing_activity_title'] = 'Single Activity';
$string['landing_coming_soon'] = 'Coming soon';
$string['landing_course_desc'] = 'Generate the full course structure from a briefing and outline.';
$string['landing_course_title'] = 'Full Course';
$string['landing_heading'] = 'Hello {$a}, what would you like to do today?';
$string['landing_section_desc'] = 'Generate a complete section from your reference material.';
$string['landing_section_title'] = 'Section';
$string['mode_gamified'] = 'Gamified';
$string['mode_gamified_detected'] = 'PlayerHUD detected';
$string['mode_gamified_disabled'] = 'Install PlayerHUD to enable this mode';
$string['mode_standard'] = 'Standard';
$string['noai_admin'] = 'You can enable one now: open Moodle AI and configure a provider for text generation.';
$string['noai_adminlink'] = 'Configure Moodle AI';
$string['noai_heading'] = 'AI provider required';
$string['noai_intro'] = 'StudioLMS builds course content with AI, so an AI provider must be available before you can generate anything.';
$string['noai_teacher'] = 'Ask your site administrator to enable an AI provider (Site administration → AI → AI providers), or to install the AI Hub (local_aihub) so you can add a personal key.';
$string['noaiprovider'] = 'No AI provider is available. Configure a provider in Moodle AI (Site administration → AI → AI providers), install the AI Hub (local_aihub) and set an API key, or configure keys in the StudioLMS editor (tiny_studiolms).';
$string['objectives_heading'] = 'Learning objectives';
$string['outline_review_heading'] = 'Review course';
$string['pluginname'] = 'StudioLMS course builder';
$string['populate_heading'] = 'Populating the course';
$string['pretraining_title'] = 'Key concepts';
$string['privacy:metadata:local_studiolms_generation_log'] = 'A log of completed course generations run by the teacher.';
$string['privacy:metadata:local_studiolms_generation_log:bloomlevel'] = 'The selected Bloom\'s taxonomy level.';
$string['privacy:metadata:local_studiolms_generation_log:courseid'] = 'The course that was populated.';
$string['privacy:metadata:local_studiolms_generation_log:gamificationprofile'] = 'The selected gamification profile.';
$string['privacy:metadata:local_studiolms_generation_log:mode'] = 'The generation mode (standard or gamified).';
$string['privacy:metadata:local_studiolms_generation_log:outlinejson'] = 'The approved course outline.';
$string['privacy:metadata:local_studiolms_generation_log:prompt'] = 'The theme or prompt provided by the teacher.';
$string['privacy:metadata:local_studiolms_generation_log:status'] = 'The final status of the generation.';
$string['privacy:metadata:local_studiolms_generation_log:timecompleted'] = 'The time the generation completed.';
$string['privacy:metadata:local_studiolms_generation_log:timecreated'] = 'The time the generation started.';
$string['privacy:metadata:local_studiolms_generation_log:userid'] = 'The teacher who ran the generation.';
$string['privacy:metadata:local_studiolms_outline'] = 'Draft outlines saved between wizard steps.';
$string['privacy:metadata:local_studiolms_outline:briefingjson'] = 'The briefing entered in step 1 (theme, mode, level).';
$string['privacy:metadata:local_studiolms_outline:courseid'] = 'The target course of the draft.';
$string['privacy:metadata:local_studiolms_outline:outlinejson'] = 'The generated outline plus teacher edits.';
$string['privacy:metadata:local_studiolms_outline:status'] = 'The draft status.';
$string['privacy:metadata:local_studiolms_outline:timecreated'] = 'The time the draft was created.';
$string['privacy:metadata:local_studiolms_outline:timemodified'] = 'The time the draft was last modified.';
$string['privacy:metadata:local_studiolms_outline:userid'] = 'The teacher who owns the draft.';
$string['privacy:metadata:local_studiolms_progress'] = 'Background generation progress records.';
$string['privacy:metadata:local_studiolms_progress:courseid'] = 'The course being populated.';
$string['privacy:metadata:local_studiolms_progress:errormsg'] = 'The error message when the generation failed.';
$string['privacy:metadata:local_studiolms_progress:status'] = 'The progress status.';
$string['privacy:metadata:local_studiolms_progress:timecreated'] = 'The time the progress record was created.';
$string['privacy:metadata:local_studiolms_progress:timemodified'] = 'The time the progress record was last updated.';
$string['privacy:metadata:local_studiolms_progress:userid'] = 'The teacher whose generation is tracked.';
$string['profile_conquest'] = 'Conquest';
$string['profile_narrative'] = 'Narrative';
$string['profile_social'] = 'Social';
$string['progress_activity'] = 'Activity created: {$a}';
$string['progress_avatars'] = 'Avatar pack created.';
$string['progress_done'] = 'Course populated.';
$string['progress_drops'] = 'Item drops added: {$a}';
$string['progress_playercoin'] = 'PlayerCoin created.';
$string['progress_playerhud'] = 'PlayerHUD configured.';
$string['progress_quests'] = 'Quests created: {$a}';
$string['progress_section'] = 'Section added: {$a}';
$string['progress_social_forum'] = 'Hourly forum collectible added.';
$string['progress_story'] = 'Narrative chapter generated.';
$string['progress_trades'] = 'Trades created: {$a}';
$string['report_activities'] = 'Activities generated:';
$string['report_blocks'] = 'Custom blocks';
$string['report_degraded'] = 'simplified';
$string['report_duration'] = 'Total time: {$a}';
$string['report_fallback'] = 'Simplified (AI unavailable)';
$string['report_heading'] = 'Generation report';
$string['report_pages'] = 'Pages generated:';
$string['report_plan'] = 'Course plan (preset)';
$string['report_success'] = 'successful';
$string['section_done'] = 'Section populated.';
$string['section_generate_another'] = 'Generate another section';
$string['section_heading'] = 'Generate section';
$string['section_new'] = 'New section (add to end of course)';
$string['section_number'] = 'Section {$a}';
$string['section_plan_add'] = 'Add activity';
$string['section_plan_heading'] = 'Activity plan';
$string['section_planning'] = 'Planning section activities...';
$string['section_success'] = 'Section populated successfully.';
$string['section_view'] = 'View section';
$string['section_wipe'] = 'Overwrite section (deletes current activities)';
$string['section_wipe_warning'] = 'This permanently removes the existing activities in this section before generating. Use with care.';
$string['section_wiping'] = 'Removing existing activities...';
$string['step_of'] = 'Step {$a->current} of {$a->total}';
$string['structure_abc'] = 'ABC Learning Design';
$string['structure_free'] = 'Free (AI decides)';
$string['studiolms:generate'] = 'Fill a course with AI using StudioLMS';
$string['studiolms:viewlog'] = 'View the StudioLMS generation log';
$string['task_generate_course'] = 'Populate a course with StudioLMS';
$string['task_generate_section'] = 'Populate a course section with StudioLMS';
$string['warnings_heading'] = 'Some activities used simplified content because the AI was unavailable:';
