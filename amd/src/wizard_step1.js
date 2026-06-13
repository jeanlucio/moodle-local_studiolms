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
 * Briefing step (step 1) of the StudioLMS course builder wizard.
 *
 * @module     local_studiolms/wizard_step1
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

// Initialises the briefing form behaviour.
export const init = () => {
    const form = document.getElementById('local-studiolms-step1');
    if (form === null) {
        return;
    }

    const themeInput = form.querySelector('#studiolms-theme');
    const errorRegion = form.querySelector('[data-region="error"]');
    const profilesRegion = form.querySelector('[data-region="profiles"]');
    const wipeWarning = form.querySelector('[data-region="wipewarning"]');
    const wipeCheckbox = form.querySelector('#studiolms-wipe');
    const spinner = form.querySelector('[data-region="spinner"]');
    const submitButton = form.querySelector('[data-action="generate"]');
    const buttonLabel = form.querySelector('[data-region="btnlabel"]');

    // Reveal the gamification profiles only when the gamified mode is selected.
    const modeRadios = form.querySelectorAll('input[name="mode"]');
    modeRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            const checkedMode = form.querySelector('input[name="mode"]:checked');
            const gamified = checkedMode !== null && checkedMode.value === 'gamified';
            profilesRegion.classList.toggle('d-none', !gamified);
        });
    });

    // Reveal the destructive warning only when the wipe option is ticked.
    wipeCheckbox.addEventListener('change', () => {
        wipeWarning.classList.toggle('d-none', !wipeCheckbox.checked);
    });

    form.addEventListener('submit', async(event) => {
        event.preventDefault();
        errorRegion.classList.add('d-none');

        if (themeInput.value.trim() === '') {
            errorRegion.textContent = await getString('error_theme_required', 'local_studiolms');
            errorRegion.classList.remove('d-none');
            themeInput.focus();
            return;
        }

        // Phase 1 only surfaces the loading state; the outline web service
        // arrives in phase 2.
        spinner.classList.remove('d-none');
        submitButton.setAttribute('disabled', 'disabled');
        buttonLabel.textContent = await getString('generating', 'local_studiolms');
    });
};
