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

use advanced_testcase;
use context_system;
use context_user;

#[\PHPUnit\Framework\Attributes\CoversClass(activity_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(activity_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(activity_updated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(activity_deleted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cycle_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cycle_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cycle_updated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cycle_deleted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category_updated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category_deleted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(report_viewed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(record_downloaded::class)]
/**
 * Tests for the CPD Tracker events.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\event\activity_base
 * @covers     \local_cpdtracker\event\activity_created
 * @covers     \local_cpdtracker\event\activity_updated
 * @covers     \local_cpdtracker\event\activity_deleted
 * @covers     \local_cpdtracker\event\cycle_base
 * @covers     \local_cpdtracker\event\cycle_created
 * @covers     \local_cpdtracker\event\cycle_updated
 * @covers     \local_cpdtracker\event\cycle_deleted
 * @covers     \local_cpdtracker\event\category_base
 * @covers     \local_cpdtracker\event\category_created
 * @covers     \local_cpdtracker\event\category_updated
 * @covers     \local_cpdtracker\event\category_deleted
 * @covers     \local_cpdtracker\event\report_viewed
 * @covers     \local_cpdtracker\event\record_downloaded
 */
final class events_test extends advanced_testcase {
    /**
     * Get the plugin data generator.
     *
     * @return \local_cpdtracker_generator
     */
    private function generator(): \local_cpdtracker_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_cpdtracker');
    }

    /**
     * Trigger an event and return it as stored by the event sink.
     *
     * @param \core\event\base $event
     * @return \core\event\base
     */
    private function trigger(\core\event\base $event): \core\event\base {
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();
        $this->assertCount(1, $events);
        return $events[0];
    }

    /**
     * Activity events, one per class.
     *
     * @return array
     */
    public static function activity_event_provider(): array {
        return [
            'created' => [activity_created::class, 'c'],
            'updated' => [activity_updated::class, 'u'],
            'deleted' => [activity_deleted::class, 'd'],
        ];
    }

    /**
     * Test the activity events.
     *
     * @dataProvider activity_event_provider
     * @param string $class
     * @param string $crud
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('activity_event_provider')]
    public function test_activity_events(string $class, string $crud): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $activity = $this->generator()->create_activity(['userid' => $user->id, 'cycleid' => $cycle->id,
            'categoryid' => $category->id]);

        $event = $this->trigger($class::create_from_activity($activity));

        $this->assertInstanceOf($class, $event);
        $this->assertSame($crud, $event->crud);
        $this->assertEquals($activity->id, $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals(context_user::instance($user->id)->id, $event->contextid);
        $this->assertEquals($cycle->id, $event->other['cycleid']);
        $this->assertEquals($category->id, $event->other['categoryid']);
        $this->assertSame(\core\event\base::LEVEL_PARTICIPATING, $event->edulevel);
        $this->assertNotEmpty($class::get_name());
        $this->assertStringContainsString((string)$activity->id, $event->get_description());
        $this->assertStringEndsWith('/local/cpdtracker/index.php', $event->get_url()->out_omit_querystring());
        $this->assertSame(\core\event\base::NOT_MAPPED, $class::get_objectid_mapping());
        $this->assertFalse($class::get_other_mapping());
    }

    public function test_activity_event_requires_related_user(): void {
        $this->expectException(\coding_exception::class);
        activity_created::create([
            'objectid' => 1,
            'context' => context_system::instance(),
            'other' => ['cycleid' => 1, 'categoryid' => 1],
        ]);
    }

    public function test_activity_event_requires_cycle(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\coding_exception::class);
        activity_created::create([
            'objectid' => 1,
            'relateduserid' => $user->id,
            'context' => context_user::instance($user->id),
            'other' => [],
        ]);
    }

    /**
     * Cycle events, one per class.
     *
     * @return array
     */
    public static function cycle_event_provider(): array {
        return [
            'created' => [cycle_created::class, 'c'],
            'updated' => [cycle_updated::class, 'u'],
            'deleted' => [cycle_deleted::class, 'd'],
        ];
    }

    /**
     * Test the cycle events.
     *
     * @dataProvider cycle_event_provider
     * @param string $class
     * @param string $crud
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cycle_event_provider')]
    public function test_cycle_events(string $class, string $crud): void {
        $this->resetAfterTest();
        $cycle = $this->generator()->create_cycle();

        $event = $this->trigger($class::create_from_cycle($cycle));

        $this->assertSame($crud, $event->crud);
        $this->assertEquals($cycle->id, $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertSame(\core\event\base::LEVEL_OTHER, $event->edulevel);
        $this->assertNotEmpty($class::get_name());
        $this->assertStringContainsString((string)$cycle->id, $event->get_description());
        $this->assertStringEndsWith('/local/cpdtracker/manage.php', $event->get_url()->out_omit_querystring());
        $this->assertSame(\core\event\base::NOT_MAPPED, $class::get_objectid_mapping());
        $this->assertFalse($class::get_other_mapping());
    }

    /**
     * Category events, one per class.
     *
     * @return array
     */
    public static function category_event_provider(): array {
        return [
            'created' => [category_created::class, 'c'],
            'updated' => [category_updated::class, 'u'],
            'deleted' => [category_deleted::class, 'd'],
        ];
    }

    /**
     * Test the category events.
     *
     * @dataProvider category_event_provider
     * @param string $class
     * @param string $crud
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('category_event_provider')]
    public function test_category_events(string $class, string $crud): void {
        $this->resetAfterTest();
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);

        $event = $this->trigger($class::create_from_category($category));

        $this->assertSame($crud, $event->crud);
        $this->assertEquals($category->id, $event->objectid);
        $this->assertEquals($cycle->id, $event->other['cycleid']);
        $this->assertNotEmpty($class::get_name());
        $this->assertStringContainsString((string)$category->id, $event->get_description());
        $this->assertEquals($cycle->id, $event->get_url()->get_param('cycleid'));
        $this->assertSame(\core\event\base::NOT_MAPPED, $class::get_objectid_mapping());
        $this->assertFalse($class::get_other_mapping());
    }

    public function test_category_event_requires_cycle(): void {
        $this->expectException(\coding_exception::class);
        category_created::create(['objectid' => 1, 'context' => context_system::instance(), 'other' => []]);
    }

    public function test_report_viewed(): void {
        $this->resetAfterTest();
        $cycle = $this->generator()->create_cycle();

        $event = $this->trigger(report_viewed::create_from_cycle($cycle));

        $this->assertSame('r', $event->crud);
        $this->assertEquals($cycle->id, $event->objectid);
        $this->assertEquals(context_system::instance()->id, $event->contextid);
        $this->assertNotEmpty(report_viewed::get_name());
        $this->assertStringContainsString((string)$cycle->id, $event->get_description());
        $this->assertEquals($cycle->id, $event->get_url()->get_param('cycleid'));
        $this->assertSame(\core\event\base::NOT_MAPPED, report_viewed::get_objectid_mapping());
        $this->assertFalse(report_viewed::get_other_mapping());
    }

    public function test_record_downloaded(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $cycle = $this->generator()->create_cycle();

        $event = $this->trigger(record_downloaded::create_from_cycle($cycle, $user->id, 'csv'));

        $this->assertSame('r', $event->crud);
        $this->assertEquals($cycle->id, $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals(context_user::instance($user->id)->id, $event->contextid);
        $this->assertSame('csv', $event->other['dataformat']);
        $this->assertNotEmpty(record_downloaded::get_name());
        $this->assertStringContainsString('csv', $event->get_description());
        $this->assertStringEndsWith('/local/cpdtracker/index.php', $event->get_url()->out_omit_querystring());
        $this->assertSame(\core\event\base::NOT_MAPPED, record_downloaded::get_objectid_mapping());
        $this->assertFalse(record_downloaded::get_other_mapping());
    }

    public function test_record_downloaded_requires_related_user_and_format(): void {
        $this->expectException(\coding_exception::class);
        record_downloaded::create(['objectid' => 1, 'context' => context_system::instance(), 'other' => []]);
    }
}
