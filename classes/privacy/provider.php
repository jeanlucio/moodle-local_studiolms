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
 * Privacy Subsystem implementation for local_studiolms.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: StudioLMS stores per-teacher generation records keyed by course.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] The plugin tables that hold teacher data, all keyed by userid and courseid. */
    private const TABLES = [
        'local_studiolms_generation_log',
        'local_studiolms_outline',
        'local_studiolms_progress',
    ];

    /**
     * Describes the personal data stored by this plugin.
     *
     * @param collection $collection The metadata collection to add to.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_studiolms_generation_log', [
            'userid'              => 'privacy:metadata:local_studiolms_generation_log:userid',
            'courseid'            => 'privacy:metadata:local_studiolms_generation_log:courseid',
            'mode'                => 'privacy:metadata:local_studiolms_generation_log:mode',
            'bloomlevel'          => 'privacy:metadata:local_studiolms_generation_log:bloomlevel',
            'gamificationprofile' => 'privacy:metadata:local_studiolms_generation_log:gamificationprofile',
            'prompt'              => 'privacy:metadata:local_studiolms_generation_log:prompt',
            'outlinejson'         => 'privacy:metadata:local_studiolms_generation_log:outlinejson',
            'status'              => 'privacy:metadata:local_studiolms_generation_log:status',
            'timecreated'         => 'privacy:metadata:local_studiolms_generation_log:timecreated',
            'timecompleted'       => 'privacy:metadata:local_studiolms_generation_log:timecompleted',
        ], 'privacy:metadata:local_studiolms_generation_log');

        $collection->add_database_table('local_studiolms_outline', [
            'userid'       => 'privacy:metadata:local_studiolms_outline:userid',
            'courseid'     => 'privacy:metadata:local_studiolms_outline:courseid',
            'briefingjson' => 'privacy:metadata:local_studiolms_outline:briefingjson',
            'outlinejson'  => 'privacy:metadata:local_studiolms_outline:outlinejson',
            'status'       => 'privacy:metadata:local_studiolms_outline:status',
            'timecreated'  => 'privacy:metadata:local_studiolms_outline:timecreated',
            'timemodified' => 'privacy:metadata:local_studiolms_outline:timemodified',
        ], 'privacy:metadata:local_studiolms_outline');

        $collection->add_database_table('local_studiolms_progress', [
            'userid'       => 'privacy:metadata:local_studiolms_progress:userid',
            'courseid'     => 'privacy:metadata:local_studiolms_progress:courseid',
            'status'       => 'privacy:metadata:local_studiolms_progress:status',
            'errormsg'     => 'privacy:metadata:local_studiolms_progress:errormsg',
            'timecreated'  => 'privacy:metadata:local_studiolms_progress:timecreated',
            'timemodified' => 'privacy:metadata:local_studiolms_progress:timemodified',
        ], 'privacy:metadata:local_studiolms_progress');

        return $collection;
    }

    /**
     * Returns the course contexts that hold data for the given user.
     *
     * @param int $userid The user to search for.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (self::TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {" . $table . "} t ON t.courseid = ctx.instanceid
                     WHERE ctx.contextlevel = :contextlevel AND t.userid = :userid";
            $contextlist->add_from_sql($sql, [
                'contextlevel' => CONTEXT_COURSE,
                'userid'       => $userid,
            ]);
        }

        return $contextlist;
    }

    /**
     * Returns the users who have data in the given course context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {" . $table . "} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Exports the user's StudioLMS data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            foreach (self::TABLES as $table) {
                $records = $DB->get_records($table, [
                    'courseid' => $context->instanceid,
                    'userid'   => $userid,
                ]);
                if (empty($records)) {
                    continue;
                }
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_studiolms'), $table],
                    (object) ['records' => array_values($records)]
                );
            }
        }
    }

    /**
     * Deletes all StudioLMS data for every user in the given course context.
     *
     * @param \context $context The context to purge.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }
        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes the StudioLMS data of one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, [
                    'courseid' => $context->instanceid,
                    'userid'   => $userid,
                ]);
            }
        }
    }

    /**
     * Deletes the StudioLMS data of the approved users within a course context.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        foreach (self::TABLES as $table) {
            $params = array_merge(['courseid' => $context->instanceid], $inparams);
            $DB->delete_records_select($table, "courseid = :courseid AND userid {$insql}", $params);
        }
    }
}
