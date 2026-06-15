# Changelog — local_studiolms

All notable changes to this plugin are documented here.
Versions align with `$plugin->release` in `version.php`.

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
