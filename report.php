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
 * Compliance report: each member's points and status for one cycle.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$cycleid = optional_param('cycleid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

// Handles require_login, the local/cpdtracker:viewreport check, page context and admin navigation.
admin_externalpage_setup('local_cpdtracker_report', '', ['cycleid' => $cycleid]);

$manager = new \local_cpdtracker\manager();
$cycles = $manager->get_cycles();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('compliancereport', 'local_cpdtracker'));

if (!$cycles) {
    echo html_writer::tag('p', get_string('nocycles', 'local_cpdtracker'));
    echo $OUTPUT->footer();
    exit;
}

// Default to the current cycle, else the most recent one.
if (!isset($cycles[$cycleid])) {
    $current = $manager->get_current_cycle();
    $cycleid = $current ? (int)$current->id : (int)array_key_first($cycles);
}
$cycle = $cycles[$cycleid];
$baseurl = new moodle_url('/local/cpdtracker/report.php', ['cycleid' => $cycleid]);

$options = [];
foreach ($cycles as $option) {
    $options[$option->id] = format_string($option->name);
}
$select = new single_select(new moodle_url('/local/cpdtracker/report.php'), 'cycleid', $options, $cycleid, null);
$select->set_label(get_string('cycle', 'local_cpdtracker'));
echo $OUTPUT->render($select);

echo html_writer::tag('p', get_string('cycledates', 'local_cpdtracker', $manager->format_cycle_dates($cycle)) . ' ' .
    get_string('requiredpointsis', 'local_cpdtracker', format_float($cycle->requiredpoints, 2)));

$report = $manager->get_report_page($cycle, $page, $perpage);

if (!$report->total) {
    echo html_writer::tag('p', get_string('nomembers', 'local_cpdtracker'));
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('member', 'local_cpdtracker'),
    get_string('pointscounted', 'local_cpdtracker'),
    get_string('categoriesmet', 'local_cpdtracker'),
    get_string('activities', 'local_cpdtracker'),
    get_string('status', 'local_cpdtracker'),
];

$required = format_float($cycle->requiredpoints, 2);
foreach ($report->rows as $row) {
    $table->data[] = [
        html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->user->id]), fullname($row->user)),
        get_string('xofy', 'local_cpdtracker', (object)['x' => format_float($row->total, 2), 'y' => $required]),
        get_string('xofy', 'local_cpdtracker', (object)['x' => $row->categoriesmet, 'y' => $row->categorycount]),
        $row->activitycount,
        $row->compliant
            ? html_writer::span(get_string('compliantshort', 'local_cpdtracker'), 'badge bg-success text-white')
            : html_writer::span(get_string('notcompliantshort', 'local_cpdtracker'), 'badge bg-warning text-dark'),
    ];
}

echo html_writer::tag('p', get_string('membercount', 'local_cpdtracker', $report->total));
if ($table->data) {
    echo html_writer::table($table);
}
echo $OUTPUT->paging_bar($report->total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
