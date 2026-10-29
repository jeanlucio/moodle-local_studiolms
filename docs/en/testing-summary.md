# 🧪 Automated Tests

* **PHPUnit:** **314 tests**, run in CI on Moodle 4.5, 5.0, 5.1, 5.2 and 5.3. Line coverage of the logic is **98%** (3,522 of 3,584 lines, measured on 29 October 2026 with Xdebug). What is left uncovered is mostly code that only runs on one Moodle version, and the PlayerHUD generator, which has no test hook.
* **Behat:** three features (access to the wizard, the settings page, and the restyle list, the page tab, the restyle itself and the polish mode), run in CI with a canned AI answer that exists only while Behat runs.
* **Regression tests that fail without the fix:** each correction from the security audits comes with a test that was seen to fail first, and the parts that guard data (the news forum kept by a wipe, the empty outline refused before a wipe, the AI switch at every entry point) were checked by breaking the code on purpose.
* **Test hooks are inert in production:** the deterministic AI provider and the canned answers are read only when PHPUnit or Behat is running.
