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
import {get_string as getString, get_strings as getStrings} from 'core/str';
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
    const done = root.querySelector('[data-region="done"]');
    const backLink = root.querySelector('[data-region="backtocourse"]');

    const formatDuration = seconds => {
        if (seconds < 60) {
            return `${seconds} s`;
        }
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return s > 0 ? `${m} min ${s} s` : `${m} min`;
    };

    // Parses the per-activity report and renders a unified summary panel.
    // Shows success/simplified badges first, then every activity with type label.
    const showReport = async(reportjson, duration = 0) => {
        const container = root.querySelector('[data-region="report"]');
        if (container === null) {
            return;
        }
        let activities = [];
        try {
            activities = JSON.parse(reportjson || '[]');
        } catch (e) {
            return;
        }
        if (!Array.isArray(activities) || activities.length === 0) {
            return;
        }

        const total = activities.length;
        const degradedCount = activities.filter(a => a.degraded).length;
        const successCount = total - degradedCount;
        const formattedDuration = duration > 0 ? formatDuration(duration) : '';

        const [
            headingStr, successStr, degradedStr, activitiesStr,
            blocksStr, planStr, fallbackStr, durationStr,
            typePageStr, typeQuizStr, typeForumStr, typeAssignStr, typeGlossaryStr, typeLabelStr,
        ] = await getStrings([
            {key: 'report_heading', component: 'local_studiolms'},
            {key: 'report_success', component: 'local_studiolms'},
            {key: 'report_degraded', component: 'local_studiolms'},
            {key: 'report_activities', component: 'local_studiolms'},
            {key: 'report_blocks', component: 'local_studiolms'},
            {key: 'report_plan', component: 'local_studiolms'},
            {key: 'report_fallback', component: 'local_studiolms'},
            {key: 'report_duration', component: 'local_studiolms', param: formattedDuration},
            {key: 'activity_page', component: 'local_studiolms'},
            {key: 'activity_quiz', component: 'local_studiolms'},
            {key: 'activity_forum', component: 'local_studiolms'},
            {key: 'activity_assign', component: 'local_studiolms'},
            {key: 'activity_glossary', component: 'local_studiolms'},
            {key: 'activity_label', component: 'local_studiolms'},
        ]);

        const typeLabels = {
            page: typePageStr, quiz: typeQuizStr, forum: typeForumStr,
            assign: typeAssignStr, glossary: typeGlossaryStr, label: typeLabelStr,
        };

        // Heading
        const heading = document.createElement('h6');
        heading.textContent = headingStr;
        container.appendChild(heading);

        // Duration
        if (formattedDuration !== '') {
            const durationEl = document.createElement('p');
            durationEl.className = 'text-muted small mb-1';
            durationEl.textContent = durationStr;
            container.appendChild(durationEl);
        }

        // Badges — success count first, then simplified count if any
        const summary = document.createElement('p');
        summary.className = 'mb-2';
        const successBadge = document.createElement('span');
        successBadge.className = 'badge bg-success me-1';
        successBadge.textContent = `${successCount} ${successStr}`;
        summary.appendChild(successBadge);
        if (degradedCount > 0) {
            const degradedBadge = document.createElement('span');
            degradedBadge.className = 'badge bg-warning text-dark';
            degradedBadge.textContent = `${degradedCount} ${degradedStr}`;
            summary.appendChild(degradedBadge);
        }
        container.appendChild(summary);

        // Full activity list
        const listLabel = document.createElement('p');
        listLabel.className = 'mb-1 mt-2';
        const strong = document.createElement('strong');
        strong.textContent = activitiesStr;
        listLabel.appendChild(strong);
        container.appendChild(listLabel);

        const list = document.createElement('ul');
        list.className = 'mb-0 small';
        activities.forEach(activity => {
            const typeLabel = typeLabels[activity.type] ?? activity.type;

            let detail = '';
            if (activity.type === 'page') {
                if (activity.preset === 'blocks') {
                    detail = blocksStr;
                } else if (activity.preset === 'plan') {
                    detail = planStr;
                } else if (activity.preset && activity.preset !== '') {
                    detail = activity.preset;
                } else {
                    detail = fallbackStr;
                }
            } else if (activity.degraded) {
                detail = fallbackStr;
            }

            const item = document.createElement('li');

            const typeEl = document.createElement('span');
            typeEl.className = 'text-muted me-1';
            typeEl.textContent = `[${typeLabel}]`;
            item.appendChild(typeEl);

            const titleEl = document.createElement('strong');
            titleEl.textContent = activity.title;
            item.appendChild(titleEl);

            if (detail !== '') {
                item.appendChild(document.createTextNode(` — ${detail}`));
            }

            list.appendChild(item);
        });
        container.appendChild(list);

        container.classList.remove('d-none');
    };

    const setBar = (step, total) => {
        const percent = total > 0 ? Math.round((step / total) * 100) : 0;
        // Keep a minimum of 5 % so the bar is never invisible while running.
        bar.style.width = Math.max(percent, 5) + '%';
        bar.setAttribute('aria-valuenow', percent);
    };

    const stopAnimation = () => {
        bar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    };

    const restartAnimation = () => {
        bar.classList.add('progress-bar-striped', 'progress-bar-animated');
    };

    // Counts consecutive 'failed' responses so we can give up after ~40 s.
    let failedPollCount = 0;
    const MAX_FAILED_POLLS = 8;

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

        // Task is queued but the worker has not picked it up yet — show a
        // visible waiting indicator instead of an invisible 0 % bar.
        if (progress.status === 'queued') {
            bar.style.width = '15%';
            bar.setAttribute('aria-valuenow', 15);
            setTimeout(poll, POLL_INTERVAL);
            return;
        }

        if (progress.status === 'completed') {
            failedPollCount = 0;
            setBar(1, 1);
            stopAnimation();
            const duration = (progress.timemodified ?? 0) - (progress.timecreated ?? 0);
            await showReport(progress.report ?? '', duration);
            backLink.setAttribute('href', Config.wwwroot + '/course/view.php?id=' + progress.courseid);
            done.classList.remove('d-none');
            return;
        }

        if (progress.status === 'failed') {
            failedPollCount++;
            stopAnimation();
            errorRegion.textContent = progress.errormsg !== ''
                ? progress.errormsg
                : await getString('error_populate', 'local_studiolms');
            errorRegion.classList.remove('d-none');
            // Moodle retries failed adhoc tasks automatically. Keep polling so
            // that a successful retry is reflected without a page reload.
            if (failedPollCount < MAX_FAILED_POLLS) {
                setTimeout(poll, POLL_INTERVAL * 3);
            }
            return;
        }

        // Running — if recovering from a previous failed attempt (Moodle auto-retry),
        // clear the error banner and restore the animation.
        if (failedPollCount > 0) {
            failedPollCount = 0;
            errorRegion.textContent = '';
            errorRegion.classList.add('d-none');
            restartAnimation();
        }

        setBar(progress.step, progress.total);
        setTimeout(poll, POLL_INTERVAL);
    };

    poll();
};
