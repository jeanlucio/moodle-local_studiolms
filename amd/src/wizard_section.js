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
 * Section-level generation form and inline progress polling for StudioLMS.
 *
 * @module     local_studiolms/wizard_section
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';
import Config from 'core/config';

const FORM_ID      = 'local-studiolms-section-form';
const WRAPPER_ID   = 'local-studiolms-section';
const POLL_INTERVAL = 2000;

let initialised = false;

export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('submit', async event => {
        if (event.target.id !== FORM_ID) {
            return;
        }
        event.preventDefault();
        await handleSubmit(event.target);
    });

    document.addEventListener('click', event => {
        if (event.target.closest('[data-action="generate-another"]') !== null) {
            resetToForm();
        }
    });
};

const handleSubmit = async form => {
    const wrapper    = document.getElementById(WRAPPER_ID);
    const errorRegion = form.querySelector('[data-region="error"]');
    const spinner    = form.querySelector('[data-region="spinner"]');
    const btnLabel   = form.querySelector('[data-region="btnlabel"]');
    const submitBtn  = form.querySelector('[data-action="generate-section"]');

    const courseid   = parseInt(form.dataset.courseid, 10);
    const sectionnum = parseInt(form.querySelector('#section-sectionnum').value, 10);
    const theme      = form.querySelector('#section-theme').value.trim();
    const reference  = form.querySelector('#section-reference').value;

    errorRegion.classList.add('d-none');

    if (theme === '') {
        errorRegion.textContent = await getString('error_theme_required', 'local_studiolms');
        errorRegion.classList.remove('d-none');
        return;
    }

    const defaultLabel = btnLabel.textContent;
    spinner.classList.remove('d-none');
    submitBtn.setAttribute('disabled', 'disabled');
    btnLabel.textContent = await getString('generating', 'local_studiolms');

    try {
        const result = await fetchMany([{
            methodname: 'local_studiolms_generate_section',
            args: {courseid, sectionnum, theme, reference, bloom: 'general'},
        }])[0];

        showProgressView(wrapper);
        startPolling(wrapper, result.progressid, courseid);
    } catch (error) {
        const fallback = await getString('error_populate', 'local_studiolms');
        errorRegion.textContent = error && error.message ? error.message : fallback;
        errorRegion.classList.remove('d-none');
        spinner.classList.add('d-none');
        submitBtn.removeAttribute('disabled');
        btnLabel.textContent = defaultLabel;
    }
};

const showProgressView = wrapper => {
    wrapper.querySelector('[data-region="form-view"]').classList.add('d-none');
    const progressView = wrapper.querySelector('[data-region="progress-view"]');
    progressView.classList.remove('d-none');
    progressView.querySelector('[data-region="done"]').classList.add('d-none');
    progressView.querySelector('[data-region="progress-error"]').classList.add('d-none');
    const bar = progressView.querySelector('[data-region="bar"]');
    bar.style.width = '0%';
    bar.setAttribute('aria-valuenow', '0');
};

const startPolling = (wrapper, progressid, courseid) => {
    const bar           = wrapper.querySelector('[data-region="bar"]');
    const messageEl     = wrapper.querySelector('[data-region="progress-message"]');
    const doneRegion    = wrapper.querySelector('[data-region="done"]');
    const errorRegion   = wrapper.querySelector('[data-region="progress-error"]');
    const viewLink      = wrapper.querySelector('[data-region="viewlink"]');

    const intervalId = setInterval(async() => {
        try {
            const progress = await fetchMany([{
                methodname: 'local_studiolms_get_progress',
                args: {progressid},
            }])[0];

            if (progress.total > 0) {
                const pct = Math.round((progress.step / progress.total) * 100);
                bar.style.width = `${pct}%`;
                bar.setAttribute('aria-valuenow', String(pct));
            }
            if (progress.message) {
                messageEl.textContent = progress.message;
            }

            if (progress.status === 'completed') {
                clearInterval(intervalId);
                bar.style.width = '100%';
                bar.setAttribute('aria-valuenow', '100');
                viewLink.href = `${Config.wwwroot}/course/view.php?id=${courseid}`;
                doneRegion.classList.remove('d-none');
            } else if (progress.status === 'failed') {
                clearInterval(intervalId);
                const msg = progress.errormsg
                    || await getString('error_populate', 'local_studiolms');
                errorRegion.textContent = msg;
                errorRegion.classList.remove('d-none');
            }
        } catch (e) {
            clearInterval(intervalId);
        }
    }, POLL_INTERVAL);
};

const resetToForm = () => {
    const wrapper = document.getElementById(WRAPPER_ID);
    if (wrapper === null) {
        return;
    }
    wrapper.querySelector('[data-region="progress-view"]').classList.add('d-none');
    const formView = wrapper.querySelector('[data-region="form-view"]');
    formView.classList.remove('d-none');
    const form = formView.querySelector(`#${FORM_ID}`);
    form.reset();
    form.querySelector('[data-region="error"]').classList.add('d-none');
    const spinner  = form.querySelector('[data-region="spinner"]');
    const btnLabel = form.querySelector('[data-region="btnlabel"]');
    const submitBtn = form.querySelector('[data-action="generate-section"]');
    spinner.classList.add('d-none');
    submitBtn.removeAttribute('disabled');
    // Restore button label via string lookup.
    getString('btn_generate_section', 'local_studiolms').then(label => {
        btnLabel.textContent = label;
        return label;
    }).catch(() => {
        // Label stays as-is if string lookup fails.
    });
};
