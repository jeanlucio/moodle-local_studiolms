# Changelog — local_studiolms

All notable changes to this plugin are documented here.

---

## [v1.0.0] — 2026-10-29

First public release.

### Added
- A wizard that builds course content from inside a course that already exists: the teacher describes the
  topic, reviews the outline the AI proposes, and the course is built in the background with sections,
  pages made of StudioLMS blocks, quizzes with questions, forums, assignments, glossaries and labels. It can
  also build a single section or a single activity.
- A course plan page built from the approved outline (objectives, how the course is organised, the stages of
  each section and a table of the graded activities), and an outline in which every objective is served by a
  section.
- Pages that use the ready-made layouts of the editor when one fits, free blocks when it does not, and plain
  text for ordinary explanation. A layout left with unfilled markers is refused and the page is planned again.
- A progress screen and a report of what was built, with every activity the AI could not fill marked as
  simplified. A run that fails removes what it had created.
- A restyle of a page or label that already exists, which writes the result into a hidden copy and leaves the
  original untouched. An optional polish mode rewrites the text, and tells the teacher about any number from
  the original that is missing in the result.
- Optional PlayerHUD gamification for a new course, with a narrative or a conquest profile, for teachers who
  may add that block.
- The AI comes from the AI Hub or Moodle's `core_ai`; the plugin stores no API key. The switch for AI tools of
  the course holds for the whole run, the AI usage policy of Moodle is accepted before the first request, and
  the use is logged under the course.
- Permissions for generating and for restyling, on top of the Moodle capabilities that govern creating and
  duplicating activities; the generation checks them again when its background task runs.
- Privacy API (the outlines, the progress and the log are exported and erased, and erased with the course),
  and a declaration of the text sent to the AI provider.
- Support for Moodle 4.5 to 5.3.
