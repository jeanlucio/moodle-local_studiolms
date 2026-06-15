# Changelog — local_studiolms

All notable changes to this plugin are documented here.
Versions align with `$plugin->release` in `version.php`.

---

## v0.4.0 — 2026-06-15

- Added two events: `course_generated` (fired with the audit-log id when a
  generation finishes) and `generation_failed` (fired with the error message
  when a run fails and rolls back).
- Added a complete Privacy API provider declaring the three teacher-owned tables
  (generation log, draft outline, progress), with export and deletion by user,
  by context and by user list.
- Added a test seam (`ai_resolver::set_provider_for_testing`) honoured only under
  PHPUnit/Behat, so the generation pipeline can be exercised deterministically
  without calling a real AI provider.

---

## v0.3.0 — 2026-06-15

- The `tiny_studiolms` editor is no longer a hard dependency: StudioLMS now runs
  standalone on Moodle 4.5+ alone. When the editor is installed, generated blocks
  use its rich visual templates and reopen for editing as before; when it is
  absent, blocks fall back to clean semantic HTML (Bootstrap/core headings,
  alerts, cards, `<details>`, tables and lists) via a new plain renderer. The
  fallback displays on any theme but cannot be reopened in the visual editor.
- The AI mind map (whose structured generator lives in the editor) is skipped
  when the editor is absent, and the first page — which would use the editor's
  "Plano de Disciplina" preset — falls back to AI-generated custom blocks.
- Integration with the editor and the PlayerGames hub is fully soft
  (`class_exists` / component detection); the plugin declares no
  `$plugin->dependencies`.

---

## v0.2.5 — 2026-06-15

- The Moodle `core_ai` manager (used as the direct fallback when the PlayerGames
  hub is absent) is now retrieved through the dependency container
  (`\core\di::get(\core_ai\manager::class)`), the documented retrieval pattern,
  instead of a reflection-based constructor shim. Behaviour is unchanged.

---

## v0.2.4 — 2026-06-15

- Removed the now-unused "preferred AI provider" admin setting (the AI engine is
  resolved automatically: PlayerGames hub → `core_ai`). The AI settings heading no
  longer points to the editor for keys; it explains that AI comes from `core_ai`
  or the PlayerGames hub.

---

## v0.2.3 — 2026-06-15

- AI text generation now goes through the PlayerGames hub (`local_playergames`)
  when installed, which owns the canonical key precedence (personal → site →
  `core_ai`); otherwise Moodle `core_ai` is called directly. When neither is
  available, a clear message guides the admin to configure a provider. The hub is
  consumed softly via `class_exists` — the editor (`tiny_studiolms`) is no longer
  the AI source. (Removing the hard `tiny_studiolms` dependency, kept for the
  visual templates, is a separate step pending the plain-HTML fallback renderer.)
- Fixed a broken provider branch that called the hub's structured `generate()`
  statically with the wrong arguments; the resolver now calls the hub's generic
  `generate_text(system, user)`.

---

## v0.2.2 — 2026-06-15

- Generated activities now use "view to complete" completion tracking (the
  course gets completion enabled automatically if it was off). Labels are
  excluded, as view completion is not meaningful for visual separators. This
  also lets the gamified mode map activity-completion quests to the generated
  content.

---

## v0.2.1 — 2026-06-15

- Fixed the gamified mode adding no PlayerHUD block when the generation runs in
  the background task: the block instance is now inserted directly instead of
  through the page block manager, which cannot resolve a default region without
  a real page/theme context. Verified end to end on a live course.

---

## v0.2.0 — 2026-06-15

- Added the gamified generation mode (PlayerHUD integration). When the gamified
  mode is selected and `block_playerhud` is installed, the background generation
  now adds and configures a PlayerHUD block on the course: PlayerCoin with an
  hourly news-forum drop, the avatar pack, coin-to-avatar trades, heuristic
  quests mapped to the generated activities, an AI narrative chapter and themed
  AI item drops on key activities.
- Three gamification profiles tune the configuration: Conquest (heavier XP and
  quests), Narrative (story-focused, ranking hidden, one chapter per section)
  and Social (visible ranking plus an hourly collectible dropped in every
  generated forum to reward ongoing participation).
- Dropped the PlayerGroup integration: the plugin now focuses solely on
  PlayerHUD for gamification.

---

## v0.1.0 — 2026-06-13

- Initial alpha: in-course AI wizard that generates sections, pages with Studio
  visual blocks, glossary, quiz, forum and assignment from a briefing.
