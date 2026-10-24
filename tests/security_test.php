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

#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
/**
 * Security and data-integrity tests: abuse of the manager API by members and by other code.
 *
 * Each test documents one weakness found in the 1.2 security review.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\manager
 * @covers     \local_cpdtracker\observer
 */
final class security_test extends advanced_testcase {
    /** @var manager */
    private manager $manager;

    protected function setUp(): void {
        parent::setUp();
        customfield\activity_handler::reset_caches();
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
     * A current cycle with one direct-points and one hourly category.
     *
     * @return array [cycle, direct category, hourly category]
     */
    private function create_cycle_with_categories(): array {
        $cycle = $this->generator()->create_cycle();
        $direct = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $hourly = $this->generator()->create_category(['cycleid' => $cycle->id, 'pointsperhour' => 2]);
        return [$cycle, $direct, $hourly];
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

    public function test_non_finite_numbers_are_rejected(): void {
        $this->resetAfterTest();
        [$cycle, $direct, $hourly] = $this->create_cycle_with_categories();
        $activity = ['cycleid' => $cycle->id, 'title' => 'Course', 'activitydate' => time()];

        foreach ([NAN, INF, -INF] as $bad) {
            $errors = $this->manager->validate_activity((object)($activity + ['categoryid' => $direct->id, 'points' => $bad]));
            $this->assertArrayHasKey('points', $errors);
            $errors = $this->manager->validate_activity((object)($activity + ['categoryid' => $hourly->id, 'hours' => $bad]));
            $this->assertArrayHasKey('hours', $errors);

            $cyclerecord = (object)['id' => 0, 'name' => 'X', 'startdate' => time() + 500 * DAYSECS,
                'enddate' => time() + 600 * DAYSECS, 'requiredpoints' => $bad];
            $this->assertArrayHasKey('requiredpoints', $this->manager->validate_cycle($cyclerecord));

            foreach (['minpoints', 'maxpoints', 'pointsperhour'] as $field) {
                $category = ['name' => 'X', 'minpoints' => 0, 'maxpoints' => 0, 'pointsperhour' => 0];
                $category[$field] = $bad;
                $this->assertArrayHasKey($field, $this->manager->validate_category((object)$category), $field);
            }
        }
    }

    public function test_amounts_that_round_to_zero_are_rejected(): void {
        $this->resetAfterTest();
        [$cycle, $direct, $hourly] = $this->create_cycle_with_categories();
        $activity = ['cycleid' => $cycle->id, 'title' => 'Course', 'activitydate' => time()];

        $errors = $this->manager->validate_activity((object)($activity + ['categoryid' => $direct->id, 'points' => 0.004]));
        $this->assertArrayHasKey('points', $errors);

        $errors = $this->manager->validate_activity((object)($activity + ['categoryid' => $hourly->id, 'hours' => 0.004]));
        $this->assertArrayHasKey('hours', $errors);

        $this->assertSame([], $this->manager->validate_activity((object)($activity + [
            'categoryid' => $direct->id, 'points' => 0.01,
        ])));
    }

    public function test_hours_cannot_produce_more_points_than_the_activity_limit(): void {
        $this->resetAfterTest();
        [$cycle, , $hourly] = $this->create_cycle_with_categories();

        // 10000 hours at 2 points per hour would be 20000 points.
        $errors = $this->manager->validate_activity((object)['cycleid' => $cycle->id, 'categoryid' => $hourly->id,
            'title' => 'Course', 'activitydate' => time(), 'hours' => manager::MAX_ACTIVITY_AMOUNT]);
        $this->assertArrayHasKey('hours', $errors);
    }

    public function test_activity_description_length_is_limited(): void {
        $this->resetAfterTest();
        [$cycle, $direct] = $this->create_cycle_with_categories();
        $activity = ['cycleid' => $cycle->id, 'categoryid' => $direct->id, 'title' => 'Course',
            'activitydate' => time(), 'points' => 1];

        $ok = $activity + ['description' => str_repeat('a', manager::MAX_DESCRIPTION_LENGTH)];
        $this->assertSame([], $this->manager->validate_activity((object)$ok));

        $toolong = $activity + ['description' => str_repeat('a', manager::MAX_DESCRIPTION_LENGTH + 1)];
        $this->assertArrayHasKey('description', $this->manager->validate_activity((object)$toolong));
    }

    public function test_activities_per_member_per_cycle_are_limited(): void {
        global $DB;
        $this->resetAfterTest();
        [$cycle, $direct] = $this->create_cycle_with_categories();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        // Fill the member's cycle up to the limit directly in the database.
        $now = time();
        $records = [];
        for ($i = 0; $i < manager::MAX_ACTIVITIES_PER_CYCLE; $i++) {
            $records[] = ['userid' => $user->id, 'cycleid' => $cycle->id, 'categoryid' => $direct->id,
                'title' => 'Bulk ' . $i, 'activitydate' => $now, 'points' => 1, 'timecreated' => $now, 'timemodified' => $now];
        }
        $DB->insert_records('local_cpdtracker_activity', $records);

        $new = (object)['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $direct->id, 'title' => 'One more',
            'activitydate' => $now, 'points' => 1];
        $this->assert_moodle_exception('erroractivitylimit', fn() => $this->manager->save_activity(clone $new, $user->id));

        // Editing an existing activity is still allowed, and other members are not affected.
        $existing = $DB->get_field('local_cpdtracker_activity', 'id', ['userid' => $user->id], IGNORE_MULTIPLE);
        $edit = clone $new;
        $edit->id = $existing;
        $this->assertEquals($existing, $this->manager->save_activity($edit, $user->id));
        $this->assertGreaterThan(0, $this->manager->save_activity(clone $new, $other->id));
    }

    public function test_export_keeps_special_characters_unescaped(): void {
        $this->resetAfterTest();
        [$cycle] = $this->create_cycle_with_categories();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'R&D "Research"']);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        // Characters from the Moodle security test string that survive PARAM_TEXT input cleaning.
        $title = "R&D 'quotes' \"double\" &amp; 碁 \\ \\\\";
        $this->manager->save_activity((object)['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $category->id,
            'title' => $title, 'activitydate' => time(), 'points' => 1], $user->id);

        $row = $this->manager->get_record_export($user->id, $cycle)->rows[0];
        $this->assertSame($title, $row['title']);
        $this->assertSame('R&D "Research"', $row['category']);
    }

