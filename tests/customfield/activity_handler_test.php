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

namespace local_cpdtracker\customfield;

use advanced_testcase;
use context_system;
use context_user;

#[\PHPUnit\Framework\Attributes\CoversClass(activity_handler::class)]
/**
 * Tests for the custom activity fields handler: who can design, answer and see the fields.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\customfield\activity_handler
 */
final class activity_handler_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        activity_handler::reset_caches();
    }

    /**
     * Create one text field and an activity owned by a new member.
     *
     * @return array [field, activity, owner]
     */
    private function create_field_and_activity(): array {
        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $cfgenerator->create_category(['component' => 'local_cpdtracker', 'area' => 'activity', 'itemid' => 0]);
        $field = $cfgenerator->create_field(['categoryid' => $category->get('id'), 'type' => 'text',
            'shortname' => 'provider', 'name' => 'Provider']);

        /** @var \local_cpdtracker_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_cpdtracker');
        $owner = $this->getDataGenerator()->create_user();
        $cycle = $generator->create_cycle();
        $cpdcategory = $generator->create_category(['cycleid' => $cycle->id]);
        $activity = $generator->create_activity(['userid' => $owner->id, 'cycleid' => $cycle->id,
            'categoryid' => $cpdcategory->id]);
        return [$field, $activity, $owner];
    }

    public function test_create_returns_one_shared_handler(): void {
        $handler = activity_handler::create();
        $this->assertSame($handler, activity_handler::create(5));
        $this->assertSame('local_cpdtracker', $handler->get_component());
        $this->assertSame('activity', $handler->get_area());
        $this->assertSame(0, $handler->get_itemid());

        activity_handler::reset_caches();
        $this->assertNotSame($handler, activity_handler::create());
    }

    public function test_configuration_location(): void {
        $handler = activity_handler::create();
        $this->assertEquals(context_system::instance(), $handler->get_configuration_context());
        $this->assertStringEndsWith('/local/cpdtracker/customfields.php', $handler->get_configuration_url()->out(false));
    }

    public function test_only_managers_can_design_the_form(): void {
        $this->resetAfterTest();
        $handler = activity_handler::create();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse($handler->can_configure());

        $this->setAdminUser();
        $this->assertTrue($handler->can_configure());
    }

    public function test_only_the_owner_can_answer_for_an_activity(): void {
        $this->resetAfterTest();
        [$field, $activity, $owner] = $this->create_field_and_activity();
        $handler = activity_handler::create();

        $this->setUser($owner);
        $this->assertTrue($handler->can_edit($field, (int)$activity->id));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse($handler->can_edit($field, (int)$activity->id));
        $this->assertTrue($handler->can_edit($field, 0));

        // Even an administrator cannot answer on a member's behalf.
        $this->setAdminUser();
        $this->assertFalse($handler->can_edit($field, (int)$activity->id));
    }

    public function test_answering_requires_the_log_activity_capability(): void {
        global $DB;
        $this->resetAfterTest();
        [$field, $activity, $owner] = $this->create_field_and_activity();
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user']);
        assign_capability('local/cpdtracker:logactivity', CAP_PROHIBIT, $userrole, context_system::instance(), true);

        $this->setUser($owner);
        $handler = activity_handler::create();
        $this->assertFalse($handler->can_edit($field, (int)$activity->id));
        $this->assertFalse($handler->can_edit($field, 0));
    }

    public function test_answers_are_visible_to_owner_and_report_viewers_only(): void {
        $this->resetAfterTest();
        [$field, $activity, $owner] = $this->create_field_and_activity();
        $handler = activity_handler::create();

        $this->setUser($owner);
        $this->assertTrue($handler->can_view($field, (int)$activity->id));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse($handler->can_view($field, (int)$activity->id));

        $this->setAdminUser();
        $this->assertTrue($handler->can_view($field, (int)$activity->id));
    }

    public function test_answers_live_in_the_owner_context(): void {
        $this->resetAfterTest();
        [, $activity, $owner] = $this->create_field_and_activity();
        $handler = activity_handler::create();
        $other = $this->getDataGenerator()->create_user();

        $this->setUser($other);
        $this->assertEquals(context_user::instance($owner->id), $handler->get_instance_context((int)$activity->id));
        $this->assertEquals(context_user::instance($other->id), $handler->get_instance_context(0));

        $this->setUser(null);
        $this->assertEquals(context_system::instance(), $handler->get_instance_context(0));
    }

    public function test_unknown_activity_is_rejected(): void {
        $this->resetAfterTest();
        [$field] = $this->create_field_and_activity();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        activity_handler::create()->can_edit($field, 999999);
    }
}
