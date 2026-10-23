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
 * Design the extra questions on the CPD activity form, using Moodle custom fields.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_customfield\output\management;
use local_cpdtracker\customfield\activity_handler;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Handles require_login, the local/cpdtracker:manage check, page context and admin navigation.
admin_externalpage_setup('local_cpdtracker_customfields');

$output = $PAGE->get_renderer('core_customfield');
$handler = activity_handler::create();

echo $output->header();
echo $output->heading(get_string('activityformfields', 'local_cpdtracker'));
echo html_writer::tag('p', get_string('activityformfields_desc', 'local_cpdtracker'));
echo $output->render(new management($handler));
echo $output->footer();
