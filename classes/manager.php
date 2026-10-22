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

namespace local_cpdtracker;

use moodle_exception;
use stdClass;

/**
 * Business logic for cycles, categories, activities and compliance.
 *
 * Page files should only call into this class, so the logic stays in one testable place.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string Cycle table. */
    const TABLE_CYCLE = 'local_cpdtracker_cycle';

    /** @var string Category table. */
    const TABLE_CATEGORY = 'local_cpdtracker_category';

    /** @var string Activity table. */
    const TABLE_ACTIVITY = 'local_cpdtracker_activity';

    /**
     * Get the cycle that is running at the given time.
     *
     * If cycles overlap, the one that started most recently wins.
     *
     * @param int|null $time Unix timestamp, defaults to now.
     * @return stdClass|null The cycle record, or null if none is running.
     */
    public function get_current_cycle(?int $time = null): ?stdClass {
        global $DB;

        $time = $time ?? time();
        $records = $DB->get_records_select(
            self::TABLE_CYCLE,
            'startdate <= :now1 AND enddate >= :now2',
            ['now1' => $time, 'now2' => $time],
            'startdate DESC, id DESC',
            '*',
            0,
            1
        );

        return $records ? reset($records) : null;
    }

    /**
     * Get a cycle by id.
     *
     * @param int $cycleid
     * @return stdClass
     */
    public function get_cycle(int $cycleid): stdClass {
        global $DB;

        return $DB->get_record(self::TABLE_CYCLE, ['id' => $cycleid], '*', MUST_EXIST);
    }

    /**
     * Check whether activities can still be added or changed in a cycle.
     *
     * @param stdClass $cycle
     * @param int|null $time Unix timestamp, defaults to now.
     * @return bool
     */
    public function is_cycle_open(stdClass $cycle, ?int $time = null): bool {
        $time = $time ?? time();

        return $time >= (int)$cycle->startdate && $time <= (int)$cycle->enddate;
    }

    /**
     * Get the categories of a cycle, keyed by id.
     *
     * @param int $cycleid
     * @return stdClass[]
     */
    public function get_categories(int $cycleid): array {
        global $DB;

        return $DB->get_records(self::TABLE_CATEGORY, ['cycleid' => $cycleid], 'sortorder ASC, name ASC');
    }

    /**
     * Get an activity by id.
     *
     * @param int $activityid
     * @return stdClass
     */
    public function get_activity(int $activityid): stdClass {
        global $DB;

        return $DB->get_record(self::TABLE_ACTIVITY, ['id' => $activityid], '*', MUST_EXIST);
    }

    /**
     * Get a user's activities in a cycle, newest first, keyed by id.
     *
     * @param int $userid
     * @param int $cycleid
     * @return stdClass[]
     */
    public function get_user_activities(int $userid, int $cycleid): array {
        global $DB;

        return $DB->get_records(
            self::TABLE_ACTIVITY,
            ['userid' => $userid, 'cycleid' => $cycleid],
            'activitydate DESC, id DESC'
        );
    }

    /**
     * Work out points per category with caps applied.
     *
     * Pure function: no DB access, so it can be unit tested directly.
     * A maxpoints of 0 means the category has no cap. Activities in a
     * category not listed in $categories are ignored.
     *
     * @param stdClass[] $categories Category records keyed by id.
     * @param stdClass[] $activities Activity records.
     * @return stdClass Object with 'categories' (keyed by category id, each
     *     with category, raw, counted, meetsmin) and 'total' (sum of counted).
     */
    public function calculate_points(array $categories, array $activities): stdClass {
        $rows = [];
        foreach ($categories as $category) {
            $rows[$category->id] = (object)[
                'category' => $category,
                'raw' => 0.0,
                'counted' => 0.0,
                'meetsmin' => false,
            ];
        }

        foreach ($activities as $activity) {
            if (isset($rows[$activity->categoryid])) {
                $rows[$activity->categoryid]->raw += (float)$activity->points;
            }
        }

        $total = 0.0;
        foreach ($rows as $row) {
            $max = (float)$row->category->maxpoints;
            $row->raw = round($row->raw, 2);
            $row->counted = ($max > 0) ? min($row->raw, $max) : $row->raw;
            $row->meetsmin = $row->counted >= round((float)$row->category->minpoints, 2);
            $total += $row->counted;
        }

        return (object)[
            'categories' => $rows,
            'total' => round($total, 2),
        ];
    }

    /**
     * Check compliance: total meets the cycle requirement and every category minimum is met.
     *
     * @param stdClass $cycle
     * @param stdClass $points Result of calculate_points().
     * @return bool
     */
    public function is_compliant(stdClass $cycle, stdClass $points): bool {
        if ($points->total < round((float)$cycle->requiredpoints, 2)) {
            return false;
        }
        foreach ($points->categories as $row) {
            if (!$row->meetsmin) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get everything the My CPD page needs for one user in one cycle.
     *
     * @param int $userid
     * @param stdClass $cycle
     * @return stdClass Object with cycle, activities, categories, total, compliant.
     */
    public function get_user_summary(int $userid, stdClass $cycle): stdClass {
        $categories = $this->get_categories($cycle->id);
        $activities = $this->get_user_activities($userid, $cycle->id);
        $points = $this->calculate_points($categories, $activities);

        return (object)[
            'cycle' => $cycle,
            'activities' => $activities,
            'categories' => $points->categories,
            'total' => $points->total,
            'compliant' => $this->is_compliant($cycle, $points),
        ];
    }

    /**
     * Check whether a user may edit or delete an activity.
     *
     * @param stdClass $activity
     * @param int $userid
     * @return bool
     */
    public function can_edit_activity(stdClass $activity, int $userid): bool {
        if ((int)$activity->userid !== $userid) {
            return false;
        }
        return $this->is_cycle_open($this->get_cycle($activity->cycleid));
    }

    /**
     * Create or update an activity for a user.
     *
     * @param stdClass $data Form data: id (0 for new), cycleid, categoryid, title,
     *     description, activitydate, points.
     * @param int $userid Owner of the activity.
     * @return int Activity id.
     * @throws moodle_exception If the cycle is closed, the data is invalid or the user does not own the activity.
     */
    public function save_activity(stdClass $data, int $userid): int {
        global $DB;

        $cycle = $this->get_cycle((int)$data->cycleid);
        if (!$this->is_cycle_open($cycle)) {
            throw new moodle_exception('cycleclosed', 'local_cpdtracker');
        }
        if (!$DB->record_exists(self::TABLE_CATEGORY, ['id' => $data->categoryid, 'cycleid' => $cycle->id])) {
            throw new moodle_exception('invalidcategory', 'local_cpdtracker');
        }
        if ((float)$data->points <= 0) {
            throw new moodle_exception('errorpointspositive', 'local_cpdtracker');
        }
        if ($data->activitydate < $cycle->startdate || $data->activitydate > $cycle->enddate) {
            throw new moodle_exception('errordateoutsidecycle', 'local_cpdtracker', '', $this->format_cycle_dates($cycle));
        }

        $now = time();
        $record = (object)[
            'cycleid' => $cycle->id,
            'categoryid' => (int)$data->categoryid,
            'title' => $data->title,
            'description' => $data->description ?? '',
            'activitydate' => (int)$data->activitydate,
            'points' => round((float)$data->points, 2),
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $existing = $this->get_activity((int)$data->id);
            if ((int)$existing->userid !== $userid || (int)$existing->cycleid !== (int)$cycle->id) {
                throw new moodle_exception('cannoteditactivity', 'local_cpdtracker');
            }
            $record->id = $existing->id;
            $DB->update_record(self::TABLE_ACTIVITY, $record);
            return (int)$record->id;
        }

        $record->userid = $userid;
        $record->timecreated = $now;
        return (int)$DB->insert_record(self::TABLE_ACTIVITY, $record);
    }

    /**
     * Delete an activity owned by a user.
     *
     * @param int $activityid
     * @param int $userid
     * @throws moodle_exception If the user may not edit the activity.
     */
    public function delete_activity(int $activityid, int $userid): void {
        global $DB;

        $activity = $this->get_activity($activityid);
        if (!$this->can_edit_activity($activity, $userid)) {
            throw new moodle_exception('cannoteditactivity', 'local_cpdtracker');
        }
        $DB->delete_records(self::TABLE_ACTIVITY, ['id' => $activity->id]);
    }

    /**
     * Format a cycle's start and end dates for display.
     *
     * @param stdClass $cycle
     * @return stdClass Object with start and end, for use as a string placeholder.
     */
    public function format_cycle_dates(stdClass $cycle): stdClass {
        $format = get_string('strftimedate', 'langconfig');

        return (object)[
            'start' => userdate($cycle->startdate, $format),
            'end' => userdate($cycle->enddate, $format),
        ];
    }
}
