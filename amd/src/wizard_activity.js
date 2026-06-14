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
 * Single-activity generation form for the StudioLMS course builder.
 *
 * @module     local_studiolms/wizard_activity
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';

const FORM_ID = 'local-studiolms-activity-form';

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
};

const handleSubmit = async form => {
    const errorRegion = form.querySelector('[data-region="error"]');
    const resultRegion = form.querySelector('[data-region="result"]');
    const viewLink = form.querySelector('[data-region="viewlink"]');
    const spinner = form.querySelector('[data-region="spinner"]');
    const btnLabel = form.querySelector('[data-region="btnlabel"]');
    const submitBtn = form.querySelector('[data-action="generate-activity"]');

    errorRegion.classList.add('d-none');
    resultRegion.classList.add('d-none');

    const courseid = parseInt(form.dataset.courseid, 10);
    const sectionnum = parseInt(form.querySelector('#activity-sectionnum').value, 10);
    const type = form.querySelector('#activity-type').value;
    const title = form.querySelector('#activity-title').value.trim();
    const theme = form.querySelector('#activity-theme').value.trim();
    const reference = form.querySelector('#activity-reference').value;

    if (title === '' || theme === '') {
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
            methodname: 'local_studiolms_generate_activity',
            args: {courseid, sectionnum, type, title, theme, reference},
        }])[0];

        viewLink.setAttribute('href', result.viewurl);
        resultRegion.classList.remove('d-none');
        resultRegion.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    } catch (error) {
        const fallback = await getString('error_populate', 'local_studiolms');
        errorRegion.textContent = error && error.message ? error.message : fallback;
        errorRegion.classList.remove('d-none');
    } finally {
        spinner.classList.add('d-none');
        submitBtn.removeAttribute('disabled');
        btnLabel.textContent = defaultLabel;
    }
};
