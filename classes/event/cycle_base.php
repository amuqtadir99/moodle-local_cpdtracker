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
 * Base for events about a CPD cycle, logged in the system context.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class cycle_base extends base {
    /**
     * Set the event's static data.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_cpdtracker_cycle';
    }

    /**
     * Create the event from a cycle record.
     *
     * @param stdClass $cycle
     * @return static
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
     * Link to the cycle management page.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/cpdtracker/manage.php');
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
