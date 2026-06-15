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
 * Three-step section generator: briefing → activity plan review → generation + report.
 *
 * @module     local_studiolms/wizard_section
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';
import Config from 'core/config';

const FORM_ID = 'local-studiolms-section-form';
const WRAPPER_ID = 'local-studiolms-section';
const TYPES_ID = 'local-studiolms-section-types';
const POLL_INTERVAL = 2000;

let initialised = false;

// Cached aria-label strings (pre-fetched on init).
let ariaTypeLabel = '';
let ariaTitleLabel = '';
let ariaRemoveLabel = '';

export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    Promise.all([
        getString('aria_activity_type', 'local_studiolms'),
        getString('aria_activity_title', 'local_studiolms'),
        getString('aria_remove_activity', 'local_studiolms'),
    ]).then(([typeL, titleL, removeL]) => {
        ariaTypeLabel = typeL;
        ariaTitleLabel = titleL;
        ariaRemoveLabel = removeL;
        return [typeL, titleL, removeL];
    }).catch(() => {
        // Labels stay empty; select/input remain accessible via visible context.
    });

    // Section select → toggle wipe checkbox visibility.
    document.addEventListener('change', event => {
        const select = event.target.closest('#section-sectionnum');
        if (select === null) {
            return;
        }
        const wrapper = document.getElementById(WRAPPER_ID);
        const wipeOption = wrapper.querySelector('[data-region="wipe-option"]');
        const isExisting = parseInt(select.value, 10) >= 0;
        wipeOption.classList.toggle('d-none', !isExisting);
        if (!isExisting) {
            wrapper.querySelector('#section-wipe').checked = false;
            wrapper.querySelector('[data-region="wipe-warning"]').classList.add('d-none');
        }
    });

    // Wipe checkbox → toggle warning message.
    document.addEventListener('change', event => {
        const checkbox = event.target.closest('#section-wipe');
        if (checkbox === null) {
            return;
        }
        const wrapper = document.getElementById(WRAPPER_ID);
        wrapper.querySelector('[data-region="wipe-warning"]').classList.toggle('d-none', !checkbox.checked);
    });

    // Form submit → plan section (step 1 → step 2).
    document.addEventListener('submit', async event => {
        if (event.target.id !== FORM_ID) {
            return;
        }
        event.preventDefault();
        await handlePlan(event.target);
    });

    // Unified click dispatcher.
    document.addEventListener('click', async event => {
        const btn = event.target.closest('[data-action]');
        if (btn === null) {
            return;
        }
        const action = btn.dataset.action;
        const wrapper = document.getElementById(WRAPPER_ID);
        if (wrapper === null) {
            return;
        }

        if (action === 'add-activity') {
            addActivityRow(wrapper);
        } else if (action === 'remove-activity') {
            btn.closest('[data-region="activity-row"]').remove();
        } else if (action === 'back-to-form') {
            showFormView(wrapper);
        } else if (action === 'generate-section') {
            await handleGenerate(wrapper);
        } else if (action === 'generate-another') {
            resetToForm();
        }
    });
};

// ─── Step 1: Briefing ────────────────────────────────────────────────────────

const handlePlan = async form => {
    const wrapper = document.getElementById(WRAPPER_ID);
    const errorRegion = form.querySelector('[data-region="error"]');
    const spinner = form.querySelector('[data-region="spinner"]');
    const btnLabel = form.querySelector('[data-region="btnlabel"]');
    const submitBtn = form.querySelector('[data-action="plan-section"]');

    const courseid = parseInt(form.dataset.courseid, 10);
    const sectionnum = parseInt(form.querySelector('#section-sectionnum').value, 10);
    const theme = form.querySelector('#section-theme').value.trim();
    const reference = form.querySelector('#section-reference').value;
    const wipeCheckbox = form.querySelector('#section-wipe');
    const wipe = wipeCheckbox !== null && wipeCheckbox.checked;

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
            methodname: 'local_studiolms_plan_section',
            args: {courseid, sectionnum, theme, reference, bloom: 'general'},
        }])[0];

        wrapper.dataset.pendingCourseid = String(courseid);
        wrapper.dataset.pendingSectionnum = String(sectionnum);
        wrapper.dataset.pendingTheme = theme;
        wrapper.dataset.pendingReference = reference;
        wrapper.dataset.pendingWipe = wipe ? '1' : '0';

        showReviewView(wrapper, result.activities);
    } catch (error) {
        const fallback = await getString('error_section_plan', 'local_studiolms');
        errorRegion.textContent = error && error.message ? error.message : fallback;
        errorRegion.classList.remove('d-none');
    } finally {
        spinner.classList.add('d-none');
        submitBtn.removeAttribute('disabled');
        btnLabel.textContent = defaultLabel;
    }
};

// ─── Step 2: Review plan ─────────────────────────────────────────────────────

const showReviewView = (wrapper, activities) => {
    const listEl = wrapper.querySelector('[data-region="activity-list"]');
    listEl.innerHTML = '';
    activities.forEach(activity => listEl.appendChild(createActivityRow(activity)));

    wrapper.querySelector('[data-region="form-view"]').classList.add('d-none');
    const reviewView = wrapper.querySelector('[data-region="review-view"]');
    reviewView.classList.remove('d-none');
    reviewView.querySelector('[data-region="review-error"]').classList.add('d-none');
};

const showFormView = wrapper => {
    wrapper.querySelector('[data-region="review-view"]').classList.add('d-none');
    wrapper.querySelector('[data-region="form-view"]').classList.remove('d-none');
};

