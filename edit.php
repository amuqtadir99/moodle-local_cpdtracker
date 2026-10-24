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
 * Log, edit or delete one of the current user's CPD activities.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$context = context_system::instance();
require_capability('local/cpdtracker:logactivity', $context);

$manager = new \local_cpdtracker\manager();
$returnurl = new moodle_url('/local/cpdtracker/index.php');

if ($id) {
    $activity = $manager->get_activity($id);
    $cycle = $manager->get_cycle($activity->cycleid);
    if (!$manager->can_edit_activity($activity, (int)$USER->id, $cycle)) {
        throw new moodle_exception('cannoteditactivity', 'local_cpdtracker', $returnurl);
    }
} else {
    $activity = null;
    $cycle = $manager->get_current_cycle();
    if (!$cycle) {
        throw new moodle_exception('nocurrentcycle', 'local_cpdtracker', $returnurl);
    }
    if (!$manager->can_add_activity((int)$USER->id, (int)$cycle->id)) {
        redirect(
            $returnurl,
            get_string('erroractivitylimit', 'local_cpdtracker', \local_cpdtracker\manager::MAX_ACTIVITIES_PER_CYCLE),
            null,
            notification::NOTIFY_ERROR
        );
    }
}

$pagetitle = get_string($id ? 'editactivity' : 'logactivity', 'local_cpdtracker');
$PAGE->set_url(new moodle_url('/local/cpdtracker/edit.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(get_string('mycpd', 'local_cpdtracker'));

// Delete, with a confirmation step.
if ($delete && $activity) {
    if ($confirm) {
        require_sesskey();
        $manager->delete_activity($activity->id, (int)$USER->id);
        redirect($returnurl, get_string('activitydeleted', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
    }

    $confirmurl = new moodle_url('/local/cpdtracker/edit.php', [
        'id' => $activity->id,
        'delete' => 1,
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'local_cpdtracker', format_string($activity->title)),
        $confirmurl,
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

$categories = $manager->get_categories($cycle->id);
if (!$categories) {
    throw new moodle_exception('nocategories', 'local_cpdtracker', $returnurl);
}

$form = new \local_cpdtracker\form\activity_form($PAGE->url, [
    'cycle' => $cycle,
    'categories' => $categories,
    'id' => $id,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    // Saves the activity and its custom field answers together.
    $manager->save_activity($data, (int)$USER->id);
    redirect($returnurl, get_string('activitysaved', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
}

if ($activity) {
    \local_cpdtracker\customfield\activity_handler::create()->instance_form_before_set_data($activity);
    $form->set_data($activity);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
$form->display();
echo $OUTPUT->footer();
