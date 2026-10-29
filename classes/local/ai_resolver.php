<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AI provider resolver for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Delegates free-text AI generation across the ecosystem's AI sources.
 *
 * Resolution order: the AI Hub (local_aihub) first, which resolves BYOK keys
 * personal → site; then Moodle core_ai directly. When neither is available, a
 * clear message asks the admin to configure AI. The hub integration is soft
 * (class_exists), so local_studiolms does not hard-depend on it.
 */
class ai_resolver {
    /** @var callable|null Deterministic provider injected by tests, bypassing real AI. */
    private static $testingprovider = null;

    /**
     * Injects a deterministic provider for tests, bypassing the real AI chain.
     *
     * The callable receives the system and user prompts and returns the generated
     * string. Only honoured under PHPUnit or Behat; a no-op (and ignored) otherwise.
     * Pass null to clear the override.
     *
     * @param callable|null $provider fn(string $system, string $user): string, or null.
     * @return void
     */
    public static function set_provider_for_testing(?callable $provider): void {
        if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
            return;
        }
        self::$testingprovider = $provider;
    }

    /**
     * Tells whether the course (or the activity) of a context has the AI tools turned on.
     *
     * The switch is a setting of the course from Moodle 5.0 and is asked to core_ai; before it, and where core_ai is
     * not there, nothing says otherwise and the answer is yes. It is the same for every AI source: a course that
     * has the AI tools off is not sent to the AI Hub either.
     *
     * @param \context $context The context of the course or of an activity in it.
     * @return bool
     */
    public static function is_enabled_in_context(\context $context): bool {
        if (!class_exists(\core_ai\manager::class) || !class_exists(\core_ai\aiactions\generate_text::class)) {
            return true;
        }
        try {
            $manager = \core\di::get(\core_ai\manager::class);
        } catch (\Throwable $e) {
            return true;
        }
        if (!method_exists($manager, 'is_action_enabled_in_context')) {
            return true;
        }
        return (bool) $manager->is_action_enabled_in_context($context, \core_ai\aiactions\generate_text::class);
    }

    /**
     * Refuses to go on when the course has the AI tools turned off.
     *
     * @param \context $context The context of the course or of an activity in it.
     * @return void
     * @throws \moodle_exception When the AI tools are off in the course.
     */
    public static function require_enabled_in_context(\context $context): void {
        if (!self::is_enabled_in_context($context)) {
            throw new \moodle_exception('aidisabledincourse', 'local_studiolms');
        }
    }

    /**
     * Returns true when any AI source in the resolution chain can be used.
     *
     * Mirrors the order of generate_text() without performing a call, so the
     * wizard can fail fast (a friendly panel before the briefing, a clear error
     * before queueing a background task) instead of letting an existential
     * dependency surface only when generation runs.
     *
     * @return bool
     */
    public static function is_available(): bool {
        if (self::$testingprovider !== null && (defined('PHPUNIT_TEST') || defined('BEHAT_SITE_RUNNING'))) {
            return true;
        }

        // Behat-only override: the test seam is process-local, so a Behat web
        // request cannot see an injected provider. A scenario sets this config to
        // exercise the wizard as if AI were available; left unset, the no-provider
        // panel is shown. Guarded by BEHAT_SITE_RUNNING, so it is inert in production.
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING && get_config('local_studiolms', 'behatfakeai')) {
            return true;
        }

        if (class_exists(\local_aihub\ai::class) && \local_aihub\ai::is_available()) {
            return true;
        }

        return self::has_core_ai_provider();
    }

    /**
     * Tells whether a user has accepted the site's AI usage policy.
     *
     * What the teacher types and the text of the activities go to an external provider, so the policy of the site is
     * shown before the first use, as the core's own AI placements do. The core keeps the answer. An older Moodle
     * without that policy has nothing to accept.
     *
     * @param int $userid The user.
     * @return bool
     */
    public static function policy_agreed(int $userid): bool {
        if (!class_exists(\core_ai\manager::class) || !method_exists(\core_ai\manager::class, 'get_user_policy_status')) {
            return true;
        }
        // Behat-only: a scenario with a canned AI answer sends nothing anywhere, so it has no policy to accept. Inert
        // in production, like the canned answer itself.
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING && get_config('local_studiolms', 'behatfakeai')) {
            return true;
        }
        return (bool) \core_ai\manager::get_user_policy_status($userid);
    }

    /**
     * Generates free text from the resolved AI provider.
     *
     * @param string $systemprompt System instruction text.
     * @param string $userprompt User prompt text.
     * @param \context|null $context Where the text is for. When core_ai is the source, the course's own switch for
     *                               AI tools is respected there; null stands for the whole site.
     * @return string The generated content as returned by the provider.
     * @throws \moodle_exception When no AI provider is available.
     */
    public static function generate_text(string $systemprompt, string $userprompt, ?\context $context = null): string {
        // Test seam: a deterministic provider injected by PHPUnit/Behat wins.
        if (
            self::$testingprovider !== null
            && (defined('PHPUNIT_TEST') || defined('BEHAT_SITE_RUNNING'))
        ) {
            return (string) (self::$testingprovider)($systemprompt, $userprompt);
        }

        // The switch of the course holds for every source, the AI Hub included.
        if ($context !== null) {
            self::require_enabled_in_context($context);
        }

        // Behat-only canned answer: a Behat web request cannot see the provider a test injects, so a scenario stores
        // the answer in this config. Guarded by BEHAT_SITE_RUNNING, so it is inert in production.
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            $canned = get_config('local_studiolms', 'behatfakeai_response');
            if ($canned !== false && $canned !== '') {
                return (string) $canned;
            }
        }

        // Primary: the AI Hub resolves personal → site BYOK keys. On a successful
        // generation return its text; otherwise fall through to the next source.
        if (class_exists(\local_aihub\ai::class)) {
            $result = \local_aihub\ai::generate_text(
                $systemprompt,
                $userprompt,
                false,
                'local_studiolms',
                get_string('aiusage', 'local_studiolms')
            );
            if (!empty($result['success'])) {
                return (string) $result['data'];
            }
        }

        // Fallback: Moodle core_ai directly when the hub is absent or has failed.
        if (self::has_core_ai_provider()) {
            return self::call_core_ai($systemprompt, $userprompt, $context);
        }

        // No AI source available: guide the admin to configure one.
        throw new \moodle_exception('noaiprovider', 'local_studiolms');
    }

    /**
     * Returns true when Moodle core_ai has a provider enabled for text generation.
     *
     * Compatible with Moodle 4.5+ — the manager is retrieved via the dependency
     * container, which injects the dependencies for the running version.
     *
     * @return bool
     */
    private static function has_core_ai_provider(): bool {
        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }

        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = \core\di::get(\core_ai\manager::class);
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            return !empty($providers[$actionclass]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generates text via the Moodle core_ai subsystem.
     *
     * core_ai's generate_text action has no separate system field, so the system
     * instruction is prepended to the user prompt.
     *
     * @param string $systemprompt System instruction (may be empty).
     * @param string $userprompt User prompt text.
     * @param \context|null $context Where the text is for; null stands for the whole site.
     * @return string The generated content.
     * @throws \moodle_exception On a failed or empty response, or when the course has the AI tools turned off.
     */
    private static function call_core_ai(string $systemprompt, string $userprompt, ?\context $context = null): string {
        global $USER;

        $manager = \core\di::get(\core_ai\manager::class);
        // The switch of the course was checked by generate_text(); a text for no course is for the whole site.
        $context = $context ?? \context_system::instance();

        $prompttext = $systemprompt !== '' ? ($systemprompt . "\n\n" . $userprompt) : $userprompt;
        $action = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: (int) $USER->id,
            prompttext: $prompttext,
        );

        $response = $manager->process_action($action);
        $content = $response->get_success()
            ? (string) ($response->get_response_data()['generatedcontent'] ?? '')
            : '';

        if ($content === '') {
            throw new \moodle_exception('invalidairesponse', 'local_studiolms');
        }

        return $content;
    }
}
