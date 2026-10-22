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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding or editing a CPD cycle.
 *
 * Custom data: 'id' (cycle id, 0 for new). The id comes from the page URL,
 * not a hidden field, so it cannot be changed by editing the form.
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cycle_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'local_cpdtracker'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'startdate', get_string('startdate', 'local_cpdtracker'));
        $mform->setDefault('startdate', time());

        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'local_cpdtracker'));
        $mform->setDefault('enddate', strtotime('+1 year -1 day'));

        $mform->addElement('float', 'requiredpoints', get_string('requiredpoints', 'local_cpdtracker'));
        $mform->addRule('requiredpoints', null, 'required', null, 'client');
        $mform->addHelpButton('requiredpoints', 'requiredpoints', 'local_cpdtracker');

        $this->add_action_buttons();
    }

    /**
     * Server-side validation, delegated to the manager.
     *
     * @param array $data
     * @param array $files
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $data['id'] = (int)($this->_customdata['id'] ?? 0);

        $manager = new \local_cpdtracker\manager();
        return array_merge($errors, $manager->validate_cycle((object)$data));
    }
}
