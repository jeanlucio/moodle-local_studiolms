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
 * Delegates free-text AI generation to the available provider.
 *
 * The default provider is the tiny_studiolms AI layer (a mandatory dependency),
 * which internally chains core_ai → Gemini → Groq → OpenAI-compatible and resolves
 * personal-first keys. local_playergames is used only when installed and selected
 * by the admin as the preferred provider.
 */
class ai_resolver {
    /**
     * Generates free text from the resolved AI provider.
     *
     * @param string $systemprompt System instruction text.
     * @param string $userprompt User prompt text.
     * @return string The generated content as returned by the provider.
     */
    public static function generate_text(string $systemprompt, string $userprompt): string {
        $preferred = get_config('local_studiolms', 'preferredprovider');

        // PlayerGames is used only when installed and explicitly preferred by the admin.
        if (
            $preferred === 'playergames'
                && class_exists('\local_playergames\cartridge\ai_generator')
        ) {
            return \local_playergames\cartridge\ai_generator::generate($userprompt);
        }

        // Default StudioLMS AI layer (mandatory dependency).
        return \tiny_studiolms\ai\generator::generate_text($systemprompt, $userprompt);
    }
}
