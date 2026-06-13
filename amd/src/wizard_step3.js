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
 * Generation progress step (step 3) of the StudioLMS course builder wizard.
 *
 * @module     local_studiolms/wizard_step3
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {get_string as getString} from 'core/str';
import Config from 'core/config';

const POLL_INTERVAL = 1500;

// Polls the generation progress and reflects it in the progress bar.
export const init = (root, options = {}) => {
    if (root === null || !options.progressid) {
        return;
    }

    const bar = root.querySelector('[data-region="bar"]');
    const message = root.querySelector('[data-region="message"]');
    const errorRegion = root.querySelector('[data-region="error"]');
    const warningsRegion = root.querySelector('[data-region="warnings"]');
    const done = root.querySelector('[data-region="done"]');
    const backLink = root.querySelector('[data-region="backtocourse"]');

    const showWarnings = async warnings => {
        if (!Array.isArray(warnings) || warnings.length === 0) {
            return;
        }
        warningsRegion.textContent = await getString('warnings_heading', 'local_studiolms');
        const list = document.createElement('ul');
        list.classList.add('mb-0', 'mt-2');
        warnings.forEach(warning => {
            const item = document.createElement('li');
            item.textContent = warning;
            list.appendChild(item);
        });
        warningsRegion.appendChild(list);
        warningsRegion.classList.remove('d-none');
    };

    const setBar = (step, total) => {
        const percent = total > 0 ? Math.round((step / total) * 100) : 0;
        bar.style.width = percent + '%';
        bar.setAttribute('aria-valuenow', percent);
    };

    const stopAnimation = () => {
        bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    };

    const poll = async() => {
        let progress;
        try {
            progress = await fetchMany([{
                methodname: 'local_studiolms_get_progress',
                args: {progressid: options.progressid},
            }])[0];
        } catch (error) {
            errorRegion.textContent = error && error.message
                ? error.message
                : await getString('error_populate', 'local_studiolms');
            errorRegion.classList.remove('d-none');
            stopAnimation();
            return;
        }

        message.textContent = progress.message;
        setBar(progress.step, progress.total);

        if (progress.status === 'completed') {
            setBar(1, 1);
            stopAnimation();
            await showWarnings(progress.warnings);
            backLink.setAttribute('href', Config.wwwroot + '/course/view.php?id=' + progress.courseid);
            done.classList.remove('d-none');
            return;
        }

        if (progress.status === 'failed') {
            stopAnimation();
            errorRegion.textContent = progress.errormsg !== ''
                ? progress.errormsg
                : await getString('error_populate', 'local_studiolms');
            errorRegion.classList.remove('d-none');
            return;
        }

        setTimeout(poll, POLL_INTERVAL);
    };

    poll();
};