const getTypeOptions = () => {
    const el = document.getElementById(TYPES_ID);
    return el ? JSON.parse(el.dataset.types) : [];
};

const createActivityRow = (activity = {type: 'page', title: ''}) => {
    const typeOptions = getTypeOptions();
    const row = document.createElement('div');
    row.dataset.region = 'activity-row';
    row.className = 'input-group mb-2';

    const select = document.createElement('select');
    select.className = 'form-select';
    select.style.maxWidth = '160px';
    select.setAttribute('aria-label', ariaTypeLabel || 'Activity type');
    typeOptions.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        if (opt.value === activity.type) {
            option.selected = true;
        }
        select.appendChild(option);
    });

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control';
    input.value = activity.title ?? '';
    input.setAttribute('aria-label', ariaTitleLabel || 'Activity title');

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline-danger';
    removeBtn.dataset.action = 'remove-activity';
    removeBtn.setAttribute('aria-label', ariaRemoveLabel || 'Remove');
    removeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';

    row.appendChild(select);
    row.appendChild(input);
    row.appendChild(removeBtn);
    return row;
};

const addActivityRow = wrapper => {
    wrapper.querySelector('[data-region="activity-list"]').appendChild(createActivityRow());
};

const handleGenerate = async wrapper => {
    const reviewView = wrapper.querySelector('[data-region="review-view"]');
    const reviewError = reviewView.querySelector('[data-region="review-error"]');
    const spinner = reviewView.querySelector('[data-region="review-spinner"]');
    const btnLabel = reviewView.querySelector('[data-region="review-btnlabel"]');
    const generateBtn = reviewView.querySelector('[data-action="generate-section"]');

    const rows = reviewView.querySelectorAll('[data-region="activity-row"]');
    const activities = [];
    rows.forEach(row => {
        const type = row.querySelector('select').value;
        const title = row.querySelector('input').value.trim();
        if (title !== '') {
            activities.push({type, title});
        }
    });

    if (activities.length === 0) {
        reviewError.textContent = await getString('error_section_plan', 'local_studiolms');
        reviewError.classList.remove('d-none');
        return;
    }

    reviewError.classList.add('d-none');

    const courseid = parseInt(wrapper.dataset.pendingCourseid, 10);
    const sectionnum = parseInt(wrapper.dataset.pendingSectionnum, 10);
    const theme = wrapper.dataset.pendingTheme;
    const reference = wrapper.dataset.pendingReference;
    const wipe = wrapper.dataset.pendingWipe === '1';

    const defaultLabel = btnLabel.textContent;
    spinner.classList.remove('d-none');
    generateBtn.setAttribute('disabled', 'disabled');
    btnLabel.textContent = await getString('generating', 'local_studiolms');

    try {
        const result = await fetchMany([{
            methodname: 'local_studiolms_generate_section',
            args: {
                courseid,
                sectionnum,
                theme,
                reference,
                activitiesjson: JSON.stringify(activities),
                bloom: 'general',
                wipe,
            },
        }])[0];

        showProgressView(wrapper);
        startPolling(wrapper, result.progressid, courseid);
    } catch (error) {
        const fallback = await getString('error_populate', 'local_studiolms');
        reviewError.textContent = error && error.message ? error.message : fallback;
        reviewError.classList.remove('d-none');
        spinner.classList.add('d-none');
        generateBtn.removeAttribute('disabled');
        btnLabel.textContent = defaultLabel;
    }
};

// ─── Step 3: Progress ────────────────────────────────────────────────────────

const showProgressView = wrapper => {
    wrapper.querySelector('[data-region="review-view"]').classList.add('d-none');
    const progressView = wrapper.querySelector('[data-region="progress-view"]');
    progressView.classList.remove('d-none');
    progressView.querySelector('[data-region="done"]').classList.add('d-none');
    progressView.querySelector('[data-region="progress-error"]').classList.add('d-none');
    const bar = progressView.querySelector('[data-region="bar"]');
    bar.style.width = '0%';
    bar.setAttribute('aria-valuenow', '0');
};

const startPolling = (wrapper, progressid, courseid) => {
    const bar = wrapper.querySelector('[data-region="bar"]');
    const messageEl = wrapper.querySelector('[data-region="progress-message"]');
    const doneRegion = wrapper.querySelector('[data-region="done"]');
    const errorRegion = wrapper.querySelector('[data-region="progress-error"]');
    const viewLink = wrapper.querySelector('[data-region="viewlink"]');

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

// ─── Reset ───────────────────────────────────────────────────────────────────

const resetToForm = () => {
    const wrapper = document.getElementById(WRAPPER_ID);
    if (wrapper === null) {
        return;
    }
    wrapper.querySelector('[data-region="progress-view"]').classList.add('d-none');
    wrapper.querySelector('[data-region="review-view"]').classList.add('d-none');

    const formView = wrapper.querySelector('[data-region="form-view"]');
    formView.classList.remove('d-none');

    const form = formView.querySelector(`#${FORM_ID}`);
    form.reset();
    form.querySelector('[data-region="error"]').classList.add('d-none');
    formView.querySelector('[data-region="wipe-option"]').classList.add('d-none');
    formView.querySelector('[data-region="wipe-warning"]').classList.add('d-none');

    const spinner = form.querySelector('[data-region="spinner"]');
    const submitBtn = form.querySelector('[data-action="plan-section"]');
    spinner.classList.add('d-none');
    submitBtn.removeAttribute('disabled');

    getString('btn_plan_section', 'local_studiolms').then(label => {
        form.querySelector('[data-region="btnlabel"]').textContent = label;
        return label;
    }).catch(() => {
        // Label stays as-is if string lookup fails.
    });
};
