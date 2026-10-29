# 🎓 Educational Purpose

StudioLMS is designed to:

* turn days of manual course setup into minutes;
* give teachers, not only administrators, an accessible course builder that works inside their own course;
* produce pedagogically structured content, not just plain text, grounded in learning science;
* give the text a teacher already wrote the same visual structure, without rewriting it;
* keep everything native and free of lock-in, editable like any Moodle course;
* optionally add motivation through PlayerHUD gamification.

It deliberately works on a course that **already exists**. Editing your own course (`moodle/course:manageactivities`) is what an editing teacher actually holds, whereas creating a course from scratch needs the administrator-level `moodle/course:create`.

## Pedagogical frameworks

* **Bloom's Taxonomy** (visible to the teacher): the wizard asks for the predominant cognitive level, which shapes the type and tone of the generated activities.
* **Backward Design** (behind the scenes): the AI produces objectives, then assessments, then learning activities, so the three fit together.
* **ABC Learning Design** (optional preset): a balanced mix of activity types per section.
* **Octalysis** (gamified mode): PlayerHUD features are mapped to the eight core drives.
