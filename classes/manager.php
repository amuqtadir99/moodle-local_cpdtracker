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

    /**
     * Get all cycles, newest first, with category and activity counts.
     *
     * @return stdClass[] Cycle records keyed by id, each with categorycount and activitycount.
     */
    public function get_cycles(): array {
        global $DB;

        $sql = "SELECT c.*,
                       (SELECT COUNT(1) FROM {local_cpdtracker_category} ca WHERE ca.cycleid = c.id) AS categorycount,
                       (SELECT COUNT(1) FROM {local_cpdtracker_activity} a WHERE a.cycleid = c.id) AS activitycount
                  FROM {local_cpdtracker_cycle} c
              ORDER BY c.startdate DESC, c.id DESC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Where a cycle sits relative to the given time.
     *
     * @param stdClass $cycle
     * @param int|null $time Unix timestamp, defaults to now.
     * @return string 'upcoming', 'current' or 'ended'.
     */
    public function get_cycle_status(stdClass $cycle, ?int $time = null): string {
        $time = $time ?? time();

        if ($time < (int)$cycle->startdate) {
            return 'upcoming';
        }
        if ($time > (int)$cycle->enddate) {
            return 'ended';
        }
        return 'current';
    }

    /**
     * Midnight at the start of the day containing $time, in the current user's timezone.
     *
     * @param int $time
     * @return int
     */
    public function start_of_day(int $time): int {
        return usergetmidnight($time);
    }

    /**
     * 23:59:59 on the day containing $time, in the current user's timezone.
     *
     * Cycles end at the end of their last day so members can log activities on it.
     *
     * @param int $time
     * @return int
     */
    public function end_of_day(int $time): int {
        $date = new \DateTime('@' . $time);
        $date->setTimezone(\core_date::get_user_timezone_object());
        $date->setTime(23, 59, 59);

        return $date->getTimestamp();
    }

    /**
     * Find a cycle whose dates overlap the given range.
     *
     * @param int $start
     * @param int $end
     * @param int $excludeid Cycle to ignore, e.g. the one being edited.
     * @return stdClass|null
     */
    public function find_overlapping_cycle(int $start, int $end, int $excludeid = 0): ?stdClass {
        global $DB;

        $records = $DB->get_records_select(
            self::TABLE_CYCLE,
            'startdate <= :enddate AND enddate >= :startdate AND id <> :excludeid',
            ['enddate' => $end, 'startdate' => $start, 'excludeid' => $excludeid],
            'startdate ASC',
            '*',
            0,
            1
        );

        return $records ? reset($records) : null;
    }

    /**
     * Count activities logged in a cycle.
     *
     * @param int $cycleid
     * @return int
     */
    public function count_cycle_activities(int $cycleid): int {
        global $DB;

        return $DB->count_records(self::TABLE_ACTIVITY, ['cycleid' => $cycleid]);
    }

    /**
     * Count a cycle's activities dated outside a range.
     *
     * @param int $cycleid
     * @param int $start
     * @param int $end
     * @return int
     */
    public function count_activities_outside(int $cycleid, int $start, int $end): int {
        global $DB;

        return $DB->count_records_select(
            self::TABLE_ACTIVITY,
            'cycleid = :cycleid AND (activitydate < :startdate OR activitydate > :enddate)',
            ['cycleid' => $cycleid, 'startdate' => $start, 'enddate' => $end]
        );
    }

    /**
     * Check cycle data before saving.
     *
     * Used by both the form and save_cycle(), so the rules live in one place.
     *
     * @param stdClass $data id (0 for new), name, startdate, enddate, requiredpoints.
     * @return string[] Error messages keyed by field name, empty if valid.
     */
    public function validate_cycle(stdClass $data): array {
        $errors = [];
        $id = (int)($data->id ?? 0);

        $name = trim($data->name ?? '');
        if ($name === '') {
            $errors['name'] = get_string('required');
        } else if (\core_text::strlen($name) > 255) {
            $errors['name'] = get_string('maximumchars', '', 255);
        }

        if ((float)($data->requiredpoints ?? 0) < 0) {
            $errors['requiredpoints'] = get_string('errornegativepoints', 'local_cpdtracker');
        }

        $start = $this->start_of_day((int)$data->startdate);
        $end = $this->end_of_day((int)$data->enddate);
        if ($end < $start) {
            $errors['enddate'] = get_string('errorenddatebeforestart', 'local_cpdtracker');
            return $errors;
        }

        $overlap = $this->find_overlapping_cycle($start, $end, $id);
        if ($overlap) {
            $errors['startdate'] = get_string('erroroverlap', 'local_cpdtracker', format_string($overlap->name));
        }

        if ($id) {
            $outside = $this->count_activities_outside($id, $start, $end);
            if ($outside) {
                $errors['enddate'] = get_string('erroractivitiesoutside', 'local_cpdtracker', $outside);
            }
        }

        return $errors;
    }

    /**
     * Create or update a cycle. Start and end dates are widened to whole days.
     *
     * @param stdClass $data id (0 for new), name, description, startdate, enddate, requiredpoints.
     * @return int Cycle id.
     * @throws moodle_exception If the data fails validate_cycle().
     */
    public function save_cycle(stdClass $data): int {
        global $DB;

        $errors = $this->validate_cycle($data);
        if ($errors) {
            throw new moodle_exception('errorinvaliddata', 'local_cpdtracker', '', implode(' ', $errors));
        }

        $now = time();
        $record = (object)[
            'name' => trim($data->name),
            'description' => $data->description ?? '',
            'startdate' => $this->start_of_day((int)$data->startdate),
            'enddate' => $this->end_of_day((int)$data->enddate),
            'requiredpoints' => round((float)$data->requiredpoints, 2),
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $record->id = $this->get_cycle((int)$data->id)->id;
            $DB->update_record(self::TABLE_CYCLE, $record);
            return (int)$record->id;
        }

        $record->timecreated = $now;
        return (int)$DB->insert_record(self::TABLE_CYCLE, $record);
    }

    /**
     * Delete a cycle and its categories. Refused if members have logged activities in it.
     *
     * @param int $cycleid
     * @throws moodle_exception If the cycle has activities.
     */
    public function delete_cycle(int $cycleid): void {
        global $DB;

        $cycle = $this->get_cycle($cycleid);
        if ($this->count_cycle_activities($cycle->id)) {
            throw new moodle_exception('cyclehasactivities', 'local_cpdtracker');
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::TABLE_CATEGORY, ['cycleid' => $cycle->id]);
        $DB->delete_records(self::TABLE_CYCLE, ['id' => $cycle->id]);
        $transaction->allow_commit();
    }

    /**
     * Get a category by id.
     *
     * @param int $categoryid
     * @return stdClass
     */
    public function get_category(int $categoryid): stdClass {
        global $DB;

        return $DB->get_record(self::TABLE_CATEGORY, ['id' => $categoryid], '*', MUST_EXIST);
    }

    /**
     * Count activities logged in a category.
     *
     * @param int $categoryid
     * @return int
     */
    public function count_category_activities(int $categoryid): int {
        global $DB;

        return $DB->count_records(self::TABLE_ACTIVITY, ['categoryid' => $categoryid]);
    }

    /**
     * Count activities per category in a cycle.
     *
     * @param int $cycleid
     * @return int[] Counts keyed by category id. Categories with no activities are missing.
     */
    public function count_activities_by_category(int $cycleid): array {
        global $DB;

        $sql = "SELECT categoryid, COUNT(1)
                  FROM {local_cpdtracker_activity}
                 WHERE cycleid = :cycleid
              GROUP BY categoryid";

        return array_map('intval', $DB->get_records_sql_menu($sql, ['cycleid' => $cycleid]));
    }

    /**
     * Check category data before saving.
     *
     * @param stdClass $data name, minpoints, maxpoints.
     * @return string[] Error messages keyed by field name, empty if valid.
     */
    public function validate_category(stdClass $data): array {
        $errors = [];

        $name = trim($data->name ?? '');
        if ($name === '') {
            $errors['name'] = get_string('required');
        } else if (\core_text::strlen($name) > 255) {
            $errors['name'] = get_string('maximumchars', '', 255);
        }

        $min = (float)($data->minpoints ?? 0);
        $max = (float)($data->maxpoints ?? 0);
        if ($min < 0) {
            $errors['minpoints'] = get_string('errornegativepoints', 'local_cpdtracker');
        }
        if ($max < 0) {
            $errors['maxpoints'] = get_string('errornegativepoints', 'local_cpdtracker');
        } else if ($max > 0 && $max < $min) {
            $errors['maxpoints'] = get_string('errormaxbelowmin', 'local_cpdtracker');
        }

        return $errors;
    }

    /**
     * Create or update a category. New categories go to the end of the cycle's list.
     *
     * @param stdClass $data id (0 for new), cycleid, name, description, minpoints, maxpoints.
     * @return int Category id.
     * @throws moodle_exception If the data is invalid or the category belongs to another cycle.
     */
    public function save_category(stdClass $data): int {
        global $DB;

        $errors = $this->validate_category($data);
        if ($errors) {
            throw new moodle_exception('errorinvaliddata', 'local_cpdtracker', '', implode(' ', $errors));
        }

        $cycle = $this->get_cycle((int)$data->cycleid);
        $now = time();
        $record = (object)[
            'name' => trim($data->name),
            'description' => $data->description ?? '',
            'minpoints' => round((float)$data->minpoints, 2),
            'maxpoints' => round((float)$data->maxpoints, 2),
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $existing = $this->get_category((int)$data->id);
            if ((int)$existing->cycleid !== (int)$cycle->id) {
                throw new moodle_exception('invalidcategory', 'local_cpdtracker');
            }
            $record->id = $existing->id;
            $DB->update_record(self::TABLE_CATEGORY, $record);
            return (int)$record->id;
        }

        $maxsort = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_cpdtracker_category} WHERE cycleid = :cycleid',
            ['cycleid' => $cycle->id]
        );
        $record->cycleid = $cycle->id;
        $record->sortorder = $maxsort + 1;
        $record->timecreated = $now;
        return (int)$DB->insert_record(self::TABLE_CATEGORY, $record);
    }

    /**
     * Delete a category. Refused if members have logged activities in it.
     *
     * @param int $categoryid
     * @throws moodle_exception If the category has activities.
     */
    public function delete_category(int $categoryid): void {
        global $DB;

        $category = $this->get_category($categoryid);
        if ($this->count_category_activities($category->id)) {
            throw new moodle_exception('categoryhasactivities', 'local_cpdtracker');
        }
        $DB->delete_records(self::TABLE_CATEGORY, ['id' => $category->id]);
    }

    /**
     * Move a category one place up or down within its cycle.
     *
     * Also renumbers the cycle's sortorder values to 1..n.
     *
     * @param int $categoryid
     * @param bool $up True to move up, false to move down.
     */
    public function move_category(int $categoryid, bool $up): void {
        global $DB;

        $category = $this->get_category($categoryid);
        $ids = array_keys($this->get_categories($category->cycleid));
        $pos = array_search((int)$category->id, $ids);
        $swap = $up ? $pos - 1 : $pos + 1;
        if ($pos === false || $swap < 0 || $swap >= count($ids)) {
            return;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        $transaction = $DB->start_delegated_transaction();
        foreach ($ids as $index => $id) {
            $DB->set_field(self::TABLE_CATEGORY, 'sortorder', $index + 1, ['id' => $id]);
        }
        $transaction->allow_commit();
    }

    /**
     * Get one page of members for the compliance report, with points and status.
     *
     * Members are active (not suspended, confirmed) users who hold
     * local/cpdtracker:logactivity at system level, sorted by surname.
     *
     * @param stdClass $cycle
     * @param int $page Zero-based page number.
     * @param int $perpage
     * @return stdClass Object with 'total' (member count) and 'rows', each with
     *     user, total, categoriesmet, categorycount, activitycount, compliant.
     */
    public function get_report_page(stdClass $cycle, int $page, int $perpage): stdClass {
        global $DB;

        $capjoin = get_with_capability_join(\context_system::instance(), 'local/cpdtracker:logactivity', 'u.id');
        $namefields = \core_user\fields::for_name()->get_sql('u', true, '', '', false);
        $from = "FROM {user} u
                 {$capjoin->joins}
                 {$namefields->joins}
                WHERE {$capjoin->wheres}
                  AND u.suspended = 0
                  AND u.confirmed = 1";
        $params = array_merge($capjoin->params, $namefields->params);

        $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
        $users = $DB->get_records_sql(
            "SELECT u.id, {$namefields->selects} $from ORDER BY u.lastname, u.firstname, u.id",
            $params,
            $page * $perpage,
            $perpage
        );

        $rows = [];
        if ($users) {
            $categories = $this->get_categories($cycle->id);

            // Points and activity counts per member and category, for this page only.
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED);
            $sums = $DB->get_recordset_sql(
                "SELECT userid, categoryid, SUM(points) AS points, COUNT(1) AS activities
                   FROM {local_cpdtracker_activity}
                  WHERE cycleid = :cycleid AND userid $insql
               GROUP BY userid, categoryid",
                ['cycleid' => $cycle->id] + $inparams
            );
            $byuser = [];
            foreach ($sums as $sum) {
                $byuser[$sum->userid][] = $sum;
            }
            $sums->close();

            foreach ($users as $user) {
                $usersums = $byuser[$user->id] ?? [];
                $points = $this->calculate_points($categories, $usersums);
                $rows[] = (object)[
                    'user' => $user,
                    'total' => $points->total,
                    'categoriesmet' => count(array_filter($points->categories, fn($row) => $row->meetsmin)),
                    'categorycount' => count($categories),
                    'activitycount' => array_sum(array_map(fn($sum) => (int)$sum->activities, $usersums)),
                    'compliant' => $this->is_compliant($cycle, $points),
                ];
            }
        }

        return (object)['total' => $total, 'rows' => $rows];
    }
}
