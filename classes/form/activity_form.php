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

use local_cpdtracker\customfield\activity_handler;
use local_cpdtracker\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for logging or editing a CPD activity.
 *
 * Custom data: 'cycle' (cycle record), 'categories' (category records keyed by id)
 * and 'id' (activity id, 0 for a new activity). Includes the site's custom activity fields.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $cycle = $this->_customdata['cycle'];
        $categories = $this->_customdata['categories'];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cycleid', $cycle->id);
        $mform->setType('cycleid', PARAM_INT);

        $manager = new manager();
        $options = [];
        $hourly = [];
        $direct = [];
        foreach ($categories as $category) {
            $options[$category->id] = format_string($category->name);
            if ($manager->is_hourly($category)) {
                $hourly[] = $category->id;
                $options[$category->id] .= ' (' . get_string(
                    'pointsperhourrate',
                    'local_cpdtracker',
                    format_float($category->pointsperhour, 2)
                ) . ')';
            } else {
                $direct[] = $category->id;
            }
        }
        $mform->addElement('select', 'categoryid', get_string('category', 'local_cpdtracker'), $options);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        $mform->addElement('text', 'title', get_string('title', 'local_cpdtracker'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'local_cpdtracker'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'activitydate', get_string('activitydate', 'local_cpdtracker'));
        $mform->setDefault('activitydate', time());

        // Hourly categories take hours (points are calculated); all others take points.
        // No client-side "required" rule: a hidden field must not block the form. The server checks.
        $mform->addElement('float', 'points', get_string('points', 'local_cpdtracker'));
        $mform->addElement('float', 'hours', get_string('hours', 'local_cpdtracker'));
        $mform->addHelpButton('hours', 'hours', 'local_cpdtracker');
        if ($hourly) {
            $mform->hideIf('points', 'categoryid', 'in', implode('|', $hourly));
        }
        if ($direct) {
            $mform->hideIf('hours', 'categoryid', 'in', implode('|', $direct));
        }

        activity_handler::create()->instance_form_definition($mform, (int)($this->_customdata['id'] ?? 0));

        $this->add_action_buttons();
    }

    /**
     * Load custom field values once the form has its data.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $id = (int)$this->_form->getElementValue('id');
        activity_handler::create()->instance_form_definition_after_data($this->_form, $id);
    }

    /**
     * Server-side validation, shared with manager::save_activity().
     *
     * @param array $data
     * @param array $files
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $manager = new manager();

        return array_merge(
            $errors,
            $manager->validate_activity((object)$data),
            activity_handler::create()->instance_form_validation($data, $files)
        );
    }
}
