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
 * Admin tree entries for local_cpdtracker.
 *
 * Adds a CPD Tracker folder under Site administration > Plugins > Local plugins.
 * Not wrapped in $hassiteconfig so non-admin roles with the matching
 * capability see these pages too.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('localplugins', new admin_category('local_cpdtracker', get_string('pluginname', 'local_cpdtracker')));

$ADMIN->add('local_cpdtracker', new admin_externalpage(
    'local_cpdtracker_manage',
    get_string('managecycles', 'local_cpdtracker'),
    new moodle_url('/local/cpdtracker/manage.php'),
    'local/cpdtracker:manage'
));

$ADMIN->add('local_cpdtracker', new admin_externalpage(
    'local_cpdtracker_report',
    get_string('compliancereport', 'local_cpdtracker'),
    new moodle_url('/local/cpdtracker/report.php'),
    'local/cpdtracker:viewreport'
));
