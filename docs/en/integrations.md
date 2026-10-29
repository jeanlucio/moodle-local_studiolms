# 🔗 Integrations

## Required: at least one source of AI

StudioLMS generates everything with AI, so **one of these two must be available**. Without either, the wizard and the restyle show guidance instead of their forms, and nothing is generated.

| Source | What it needs |
|---|---|
| [`local_aihub`](https://github.com/jeanlucio/moodle-local_aihub) (the AI Hub, a plugin by the same author) | The plugin installed, and an API key (a personal one, or the site's) |
| `core_ai` (Moodle's AI subsystem, part of Moodle) | A provider configured and enabled for text generation, under **Site administration > AI** |

You need only one. If both are there, the AI Hub is asked first and `core_ai` answers when the hub is absent or fails. See [AI & Third-party Services](#ai).

## Optional integrations

None of these is needed. Every one is **soft** (the plugin checks that the other is there), and there are no hard `$plugin->dependencies`.

| Plugin | What it adds when present |
|---|---|
| `tiny_studiolms` | The rich visual blocks of generated and restyled pages, and the editor to adjust them. Without it, pages are clean semantic HTML and the restyle cannot reopen the blocks |
| `filter_studiolms` | The `[QUIZINFO]` marker in the introduction of a generated quiz, so its rules stay up to date. Used only where the filter is installed, has the widget on, and is active in the course |
| `block_playerhud` | The gamified mode |
