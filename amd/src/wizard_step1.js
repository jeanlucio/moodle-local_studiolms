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

import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';
import {renderForPromise, replaceNodeContents} from 'core/templates';
import Config from 'core/config';
import * as Step2 from 'local_studiolms/wizard_step2';

// Initialises the briefing form behaviour.
export const init = () => {
    const wrapper = document.getElementById('local-studiolms-step1-wrapper');
    const form = document.getElementById('local-studiolms-step1');
    const step2Container = document.getElementById('local-studiolms-step2');
    if (form === null || wrapper === null || step2Container === null) {
        return;
    }

    const themeInput = form.querySelector('#studiolms-theme');
    const referenceInput = form.querySelector('#studiolms-reference');
    const errorRegion = form.querySelector('[data-region="error"]');
    const profilesRegion = form.querySelector('[data-region="profiles"]');
    const wipeWarning = form.querySelector('[data-region="wipewarning"]');
    const wipeCheckbox = form.querySelector('#studiolms-wipe');
    const spinner = form.querySelector('[data-region="spinner"]');
    const submitButton = form.querySelector('[data-action="generate"]');
    const buttonLabel = form.querySelector('[data-region="btnlabel"]');

    const getRadioValue = name => {
        const checked = form.querySelector(`input[name="${name}"]:checked`);
        return checked === null ? '' : checked.value;
    };

    // Reveal the gamification profiles only when the gamified mode is selected.
    form.querySelectorAll('input[name="mode"]').forEach(radio => {
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

    const resetButton = defaultLabel => {
        spinner.classList.add('d-none');
        submitButton.removeAttribute('disabled');
        buttonLabel.textContent = defaultLabel;
    };

    const showStep2 = async response => {
        const steplabel = await getString('step_of', 'local_studiolms', {current: 2, total: 3});
        const context = {
            outlineid: response.outlineid,
            courseid: parseInt(form.dataset.courseid, 10),
            cancelurl: `${Config.wwwroot}/course/view.php?id=${form.dataset.courseid}`,
            steplabel: steplabel,
            hasobjectives: response.objectives.length > 0,
            objectives: response.objectives,
            sections: response.sections,
        };

        const {html, js} = await renderForPromise('local_studiolms/wizard_step2', context);
        replaceNodeContents(step2Container, html, js);
        wrapper.classList.add('d-none');
        step2Container.classList.remove('d-none');

        Step2.init(step2Container.firstElementChild, {
            onBack: () => {
                step2Container.classList.add('d-none');
                step2Container.innerHTML = '';
                wrapper.classList.remove('d-none');
            },
        });
    };

    form.addEventListener('submit', async event => {
        event.preventDefault();
        errorRegion.classList.add('d-none');

        if (themeInput.value.trim() === '') {
            errorRegion.textContent = await getString('error_theme_required', 'local_studiolms');
            errorRegion.classList.remove('d-none');
            themeInput.focus();
            return;
        }

        const defaultLabel = buttonLabel.textContent;
        spinner.classList.remove('d-none');
        submitButton.setAttribute('disabled', 'disabled');
        buttonLabel.textContent = await getString('generating', 'local_studiolms');

        try {
            const response = await fetchMany([{
                methodname: 'local_studiolms_generate_outline',
                args: {
                    courseid: parseInt(form.dataset.courseid, 10),
                    theme: themeInput.value.trim(),
                    reference: referenceInput.value,
                    bloom: getRadioValue('bloom'),
                    structure: getRadioValue('structure'),
                    mode: getRadioValue('mode'),
                    profile: getRadioValue('profile'),
                },
            }])[0];

            await showStep2(response);
            resetButton(defaultLabel);
        } catch (error) {
            const fallback = await getString('error_outline_generation', 'local_studiolms');
            errorRegion.textContent = error && error.message ? error.message : fallback;
            errorRegion.classList.remove('d-none');
            resetButton(defaultLabel);
        }
    });
};
