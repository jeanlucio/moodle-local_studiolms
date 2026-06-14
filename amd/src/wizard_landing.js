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
 * Landing page of the StudioLMS course builder wizard.
 *
 * Handles mode card selection and routes to the appropriate view:
 * single activity form, section form (coming soon), or the full wizard.
 *
 * @module     local_studiolms/wizard_landing
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const VIEWS = {
    landing: 'local-studiolms-landing',
    step1: 'local-studiolms-step1-wrapper',
    activity: 'local-studiolms-activity',
};

const getView = id => document.getElementById(id);

// Shows exactly one view and hides all others.
const showOnly = targetId => {
    Object.values(VIEWS).forEach(id => {
        const el = getView(id);
        if (el !== null) {
            el.classList.toggle('d-none', id !== targetId);
        }
    });
};

let initialised = false;

export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', event => {
        const modeBtn = event.target.closest('[data-action="select-mode"]');
        if (modeBtn !== null) {
            const mode = modeBtn.dataset.mode;
            if (mode === 'course') {
                showOnly(VIEWS.step1);
            } else if (mode === 'activity') {
                showOnly(VIEWS.activity);
            }
            return;
        }

        if (event.target.closest('[data-action="back-to-landing"]') !== null) {
            showOnly(VIEWS.landing);
        }
    });
};
