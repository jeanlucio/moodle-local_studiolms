# 🤖 AI & Third-party Services

StudioLMS has **no key and no key screen of its own**. Text generation is resolved in this order, every step optional:

| Order | Source |
|------|--------|
| 1 | **[`local_aihub`](https://github.com/jeanlucio/moodle-local_aihub)** (the AI Hub, by the same author): when installed and holding a key (personal, then site) |
| 2 | **Moodle `core_ai`**: called directly; no key needed when the site already has a provider |
| 3 | A clear message asks the administrator to configure AI (Site administration > AI), or to install the hub |

Calls are batched (one per quiz, one per outline) and a malformed answer is retried with a pause between attempts, to be kind to free provider tiers.

## The switch for AI tools of the course

From Moodle 5.0 each course has a switch for AI tools. StudioLMS respects it **before any source, the AI Hub included**: the five web services of the wizard and the restyle refuse a course that has the tools off, the background tasks check again when they run (it can be turned off between the click and the cron), and the wizard and the restyle show a notice instead of their forms. Before Moodle 5.0 there is no switch, so nothing is checked.

## Third-party service disclosure

AI is essential to the plugin, but it relies entirely on the AI the site already provides.

* **What leaves the site:** the teacher's theme, any pasted reference material, and, when restyling, the text of the page or label. It goes to the provider the site resolved (the AI Hub or `core_ai`), under that provider's own terms and privacy policy.
* **What is kept:** StudioLMS does not store raw AI answers. It stores what the teacher typed in the wizard (theme and pasted material) in its own tables (draft outline and generation log), with no automatic expiry. They are exported and erased by the Privacy API and removed when the course is deleted. **Do not paste personal data into the reference field.**
* **When:** nothing is sent unless a generation or a restyle is explicitly started. The first time, the AI usage policy of the site is shown (when the site has one) and the answer is kept by the core; without accepting it nothing is sent.
* **AI output is untrusted:** it is validated and cleaned before it is shown or saved (see [Security](#security)).
