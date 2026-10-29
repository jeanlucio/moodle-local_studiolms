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
 * Restyles a page or label into a hidden copy from the list of the course.
 *
 * @module     local_studiolms/restyle
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import {ensure as ensureAiPolicy} from 'local_studiolms/ai_policy';

const STRINGS = ['restyle_working', 'restyle_open_copy', 'restyle_view_copy', 'restyle_error_title'];

let initialised = false;

/**
 * Shows the copy that was made, with the ways to open it.
 *
 * @param {HTMLElement} region The result region of the row.
 * @param {Object} copy The answer of the web service.
 * @param {string[]} strings The strings of the module.
 */
const showCopy = async(region, copy, [, open, view]) => {
    const ready = await getString('restyle_copy_ready', 'local_studiolms', copy.name);
    region.replaceChildren();
    const alert = document.createElement('div');
    alert.className = 'alert alert-success mb-0';

    const message = document.createElement('p');
    message.className = 'mb-2';
    message.textContent = ready;
    alert.append(message);

    [[copy.editurl, open, 'btn btn-primary btn-sm me-2'], [copy.viewurl, view, 'btn btn-secondary btn-sm']]
        .forEach(([href, label, className]) => {
            const link = document.createElement('a');
            link.href = href;
            link.className = className;
            link.textContent = label;
            alert.append(link);
        });
    // A polished copy may have lost a number of the original (a year, a quantity): the teacher is told which.
    if (Array.isArray(copy.missingnumbers) && copy.missingnumbers.length > 0) {
        const warning = document.createElement('p');
        warning.className = 'mt-2 mb-0';
        warning.setAttribute('role', 'alert');
        warning.textContent = await getString('restyle_numbers_missing', 'local_studiolms', copy.missingnumbers.join(', '));
        alert.append(warning);
    }
    region.append(alert);
};

/**
 * Asks for the restyle of one activity and shows the result in its row.
 *
 * @param {HTMLButtonElement} button The button of the row.
 */
const restyle = async(button) => {
    const row = button.closest('[data-region="restyle-row"]');
    const region = row.querySelector('[data-region="restyle-result"]');
    const strings = await getStrings(STRINGS.map(key => ({key, component: 'local_studiolms'})));

    if (!await ensureAiPolicy()) {
        return;
    }

    button.disabled = true;
    region.textContent = strings[0];
    try {
        const copy = await Ajax.call([{
            methodname: 'local_studiolms_restyle_activity',
            args: {
                cmid: Number(button.dataset.cmid),
                polish: document.querySelector('[data-region="restyle-polish"]')?.checked === true,
            },
        }])[0];
        await showCopy(region, copy, strings);
    } catch (error) {
        region.replaceChildren();
        button.disabled = false;
        Notification.alert(strings[3], error.message);
    }
};

/**
 * Starts the module.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-action="restyle"]');
        if (button !== null) {
            restyle(button);
        }
    });
};
