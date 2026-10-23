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
 * Upgrade steps for local_cpdtracker.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database schema.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_cpdtracker_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102301) {
        // Hours-to-points rate per category. 0 means members enter points directly.
        $table = new xmldb_table('local_cpdtracker_category');
        $field = new xmldb_field('pointsperhour', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0', 'maxpoints');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Hours logged against an activity in an hours-based category.
        $table = new xmldb_table('local_cpdtracker_activity');
        $field = new xmldb_field('hours', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'points');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Index for per-cycle, per-member lookups (My CPD and the compliance report).
        $index = new xmldb_index('cycleid-userid', XMLDB_INDEX_NOTUNIQUE, ['cycleid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026102301, 'local', 'cpdtracker');
    }

    return true;
}
