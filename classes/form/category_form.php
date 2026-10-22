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
 * Form for adding or editing a CPD category.
 *
 * The category and cycle ids come from the page URL, not hidden fields.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_form extends \moodleform {
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

        $mform->addElement('float', 'minpoints', get_string('minpoints', 'local_cpdtracker'));
        $mform->setDefault('minpoints', 0);
        $mform->addHelpButton('minpoints', 'minpoints', 'local_cpdtracker');

        $mform->addElement('float', 'maxpoints', get_string('maxpoints', 'local_cpdtracker'));
        $mform->setDefault('maxpoints', 0);
        $mform->addHelpButton('maxpoints', 'maxpoints', 'local_cpdtracker');

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

        $manager = new \local_cpdtracker\manager();
        return array_merge($errors, $manager->validate_category((object)$data));
    }
}
