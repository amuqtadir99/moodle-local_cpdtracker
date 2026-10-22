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
 * Manage the categories of one CPD cycle: list, reorder and delete.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$cycleid = required_param('cycleid', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$move = optional_param('move', 0, PARAM_INT);
$up = optional_param('up', 0, PARAM_BOOL);

$url = new moodle_url('/local/cpdtracker/categories.php', ['cycleid' => $cycleid]);
admin_externalpage_setup('local_cpdtracker_manage', '', null, $url);

$manager = new \local_cpdtracker\manager();
$cycle = $manager->get_cycle($cycleid);

$title = get_string('managecategoriesfor', 'local_cpdtracker', format_string($cycle->name));
$PAGE->set_title($title);
$PAGE->navbar->add(format_string($cycle->name));

// Reorder.
if ($move) {
    require_sesskey();
    $category = $manager->get_category($move);
    if ((int)$category->cycleid === (int)$cycle->id) {
        $manager->move_category($category->id, (bool)$up);
    }
    redirect($url);
}

// Delete, with a confirmation step. Categories with logged activities cannot be deleted.
if ($delete) {
    $category = $manager->get_category($delete);
    if ((int)$category->cycleid !== (int)$cycle->id) {
        throw new moodle_exception('invalidcategory', 'local_cpdtracker', $url);
    }
    if ($manager->count_category_activities($category->id)) {
        redirect($url, get_string('categoryhasactivities', 'local_cpdtracker'), null, notification::NOTIFY_ERROR);
    }

    if ($confirm) {
        require_sesskey();
        $manager->delete_category($category->id);
        redirect($url, get_string('categorydeleted', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
    }

    $confirmurl = new moodle_url($url, ['delete' => $category->id, 'confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletecategory', 'local_cpdtracker', format_string($category->name)),
        $confirmurl,
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

echo html_writer::tag('p', get_string('cycledates', 'local_cpdtracker', $manager->format_cycle_dates($cycle)) . ' ' .
    get_string('requiredpointsis', 'local_cpdtracker', format_float($cycle->requiredpoints, 2)));

echo html_writer::div(
    $OUTPUT->single_button(new moodle_url('/local/cpdtracker/editcategory.php', ['cycleid' => $cycle->id]),
        get_string('addcategory', 'local_cpdtracker'), 'get') .
    $OUTPUT->single_button(new moodle_url('/local/cpdtracker/manage.php'),
        get_string('backtocycles', 'local_cpdtracker'), 'get'),
    'd-flex gap-2 mb-3'
);

$categories = $manager->get_categories($cycle->id);

if (!$categories) {
    echo $OUTPUT->notification(get_string('nocategoriesadmin', 'local_cpdtracker'), notification::NOTIFY_WARNING);
} else {
    $counts = $manager->count_activities_by_category($cycle->id);
    $last = count($categories) - 1;

    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('minpoints', 'local_cpdtracker'),
        get_string('maxpoints', 'local_cpdtracker'),
        get_string('activities', 'local_cpdtracker'),
        get_string('actions'),
    ];

    $index = 0;
    foreach ($categories as $category) {
        $count = $counts[$category->id] ?? 0;
        $max = (float)$category->maxpoints;

        $actions = '';
        if ($index > 0) {
            $actions .= $OUTPUT->action_icon(
                new moodle_url($url, ['move' => $category->id, 'up' => 1, 'sesskey' => sesskey()]),
                new pix_icon('t/up', get_string('moveup'))
            );
        }
        if ($index < $last) {
            $actions .= $OUTPUT->action_icon(
                new moodle_url($url, ['move' => $category->id, 'up' => 0, 'sesskey' => sesskey()]),
                new pix_icon('t/down', get_string('movedown'))
            );
        }
        $actions .= $OUTPUT->action_icon(
            new moodle_url('/local/cpdtracker/editcategory.php', ['id' => $category->id]),
            new pix_icon('t/edit', get_string('edit'))
        );
        if (!$count) {
            $actions .= $OUTPUT->action_icon(
                new moodle_url($url, ['delete' => $category->id]),
                new pix_icon('t/delete', get_string('delete'))
            );
        }

        $table->data[] = [
            format_string($category->name),
            format_float($category->minpoints, 2),
            $max > 0 ? format_float($max, 2) : get_string('nocap', 'local_cpdtracker'),
            $count,
            $actions,
        ];
        $index++;
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
