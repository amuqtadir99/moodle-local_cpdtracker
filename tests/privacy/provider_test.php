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

namespace local_cpdtracker\privacy;

use context_system;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use stdClass;

#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Tests for the CPD Tracker privacy provider.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var stdClass Member with two activities. */
    private stdClass $user1;

    /** @var stdClass Member with one activity. */
    private stdClass $user2;

    /** @var stdClass Member with no activities. */
    private stdClass $user3;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \local_cpdtracker\customfield\activity_handler::reset_caches();

        /** @var \local_cpdtracker_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_cpdtracker');
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();

        $cycle = $generator->create_cycle(['name' => '2026 CPD year']);
        $category = $generator->create_category(['cycleid' => $cycle->id, 'name' => 'Structured learning']);
        $base = ['cycleid' => $cycle->id, 'categoryid' => $category->id];

        $generator->create_activity($base + [
            'userid' => $this->user1->id,
            'title' => 'Ethics webinar',
            'description' => 'One hour',
            'points' => 1.5,
        ]);
        $generator->create_activity($base + ['userid' => $this->user1->id, 'title' => 'Conference', 'points' => 6]);
        $generator->create_activity($base + ['userid' => $this->user2->id, 'title' => 'Reading', 'points' => 2]);
    }

    /**
     * Count a user's activities.
     *
     * @param stdClass $user
     * @return int
     */
    private function count_activities(stdClass $user): int {
        global $DB;
        return $DB->count_records('local_cpdtracker_activity', ['userid' => $user->id]);
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_cpdtracker'));
        $items = $collection->get_collection();

        $this->assertCount(2, $items);
        [$table, $link] = array_values($items);
        $this->assertSame('local_cpdtracker_activity', $table->get_name());
        $this->assertArrayHasKey('userid', $table->get_privacy_fields());
        $this->assertArrayHasKey('title', $table->get_privacy_fields());
        $this->assertArrayHasKey('points', $table->get_privacy_fields());
        $this->assertArrayHasKey('hours', $table->get_privacy_fields());
        $this->assertSame('core_customfield', $link->get_name());
    }

    /**
     * Give user1 an activity with an answer to a custom activity field.
     *
     * @return int The activity id.
     */
    private function create_activity_with_custom_field(): int {
        global $DB;

        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $cfgenerator->create_category(['component' => 'local_cpdtracker', 'area' => 'activity', 'itemid' => 0]);
        $cfgenerator->create_field(['categoryid' => $category->get('id'), 'type' => 'text', 'shortname' => 'provider',
            'name' => 'Provider']);

        $this->setUser($this->user1);
        $activity = $DB->get_records('local_cpdtracker_activity', ['userid' => $this->user1->id], 'id', '*', 0, 1);
        $activity = reset($activity);
        $manager = new \local_cpdtracker\manager();
        return $manager->save_activity((object)[
            'id' => $activity->id,
            'cycleid' => $activity->cycleid,
            'categoryid' => $activity->categoryid,
            'title' => $activity->title,
            'activitydate' => $activity->activitydate,
            'points' => $activity->points,
            'customfield_provider' => 'ACME Training',
        ], $this->user1->id);
    }

    public function test_export_includes_custom_fields(): void {
        global $DB;
        $activityid = $this->create_activity_with_custom_field();
        $context = context_user::instance($this->user1->id);

        $this->export_context_data_for_user($this->user1->id, $context, 'local_cpdtracker');

        $dataid = $DB->get_field('customfield_data', 'id', ['instanceid' => $activityid], MUST_EXIST);
        $exported = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_cpdtracker'),
            get_string('activityformfields', 'local_cpdtracker'),
            $dataid,
        ]);
        $this->assertStringContainsString('ACME Training', json_encode($exported));
    }

    public function test_delete_removes_custom_fields(): void {
        global $DB;
        $activityid = $this->create_activity_with_custom_field();
        $this->assertTrue($DB->record_exists('customfield_data', ['instanceid' => $activityid]));

        provider::delete_data_for_all_users_in_context(context_user::instance($this->user1->id));

        $this->assertFalse($DB->record_exists('customfield_data', ['instanceid' => $activityid]));
        $this->assertSame(0, $this->count_activities($this->user1));
    }

    public function test_get_contexts_for_userid(): void {
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertEquals([context_user::instance($this->user1->id)->id], $contextlist->get_contextids());

        $this->assertEmpty(provider::get_contexts_for_userid($this->user3->id)->get_contextids());
    }

    public function test_get_users_in_context(): void {
        $userlist = new userlist(context_user::instance($this->user1->id), 'local_cpdtracker');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->user1->id], $userlist->get_userids());

        $userlist = new userlist(context_user::instance($this->user3->id), 'local_cpdtracker');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        $userlist = new userlist(context_system::instance(), 'local_cpdtracker');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $context = context_user::instance($this->user1->id);
        $this->export_context_data_for_user($this->user1->id, $context, 'local_cpdtracker');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_cpdtracker')]);

        $this->assertCount(2, $data->activities);
        $titles = array_column($data->activities, 'title');
        sort($titles);
        $this->assertSame(['Conference', 'Ethics webinar'], $titles);

        $webinar = array_values(array_filter($data->activities, fn($a) => $a->title === 'Ethics webinar'))[0];
        $this->assertSame('2026 CPD year', $webinar->cycle);
        $this->assertSame('Structured learning', $webinar->category);
        $this->assertSame('One hour', $webinar->description);
        $this->assertSame(1.5, $webinar->points);

        // Another member's data is not exported in this user's context.
        $this->assertFalse(writer::with_context(context_user::instance($this->user2->id))->has_any_data());
    }

    public function test_export_user_data_ignores_other_users_context(): void {
        $context = context_user::instance($this->user2->id);
        $this->export_context_data_for_user($this->user1->id, $context, 'local_cpdtracker');

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context(context_user::instance($this->user1->id));
        $this->assertSame(0, $this->count_activities($this->user1));
        $this->assertSame(1, $this->count_activities($this->user2));

        // The system context holds no personal data, so nothing is deleted.
        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertSame(1, $this->count_activities($this->user2));
    }

    public function test_delete_data_for_user(): void {
        $contextids = [
            context_user::instance($this->user1->id)->id,
            context_user::instance($this->user2->id)->id,
        ];
        provider::delete_data_for_user(new approved_contextlist($this->user1, 'local_cpdtracker', $contextids));

        $this->assertSame(0, $this->count_activities($this->user1));
        $this->assertSame(1, $this->count_activities($this->user2));
    }

    public function test_delete_data_for_users(): void {
        $context = context_user::instance($this->user1->id);

        // A userlist that does not include the context's owner deletes nothing.
        provider::delete_data_for_users(new approved_userlist($context, 'local_cpdtracker', [$this->user2->id]));
        $this->assertSame(2, $this->count_activities($this->user1));

        provider::delete_data_for_users(new approved_userlist($context, 'local_cpdtracker', [$this->user1->id]));
        $this->assertSame(0, $this->count_activities($this->user1));
        $this->assertSame(1, $this->count_activities($this->user2));
    }
}
