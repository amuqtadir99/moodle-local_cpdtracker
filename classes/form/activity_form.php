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
 * Form for logging or editing a CPD activity.
 *
 * Custom data: 'cycle' (cycle record) and 'categories' (category records keyed by id).
 *
 * @package    local_cpdtracker
 * @copyright  2026 Abdul Muqtadir
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

        $options = [];
        foreach ($categories as $category) {
            $options[$category->id] = format_string($category->name);
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

        $mform->addElement('float', 'points', get_string('points', 'local_cpdtracker'));
        $mform->addRule('points', null, 'required', null, 'client');

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $cycle = $this->_customdata['cycle'];
        $categories = $this->_customdata['categories'];

        if (!array_key_exists($data['categoryid'], $categories)) {
            $errors['categoryid'] = get_string('invalidcategory', 'local_cpdtracker');
        }

        if ((float)$data['points'] <= 0) {
            $errors['points'] = get_string('errorpointspositive', 'local_cpdtracker');
        }

        if ($data['activitydate'] < $cycle->startdate || $data['activitydate'] > $cycle->enddate) {
            $manager = new \local_cpdtracker\manager();
            $errors['activitydate'] = get_string(
                'errordateoutsidecycle',
                'local_cpdtracker',
                $manager->format_cycle_dates($cycle)
            );
        }

        return $errors;
    }
}
