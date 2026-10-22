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

namespace local_cpdtracker\privacy;

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_cpdtracker.
 *
 * Activities are personal data owned by the member, so they live in the
 * member's user context. Cycles and categories hold no personal data.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_cpdtracker_activity', [
            'userid' => 'privacy:metadata:activity:userid',
            'cycleid' => 'privacy:metadata:activity:cycleid',
            'categoryid' => 'privacy:metadata:activity:categoryid',
            'title' => 'privacy:metadata:activity:title',
            'description' => 'privacy:metadata:activity:description',
            'activitydate' => 'privacy:metadata:activity:activitydate',
            'points' => 'privacy:metadata:activity:points',
            'timecreated' => 'privacy:metadata:activity:timecreated',
            'timemodified' => 'privacy:metadata:activity:timemodified',
        ], 'privacy:metadata:activity');

        return $collection;
    }

    /**
     * Get the contexts holding data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :userid
                   AND EXISTS (SELECT 1 FROM {local_cpdtracker_activity} a WHERE a.userid = ctx.instanceid)";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_USER, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the users holding data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        $sql = "SELECT userid FROM {local_cpdtracker_activity} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
    }

    /**
     * Export a user's activities.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int)$context->instanceid !== $userid) {
                continue;
            }

            $sql = "SELECT a.*, cy.name AS cyclename, ca.name AS categoryname
                      FROM {local_cpdtracker_activity} a
                 LEFT JOIN {local_cpdtracker_cycle} cy ON cy.id = a.cycleid
                 LEFT JOIN {local_cpdtracker_category} ca ON ca.id = a.categoryid
                     WHERE a.userid = :userid
                  ORDER BY a.activitydate, a.id";
            $records = $DB->get_records_sql($sql, ['userid' => $userid]);
            if (!$records) {
                continue;
            }

            $activities = [];
            foreach ($records as $record) {
                $activities[] = (object)[
                    'cycle' => format_string($record->cyclename ?? '', true, ['context' => $context]),
                    'category' => format_string($record->categoryname ?? '', true, ['context' => $context]),
                    'title' => format_string($record->title, true, ['context' => $context]),
                    'description' => $record->description,
                    'activitydate' => transform::date($record->activitydate),
                    'points' => (float)$record->points,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_cpdtracker')],
                (object)['activities' => $activities]
            );
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context instanceof context_user) {
            $DB->delete_records('local_cpdtracker_activity', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete a user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int)$context->instanceid === $userid) {
                $DB->delete_records('local_cpdtracker_activity', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete data for the approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        if (in_array((int)$context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            $DB->delete_records('local_cpdtracker_activity', ['userid' => $context->instanceid]);
        }
    }
}
