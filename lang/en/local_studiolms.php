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
$string['activity_heading'] = 'Generate an activity or resource';
$string['activity_label'] = 'Label';
$string['activity_page'] = 'Page';
$string['activity_quiz'] = 'Quiz';
$string['activity_section'] = 'Target section';
$string['activity_success'] = 'Created successfully.';
$string['activity_theme'] = 'Theme / content focus (optional: the title is used if left empty)';
$string['activity_view'] = 'Open the new content';
$string['activity_wait'] = 'This can take about a minute. Keep this page open.';
$string['add_activity'] = 'Add activity';
$string['add_objective'] = 'Add objective';
$string['add_section'] = 'Add section';
$string['aidisabledincourse'] = 'The AI tools are turned off in this course.';
$string['aiheading'] = 'Artificial intelligence';
$string['aikeys_info'] = 'StudioLMS generates content through the AI Hub (local_aihub) when installed, or Moodle core_ai. There is no API key to configure here — set up an AI provider under Site administration → AI, or in the AI Hub settings.';
$string['aiusage'] = 'Course content generation';
$string['aria_activity_title'] = 'Activity title';
$string['aria_activity_type'] = 'Type of activity or resource';
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
$string['duration_minutes'] = '{$a} min';
$string['duration_minutes_seconds'] = '{$a->minutes} min {$a->seconds} s';
$string['duration_seconds'] = '{$a} s';
$string['error_noactivities'] = 'The list of activities is empty or is not valid.';
$string['error_nosections'] = 'The outline has no section with a title, so nothing would be created. Add a section, or give each one a title, and try again.';
$string['error_outline_generation'] = 'The outline could not be generated. Please try again.';
$string['error_playerhud_permission'] = 'The PlayerHUD block was not added: you may not add blocks to this course.';
$string['error_populate'] = 'The course could not be populated. Please try again.';
$string['error_section_plan'] = 'The section plan could not be generated. Please try again.';
$string['error_theme_required'] = 'Please enter the theme or content focus.';
$string['error_title_required'] = 'Enter the title of the activity or resource.';
$string['error_unnamed_sections'] = 'Some sections have no title and would be left out. Give each one a title, or remove it, and try again.';
$string['error_unsupportedtype'] = 'The type of activity {$a} is not supported.';
$string['estimate_time'] = 'Estimated time: ~{$a} min';
$string['estimate_time_short'] = 'Estimated time: < 1 min';
$string['event_course_generated'] = 'Course populated with StudioLMS';
$string['event_generation_failed'] = 'StudioLMS generation failed';
$string['fillwithai'] = 'StudioLMS';
$string['gamification_profile'] = 'Gamification profile';
$string['generate_heading'] = 'Generate course with StudioLMS';
$string['generating'] = 'Generating course...';
$string['generating_activity'] = 'Generating…';
$string['generating_section'] = 'Generating section…';
$string['glossary_default_title'] = 'Course glossary';
$string['glossary_intro'] = 'Key terms for this course.';
$string['invalidairesponse'] = 'The AI did not return a valid course outline. Please try again.';
$string['landing_activity_desc'] = 'Generate one activity or resource (page, label, quiz, forum, assignment or glossary) in an existing section of this course.';
$string['landing_activity_title'] = 'Activity or resource';
$string['landing_coming_soon'] = 'Coming soon';
$string['landing_course_desc'] = 'Generate the full course structure from a briefing and outline.';
$string['landing_course_title'] = 'Full Course';
$string['landing_heading'] = 'Hello {$a}, what would you like to do today?';
$string['landing_restyle_desc'] = 'Reorganise a page or label you already wrote into Studio blocks. Your original is kept and the result goes to a hidden copy.';
$string['landing_restyle_title'] = 'Restyle existing';
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
$string['noaiprovider'] = 'No AI provider is available. Configure a provider in Moodle AI (Site administration → AI → AI providers), or install the AI Hub (local_aihub) and set an API key.';
$string['objectives_heading'] = 'Learning objectives';
$string['outline_review_heading'] = 'Review course';
$string['plan_assessment'] = 'Graded activities';
$string['plan_col_activity'] = 'Activity';
$string['plan_col_section'] = 'Section';
$string['plan_col_type'] = 'Type';
$string['plan_general'] = 'General objective';
$string['plan_heading'] = 'Course plan — {$a}';
$string['plan_method'] = 'How the course is organised';
$string['plan_method_text'] = 'The course has {$a->sections} sections and {$a->activities} activities.';
$string['plan_note_text'] = 'Read the whole plan before you start the activities. If you have questions, ask the teacher of the course.';
$string['plan_note_title'] = 'Note';
$string['plan_objectives'] = 'Course objectives';
$string['plan_organisation'] = 'Course organisation';
$string['plan_specific'] = 'Specific objectives';
$string['plan_syllabus'] = 'Syllabus';
$string['planning_section'] = 'Planning section…';
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
$string['privacy:metadata:local_studiolms_outline:briefingjson'] = 'The briefing entered in step 1 (theme, pasted reference material, mode, level).';
$string['privacy:metadata:local_studiolms_outline:courseid'] = 'The target course of the draft.';
$string['privacy:metadata:local_studiolms_outline:outlinejson'] = 'The generated outline plus teacher edits.';
$string['privacy:metadata:local_studiolms_outline:status'] = 'The draft status.';
$string['privacy:metadata:local_studiolms_outline:timecreated'] = 'The time the draft was created.';
$string['privacy:metadata:local_studiolms_outline:timemodified'] = 'The time the draft was last modified.';
$string['privacy:metadata:local_studiolms_outline:userid'] = 'The teacher who owns the draft.';
$string['privacy:metadata:local_studiolms_progress'] = 'Background generation progress records.';
$string['privacy:metadata:local_studiolms_progress:courseid'] = 'The course being populated.';
$string['privacy:metadata:local_studiolms_progress:createditems'] = 'The ids of the sections and activities created by the run, used for rollback.';
$string['privacy:metadata:local_studiolms_progress:errormsg'] = 'The error message when the generation failed.';
$string['privacy:metadata:local_studiolms_progress:message'] = 'The message describing the current step, which can include an activity title written by the teacher.';
$string['privacy:metadata:local_studiolms_progress:outlineid'] = 'The draft outline the run was started from.';
$string['privacy:metadata:local_studiolms_progress:reportjson'] = 'The per-activity generation report, which includes the activity titles written by the teacher.';
$string['privacy:metadata:local_studiolms_progress:status'] = 'The progress status.';
$string['privacy:metadata:local_studiolms_progress:step'] = 'The number of steps completed so far.';
$string['privacy:metadata:local_studiolms_progress:timecreated'] = 'The time the progress record was created.';
$string['privacy:metadata:local_studiolms_progress:timemodified'] = 'The time the progress record was last updated.';
$string['privacy:metadata:local_studiolms_progress:total'] = 'The total number of planned steps.';
$string['privacy:metadata:local_studiolms_progress:userid'] = 'The teacher whose generation is tracked.';
$string['privacy:metadata:local_studiolms_progress:warnings'] = 'The list of activities that used simplified content, identified by their titles.';
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
$string['report_plan'] = 'Course plan';
$string['report_success'] = 'successful';
$string['restyle_ai_notice'] = 'The text of the activity is sent to the AI provider only to plan the blocks. The AI does not rewrite it: your words stay exactly as they are, and your original is not changed.';
$string['restyle_button'] = 'Restyle';
$string['restyle_button_aria'] = 'Restyle {$a}';
$string['restyle_copy_ready'] = 'The hidden copy "{$a}" is ready.';
$string['restyle_copyname'] = '{$a} (StudioLMS)';
$string['restyle_error_title'] = 'The activity could not be restyled';
$string['restyle_heading'] = 'Restyle existing activities';
$string['restyle_hidden'] = 'Hidden';
$string['restyle_intro'] = 'Choose a page or label. StudioLMS organises its text into Studio blocks in a hidden copy, right after the original, so you can adjust it and decide what to publish.';
$string['restyle_menu'] = 'Restyle with StudioLMS';
$string['restyle_nochange'] = 'There is no text in this activity to organise into blocks.';
$string['restyle_noduplicate'] = 'Moodle could not duplicate this activity.';
$string['restyle_none'] = 'This course has no pages or labels to restyle.';
$string['restyle_noplan'] = 'The AI could not plan the blocks. Please try again.';
$string['restyle_notsupported'] = 'Only pages and labels can be restyled.';
$string['restyle_notsupportedformat'] = 'The text of this activity is not in HTML format, so it cannot be restyled.';
$string['restyle_numbers_missing'] = 'These numbers of your text are not in the copy: {$a}. Check that this is what you wanted.';
$string['restyle_open_copy'] = 'Open the copy to adjust it';
$string['restyle_polish'] = 'Reorganise and polish the text';
$string['restyle_polish_help'] = 'The AI may rewrite, shorten and split your text, and remove repetition, but it must not add facts. Your original is kept untouched and the copy is hidden, so review it before you publish it.';
$string['restyle_polish_length'] = 'The polished text came out too different in length from the original, so nothing was copied. Try again, or restyle without polishing.';
$string['restyle_polish_numbers'] = 'The AI wrote a number that is not in your text, so nothing was copied. Try again, or restyle without polishing.';
$string['restyle_polish_toolong'] = 'This text is too long to be polished at once, so nothing was copied. Restyle it without polishing, or split the page first.';
$string['restyle_view_copy'] = 'View the copy';
$string['restyle_working'] = 'Planning the blocks…';
$string['rich_assign_criteria'] = 'Evaluation criteria';
$string['rich_assign_criterion'] = 'Criterion';
$string['rich_assign_deliver'] = 'What to hand in';
$string['rich_assign_expectation'] = 'What is expected';
$string['rich_assign_steps'] = 'How to do it';
$string['rich_forum_question'] = 'Question for the discussion:';
$string['rich_forum_steps'] = 'How to take part';
$string['rich_glossary_howto'] = 'Use the search box and the letter filter to find a term.';
$string['rich_label_in_section'] = 'In this section';
$string['rich_no_limit'] = 'No limit';
$string['rich_quiz_attempts'] = 'Attempts';
$string['rich_quiz_intro'] = 'Answer the questions to check what you have learned.';
$string['rich_quiz_questions'] = 'Questions';
$string['rich_quiz_time'] = 'Time limit';
$string['rich_quiz_tip'] = 'Read each question carefully before you answer.';
$string['rich_unlimited'] = 'Unlimited';
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
$string['studiolms:restyle'] = 'Restyle an existing activity with StudioLMS';
$string['task_generate_course'] = 'Populate a course with StudioLMS';
$string['task_generate_section'] = 'Populate a course section with StudioLMS';
$string['warnings_heading'] = 'Some activities used simplified content because the AI was unavailable:';
$string['wipe_confirm_body'] = 'Every activity and every section will be deleted, together with what the students did in them, except the Announcements forum. This cannot be undone.';
$string['wipe_confirm_body_section'] = 'Every activity of this section will be deleted, together with what the students did in them, except the Announcements forum. This cannot be undone.';
$string['wipe_confirm_button'] = 'Delete and generate';
$string['wipe_confirm_title'] = 'Delete the content that is there?';
