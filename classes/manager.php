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
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Cycle table. */
    const TABLE_CYCLE = 'local_cpdtracker_cycle';

    /** @var string Category table. */
    const TABLE_CATEGORY = 'local_cpdtracker_category';

    /** @var string Activity table. */
    const TABLE_ACTIVITY = 'local_cpdtracker_activity';

    /** @var int Most points or hours one activity can record. */
    const MAX_ACTIVITY_AMOUNT = 10000;

    /** @var int Most points a cycle can require, or a category can set as its minimum or cap. */
    const MAX_POINTS_SETTING = 100000;

    /** @var int Highest points-per-hour rate a category can use. */
    const MAX_POINTS_PER_HOUR = 1000;

    /** @var int Longest activity description, in characters. */
    const MAX_DESCRIPTION_LENGTH = 10000;

    /** @var int Most activities one member can log in one cycle (limits storage abuse by any account). */
    const MAX_ACTIVITIES_PER_CYCLE = 1000;

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
     * @param stdClass|null $cycle The activity's cycle, if already loaded.
     * @return bool
     */
    public function can_edit_activity(stdClass $activity, int $userid, ?stdClass $cycle = null): bool {
        if ((int)$activity->userid !== $userid) {
            return false;
        }
        if (!$cycle || (int)$cycle->id !== (int)$activity->cycleid) {
            $cycle = $this->get_cycle($activity->cycleid);
        }
        return $this->is_cycle_open($cycle);
    }

    /**
     * Whether a category converts hours into points.
     *
     * @param stdClass $category
     * @return bool
     */
    public function is_hourly(stdClass $category): bool {
        return (float)$category->pointsperhour > 0;
    }

    /**
     * Convert hours into points at a category's rate.
     *
     * @param float $hours
     * @param stdClass $category
     * @return float Points, rounded to two decimals.
     */
    public function hours_to_points(float $hours, stdClass $category): float {
        return round($hours * (float)$category->pointsperhour, 2);
    }

    /**
     * Check activity data before saving.
     *
     * Used by both the form and save_activity(), so the rules live in one place.
     * Hours are checked for hourly categories, points for all others.
     *
     * @param stdClass $data cycleid, categoryid, title, activitydate, points or hours.
     * @param int|null $time Unix timestamp for "now", defaults to the current time.
     * @return string[] Error messages keyed by field name, empty if valid.
     */
    public function validate_activity(stdClass $data, ?int $time = null): array {
        global $DB;

        $errors = [];
        $time = $time ?? time();
        $cycle = $this->get_cycle((int)$data->cycleid);
        $category = $DB->get_record(self::TABLE_CATEGORY, ['id' => (int)($data->categoryid ?? 0), 'cycleid' => $cycle->id]);
        if (!$category) {
            $errors['categoryid'] = get_string('invalidcategory', 'local_cpdtracker');
        }

        $title = trim($data->title ?? '');
        if ($title === '') {
            $errors['title'] = get_string('required');
        } else if (\core_text::strlen($title) > 255) {
            $errors['title'] = get_string('maximumchars', '', 255);
        }

        $date = (int)($data->activitydate ?? 0);
        if ($date < (int)$cycle->startdate || $date > (int)$cycle->enddate) {
            $errors['activitydate'] = get_string('errordateoutsidecycle', 'local_cpdtracker', $this->format_cycle_dates($cycle));
        } else if ($date > $time) {
            $errors['activitydate'] = get_string('errordatefuture', 'local_cpdtracker');
        }

        // Amounts are checked as they will be stored: finite and still above zero after rounding.
        $hourly = $category && $this->is_hourly($category);
        $field = $hourly ? 'hours' : 'points';
        $amount = (float)($data->$field ?? 0);
        $positive = get_string($hourly ? 'errorhourspositive' : 'errorpointspositive', 'local_cpdtracker');
        if (!is_finite($amount) || round($amount, 2) <= 0) {
            $errors[$field] = $positive;
        } else if ($amount > self::MAX_ACTIVITY_AMOUNT) {
            $errors[$field] = get_string('errortoolarge', 'local_cpdtracker', format_float(self::MAX_ACTIVITY_AMOUNT, 0));
        } else if ($hourly) {
            $points = $this->hours_to_points(round($amount, 2), $category);
            if ($points <= 0) {
                $errors[$field] = $positive;
            } else if ($points > self::MAX_ACTIVITY_AMOUNT) {
                $errors[$field] = get_string(
                    'errorhourstoomanypoints',
                    'local_cpdtracker',
                    format_float(self::MAX_ACTIVITY_AMOUNT, 0)
                );
            }
        }

        if (\core_text::strlen((string)($data->description ?? '')) > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = get_string('maximumchars', '', self::MAX_DESCRIPTION_LENGTH);
        }

        return $errors;
    }

    /**
     * Whether a member can log another activity in a cycle without passing the per-cycle limit.
     *
     * @param int $userid
     * @param int $cycleid
     * @return bool
     */
    public function can_add_activity(int $userid, int $cycleid): bool {
        global $DB;

        return $DB->count_records(self::TABLE_ACTIVITY, ['userid' => $userid, 'cycleid' => $cycleid])
            < self::MAX_ACTIVITIES_PER_CYCLE;
    }

    /**
     * Create or update an activity for a user.
     *
     * For an hourly category the points are calculated from the hours at the
     * category's current rate; any submitted points are ignored.
     *
     * @param stdClass $data Form data: id (0 for new), cycleid, categoryid, title,
     *     description, activitydate, points or hours.
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
        $errors = $this->validate_activity($data);
        if ($errors) {
            throw new moodle_exception('errorinvaliddata', 'local_cpdtracker', '', implode(' ', $errors));
        }
        if (empty($data->id) && !$this->can_add_activity($userid, (int)$cycle->id)) {
            throw new moodle_exception('erroractivitylimit', 'local_cpdtracker', '', self::MAX_ACTIVITIES_PER_CYCLE);
        }

        $category = $this->get_category((int)$data->categoryid);
        $hours = null;
        if ($this->is_hourly($category)) {
            $hours = round((float)$data->hours, 2);
            $points = $this->hours_to_points($hours, $category);
        } else {
            $points = round((float)$data->points, 2);
        }

        $now = time();
        $record = (object)[
            'cycleid' => $cycle->id,
            'categoryid' => (int)$category->id,
            'title' => trim($data->title),
            'description' => $data->description ?? '',
            'activitydate' => (int)$data->activitydate,
            'points' => $points,
            'hours' => $hours,
            'timemodified' => $now,
        ];

        $isnew = empty($data->id);
        if (!$isnew) {
            $existing = $this->get_activity((int)$data->id);
            if ((int)$existing->userid !== $userid || (int)$existing->cycleid !== (int)$cycle->id) {
                throw new moodle_exception('cannoteditactivity', 'local_cpdtracker');
            }
            $record->id = $existing->id;
        }

        // The activity and its custom field answers are saved together.
        $transaction = $DB->start_delegated_transaction();
        if ($isnew) {
            $record->userid = $userid;
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE_ACTIVITY, $record);
        } else {
            $DB->update_record(self::TABLE_ACTIVITY, $record);
        }
        $data->id = $record->id;
        customfield\activity_handler::create()->instance_form_save($data, $isnew);
        $transaction->allow_commit();

        $saved = $this->get_activity($record->id);
        if ($isnew) {
            event\activity_created::create_from_activity($saved)->trigger();
        } else {
            event\activity_updated::create_from_activity($saved)->trigger();
        }
        return (int)$record->id;
    }

    /**
     * Delete an activity owned by a user, including its custom field data.
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
        $transaction = $DB->start_delegated_transaction();
        customfield\activity_handler::create()->delete_instance((int)$activity->id);
        $DB->delete_records(self::TABLE_ACTIVITY, ['id' => $activity->id]);
        $transaction->allow_commit();
        event\activity_deleted::create_from_activity($activity)->trigger();
    }

    /**
     * Remove all of a user's activities and custom field answers, for example when the user is deleted.
     *
     * No events are triggered: the user's context may already be gone.
     *
     * @param int $userid
     */
    public function delete_user_data(int $userid): void {
        global $DB;

        $activityids = $DB->get_fieldset_select(self::TABLE_ACTIVITY, 'id', 'userid = :userid', ['userid' => $userid]);
        if (!$activityids) {
            return;
        }
        $handler = customfield\activity_handler::create();
        $transaction = $DB->start_delegated_transaction();
        foreach ($activityids as $activityid) {
            // Through the handler, so files of text area answers are removed too.
            $handler->delete_instance((int)$activityid);
        }
        $DB->delete_records(self::TABLE_ACTIVITY, ['userid' => $userid]);
        $transaction->allow_commit();
    }

    /**
     * Build a member's downloadable CPD record for one cycle, oldest activity first.
     *
     * Includes one column per custom activity field. Values are plain text (not HTML-escaped)
     * and made safe for spreadsheets with safe_export_value().
     *
     * @param int $userid
     * @param stdClass $cycle
     * @return stdClass Object with 'columns' (key => heading) and 'rows' (arrays keyed like columns).
     */
    public function get_record_export(int $userid, stdClass $cycle): stdClass {
        $categories = $this->get_categories($cycle->id);
        $activities = array_reverse($this->get_user_activities($userid, $cycle->id), true);
        $handler = customfield\activity_handler::create();
        $fields = $handler->get_fields();
        // Saved answers only: a field added after an activity was logged stays blank rather than showing its default.
        $fielddata = $activities ? $handler->get_instances_data(array_keys($activities)) : [];

        $columns = [
            'activitydate' => get_string('activitydate', 'local_cpdtracker'),
            'title' => get_string('title', 'local_cpdtracker'),
            'category' => get_string('category', 'local_cpdtracker'),
            'hours' => get_string('hours', 'local_cpdtracker'),
            'points' => get_string('points', 'local_cpdtracker'),
            'description' => get_string('description', 'local_cpdtracker'),
        ];
        // Exports are not HTML, so text is formatted without HTML escaping.
        $plain = ['context' => \context_system::instance(), 'escape' => false];
        foreach ($fields as $field) {
            $columns['field' . $field->get('id')] = format_string($field->get('name'), true, $plain);
        }

        $dateformat = get_string('strftimedate', 'langconfig');
        $rows = [];
        foreach ($activities as $activity) {
            $category = $categories[$activity->categoryid] ?? null;
            $row = [
                'activitydate' => userdate($activity->activitydate, $dateformat),
                'title' => $this->safe_export_value(format_string($activity->title, true, $plain)),
                'category' => $this->safe_export_value($category ? format_string($category->name, true, $plain) : ''),
                'hours' => $activity->hours === null ? '' : format_float($activity->hours, 2),
                'points' => format_float($activity->points, 2),
                'description' => $this->safe_export_value((string)$activity->description),
            ];
            foreach ($fields as $field) {
                $data = $fielddata[$activity->id][$field->get('id')] ?? null;
                $value = $data ? (string)$data->export_value() : '';
                $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
                $row['field' . $field->get('id')] = $this->safe_export_value($value);
            }
            $rows[] = $row;
        }

        return (object)['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Stop a text value being run as a formula when the export is opened in a spreadsheet.
     *
     * Values starting with =, +, -, @, a tab or a carriage return get a leading apostrophe.
     *
     * @param string $value
     * @return string
     */
    public function safe_export_value(string $value): string {
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            return "'" . $value;
        }
        return $value;
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
     * Cycle names keyed by id, newest first, for a select element.
     *
     * Core select templates print option labels without escaping them,
     * so the labels are HTML-escaped exactly once here.
     *
     * @param bool $withcategoriesonly Only cycles that have categories, for example as a copy source.
     * @return string[]
     */
    public function get_cycle_options(bool $withcategoriesonly = false): array {
        $options = [];
        foreach ($this->get_cycles() as $cycle) {
            if (!$withcategoriesonly || $cycle->categorycount) {
                $options[$cycle->id] = format_string($cycle->name);
            }
        }
        return $options;
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

        $required = (float)($data->requiredpoints ?? 0);
        if (!is_finite($required)) {
            $errors['requiredpoints'] = get_string('errornotanumber', 'local_cpdtracker');
        } else if ($required < 0) {
            $errors['requiredpoints'] = get_string('errornegativepoints', 'local_cpdtracker');
        } else if ($required > self::MAX_POINTS_SETTING) {
            $errors['requiredpoints'] = get_string('errortoolarge', 'local_cpdtracker', format_float(self::MAX_POINTS_SETTING, 0));
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
     * A new cycle can copy all categories from an existing cycle (copyfrom).
     *
     * @param stdClass $data id (0 for new), name, description, startdate, enddate, requiredpoints,
     *     and optionally copyfrom (cycle id) for new cycles.
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
            event\cycle_updated::create_from_cycle($this->get_cycle($record->id))->trigger();
            return (int)$record->id;
        }

        $transaction = $DB->start_delegated_transaction();
        $record->timecreated = $now;
        $record->id = $DB->insert_record(self::TABLE_CYCLE, $record);
        if (!empty($data->copyfrom)) {
            $this->copy_categories((int)$data->copyfrom, (int)$record->id);
        }
        $transaction->allow_commit();

        event\cycle_created::create_from_cycle($this->get_cycle($record->id))->trigger();
        return (int)$record->id;
    }

    /**
     * Copy every category of one cycle into another, keeping their order.
     *
     * @param int $fromcycleid
     * @param int $tocycleid
     * @return int Number of categories copied.
     */
    public function copy_categories(int $fromcycleid, int $tocycleid): int {
        global $DB;

        $this->get_cycle($tocycleid);
        $now = time();
        $copied = 0;
        $sortorder = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_cpdtracker_category} WHERE cycleid = :cycleid',
            ['cycleid' => $tocycleid]
        );
        foreach ($this->get_categories($this->get_cycle($fromcycleid)->id) as $category) {
            $copy = (object)[
                'cycleid' => $tocycleid,
                'name' => $category->name,
                'description' => $category->description,
                'minpoints' => $category->minpoints,
                'maxpoints' => $category->maxpoints,
                'pointsperhour' => $category->pointsperhour,
                'sortorder' => ++$sortorder,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $copy->id = $DB->insert_record(self::TABLE_CATEGORY, $copy);
            event\category_created::create_from_category($copy)->trigger();
            $copied++;
        }
        return $copied;
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

        event\cycle_deleted::create_from_cycle($cycle)->trigger();
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
     * @param stdClass $data name, minpoints, maxpoints, pointsperhour.
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

        $limits = [
            'minpoints' => self::MAX_POINTS_SETTING,
            'maxpoints' => self::MAX_POINTS_SETTING,
            'pointsperhour' => self::MAX_POINTS_PER_HOUR,
        ];
        foreach ($limits as $field => $limit) {
            $value = (float)($data->$field ?? 0);
            if (!is_finite($value)) {
                $errors[$field] = get_string('errornotanumber', 'local_cpdtracker');
            } else if ($value < 0) {
                $errors[$field] = get_string('errornegativepoints', 'local_cpdtracker');
            } else if ($value > $limit) {
                $errors[$field] = get_string('errortoolarge', 'local_cpdtracker', format_float($limit, 0));
            }
        }

        $min = (float)($data->minpoints ?? 0);
        $max = (float)($data->maxpoints ?? 0);
        if (!isset($errors['maxpoints']) && $max > 0 && $max < $min) {
            $errors['maxpoints'] = get_string('errormaxbelowmin', 'local_cpdtracker');
        }

        return $errors;
    }

    /**
     * Create or update a category. New categories go to the end of the cycle's list.
     *
     * @param stdClass $data id (0 for new), cycleid, name, description, minpoints, maxpoints, pointsperhour.
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
            'pointsperhour' => round((float)($data->pointsperhour ?? 0), 2),
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $existing = $this->get_category((int)$data->id);
            if ((int)$existing->cycleid !== (int)$cycle->id) {
                throw new moodle_exception('invalidcategory', 'local_cpdtracker');
            }
            $record->id = $existing->id;
            $DB->update_record(self::TABLE_CATEGORY, $record);
            event\category_updated::create_from_category($this->get_category($record->id))->trigger();
            return (int)$record->id;
        }

        $maxsort = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_cpdtracker_category} WHERE cycleid = :cycleid',
            ['cycleid' => $cycle->id]
        );
        $record->cycleid = $cycle->id;
        $record->sortorder = $maxsort + 1;
        $record->timecreated = $now;
        $record->id = $DB->insert_record(self::TABLE_CATEGORY, $record);
        event\category_created::create_from_category($this->get_category($record->id))->trigger();
        return (int)$record->id;
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
        event\category_deleted::create_from_category($category)->trigger();
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
     *     categoriesmet and categorycount only include categories that have a minimum.
     */
    public function get_report_page(stdClass $cycle, int $page, int $perpage): stdClass {
        global $DB;

        // A negative page or page size from the URL must not reach the database layer.
        $page = max(0, $page);
        $perpage = max(1, $perpage);

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
                // A category without a minimum has nothing to meet, so it is left out of the count.
                $withminimum = array_filter($points->categories, fn($row) => (float)$row->category->minpoints > 0);
                $rows[] = (object)[
                    'user' => $user,
                    'total' => $points->total,
                    'categoriesmet' => count(array_filter($withminimum, fn($row) => $row->meetsmin)),
                    'categorycount' => count($withminimum),
                    'activitycount' => array_sum(array_map(fn($sum) => (int)$sum->activities, $usersums)),
                    'compliant' => $this->is_compliant($cycle, $points),
                ];
            }
        }

        return (object)['total' => $total, 'rows' => $rows];
    }
}
