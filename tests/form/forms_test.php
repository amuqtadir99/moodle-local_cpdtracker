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

namespace local_cpdtracker\form;

use advanced_testcase;
use local_cpdtracker\customfield\activity_handler;

#[\PHPUnit\Framework\Attributes\CoversClass(activity_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(cycle_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(category_form::class)]
/**
 * Tests for the activity, cycle and category forms.
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\form\activity_form
 * @covers     \local_cpdtracker\form\cycle_form
 * @covers     \local_cpdtracker\form\category_form
 */
final class forms_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        activity_handler::reset_caches();
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
     * Build an activity form for the given cycle.
     *
     * @param \stdClass $cycle
     * @return activity_form
     */
    private function activity_form(\stdClass $cycle): activity_form {
        $manager = new \local_cpdtracker\manager();
        return new activity_form(null, ['cycle' => $cycle, 'categories' => $manager->get_categories($cycle->id), 'id' => 0]);
    }

    public function test_activity_form_shows_names_escaped_once_and_rates(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $cycle = $this->generator()->create_cycle();
        $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'R&D "Research"']);
        $this->generator()->create_category(['cycleid' => $cycle->id, 'name' => 'Webinars', 'pointsperhour' => 1.5]);

        $html = $this->activity_form($cycle)->render();

        // Escaped exactly once by the form, not twice.
        $this->assertStringContainsString('R&amp;D', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
        $this->assertStringContainsString('1.50 points per hour', $html);
        $this->assertStringContainsString('name="hours"', $html);
        $this->assertStringContainsString('name="points"', $html);
    }

    public function test_activity_form_validation_uses_the_shared_rules(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $form = $this->activity_form($cycle);

        $valid = ['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $category->id, 'title' => 'Course',
            'description' => '', 'activitydate' => time(), 'points' => 2];
        $this->assertSame([], $form->validation($valid, []));

        $errors = $form->validation(['activitydate' => time() + 2 * DAYSECS, 'points' => 0] + $valid, []);
        $this->assertArrayHasKey('activitydate', $errors);
        $this->assertArrayHasKey('points', $errors);
    }

    public function test_activity_form_includes_required_custom_fields(): void {
        $this->resetAfterTest();
        /** @var \core_customfield_generator $cfgenerator */
        $cfgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $cfcategory = $cfgenerator->create_category(['component' => 'local_cpdtracker', 'area' => 'activity', 'itemid' => 0]);
        $cfgenerator->create_field(['categoryid' => $cfcategory->get('id'), 'type' => 'text', 'shortname' => 'provider',
            'name' => 'Provider', 'configdata' => ['required' => 1, 'displaysize' => 50, 'maxlength' => 255,
            'ispassword' => 0, 'link' => '', 'linktarget' => '', 'defaultvalue' => '', 'locked' => 0, 'uniquevalues' => 0,
            'visibility' => 2]]);
        $this->setUser($this->getDataGenerator()->create_user());
        $cycle = $this->generator()->create_cycle();
        $category = $this->generator()->create_category(['cycleid' => $cycle->id]);
        $this->assertStringContainsString('name="customfield_provider"', $this->activity_form($cycle)->render());

        // Required custom fields are enforced by the form rules on submission.
        $date = usergetdate(time());
        $submission = ['id' => 0, 'cycleid' => $cycle->id, 'categoryid' => $category->id, 'title' => 'Course',
            'description' => '', 'points' => 2,
            'activitydate' => ['day' => $date['mday'], 'month' => $date['mon'], 'year' => $date['year']]];
        activity_form::mock_submit($submission + ['customfield_provider' => '']);
        $this->assertNull($this->activity_form($cycle)->get_data());

        activity_form::mock_submit($submission + ['customfield_provider' => 'ACME']);
        $data = $this->activity_form($cycle)->get_data();
        $this->assertNotNull($data);
        $this->assertSame('ACME', $data->customfield_provider);
    }

    public function test_cycle_form(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $existing = $this->generator()->create_cycle(['name' => 'Running']);

        // Select labels are printed raw by core templates, so callers pass them already escaped once.
        $new = new cycle_form(null, ['id' => 0, 'copyoptions' => [$existing->id => format_string('Running & more')]]);
        $html = $new->render();
        $this->assertStringContainsString('name="copyfrom"', $html);
        $this->assertStringContainsString('Running &amp; more', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);

        $edit = new cycle_form(null, ['id' => $existing->id, 'copyoptions' => [$existing->id => 'Running']]);
        $this->assertStringNotContainsString('name="copyfrom"', $edit->render());

        $overlapping = ['name' => 'Clash', 'startdate' => time(), 'enddate' => time() + DAYSECS, 'requiredpoints' => 10];
        $this->assertArrayHasKey('startdate', $new->validation($overlapping, []));
        $this->assertSame([], $edit->validation(['startdate' => $existing->startdate, 'enddate' => $existing->enddate] +
            $overlapping, []));
    }

    public function test_category_form(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = new category_form();

        $this->assertStringContainsString('name="pointsperhour"', $form->render());
        $valid = ['name' => 'Structured', 'minpoints' => 10, 'maxpoints' => 0, 'pointsperhour' => 0];
        $this->assertSame([], $form->validation($valid, []));
        $this->assertArrayHasKey('maxpoints', $form->validation(['maxpoints' => 5] + $valid, []));
        $this->assertArrayHasKey('pointsperhour', $form->validation(['pointsperhour' => -1] + $valid, []));
    }
}
