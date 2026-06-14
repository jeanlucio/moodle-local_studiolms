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
 * Course review step (step 2) of the StudioLMS course builder wizard.
 *
 * @module     local_studiolms/wizard_step2
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString, get_strings as getStrings} from 'core/str';

// Wires the editable course review: renaming, removing and adding objectives,
// sections and activities, with automatic section numbering and back navigation.
export const init = (root, callbacks = {}) => {
    if (root === null) {
        return;
    }

    const clone = name => {
        const template = root.querySelector('[data-template="' + name + '"]');
        return template.content.firstElementChild.cloneNode(true);
    };

    const focusField = (node, field) => {
        const input = node.querySelector('[data-field="' + field + '"]');
        if (input !== null) {
            input.focus();
        }
    };

    const serialize = () => {
        const objectives = Array.from(root.querySelectorAll('[data-region="objectives"] [data-field="objective"]'))
            .map(input => input.value.trim())
            .filter(value => value !== '');

        const sections = Array.from(root.querySelectorAll('[data-region="sections"] > [data-region="section"]'))
            .map(section => ({
                title: section.querySelector('[data-field="sectiontitle"]').value.trim(),
                activities: Array.from(section.querySelectorAll('[data-region="activities"] > [data-region="activity"]'))
                    .map(activity => ({
                        type: activity.querySelector('[data-field="activitytype"]').value,
                        title: activity.querySelector('[data-field="activitytitle"]').value.trim(),
                    }))
                    .filter(activity => activity.title !== ''),
            }))
            .filter(section => section.title !== '');

        return {
            outlineid: parseInt(root.dataset.outlineid, 10),
            objectives: objectives,
            sections: sections,
        };
    };

    // Counts activities by type, applies conservative per-type estimates and
    // updates the estimate badge near the populate button.
    const estimateTime = async() => {
        const el = root.querySelector('[data-region="estimate"]');
        if (el === null) {
            return;
        }
        const costs = {page: 12, quiz: 15, assign: 8, forum: 5, label: 2, glossary: 10};
        let seconds = 12; // The "Course plan" intro page is always generated.
        root.querySelectorAll('[data-region="activity"]').forEach(activity => {
            const type = activity.querySelector('[data-field="activitytype"]')?.value ?? 'page';
            seconds += costs[type] ?? 8;
        });
        const minutes = Math.ceil(seconds / 60);
        const str = minutes < 1
            ? await getString('estimate_time_short', 'local_studiolms')
            : await getString('estimate_time', 'local_studiolms', minutes);
        el.textContent = str;
        el.classList.remove('d-none');
    };

    const renumberSections = async() => {
        const sections = Array.from(root.querySelectorAll('[data-region="sections"] > [data-region="section"]'));
        if (sections.length === 0) {
            return;
        }
        const labels = await getStrings(sections.map((section, index) => ({
            key: 'section_number',
            component: 'local_studiolms',
            param: index + 1,
        })));
        sections.forEach((section, index) => {
            const number = section.querySelector('[data-region="sectionnum"]');
            if (number !== null) {
                number.textContent = labels[index];
            }
        });
    };

    root.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-action]');
        if (trigger === null || !root.contains(trigger)) {
            return;
        }

        switch (trigger.dataset.action) {
            case 'remove-section':
                trigger.closest('[data-region="section"]').remove();
                await renumberSections();
                await estimateTime();
                break;
            case 'remove-activity':
                trigger.closest('[data-region="activity"]').remove();
                await estimateTime();
                break;
            case 'remove-objective':
                trigger.closest('[data-region="objective"]').remove();
                break;
            case 'add-objective': {
                const node = clone('objective');
                root.querySelector('[data-region="objectives"]').appendChild(node);
                focusField(node, 'objective');
                break;
            }
            case 'add-section': {
                const node = clone('section');
                root.querySelector('[data-region="sections"]').appendChild(node);
                await renumberSections();
                await estimateTime();
                focusField(node, 'sectiontitle');
                break;
            }
            case 'add-activity': {
                const activities = trigger.closest('[data-region="section"]').querySelector('[data-region="activities"]');
                const node = clone('activity');
                activities.appendChild(node);
                await estimateTime();
                focusField(node, 'activitytitle');
                break;
            }
            case 'back':
                if (typeof callbacks.onBack === 'function') {
                    callbacks.onBack();
                }
                break;
            case 'populate':
                if (typeof callbacks.onPopulate === 'function') {
                    const spinner = trigger.querySelector('[data-region="spinner"]');
                    if (spinner !== null) {
                        spinner.classList.remove('d-none');
                    }
                    trigger.setAttribute('disabled', 'disabled');
                    callbacks.onPopulate(serialize());
                }
                break;
        }
    });

    root.addEventListener('change', async event => {
        if (event.target.closest('[data-field="activitytype"]') !== null) {
            await estimateTime();
        }
    });

    renumberSections();
    estimateTime();
};