    public function test_cycle_options_are_escaped_exactly_once(): void {
        $this->resetAfterTest();
        $now = time();
        $used = $this->generator()->create_cycle(['name' => 'R&D "2026"']);
        $this->generator()->create_category(['cycleid' => $used->id]);
        $empty = $this->generator()->create_cycle(['name' => 'Empty', 'startdate' => $now + 400 * DAYSECS,
            'enddate' => $now + 500 * DAYSECS]);

        // Core select templates print labels raw, so labels must be HTML-escaped exactly once.
        $options = $this->manager->get_cycle_options();
        $this->assertSame([(int)$empty->id, (int)$used->id], array_keys($options));
        $this->assertSame(format_string('R&D "2026"'), $options[$used->id]);
        $this->assertStringContainsString('R&amp;D', $options[$used->id]);
        $this->assertStringNotContainsString('&amp;amp;', $options[$used->id]);
        $this->assertSame([(int)$used->id], array_keys($this->manager->get_cycle_options(true)));
    }

    public function test_report_tolerates_a_negative_page_number(): void {
        $this->resetAfterTest();
        [$cycle] = $this->create_cycle_with_categories();

        $report = $this->manager->get_report_page($cycle, -5, 10);

        $this->assertNotEmpty($report->rows);
        $this->assertEquals($report->rows, $this->manager->get_report_page($cycle, 0, 10)->rows);
    }

    public function test_deleting_a_user_removes_their_cpd_data(): void {
        global $DB;
        $this->resetAfterTest();
        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $cfcategory = $cfgenerator->create_category(['component' => 'local_cpdtracker', 'area' => 'activity', 'itemid' => 0]);
        $cfgenerator->create_field(['categoryid' => $cfcategory->get('id'), 'type' => 'text', 'shortname' => 'provider',
            'name' => 'Provider']);
        [$cycle, $direct] = $this->create_cycle_with_categories();
        $leaver = $this->getDataGenerator()->create_user();
        $stayer = $this->getDataGenerator()->create_user();

        $this->setUser($leaver);
        $leaverid = $this->manager->save_activity((object)['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $direct->id,
            'title' => 'Mine', 'activitydate' => time(), 'points' => 1, 'customfield_provider' => 'ACME'], $leaver->id);
        $this->setUser($stayer);
        $stayerid = $this->manager->save_activity((object)['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $direct->id,
            'title' => 'Theirs', 'activitydate' => time(), 'points' => 1, 'customfield_provider' => 'Other'], $stayer->id);
        $this->setAdminUser();

        delete_user($leaver);

        $this->assertFalse($DB->record_exists('local_cpdtracker_activity', ['userid' => $leaver->id]));
        $this->assertFalse($DB->record_exists('customfield_data', ['instanceid' => $leaverid]));
        $this->assertTrue($DB->record_exists('local_cpdtracker_activity', ['id' => $stayerid]));
        $this->assertTrue($DB->record_exists('customfield_data', ['instanceid' => $stayerid]));
    }

    public function test_manage_capability_is_flagged_as_risky(): void {
        $this->resetAfterTest();
        $capability = get_capability_info('local/cpdtracker:manage');

        $this->assertNotEmpty($capability->riskbitmask & RISK_CONFIG);
        $this->assertNotEmpty($capability->riskbitmask & RISK_SPAM);
        $this->assertNotEmpty(get_capability_info('local/cpdtracker:viewreport')->riskbitmask & RISK_PERSONAL);
    }
}
