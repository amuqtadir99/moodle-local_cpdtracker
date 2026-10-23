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

use advanced_testcase;
use moodle_exception;
use stdClass;

#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
/**
 * Tests for the CPD Tracker manager.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\manager
 */
final class manager_test extends advanced_testcase {
    /** @var manager */
    private manager $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->manager = new manager();
    }

    /**
     * Get the plugin data generator.
     *
     * @return \local_cpdtracker_generator
     */
    private function generator(): \local_cpdtracker_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_cpdtracker');
    }

    /**
     * Build an in-memory category record for the pure calculation tests.
     *
     * @param int $id
     * @param float $min
     * @param float $max 0 for no cap.
     * @return stdClass
     */
    private static function category(int $id, float $min = 0, float $max = 0): stdClass {
        return (object)['id' => $id, 'name' => 'Category ' . $id, 'minpoints' => $min, 'maxpoints' => $max];
    }

    /**
     * Build an in-memory activity record for the pure calculation tests.
     *
     * @param int $categoryid
     * @param float $points
     * @return stdClass
     */
    private static function activity(int $categoryid, float $points): stdClass {
        return (object)['categoryid' => $categoryid, 'points' => $points];
    }

    /**
     * Assert that a callback throws a moodle_exception with the given error code.
     *
     * @param string $errorcode
     * @param callable $callback
     */
    private function assert_moodle_exception(string $errorcode, callable $callback): void {
        try {
            $callback();
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            return;
        }
        $this->fail("Expected moodle_exception '$errorcode' was not thrown.");
    }

    /**
     * Format a timestamp as a local date-time string in the given timezone.
     *
     * @param int $time
     * @param string $timezone
     * @return string
     */
    private static function local_datetime(int $time, string $timezone): string {
        return (new \DateTime('@' . $time))->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d H:i:s');
    }

    /**
     * Get a timestamp for a local date-time string in the given timezone.
     *
     * @param string $datetime
     * @param string $timezone
     * @return int
     */
    private static function timestamp(string $datetime, string $timezone): int {
        return (new \DateTime($datetime, new \DateTimeZone($timezone)))->getTimestamp();
    }

    public function test_calculate_points_applies_category_cap(): void {
        $categories = [1 => self::category(1, 0, 10), 2 => self::category(2, 10, 0)];
        $activities = [self::activity(1, 8), self::activity(1, 7), self::activity(2, 12)];

        $points = $this->manager->calculate_points($categories, $activities);

        $this->assertEquals(15, $points->categories[1]->raw);
        $this->assertEquals(10, $points->categories[1]->counted);
        $this->assertEquals(12, $points->categories[2]->raw);
        $this->assertEquals(12, $points->categories[2]->counted);
        $this->assertEquals(22, $points->total);
    }

    public function test_calculate_points_checks_category_minimums(): void {
        $categories = [1 => self::category(1, 10), 2 => self::category(2, 0)];
        $activities = [self::activity(1, 9.5)];

        $points = $this->manager->calculate_points($categories, $activities);

        $this->assertFalse($points->categories[1]->meetsmin);
        $this->assertTrue($points->categories[2]->meetsmin);
        $this->assertEquals(0, $points->categories[2]->counted);
    }

    public function test_calculate_points_minimum_uses_capped_points(): void {
        // A misconfigured category whose cap is below its minimum can never be met.
        $categories = [1 => self::category(1, 10, 5)];
        $activities = [self::activity(1, 20)];

        $points = $this->manager->calculate_points($categories, $activities);

        $this->assertEquals(5, $points->categories[1]->counted);
        $this->assertFalse($points->categories[1]->meetsmin);
    }

    public function test_calculate_points_ignores_unknown_categories(): void {
        $categories = [1 => self::category(1)];
        $activities = [self::activity(99, 50), self::activity(1, 3)];

        $points = $this->manager->calculate_points($categories, $activities);

        $this->assertEquals(3, $points->total);
        $this->assertCount(1, $points->categories);
    }

    public function test_calculate_points_rounds_to_two_decimals(): void {
        $categories = [1 => self::category(1, 0.3)];
        $activities = [self::activity(1, 0.1), self::activity(1, 0.2)];

        $points = $this->manager->calculate_points($categories, $activities);

        $this->assertSame(0.3, $points->categories[1]->raw);
        $this->assertSame(0.3, $points->total);
        $this->assertTrue($points->categories[1]->meetsmin);
    }

    /**
     * Compliance scenarios.
     *
     * @return array
     */
    public static function is_compliant_provider(): array {
        return [
            'total below required' => [
                'required' => 30,
                'categories' => [[1, 0, 0]],
                'activities' => [[1, 29.99]],
                'expected' => false,
            ],
            'total met but a category minimum is not' => [
                'required' => 20,
                'categories' => [[1, 10, 0], [2, 0, 0]],
                'activities' => [[1, 5], [2, 30]],
                'expected' => false,
            ],
            'total only reached by points above a cap' => [
                'required' => 20,
                'categories' => [[1, 0, 10]],
                'activities' => [[1, 25]],
                'expected' => false,
            ],
            'total and every minimum met' => [
                'required' => 20,
                'categories' => [[1, 10, 0], [2, 0, 10]],
                'activities' => [[1, 10], [2, 15]],
                'expected' => true,
            ],
            'nothing required' => [
                'required' => 0,
                'categories' => [],
                'activities' => [],
                'expected' => true,
            ],
        ];
    }

    /**
     * Test is_compliant().
     *
     * @dataProvider is_compliant_provider
     * @param float $required
     * @param array $categories Each [id, min, max].
     * @param array $activities Each [categoryid, points].
     * @param bool $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('is_compliant_provider')]
    public function test_is_compliant(float $required, array $categories, array $activities, bool $expected): void {
        $categoryrecords = [];
        foreach ($categories as [$id, $min, $max]) {
            $categoryrecords[$id] = self::category($id, $min, $max);
        }
        $activityrecords = array_map(fn($activity) => self::activity(...$activity), $activities);

        $points = $this->manager->calculate_points($categoryrecords, $activityrecords);
        $cycle = (object)['requiredpoints' => $required];

        $this->assertSame($expected, $this->manager->is_compliant($cycle, $points));
    }

    public function test_get_current_cycle(): void {
        $this->resetAfterTest();
        $now = time();
        $past = $this->generator()->create_cycle(['startdate' => $now - 100 * DAYSECS, 'enddate' => $now - 50 * DAYSECS]);
        $current = $this->generator()->create_cycle(['startdate' => $now - 10 * DAYSECS, 'enddate' => $now + 10 * DAYSECS]);

        $this->assertEquals($current->id, $this->manager->get_current_cycle()->id);
        $this->assertEquals($past->id, $this->manager->get_current_cycle($now - 60 * DAYSECS)->id);
        $this->assertNull($this->manager->get_current_cycle($now - 30 * DAYSECS));
        $this->assertNull($this->manager->get_current_cycle($now + 20 * DAYSECS));

        // Start and end are inclusive.
        $this->assertEquals($current->id, $this->manager->get_current_cycle((int)$current->startdate)->id);
        $this->assertEquals($current->id, $this->manager->get_current_cycle((int)$current->enddate)->id);
    }

    public function test_get_current_cycle_prefers_latest_start_when_overlapping(): void {
        $this->resetAfterTest();
        $now = time();
        $this->generator()->create_cycle(['startdate' => $now - 20 * DAYSECS, 'enddate' => $now + 20 * DAYSECS]);
        $later = $this->generator()->create_cycle(['startdate' => $now - 5 * DAYSECS, 'enddate' => $now + 5 * DAYSECS]);

        $this->assertEquals($later->id, $this->manager->get_current_cycle()->id);
    }

    public function test_get_cycle_status(): void {
        $cycle = (object)['startdate' => 1000, 'enddate' => 2000];

        $this->assertSame('upcoming', $this->manager->get_cycle_status($cycle, 999));
        $this->assertSame('current', $this->manager->get_cycle_status($cycle, 1000));
        $this->assertSame('current', $this->manager->get_cycle_status($cycle, 2000));
        $this->assertSame('ended', $this->manager->get_cycle_status($cycle, 2001));
    }

    public function test_start_and_end_of_day(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London');

        $time = self::timestamp('2027-07-15 14:30:00', 'Europe/London');
        $this->assertSame('2027-07-15 00:00:00', self::local_datetime($this->manager->start_of_day($time), 'Europe/London'));
        $this->assertSame('2027-07-15 23:59:59', self::local_datetime($this->manager->end_of_day($time), 'Europe/London'));
    }

    public function test_end_of_day_on_daylight_saving_change(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/London');

        // Clocks go forward on 28 March 2027, so the day is 23 hours long.
        $time = self::timestamp('2027-03-28 12:00:00', 'Europe/London');
        $start = $this->manager->start_of_day($time);
        $end = $this->manager->end_of_day($time);

        $this->assertSame('2027-03-28 00:00:00', self::local_datetime($start, 'Europe/London'));
        $this->assertSame('2027-03-28 23:59:59', self::local_datetime($end, 'Europe/London'));
        $this->assertSame(23 * HOURSECS - 1, $end - $start);
    }

    public function test_save_activity_creates_and_updates(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);

        $data = (object)[
            'id' => 0,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
            'title' => 'Ethics webinar',
            'description' => 'One hour',
            'activitydate' => time(),
            'points' => 7.5,
        ];
        $id = $this->manager->save_activity($data, $user->id);

        $record = $DB->get_record('local_cpdtracker_activity', ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals(7.5, (float)$record->points);
        $this->assertSame('Ethics webinar', $record->title);

        $data->id = $id;
        $data->points = 9;
        $this->assertSame($id, $this->manager->save_activity($data, $user->id));
        $this->assertEquals(9, (float)$DB->get_field('local_cpdtracker_activity', 'points', ['id' => $id]));
        $this->assertEquals(1, $DB->count_records('local_cpdtracker_activity'));
    }

    public function test_save_activity_rejects_invalid_data(): void {
        $this->resetAfterTest();
        $now = time();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $closed = $this->generator()->create_cycle(['startdate' => $now - 100 * DAYSECS, 'enddate' => $now - 50 * DAYSECS]);
        $closedcategory = $this->generator()->create_category(['cycleid' => $closed->id]);

        $valid = [
            'id' => 0,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
            'title' => 'Course',
            'activitydate' => $now,
            'points' => 5,
        ];
        $save = fn(array $changes) => fn() => $this->manager->save_activity((object)array_merge($valid, $changes), $user->id);

        $this->assert_moodle_exception('cycleclosed', $save([
            'cycleid' => $closed->id,
            'categoryid' => $closedcategory->id,
            'activitydate' => $now - 60 * DAYSECS,
        ]));
        $this->assert_moodle_exception('invalidcategory', $save(['categoryid' => $closedcategory->id]));
        $this->assert_moodle_exception('errorpointspositive', $save(['points' => 0]));
        $this->assert_moodle_exception('errorpointspositive', $save(['points' => -2]));
        $this->assert_moodle_exception('errordateoutsidecycle', $save(['activitydate' => $now + 60 * DAYSECS]));
    }

    public function test_save_activity_protects_existing_activities(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $activity = $this->generator()->create_activity([
            'userid' => $owner->id,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
        ]);

        // Another member cannot overwrite it.
        $data = clone $activity;
        $this->assert_moodle_exception('cannoteditactivity', fn() => $this->manager->save_activity($data, $other->id));

        // The owner cannot move it into a different cycle.
        $othercycle = $this->generator()->create_cycle();
        $othercategory = $this->generator()->create_category(['cycleid' => $othercycle->id]);
        $data->cycleid = $othercycle->id;
        $data->categoryid = $othercategory->id;
        $this->assert_moodle_exception('cannoteditactivity', fn() => $this->manager->save_activity($data, $owner->id));
    }

    public function test_delete_activity(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $activity = $this->generator()->create_activity([
            'userid' => $owner->id,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
        ]);
        $closed = $this->generator()->create_cycle(['startdate' => $now - 100 * DAYSECS, 'enddate' => $now - 50 * DAYSECS]);
        $closedcategory = $this->generator()->create_category(['cycleid' => $closed->id]);
        $closedactivity = $this->generator()->create_activity([
            'userid' => $owner->id,
            'cycleid' => $closed->id,
            'categoryid' => $closedcategory->id,
            'activitydate' => $now - 60 * DAYSECS,
        ]);

        $this->assertFalse($this->manager->can_edit_activity($activity, $other->id));
        $this->assert_moodle_exception('cannoteditactivity', fn() => $this->manager->delete_activity($activity->id, $other->id));

        $this->assertFalse($this->manager->can_edit_activity($closedactivity, $owner->id));
        $this->assert_moodle_exception(
            'cannoteditactivity',
            fn() => $this->manager->delete_activity($closedactivity->id, $owner->id)
        );

        $this->assertTrue($this->manager->can_edit_activity($activity, $owner->id));
        $this->manager->delete_activity($activity->id, $owner->id);
        $this->assertFalse($DB->record_exists('local_cpdtracker_activity', ['id' => $activity->id]));
        $this->assertTrue($DB->record_exists('local_cpdtracker_activity', ['id' => $closedactivity->id]));
    }

    public function test_get_user_summary(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle(['requiredpoints' => 20]);
        $structured = $this->generator()->create_category(['cycleid' => $cycle->id, 'minpoints' => 5, 'maxpoints' => 10]);
        $selfdirected = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $othercycle = $this->generator()->create_cycle(['startdate' => time() - 100 * DAYSECS, 'enddate' => time() - 50 * DAYSECS]);
        $othercategory = $this->generator()->create_category(['cycleid' => $othercycle->id]);

        $log = fn(int $userid, stdClass $category, float $points) => $this->generator()->create_activity([
            'userid' => $userid,
            'cycleid' => $category->cycleid,
            'categoryid' => $category->id,
            'points' => $points,
        ]);
        $log($user->id, $structured, 12);
        $log($user->id, $selfdirected, 6);
        // Neither of these should count.
        $log($other->id, $selfdirected, 50);
        $log($user->id, $othercategory, 50);

        $summary = $this->manager->get_user_summary($user->id, $cycle);
        $this->assertCount(2, $summary->activities);
        $this->assertEquals(16, $summary->total);
        $this->assertEquals(10, $summary->categories[$structured->id]->counted);
        $this->assertFalse($summary->compliant);

        $log($user->id, $selfdirected, 4);
        $summary = $this->manager->get_user_summary($user->id, $cycle);
        $this->assertEquals(20, $summary->total);
        $this->assertTrue($summary->compliant);
    }

    public function test_validate_cycle(): void {
        $this->resetAfterTest();
        $now = time();
        $existing = $this->generator()->create_cycle([
            'name' => 'Existing',
            'startdate' => $now - 10 * DAYSECS,
            'enddate' => $now + 10 * DAYSECS,
        ]);
        $category = $this->generator()->create_category(['cycleid' => $existing->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->generator()->create_activity([
            'userid' => $user->id,
            'cycleid' => $existing->id,
            'categoryid' => $category->id,
            'activitydate' => $now + 5 * DAYSECS,
        ]);

        $valid = [
            'id' => 0,
            'name' => 'Next year',
            'startdate' => $now + 100 * DAYSECS,
            'enddate' => $now + 200 * DAYSECS,
            'requiredpoints' => 30,
        ];
        $validate = fn(array $changes) => $this->manager->validate_cycle((object)array_merge($valid, $changes));

        $this->assertSame([], $validate([]));
        $this->assertArrayHasKey('name', $validate(['name' => '   ']));
        $this->assertArrayHasKey('name', $validate(['name' => str_repeat('x', 256)]));
        $this->assertArrayHasKey('requiredpoints', $validate(['requiredpoints' => -1]));
        $this->assertArrayHasKey('enddate', $validate(['enddate' => $now + 50 * DAYSECS]));

        // Overlapping another cycle is rejected; a cycle never overlaps itself.
        $this->assertArrayHasKey('startdate', $validate(['startdate' => $now]));
        $this->assertSame([], $validate([
            'id' => $existing->id,
            'startdate' => $existing->startdate,
            'enddate' => $existing->enddate,
        ]));

        // Editing a cycle so its dates exclude logged activities is rejected.
        $errors = $validate(['id' => $existing->id, 'startdate' => $existing->startdate, 'enddate' => $now]);
        $this->assertArrayHasKey('enddate', $errors);
    }

    public function test_save_cycle_stores_whole_days(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setTimezone('Europe/London');

        $data = (object)[
            'id' => 0,
            'name' => ' 2027 CPD year ',
            'description' => '',
            'startdate' => self::timestamp('2027-01-01 15:00:00', 'Europe/London'),
            'enddate' => self::timestamp('2027-12-31 09:00:00', 'Europe/London'),
            'requiredpoints' => 30,
        ];
        $id = $this->manager->save_cycle($data);

        $cycle = $DB->get_record('local_cpdtracker_cycle', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('2027 CPD year', $cycle->name);
        $this->assertSame('2027-01-01 00:00:00', self::local_datetime($cycle->startdate, 'Europe/London'));
        $this->assertSame('2027-12-31 23:59:59', self::local_datetime($cycle->enddate, 'Europe/London'));

        $data->id = $id;
        $data->requiredpoints = 40;
        $this->assertSame($id, $this->manager->save_cycle($data));
        $this->assertEquals(40, (float)$DB->get_field('local_cpdtracker_cycle', 'requiredpoints', ['id' => $id]));

        $data->id = 0;
        $data->name = '';
        $this->assert_moodle_exception('errorinvaliddata', fn() => $this->manager->save_cycle($data));
    }

    public function test_delete_cycle(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $empty = $this->generator()->create_cycle();
        $this->generator()->create_category(['cycleid' => $empty->id]);
        $this->generator()->create_category(['cycleid' => $empty->id]);
        $used = $this->generator()->create_cycle();
        $usedcategory = $this->generator()->create_category(['cycleid' => $used->id]);
        $this->generator()->create_activity(['userid' => $user->id, 'cycleid' => $used->id, 'categoryid' => $usedcategory->id]);

        $this->manager->delete_cycle($empty->id);
        $this->assertFalse($DB->record_exists('local_cpdtracker_cycle', ['id' => $empty->id]));
        $this->assertEquals(0, $DB->count_records('local_cpdtracker_category', ['cycleid' => $empty->id]));

        $this->assert_moodle_exception('cyclehasactivities', fn() => $this->manager->delete_cycle($used->id));
        $this->assertTrue($DB->record_exists('local_cpdtracker_cycle', ['id' => $used->id]));
        $this->assertTrue($DB->record_exists('local_cpdtracker_category', ['id' => $usedcategory->id]));
    }

    public function test_validate_category(): void {
        $valid = ['name' => 'Structured learning', 'minpoints' => 10, 'maxpoints' => 0];
        $validate = fn(array $changes) => $this->manager->validate_category((object)array_merge($valid, $changes));

        $this->assertSame([], $validate([]));
        $this->assertSame([], $validate(['maxpoints' => 10]));
        $this->assertArrayHasKey('name', $validate(['name' => '']));
        $this->assertArrayHasKey('minpoints', $validate(['minpoints' => -1]));
        $this->assertArrayHasKey('maxpoints', $validate(['maxpoints' => -1]));
        $this->assertArrayHasKey('maxpoints', $validate(['maxpoints' => 5]));
    }

    public function test_save_category(): void {
        global $DB;
        $this->resetAfterTest();
        $cycle = $this->generator()->create_cycle();
        $othercycle = $this->generator()->create_cycle();

        $data = (object)['id' => 0, 'cycleid' => $cycle->id, 'name' => 'First', 'minpoints' => 10, 'maxpoints' => 0];
        $first = $this->manager->save_category($data);
        $data->name = 'Second';
        $second = $this->manager->save_category($data);

        $this->assertEquals(1, $DB->get_field('local_cpdtracker_category', 'sortorder', ['id' => $first]));
        $this->assertEquals(2, $DB->get_field('local_cpdtracker_category', 'sortorder', ['id' => $second]));

        $update = (object)['id' => $first, 'cycleid' => $cycle->id, 'name' => 'Renamed', 'minpoints' => 5, 'maxpoints' => 20];
        $this->assertSame($first, $this->manager->save_category($update));
        $record = $DB->get_record('local_cpdtracker_category', ['id' => $first], '*', MUST_EXIST);
        $this->assertSame('Renamed', $record->name);
        $this->assertEquals(20, (float)$record->maxpoints);

        $update->cycleid = $othercycle->id;
        $this->assert_moodle_exception('invalidcategory', fn() => $this->manager->save_category($update));

        $update->cycleid = $cycle->id;
        $update->maxpoints = 1;
        $this->assert_moodle_exception('errorinvaliddata', fn() => $this->manager->save_category($update));
    }

    public function test_delete_category(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $empty = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $used = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $this->generator()->create_activity(['userid' => $user->id, 'cycleid' => $cycle->id, 'categoryid' => $used->id]);

        $this->manager->delete_category($empty->id);
        $this->assertFalse($DB->record_exists('local_cpdtracker_category', ['id' => $empty->id]));

        $this->assert_moodle_exception('categoryhasactivities', fn() => $this->manager->delete_category($used->id));
        $this->assertTrue($DB->record_exists('local_cpdtracker_category', ['id' => $used->id]));
    }

    public function test_move_category(): void {
        $this->resetAfterTest();
        $cycle = $this->generator()->create_cycle();
        $a = $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'A', 'sortorder' => 5]);
        $b = $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'B', 'sortorder' => 10]);
        $c = $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'C', 'sortorder' => 20]);
        $order = fn() => array_values(array_map(fn($category) => $category->name, $this->manager->get_categories($cycle->id)));

        $this->manager->move_category($c->id, true);
        $this->assertSame(['A', 'C', 'B'], $order());

        // Moving the first category up, or the last one down, changes nothing.
        $this->manager->move_category($a->id, true);
        $this->manager->move_category($b->id, false);
        $this->assertSame(['A', 'C', 'B'], $order());

        $this->manager->move_category($a->id, false);
        $this->assertSame(['C', 'A', 'B'], $order());

        // Sort orders are renumbered 1..n.
        $sortorders = array_values(array_map(
            fn($category) => (int)$category->sortorder,
            $this->manager->get_categories($cycle->id)
        ));
        $this->assertSame([1, 2, 3], $sortorders);
    }

    public function test_count_activities_by_category(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $busy = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $quiet = $this->generator()->create_category(['cycleid' => $cycle->id]);
        for ($i = 0; $i < 3; $i++) {
            $this->generator()->create_activity(['userid' => $user->id, 'cycleid' => $cycle->id, 'categoryid' => $busy->id]);
        }

        $counts = $this->manager->count_activities_by_category($cycle->id);

        $this->assertSame(3, $counts[$busy->id]);
        $this->assertArrayNotHasKey($quiet->id, $counts);
        $this->assertSame(3, $this->manager->count_cycle_activities($cycle->id));
        $this->assertSame(0, $this->manager->count_category_activities($quiet->id));
    }

    public function test_get_report_page(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $aaa = $this->getDataGenerator()->create_user(['firstname' => 'Ann', 'lastname' => 'Aaa']);
        $bbb = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Bbb']);
        $suspended = $this->getDataGenerator()->create_user(['lastname' => 'Abb', 'suspended' => 1]);
        $cycle = $this->generator()->create_cycle(['requiredpoints' => 10]);
        $category = $this->generator()->create_category(['cycleid' => $cycle->id, 'minpoints' => 5]);
        $this->generator()->create_activity([
            'userid' => $aaa->id,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
            'points' => 10,
        ]);
        $this->generator()->create_activity([
            'userid' => $bbb->id,
            'cycleid' => $cycle->id,
            'categoryid' => $category->id,
            'points' => 3,
        ]);

        $report = $this->manager->get_report_page($cycle, 0, 10);

        // Suspended users and the guest account are left out; sorted by surname (admin is "User").
        $this->assertSame(3, $report->total);
        $this->assertEquals([$aaa->id, $bbb->id, $admin->id], array_map(fn($row) => $row->user->id, $report->rows));
        $this->assertNotContains($suspended->id, array_map(fn($row) => $row->user->id, $report->rows));

        [$rowaaa, $rowbbb, $rowadmin] = $report->rows;
        $this->assertTrue($rowaaa->compliant);
        $this->assertEquals(10, $rowaaa->total);
        $this->assertSame(1, $rowaaa->categoriesmet);
        $this->assertSame(1, $rowaaa->activitycount);
        $this->assertFalse($rowbbb->compliant);
        $this->assertSame(0, $rowbbb->categoriesmet);
        $this->assertFalse($rowadmin->compliant);
        $this->assertSame(0, $rowadmin->activitycount);
        $this->assertSame('Ann Aaa', fullname($rowaaa->user));

        // Paging.
        $page = $this->manager->get_report_page($cycle, 1, 2);
        $this->assertSame(3, $page->total);
        $this->assertEquals([$admin->id], array_map(fn($row) => $row->user->id, $page->rows));
    }
}
