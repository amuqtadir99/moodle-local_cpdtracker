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
 * Download the current user's own CPD record for one cycle.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cycleid = required_param('cycleid', PARAM_INT);
$dataformat = required_param('dataformat', PARAM_ALPHA);

require_login();

$context = context_system::instance();
require_capability('local/cpdtracker:logactivity', $context);

$PAGE->set_url(new moodle_url('/local/cpdtracker/download.php', ['cycleid' => $cycleid]));
$PAGE->set_context($context);

$manager = new \local_cpdtracker\manager();
$cycle = $manager->get_cycle($cycleid);

// Only ever the current user's own activities.
$export = $manager->get_record_export((int)$USER->id, $cycle);
$filename = clean_filename(get_string('downloadfilename', 'local_cpdtracker', format_string($cycle->name)));

\core\dataformat::download_data($filename, $dataformat, $export->columns, $export->rows);
