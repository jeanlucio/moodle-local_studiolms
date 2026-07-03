<?php
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
 * Event observer for local_studiolms.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms;

use core\event\course_deleted;
use local_studiolms\privacy\provider;

/**
 * Class observer
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Removes the wizard's draft/progress/log rows for a course that has just been deleted.
     *
     * local_studiolms_outline and local_studiolms_progress hold live wizard state (not just an
     * audit trail) keyed by courseid, and Moodle never cascades plugin tables when a course is
     * removed. Reuses the privacy provider's existing context-scoped delete so both cleanup
     * paths (GDPR deletion request and course deletion) stay in sync.
     *
     * @param course_deleted $event The core course deletion event.
     * @return void
     */
    public static function course_deleted(course_deleted $event): void {
        provider::delete_data_for_all_users_in_context($event->get_context());
    }
}
