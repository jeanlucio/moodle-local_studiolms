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
 * Primary engine: the local_playergames hub (cartridge\ai_generator), which owns
 * the canonical key precedence (personal → site → core_ai). When the hub is not
 * installed, Moodle core_ai is called directly. When neither is available, a clear
 * message asks the admin to configure AI. The integration with the hub is soft
 * (class_exists), so local_studiolms does not hard-depend on it.
 */
class ai_resolver {
    /**
     * Generates free text from the resolved AI provider.
     *
     * @param string $systemprompt System instruction text.
     * @param string $userprompt User prompt text.
     * @return string The generated content as returned by the provider.
     * @throws \moodle_exception When no AI provider is available.
     */
    public static function generate_text(string $systemprompt, string $userprompt): string {
        // Primary: the PlayerGames hub resolves personal → site → core_ai internally.
        if (class_exists('\local_playergames\cartridge\ai_generator')) {
            $hub = new \local_playergames\cartridge\ai_generator();
            if ($hub->has_key()) {
                return $hub->generate_text($systemprompt, $userprompt);
            }
        }

        // Fallback: Moodle core_ai directly when the hub is not installed.
        if (self::has_core_ai_provider()) {
            return self::call_core_ai($systemprompt, $userprompt);
        }

        // No AI source available: guide the admin to configure one.
        throw new \moodle_exception('noaiprovider', 'local_studiolms');
    }

    /**
     * Returns true when Moodle core_ai has a provider enabled for text generation.
     *
     * Compatible with Moodle 4.5 (static API) and 5.x (instance API with DB injection).
     *
     * @return bool
     */
    private static function has_core_ai_provider(): bool {
        global $DB;

        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }

        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $reflection = new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions');
            if ($reflection->isStatic()) {
                $providers = \core_ai\manager::get_providers_for_actions([$actionclass], true);
            } else {
                $providers = (new \core_ai\manager($DB))->get_providers_for_actions([$actionclass], true);
            }
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
        global $DB, $USER;

        $reflection = new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions');
        $manager = $reflection->isStatic() ? new \core_ai\manager() : new \core_ai\manager($DB);

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
