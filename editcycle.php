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
 * Add or edit a CPD cycle.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/cpdtracker/editcycle.php', ['id' => $id]);
admin_externalpage_setup('local_cpdtracker_manage', '', null, $url);

$manager = new \local_cpdtracker\manager();
$returnurl = new moodle_url('/local/cpdtracker/manage.php');
$cycle = $id ? $manager->get_cycle($id) : null;

$title = get_string($id ? 'editcycle' : 'addcycle', 'local_cpdtracker');
$PAGE->set_title($title);
$PAGE->navbar->add($title);

$form = new \local_cpdtracker\form\cycle_form($url, ['id' => $id]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $data->id = $id;
    $cycleid = $manager->save_cycle($data);
    // A new cycle needs categories next, so go straight there.
    $next = $id ? $returnurl : new moodle_url('/local/cpdtracker/categories.php', ['cycleid' => $cycleid]);
    redirect($next, get_string('cyclesaved', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
}

if ($cycle) {
    $form->set_data($cycle);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
