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
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_cpdtracker\customfield\activity_handler;
use local_cpdtracker\output\helper;

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

// Shortcuts for staff.
$buttons = '';
if (has_capability('local/cpdtracker:manage', $context)) {
    $buttons .= $OUTPUT->single_button(
        new moodle_url('/local/cpdtracker/manage.php'),
        get_string('managecycles', 'local_cpdtracker'),
        'get'
    );
}
if (has_capability('local/cpdtracker:viewreport', $context)) {
    $buttons .= $OUTPUT->single_button(
        new moodle_url('/local/cpdtracker/report.php'),
        get_string('compliancereport', 'local_cpdtracker'),
        'get'
    );
}
if ($buttons) {
    echo html_writer::div($buttons, 'd-flex gap-2 mb-3');
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
echo html_writer::div(
    helper::progress_bar($summary->total, (float)$cycle->requiredpoints, get_string('overallprogress', 'local_cpdtracker')),
    'mb-3'
);

if ($summary->compliant) {
    echo $OUTPUT->notification(get_string('compliant', 'local_cpdtracker'), notification::NOTIFY_SUCCESS);
} else {
    echo $OUTPUT->notification(get_string('notcompliant', 'local_cpdtracker'), notification::NOTIFY_WARNING);
}

// Category breakdown, with each category's guidance and progress towards its minimum.
if ($summary->categories) {
    $table = new html_table();
    $table->head = [
        get_string('category', 'local_cpdtracker'),
        get_string('minpoints', 'local_cpdtracker'),
        get_string('maxpoints', 'local_cpdtracker'),
        get_string('pointslogged', 'local_cpdtracker'),
        get_string('pointscounted', 'local_cpdtracker'),
        get_string('progress', 'local_cpdtracker'),
        get_string('status', 'local_cpdtracker'),
    ];
    foreach ($summary->categories as $row) {
        $category = $row->category;
        $max = (float)$category->maxpoints;
        $min = (float)$category->minpoints;
        $name = format_string($category->name);

        $details = html_writer::tag('strong', $name);
        if ($manager->is_hourly($category)) {
            $details .= html_writer::div(
                get_string('pointsperhourrate', 'local_cpdtracker', format_float($category->pointsperhour, 2)),
                'small text-muted'
            );
        }
        if (trim((string)$category->description) !== '') {
            $details .= html_writer::div(format_text($category->description, FORMAT_PLAIN, ['context' => $context]), 'small');
        }

        $table->data[] = [
            $details,
            format_float($min, 2),
            $max > 0 ? format_float($max, 2) : get_string('nocap', 'local_cpdtracker'),
            format_float($row->raw, 2),
            format_float($row->counted, 2),
            $min > 0 ? helper::progress_bar($row->counted, $min, $name) : get_string('nominimum', 'local_cpdtracker'),
            get_string($row->meetsmin ? 'met' : 'notmet', 'local_cpdtracker'),
        ];
    }
    echo html_writer::table($table);
}

// Activities.
echo $OUTPUT->heading(get_string('activities', 'local_cpdtracker'), 4);

if ($cycleopen) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/cpdtracker/edit.php'),
        get_string('logactivity', 'local_cpdtracker'),
        'get'
    );
}

if (!$summary->activities) {
    echo html_writer::tag('p', get_string('noactivities', 'local_cpdtracker'));
} else {
    $table = new html_table();
    $table->head = [
        get_string('activitydate', 'local_cpdtracker'),
        get_string('title', 'local_cpdtracker'),
        get_string('category', 'local_cpdtracker'),
        get_string('hours', 'local_cpdtracker'),
        get_string('points', 'local_cpdtracker'),
    ];
    if ($cycleopen) {
        $table->head[] = get_string('actions');
    }

    // Custom field answers for every activity on the page, in one query.
    $handler = activity_handler::create();
    $fielddata = $handler->get_instances_data(array_keys($summary->activities));

    $dateformat = get_string('strftimedate', 'langconfig');
    foreach ($summary->activities as $activity) {
        $categoryname = isset($summary->categories[$activity->categoryid])
            ? format_string($summary->categories[$activity->categoryid]->category->name)
            : '';
        $title = format_string($activity->title);
        if (!empty($fielddata[$activity->id])) {
            $title .= html_writer::div($handler->display_custom_fields_data($fielddata[$activity->id]), 'small text-muted');
        }
        $row = [
            userdate($activity->activitydate, $dateformat),
            $title,
            $categoryname,
            $activity->hours === null ? '' : format_float($activity->hours, 2),
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

    echo $OUTPUT->download_dataformat_selector(
        get_string('downloadrecord', 'local_cpdtracker'),
        new moodle_url('/local/cpdtracker/download.php'),
        'dataformat',
        ['cycleid' => $cycle->id]
    );
}

echo $OUTPUT->footer();
