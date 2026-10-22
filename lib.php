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
 * Callbacks for local_cpdtracker.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a "My CPD" link to the user's own profile page.
 *
 * @param \core_user\output\myprofile\tree $tree Profile tree.
 * @param stdClass $user Profile owner.
 * @param bool $iscurrentuser Whether the profile belongs to the viewer.
 * @param stdClass|null $course Course the profile is viewed in, if any.
 * @return bool
 */
function local_cpdtracker_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser || isguestuser($user)) {
        return false;
    }
    if (!has_capability('local/cpdtracker:logactivity', context_system::instance())) {
        return false;
    }

    $url = new moodle_url('/local/cpdtracker/index.php');
    $node = new \core_user\output\myprofile\node('miscellaneous', 'local_cpdtracker',
        get_string('mycpd', 'local_cpdtracker'), null, $url);
    $tree->add_node($node);

    return true;
}
