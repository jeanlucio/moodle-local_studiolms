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
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Templates from 'core/templates';
import Config from 'core/config';
import Notification from 'core/notification';
import * as Step2 from 'local_studiolms/wizard_step2';
import * as Step3 from 'local_studiolms/wizard_step3';

const SELECTORS = {
    form: 'local-studiolms-step1',
    wrapper: 'local-studiolms-step1-wrapper',
    step2: 'local-studiolms-step2',
};

let initialised = false;

// Returns the value of the checked radio with the given name, or an empty string.
const getRadioValue = (form, name) => {
    const checked = form.querySelector('input[name="' + name + '"]:checked');
    return checked === null ? '' : checked.value;
};

// Renders the reviewed outline (step 2) and transitions away from the briefing.
const showStep2 = async(form, response) => {
    const wrapper = document.getElementById(SELECTORS.wrapper);
    const step2Container = document.getElementById(SELECTORS.step2);
    const courseid = form.dataset.courseid;

    const steplabel = await getString('step_of', 'local_studiolms', {current: 2, total: 3});

    const typeKeys = ['page', 'label', 'quiz', 'forum', 'assign', 'glossary'];
    const typeLabels = await getStrings(typeKeys.map(key => ({key: 'activity_' + key, component: 'local_studiolms'})));
    const activitytypes = typeKeys.map((value, index) => ({value: value, label: typeLabels[index]}));

    const sections = response.sections.map(section => ({
        title: section.title,
        activities: section.activities.map(activity => ({
            title: activity.title,
            typeoptions: activitytypes.map(option => ({
                value: option.value,
                label: option.label,
                selected: option.value === activity.type,
            })),
        })),
    }));

    const context = {
        outlineid: response.outlineid,
        courseid: parseInt(courseid, 10),
        cancelurl: Config.wwwroot + '/course/view.php?id=' + courseid,
        steplabel: steplabel,
        objectives: response.objectives,
        sections: sections,
        activitytypes: activitytypes,
    };

    const {html, js} = await Templates.renderForPromise('local_studiolms/wizard_step2', context);
    Templates.replaceNodeContents(step2Container, html, js);
    wrapper.classList.add('d-none');
    step2Container.classList.remove('d-none');

    Step2.init(step2Container.firstElementChild, {
        onBack: () => {
            step2Container.classList.add('d-none');
            step2Container.innerHTML = '';
            wrapper.classList.remove('d-none');
        },
        onPopulate: outline => populate(form, step2Container, outline),
    });
};

// Queues the background population and shows the progress step.
const populate = async(form, step2Container, outline) => {
    try {
        const response = await fetchMany([{
            methodname: 'local_studiolms_populate_course',
            args: {
                courseid: parseInt(form.dataset.courseid, 10),
                outlineid: outline.outlineid,
                wipe: form.querySelector('#studiolms-wipe').checked,
                objectives: outline.objectives,
                sections: outline.sections,
            },
        }])[0];

        const steplabel = await getString('step_of', 'local_studiolms', {current: 3, total: 3});
        const {html, js} = await Templates.renderForPromise('local_studiolms/wizard_step3', {steplabel: steplabel});
        Templates.replaceNodeContents(step2Container, html, js);
        Step3.init(step2Container.firstElementChild, {progressid: response.progressid});
    } catch (error) {
        const button = step2Container.querySelector('[data-action="populate"]');
        if (button !== null) {
            button.removeAttribute('disabled');
            const spinner = button.querySelector('[data-region="spinner"]');
            if (spinner !== null) {
                spinner.classList.add('d-none');
            }
        }
        Notification.exception(error);
    }
};

// Validates the briefing, calls the outline web service and moves to step 2.
const handleSubmit = async form => {
    const themeInput = form.querySelector('#studiolms-theme');
    const referenceInput = form.querySelector('#studiolms-reference');
    const errorRegion = form.querySelector('[data-region="error"]');
    const spinner = form.querySelector('[data-region="spinner"]');
    const submitButton = form.querySelector('[data-action="generate"]');
    const buttonLabel = form.querySelector('[data-region="btnlabel"]');

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
                bloom: getRadioValue(form, 'bloom'),
                structure: getRadioValue(form, 'structure'),
                mode: getRadioValue(form, 'mode'),
                profile: getRadioValue(form, 'profile'),
            },
        }])[0];

        await showStep2(form, response);
    } catch (error) {
        const fallback = await getString('error_outline_generation', 'local_studiolms');
        errorRegion.textContent = error && error.message ? error.message : fallback;
        errorRegion.classList.remove('d-none');
    } finally {
        spinner.classList.add('d-none');
        submitButton.removeAttribute('disabled');
        buttonLabel.textContent = defaultLabel;
    }
};

// Initialises the briefing form using event delegation on the document.
// Delegation keeps the wizard working regardless of when the module loads
// or whether the content nodes are re-rendered after page load.
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('change', event => {
        const form = document.getElementById(SELECTORS.form);
        if (form === null) {
            return;
        }
        if (event.target.matches('#' + SELECTORS.form + ' input[name="mode"]')) {
            const gamified = getRadioValue(form, 'mode') === 'gamified';
            form.querySelector('[data-region="profiles"]').classList.toggle('d-none', !gamified);
        } else if (event.target.id === 'studiolms-wipe') {
            form.querySelector('[data-region="wipewarning"]').classList.toggle('d-none', !event.target.checked);
        }
    });

    document.addEventListener('submit', event => {
        if (event.target.id === SELECTORS.form) {
            event.preventDefault();
            handleSubmit(event.target);
        }
    });
};
