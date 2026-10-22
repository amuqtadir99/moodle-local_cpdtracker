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
 * Manage CPD cycles: list, add, edit and delete.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Handles require_login, the local/cpdtracker:manage check, page context and admin navigation.
admin_externalpage_setup('local_cpdtracker_manage');

$manager = new \local_cpdtracker\manager();
$baseurl = new moodle_url('/local/cpdtracker/manage.php');

// Delete, with a confirmation step. Cycles with logged activities cannot be deleted.
if ($delete) {
    $cycle = $manager->get_cycle($delete);
    if ($manager->count_cycle_activities($cycle->id)) {
        redirect($baseurl, get_string('cyclehasactivities', 'local_cpdtracker'), null, notification::NOTIFY_ERROR);
    }

    if ($confirm) {
        require_sesskey();
        $manager->delete_cycle($cycle->id);
        redirect($baseurl, get_string('cycledeleted', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
    }

    $confirmurl = new moodle_url($baseurl, ['delete' => $cycle->id, 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletecycle', 'local_cpdtracker', format_string($cycle->name)),
        $confirmurl,
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecycles', 'local_cpdtracker'));

echo $OUTPUT->single_button(new moodle_url('/local/cpdtracker/editcycle.php'),
    get_string('addcycle', 'local_cpdtracker'), 'get');

$cycles = $manager->get_cycles();

if (!$cycles) {
    echo html_writer::tag('p', get_string('nocycles', 'local_cpdtracker'));
} else {
    $dateformat = get_string('strftimedate', 'langconfig');

    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('startdate', 'local_cpdtracker'),
        get_string('enddate', 'local_cpdtracker'),
        get_string('requiredpoints', 'local_cpdtracker'),
        get_string('categories', 'local_cpdtracker'),
        get_string('activities', 'local_cpdtracker'),
        get_string('status', 'local_cpdtracker'),
        get_string('actions'),
    ];

    foreach ($cycles as $cycle) {
        $categoriesurl = new moodle_url('/local/cpdtracker/categories.php', ['cycleid' => $cycle->id]);

        $actions = $OUTPUT->action_icon(
            new moodle_url('/local/cpdtracker/editcycle.php', ['id' => $cycle->id]),
            new pix_icon('t/edit', get_string('edit'))
        );
        if (!$cycle->activitycount) {
            $actions .= $OUTPUT->action_icon(
                new moodle_url($baseurl, ['delete' => $cycle->id]),
                new pix_icon('t/delete', get_string('delete'))
            );
        }

        $table->data[] = [
            html_writer::link($categoriesurl, format_string($cycle->name)),
            userdate($cycle->startdate, $dateformat),
            userdate($cycle->enddate, $dateformat),
            format_float($cycle->requiredpoints, 2),
            html_writer::link($categoriesurl, (int)$cycle->categorycount),
            (int)$cycle->activitycount,
            get_string('cycle' . $manager->get_cycle_status($cycle), 'local_cpdtracker'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
