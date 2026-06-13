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
 * Outline review step (step 2) of the StudioLMS course builder wizard.
 *
 * @module     local_studiolms/wizard_step2
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wires the editable outline behaviour: removing sections and activities and
 * going back to the briefing step.
 *
 * @param {HTMLElement} root The rendered step 2 root element.
 * @param {object} callbacks Optional callbacks ({onBack}).
 */
export const init = (root, callbacks = {}) => {
    if (root === null) {
        return;
    }

    root.addEventListener('click', event => {
        const removeSection = event.target.closest('[data-action="remove-section"]');
        if (removeSection !== null) {
            const section = removeSection.closest('[data-region="section"]');
            if (section !== null) {
                section.remove();
            }
            return;
        }

        const removeActivity = event.target.closest('[data-action="remove-activity"]');
        if (removeActivity !== null) {
            const activity = removeActivity.closest('[data-region="activity"]');
            if (activity !== null) {
                activity.remove();
            }
            return;
        }

        const back = event.target.closest('[data-action="back"]');
        if (back !== null && typeof callbacks.onBack === 'function') {
            callbacks.onBack();
        }
    });
};
