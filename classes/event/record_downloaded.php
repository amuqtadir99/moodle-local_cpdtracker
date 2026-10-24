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

namespace local_cpdtracker\event;

use core\event\base;
use stdClass;

/**
 * Event triggered when a member downloads their own CPD record (an export of personal data).
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_downloaded extends base {
    /**
     * Set the event's static data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_cpdtracker_cycle';
    }

    /**
     * Create the event for a member downloading their record for a cycle.
     *
     * @param stdClass $cycle
     * @param int $userid The member whose record was downloaded.
     * @param string $dataformat Data format plugin used, for example csv.
     * @return self
     */
    public static function create_from_cycle(stdClass $cycle, int $userid, string $dataformat): self {
        return static::create([
            'objectid' => $cycle->id,
            'relateduserid' => $userid,
            'context' => \context_user::instance($userid),
            'other' => ['dataformat' => $dataformat],
        ]);
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventrecorddownloaded', 'local_cpdtracker');
    }

    /**
     * Non-localised description, as shown in logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' downloaded the CPD record of the user with id '$this->relateduserid' " .
            "for the cycle with id '$this->objectid' as '{$this->other['dataformat']}'.";
    }

    /**
     * Link to the member's CPD page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/cpdtracker/index.php');
    }

    /**
     * Check the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
        if (!isset($this->other['dataformat'])) {
            throw new \coding_exception('The dataformat must be set in other.');
        }
    }

    /**
     * Cycles are not part of course backups.
     *
     * @return int
     */
    public static function get_objectid_mapping() {
        return base::NOT_MAPPED;
    }

    /**
     * Not part of course backups.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
