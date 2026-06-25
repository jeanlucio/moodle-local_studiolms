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
 * Delegates free-text AI generation to the PlayerGames ecosystem.
 *
 * Resolution order: the local_playergames hub (cartridge\ai_generator) first,
 * which owns the canonical key precedence (personal → site → core_ai); then the
 * tiny_studiolms editor's own keys when it is installed and has a key of its own;
 * then Moodle core_ai directly. When none is available, a clear message asks the
 * admin to configure AI. Every integration is soft (class_exists), so
 * local_studiolms does not hard-depend on the hub or the editor.
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
     * Generates free text from the resolved AI provider.
     *
     * @param string $systemprompt System instruction text.
     * @param string $userprompt User prompt text.
     * @return string The generated content as returned by the provider.
     * @throws \moodle_exception When no AI provider is available.
     */
    public static function generate_text(string $systemprompt, string $userprompt): string {
        // Test seam: a deterministic provider injected by PHPUnit/Behat wins.
        if (
            self::$testingprovider !== null
            && (defined('PHPUNIT_TEST') || defined('BEHAT_SITE_RUNNING'))
        ) {
            return (string) (self::$testingprovider)($systemprompt, $userprompt);
        }

        // Primary: the PlayerGames hub resolves personal → site → core_ai internally.
        if (class_exists('\local_playergames\cartridge\ai_generator')) {
            $hub = new \local_playergames\cartridge\ai_generator();
            if ($hub->has_key()) {
                return $hub->generate_text($systemprompt, $userprompt);
            }
        }

        // Fallback: the tiny_studiolms editor's own keys, when the hub is absent.
        if (class_exists('\tiny_studiolms\ai\generator') && self::tiny_has_key()) {
            return \tiny_studiolms\ai\generator::generate_text($systemprompt, $userprompt);
        }

        // Fallback: Moodle core_ai directly when neither the hub nor the editor apply.
        if (self::has_core_ai_provider()) {
            return self::call_core_ai($systemprompt, $userprompt);
        }

        // No AI source available: guide the admin to configure one.
        throw new \moodle_exception('noaiprovider', 'local_studiolms');
    }

    /**
     * Returns true when the tiny_studiolms editor has an AI key of its own.
     *
     * Checks only the editor's own personal preferences and site config; the
     * PlayerGames hub tiers it also consults are intentionally ignored, since this
     * branch runs only when the hub is absent. Mirrors the hub's has_key() gate so
     * that, with no editor key, resolution falls through to core_ai cleanly.
     *
     * @return bool
     */
    private static function tiny_has_key(): bool {
        $personal = (string) get_user_preferences('tiny_studiolms_gemini_key', '')
            . (string) get_user_preferences('tiny_studiolms_groq_key', '')
            . (string) get_user_preferences('tiny_studiolms_custom_key', '');
        if ($personal !== '') {
            return true;
        }

        $site = (string) get_config('tiny_studiolms', 'apikey_gemini')
            . (string) get_config('tiny_studiolms', 'apikey_groq')
            . (string) get_config('tiny_studiolms', 'apikey_custom');

        return $site !== '';
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
     * @return string The generated content.
     * @throws \moodle_exception On a failed or empty response.
     */
    private static function call_core_ai(string $systemprompt, string $userprompt): string {
        global $USER;

        $manager = \core\di::get(\core_ai\manager::class);

        $prompttext = $systemprompt !== '' ? ($systemprompt . "\n\n" . $userprompt) : $userprompt;
        $action = new \core_ai\aiactions\generate_text(
            contextid: \context_system::instance()->id,
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
