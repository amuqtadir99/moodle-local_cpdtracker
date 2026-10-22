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
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activities'] = 'Activities';
$string['activitydate'] = 'Activity date';
$string['activitydeleted'] = 'Activity deleted.';
$string['activitysaved'] = 'Activity saved.';
$string['addcategory'] = 'Add category';
$string['addcycle'] = 'Add cycle';
$string['backtocycles'] = 'Back to cycles';
$string['cannoteditactivity'] = 'You cannot edit this activity.';
$string['categories'] = 'Categories';
$string['categoriesmet'] = 'Categories met';
$string['category'] = 'Category';
$string['categorydeleted'] = 'Category deleted.';
$string['categoryhasactivities'] = 'This category has logged activities and cannot be deleted.';
$string['categorysaved'] = 'Category saved.';
$string['compliancereport'] = 'CPD compliance report';
$string['compliant'] = 'You have met the CPD requirements for this cycle.';
$string['compliantshort'] = 'Compliant';
$string['confirmdelete'] = 'Delete the activity "{$a}"? This cannot be undone.';
$string['confirmdeletecategory'] = 'Delete the category "{$a}"? This cannot be undone.';
$string['confirmdeletecycle'] = 'Delete the cycle "{$a}" and all its categories? This cannot be undone.';
$string['cpdtracker:logactivity'] = 'Log own CPD activities';
$string['cpdtracker:manage'] = 'Manage CPD cycles and categories';
$string['cpdtracker:viewreport'] = 'View CPD compliance report';
$string['cycle'] = 'Cycle';
$string['cycleclosed'] = 'This CPD cycle is closed. Activities can no longer be added or changed.';
$string['cyclecurrent'] = 'Current';
$string['cycledates'] = 'Cycle runs from {$a->start} to {$a->end}.';
$string['cycledeleted'] = 'Cycle deleted.';
$string['cycleended'] = 'Ended';
$string['cyclehasactivities'] = 'This cycle has logged activities and cannot be deleted.';
$string['cyclesaved'] = 'Cycle saved.';
$string['cycleupcoming'] = 'Upcoming';
$string['description'] = 'Description';
$string['editactivity'] = 'Edit activity';
$string['editcategory'] = 'Edit category';
$string['editcycle'] = 'Edit cycle';
$string['enddate'] = 'End date';
$string['erroractivitiesoutside'] = '{$a} logged activities fall outside these dates. Choose dates that include all existing activities.';
$string['errordateoutsidecycle'] = 'The activity date must fall within the cycle ({$a->start} to {$a->end}).';
$string['errorenddatebeforestart'] = 'The end date must be on or after the start date.';
$string['errorinvaliddata'] = 'Invalid data: {$a}';
$string['errormaxbelowmin'] = 'The maximum must be 0 (no cap) or at least the minimum.';
$string['errornegativepoints'] = 'Points cannot be negative.';
$string['erroroverlap'] = 'These dates overlap the cycle "{$a}". Cycles cannot overlap.';
$string['errorpointspositive'] = 'Points must be greater than zero.';
$string['invalidcategory'] = 'The selected category does not belong to this cycle.';
$string['logactivity'] = 'Log activity';
$string['managecategoriesfor'] = 'Categories: {$a}';
$string['managecycles'] = 'Manage CPD cycles';
$string['maxpoints'] = 'Maximum points';
$string['maxpoints_help'] = 'The most points from this category that count towards the cycle total. Members can log more, but the extra is not counted. Enter 0 for no cap.';
$string['member'] = 'Member';
$string['membercount'] = '{$a} members.';
$string['met'] = 'Met';
$string['minpoints'] = 'Minimum points';
$string['minpoints_help'] = 'The points a member must have counted in this category to be compliant. Enter 0 for no minimum.';
$string['mycpd'] = 'My CPD';
$string['noactivities'] = 'You have not logged any activities in this cycle yet.';
$string['nocap'] = 'No cap';
$string['nocategories'] = 'No categories have been set up for this cycle yet. Contact your administrator.';
$string['nocategoriesadmin'] = 'This cycle has no categories yet. Add at least one so members can log activities.';
$string['nocurrentcycle'] = 'There is no active CPD cycle. Contact your administrator.';
$string['nocycles'] = 'No cycles have been set up yet.';
$string['nomembers'] = 'No members found. Members are active users with the "Log own CPD activities" capability.';
$string['notcompliant'] = 'You have not yet met the CPD requirements for this cycle.';
$string['notcompliantshort'] = 'Not compliant';
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
$string['requiredpoints'] = 'Required points';
$string['requiredpoints_help'] = 'The total points a member must have counted in this cycle to be compliant, after category caps are applied.';
$string['requiredpointsis'] = 'Members need {$a} points.';
$string['startdate'] = 'Start date';
$string['status'] = 'Status';
$string['title'] = 'Title';
$string['xofy'] = '{$a->x} of {$a->y}';
