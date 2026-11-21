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
 * Asks the user to accept the site's AI usage policy before anything is sent to an AI provider.
 *
 * What the teacher types and the text of the course go to an external provider, so, as the AI placements of the
 * core do, the policy dialogue of the core is shown the first time and the answer is kept by the core. The core
 * modules are loaded only when the dialogue is needed.
 *
 * @module     local_studiolms/ai_policy
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import ModalEvents from 'core/modal_events';

let agreed = true;

/**
 * Waits until the event being handled is over.
 *
 * @returns {Promise<void>}
 */
const afterThisEvent = () => new Promise(resolve => setTimeout(resolve));

/**
 * Receives from the page whether the user has already accepted the policy.
 *
 * @param {boolean} accepted True when the policy was accepted, or when the site has none.
 */
export const init = (accepted) => {
    agreed = accepted;
};

/**
 * Makes sure the policy is accepted, showing the dialogue of the core when it is not.
 *
 * @returns {Promise<boolean>} True when the policy is accepted, false when the user closed the dialogue without it.
 */
export const ensure = async() => {
    if (agreed) {
        return true;
    }

    const [Policy, PolicyModal] = await Promise.all([
        import('core_ai/policy'),
        import('core_ai/policymodal'),
    ]);

    Policy.preconfigurePolicyState(Config.userId, agreed);
    if (await Policy.getPolicyStatus(Config.userId)) {
        agreed = true;
        return true;
    }

    const modal = await PolicyModal.create();
    // Whichever way the dialogue closes, the answer is read back from the policy module. The accept button of the
    // core closes the dialogue before it records the answer, both in the same click: the answer is read once that
    // click is over, or the dialogue reads as declined and nothing is generated.
    return new Promise((resolve) => {
        modal.getRoot().on(ModalEvents.hidden, async() => {
            await afterThisEvent();
            agreed = Boolean(await Policy.getPolicyStatus(Config.userId));
            resolve(agreed);
        });
    });
};
