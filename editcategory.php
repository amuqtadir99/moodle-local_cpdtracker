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
 * Add or edit a CPD category.
 *
 * Add: ?cycleid=X. Edit: ?id=Y (the cycle is taken from the category).
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);
$cycleid = optional_param('cycleid', 0, PARAM_INT);

$url = new moodle_url('/local/cpdtracker/editcategory.php', ['id' => $id, 'cycleid' => $cycleid]);
admin_externalpage_setup('local_cpdtracker_manage', '', null, $url);

$manager = new \local_cpdtracker\manager();
$category = $id ? $manager->get_category($id) : null;
$cycle = $manager->get_cycle($category ? (int)$category->cycleid : $cycleid);
$returnurl = new moodle_url('/local/cpdtracker/categories.php', ['cycleid' => $cycle->id]);

$title = get_string($id ? 'editcategory' : 'addcategory', 'local_cpdtracker');
$PAGE->set_title($title);
$PAGE->navbar->add(format_string($cycle->name), $returnurl);
$PAGE->navbar->add($title);

$form = new \local_cpdtracker\form\category_form($url);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    $data->id = $id;
    $data->cycleid = $cycle->id;
    $manager->save_category($data);
    redirect($returnurl, get_string('categorysaved', 'local_cpdtracker'), null, notification::NOTIFY_SUCCESS);
}

if ($category) {
    $form->set_data($category);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title . ': ' . format_string($cycle->name));
$form->display();
echo $OUTPUT->footer();
