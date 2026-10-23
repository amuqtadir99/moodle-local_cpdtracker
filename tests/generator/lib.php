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
 * Test data generator for local_cpdtracker.
 *
 * Writes records directly, without manager validation, so tests can build
 * any state they need (for example activities in a closed cycle).
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_cpdtracker_generator extends component_generator_base {
    /** @var int Cycles created since the last reset. */
    protected $cyclecount = 0;

    /** @var int Categories created since the last reset. */
    protected $categorycount = 0;

    /** @var int Activities created since the last reset. */
    protected $activitycount = 0;

    /**
     * Reset counters between tests.
     */
    public function reset() {
        $this->cyclecount = 0;
        $this->categorycount = 0;
        $this->activitycount = 0;
    }

    /**
     * Create a cycle. Defaults to one running from 30 days ago to 30 days ahead, needing 30 points.
     *
     * @param array $record Fields to override.
     * @return stdClass
     */
    public function create_cycle(array $record = []): stdClass {
        global $DB;

        $this->cyclecount++;
        $now = time();
        $record = (object)array_merge([
            'name' => 'Cycle ' . $this->cyclecount,
            'description' => '',
            'startdate' => $now - 30 * DAYSECS,
            'enddate' => $now + 30 * DAYSECS,
            'requiredpoints' => 30,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $id = $DB->insert_record('local_cpdtracker_cycle', $record);
        return $DB->get_record('local_cpdtracker_cycle', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create a category. Requires cycleid. Defaults to no minimum, no cap and direct points entry, placed last.
     *
     * @param array $record Fields to override; must include cycleid.
     * @return stdClass
     */
    public function create_category(array $record): stdClass {
        global $DB;

        if (empty($record['cycleid'])) {
            throw new coding_exception('create_category() requires cycleid');
        }

        $this->categorycount++;
        $now = time();
        $maxsort = (int)$DB->get_field('local_cpdtracker_category', 'MAX(sortorder)', ['cycleid' => $record['cycleid']]);
        $record = (object)array_merge([
            'name' => 'Category ' . $this->categorycount,
            'description' => '',
            'minpoints' => 0,
            'maxpoints' => 0,
            'pointsperhour' => 0,
            'sortorder' => $maxsort + 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $id = $DB->insert_record('local_cpdtracker_category', $record);
        return $DB->get_record('local_cpdtracker_category', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Create an activity. Requires userid, cycleid and categoryid. Defaults to 5 points, no hours, dated now.
     *
     * @param array $record Fields to override; must include userid, cycleid and categoryid.
     * @return stdClass
     */
    public function create_activity(array $record): stdClass {
        global $DB;

        foreach (['userid', 'cycleid', 'categoryid'] as $field) {
            if (empty($record[$field])) {
                throw new coding_exception("create_activity() requires $field");
            }
        }

        $this->activitycount++;
        $now = time();
        $record = (object)array_merge([
            'title' => 'Activity ' . $this->activitycount,
            'description' => '',
            'activitydate' => $now,
            'points' => 5,
            'hours' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $id = $DB->insert_record('local_cpdtracker_activity', $record);
        return $DB->get_record('local_cpdtracker_activity', ['id' => $id], '*', MUST_EXIST);
    }
}
