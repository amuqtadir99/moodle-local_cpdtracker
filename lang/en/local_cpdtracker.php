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
 * English strings for local_cpdtracker.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activities'] = 'Activities';
$string['activitydate'] = 'Activity date';
$string['activitydeleted'] = 'Activity deleted.';
$string['activitysaved'] = 'Activity saved.';
$string['cannoteditactivity'] = 'You cannot edit this activity.';
$string['category'] = 'Category';
$string['compliant'] = 'You have met the CPD requirements for this cycle.';
$string['confirmdelete'] = 'Delete the activity "{$a}"? This cannot be undone.';
$string['cpdtracker:logactivity'] = 'Log own CPD activities';
$string['cpdtracker:manage'] = 'Manage CPD cycles and categories';
$string['cpdtracker:viewreport'] = 'View CPD compliance report';
$string['cycleclosed'] = 'This CPD cycle is closed. Activities can no longer be added or changed.';
$string['cycledates'] = 'Cycle runs from {$a->start} to {$a->end}.';
$string['description'] = 'Description';
$string['editactivity'] = 'Edit activity';
$string['errordateoutsidecycle'] = 'The activity date must fall within the cycle ({$a->start} to {$a->end}).';
$string['errorpointspositive'] = 'Points must be greater than zero.';
$string['invalidcategory'] = 'The selected category does not belong to this cycle.';
$string['logactivity'] = 'Log activity';
$string['maxpoints'] = 'Maximum points';
$string['met'] = 'Met';
$string['minpoints'] = 'Minimum points';
$string['mycpd'] = 'My CPD';
$string['noactivities'] = 'You have not logged any activities in this cycle yet.';
$string['nocap'] = 'No cap';
$string['nocategories'] = 'No categories have been set up for this cycle yet. Contact your administrator.';
$string['nocurrentcycle'] = 'There is no active CPD cycle. Contact your administrator.';
$string['notcompliant'] = 'You have not yet met the CPD requirements for this cycle.';
$string['notmet'] = 'Not met';
$string['pluginname'] = 'CPD Tracker';
$string['points'] = 'Points';
$string['pointscounted'] = 'Points counted';
$string['pointslogged'] = 'Points logged';
$string['pointstotal'] = 'Total points counted: {$a->total} of {$a->required} required.';
$string['privacy:metadata:activity'] = 'CPD activities logged by the member.';
$string['privacy:metadata:activity:activitydate'] = 'The date the activity took place.';
$string['privacy:metadata:activity:categoryid'] = 'The CPD category the activity counts towards.';
$string['privacy:metadata:activity:cycleid'] = 'The CPD cycle the activity belongs to.';
$string['privacy:metadata:activity:description'] = 'The member\'s description of the activity.';
$string['privacy:metadata:activity:points'] = 'The points claimed for the activity.';
$string['privacy:metadata:activity:timecreated'] = 'The time the activity was logged.';
$string['privacy:metadata:activity:timemodified'] = 'The time the activity was last changed.';
$string['privacy:metadata:activity:title'] = 'The title of the activity.';
$string['privacy:metadata:activity:userid'] = 'The ID of the member who logged the activity.';
$string['status'] = 'Status';
$string['title'] = 'Title';
