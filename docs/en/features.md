# ✨ Features

* 🪄 **In-course AI wizard:** open the course and click **StudioLMS** in the course navigation. The wizard runs in the course context, so it never asks for a category or a course name: the course already exists.
* 🎚️ **Three levels of generation**, chosen on the landing:
  * **Activity or resource:** one activity or resource of any of the six types (page, label, quiz, forum, assignment or glossary), in a section you pick (runs while you wait).
  * **Section:** the AI plans the activities of one existing (or new) section from reference material, you review the plan, and the section is built in the background.
  * **Full course:** outline, review, then generation in the background with live progress.
* 🧭 **Two modes:** **Standard** (pedagogical structure) and **Gamified** (adds PlayerHUD; offered only when `block_playerhud` is installed and you may add blocks to the course).
* 🗺️ **A plan that matches the course:** the first page of a generated course (the course plan) is written from the outline you approved: syllabus, objectives, how the course is organised, the sections as steps and a table of the graded activities, with no placeholder left to fill. The AI only adds a sentence for each section.
* 🎯 **Objectives the course really covers:** the outline asks the AI which objective each section serves, asks again for an objective no section serves and, if it stays unserved, leaves it out.
* 💡 **Key terms of the page:** the "Key concepts" box of a page lists only the glossary terms that page uses.
* 📑 **Six kinds of activity, all native Moodle content:**
  * **Page:** AI content as rich Studio blocks (infographics, cards, callouts, grids) when `tiny_studiolms` is installed, or clean semantic HTML without it, with an automatic "Key concepts" callout taken from the course glossary.
  * **Label:** a styled heading that separates groups of activities; generated with the other activities of its section, it also lists them.
  * **Quiz:** questions made by the AI (`multichoice`, `truefalse`, `shortanswer`) go to the question bank and are attached to the quiz. Its introduction shows the rules (questions, attempts, time limit). With the optional `filter_studiolms` active in the course it holds the `[QUIZINFO]` marker, so the rules are calculated every time the page is shown and follow later changes and each student's exceptions. Without the filter the numbers are written once, at generation, and go stale if you change the quiz.
  * **Forum:** an introduction made of Studio blocks: the title, framing text, the guiding question, three steps to take part and a tip.
  * **Assignment:** a rich brief: what to hand in, the steps and the evaluation criteria as a table (the AI is asked not to invent dates or points).
  * **Glossary:** one course glossary, created first, with auto-linking on and an introduction that says how to use it. Its terms feed the "Key concepts" callouts of every page.
* 🖌️ **Restyle what you already wrote:** turns a page or label you wrote into Studio blocks, in a hidden copy. [Read more](#restyle).
* 🧩 **Editable blocks:** generated blocks carry the `data-slms-block-type` and `data-slms-state` wrappers, so they reopen for normal editing in the `tiny_studiolms` editor. Ready-made presets are used when one fits and the AI fills every placeholder of it; otherwise the AI composes blocks on the fly, with the editor's **Text** block for ordinary prose, so a lesson reads as text and not as a stack of boxes. A preset the generator cannot draw completely is never offered to the AI.
* 🛟 **Safe by default:** StudioLMS adds content and does not touch what is already there. The optional "wipe first" asks you to confirm, says that what the students did goes too, and never deletes the Announcements forum. An outline with no titled section is refused before anything is deleted.
* ⏳ **Background generation:** sections and full courses run as an adhoc task with live progress and a final report (sections, activities by type, quiz questions, glossary terms, preset per page).
* 🩹 **Surgical rollback:** the course is never deleted. Each section and module created is tracked, and if a step fails only those are removed.
* 🎮 **Gamified mode (PlayerHUD):** reuses PlayerHUD's own generators: a PlayerCoin dropped in the news forum, an avatar pack, coin-to-avatar trades and quests (deterministic, no AI), plus an AI narrative chapter and themed item drops. Three profiles (Conquest, Narrative, Social) tune XP, quests, ranking and drops; Social also adds an hourly collectible to every forum it generates.
* 🌐 **Content language:** everything is generated in the teacher's interface language, passed explicitly in each prompt.
* 📊 **Audit and events:** a generation log and the `course_generated` and `generation_failed` events in Moodle's log.
* 🔒 **Privacy API:** export and deletion of the three tables that hold a teacher's data (generation log, draft outline, progress).
