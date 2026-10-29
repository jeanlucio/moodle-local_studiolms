# 🔗 Optional Integrations

StudioLMS works alone on Moodle 4.5 or later. Every integration is **soft** (the plugin checks that the other one is there): there are no hard `$plugin->dependencies`.

| Plugin | What it adds when present |
|---|---|
| `tiny_studiolms` | The rich visual blocks of generated and restyled pages, and the editor to adjust them. Without it, pages are clean semantic HTML and the restyle cannot reopen the blocks |
| `filter_studiolms` | The `[QUIZINFO]` marker in the introduction of a generated quiz, so its rules stay up to date. Used only where the filter is installed, has the widget on, and is active in the course |
| `block_playerhud` | The gamified mode |
| `local_aihub` | The AI Hub: keys of the user or of the site |
| `core_ai` (Moodle) | The AI providers configured on the site |
