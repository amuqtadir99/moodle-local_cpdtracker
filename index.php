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
 * My CPD: the current user's points and compliance status for the current cycle.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/cpdtracker:logactivity', $context);

$PAGE->set_url(new moodle_url('/local/cpdtracker/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycpd', 'local_cpdtracker'));
$PAGE->set_heading(get_string('mycpd', 'local_cpdtracker'));

$manager = new \local_cpdtracker\manager();
$cycle = $manager->get_current_cycle();

echo $OUTPUT->header();

if (has_capability('local/cpdtracker:manage', $context)) {
    echo $OUTPUT->single_button(new moodle_url('/local/cpdtracker/manage.php'),
        get_string('managecycles', 'local_cpdtracker'), 'get');
}

if (!$cycle) {
    echo $OUTPUT->notification(get_string('nocurrentcycle', 'local_cpdtracker'), notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$summary = $manager->get_user_summary($USER->id, $cycle);
$cycleopen = $manager->is_cycle_open($cycle);

// Cycle and overall status.
echo $OUTPUT->heading(format_string($cycle->name), 3);
echo html_writer::tag('p', get_string('cycledates', 'local_cpdtracker', $manager->format_cycle_dates($cycle)));
echo html_writer::tag('p', get_string('pointstotal', 'local_cpdtracker', (object)[
    'total' => format_float($summary->total, 2),
    'required' => format_float($cycle->requiredpoints, 2),
]));

if ($summary->compliant) {
    echo $OUTPUT->notification(get_string('compliant', 'local_cpdtracker'), notification::NOTIFY_SUCCESS);
} else {
    echo $OUTPUT->notification(get_string('notcompliant', 'local_cpdtracker'), notification::NOTIFY_WARNING);
}

// Category breakdown.
if ($summary->categories) {
    $table = new html_table();
    $table->head = [
        get_string('category', 'local_cpdtracker'),
        get_string('minpoints', 'local_cpdtracker'),
        get_string('maxpoints', 'local_cpdtracker'),
        get_string('pointslogged', 'local_cpdtracker'),
        get_string('pointscounted', 'local_cpdtracker'),
        get_string('status', 'local_cpdtracker'),
    ];
    foreach ($summary->categories as $row) {
        $max = (float)$row->category->maxpoints;
        $table->data[] = [
            format_string($row->category->name),
            format_float($row->category->minpoints, 2),
            $max > 0 ? format_float($max, 2) : get_string('nocap', 'local_cpdtracker'),
            format_float($row->raw, 2),
            format_float($row->counted, 2),
            get_string($row->meetsmin ? 'met' : 'notmet', 'local_cpdtracker'),
        ];
    }
    echo html_writer::table($table);
}

// Activities.
echo $OUTPUT->heading(get_string('activities', 'local_cpdtracker'), 4);

if ($cycleopen) {
    echo $OUTPUT->single_button(new moodle_url('/local/cpdtracker/edit.php'),
        get_string('logactivity', 'local_cpdtracker'), 'get');
}

if (!$summary->activities) {
    echo html_writer::tag('p', get_string('noactivities', 'local_cpdtracker'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('activitydate', 'local_cpdtracker'),
        get_string('title', 'local_cpdtracker'),
        get_string('category', 'local_cpdtracker'),
        get_string('points', 'local_cpdtracker'),
    ];
    if ($cycleopen) {
        $table->head[] = get_string('actions');
    }

    $dateformat = get_string('strftimedate', 'langconfig');
    foreach ($summary->activities as $activity) {
        $categoryname = isset($summary->categories[$activity->categoryid])
            ? format_string($summary->categories[$activity->categoryid]->category->name)
            : '';
        $row = [
            userdate($activity->activitydate, $dateformat),
            format_string($activity->title),
            $categoryname,
            format_float($activity->points, 2),
        ];
        if ($cycleopen) {
            $row[] = $OUTPUT->action_icon(
                new moodle_url('/local/cpdtracker/edit.php', ['id' => $activity->id]),
                new pix_icon('t/edit', get_string('edit'))
            ) . $OUTPUT->action_icon(
                new moodle_url('/local/cpdtracker/edit.php', ['id' => $activity->id, 'delete' => 1]),
                new pix_icon('t/delete', get_string('delete'))
            );
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
