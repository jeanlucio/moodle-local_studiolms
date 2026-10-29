# 🔐 Security & Compliance

* **Access is always checked in the course:** `require_login($course)` and `require_capability('local/studiolms:generate', ...)` in the course context, checked again in each web service and again inside the background task, since a permission can be revoked between the click and the cron.
* **Subordinate to Moodle's own capabilities:** creating content also requires `moodle/course:manageactivities`, `moodle/course:update` (for new sections) and `mod/<type>:addinstance`; restyling requires the backup, restore and add capabilities of the activity. The plugin's capabilities never grant more than those.
* **No course creation:** the wizard works on an existing course, which removes the `course:create` surface.
* **Destructive actions are guarded:** the optional wipe needs `moodle/course:manageactivities` and an explicit confirmation, keeps the Announcements forum, and an outline with nothing to build is refused before anything is deleted. The restyle never writes to the original.
* **Records are scoped by owner and course:** a progress row is read only by the user who owns it, and an outline only when its id, user and course all match.
* **AI output is untrusted input:** AI JSON is checked against the shape expected, text goes through `clean_param()` and HTML through `clean_text()`, and in the polish mode links are allowed only if the original had them, scripts are removed, and pictures, tables and videos are never taken from the AI.
* **Web services:** declared in `db/services.php`, called through Moodle's AJAX layer (which checks the session key and the context). A reference to a section is always looked up inside the course, never by its id alone.
* **The AI switch of the course** is respected at every entry point.
* **Privacy API:** export and deletion for the three tables of a teacher's data, and the rows are removed when the course is deleted.
* **Audited:** the plugin was audited on 29 October 2026 and had no security finding; the code-quality findings were fixed.
