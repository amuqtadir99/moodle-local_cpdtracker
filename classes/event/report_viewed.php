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
 * Event triggered when staff view the compliance report, which shows other members' personal data.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_viewed extends base {
    /**
     * Set the event's static data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_cpdtracker_cycle';
    }

    /**
     * Create the event for a cycle's report.
     *
     * @param stdClass $cycle
     * @return self
     */
    public static function create_from_cycle(stdClass $cycle): self {
        $event = static::create([
            'objectid' => $cycle->id,
            'context' => \context_system::instance(),
        ]);
        $event->add_record_snapshot('local_cpdtracker_cycle', $cycle);
        return $event;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportviewed', 'local_cpdtracker');
    }

    /**
     * Non-localised description, as shown in logs.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the CPD compliance report for the cycle with id '$this->objectid'.";
    }

    /**
     * Link to the report for the cycle.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/cpdtracker/report.php', ['cycleid' => $this->objectid]);
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
     * Cycles are not part of course backups.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
