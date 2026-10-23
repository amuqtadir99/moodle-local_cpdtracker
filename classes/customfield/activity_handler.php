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

use context;
use context_system;
use context_user;
use core_customfield\field_controller;
use core_customfield\handler;

/**
 * Custom fields on the CPD activity form (component local_cpdtracker, area activity).
 *
 * One site-wide set of fields, designed by users with local/cpdtracker:manage.
 * Answers belong to the member who logged the activity and live in their user context.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_handler extends handler {
    /** @var activity_handler|null Shared instance. */
    protected static $singleton;

    /** @var int[] Activity owner ids already looked up, keyed by activity id. */
    protected $owners = [];

    /**
     * Get the shared handler instance.
     *
     * @param int $itemid Not used: there is one site-wide set of fields.
     * @return handler
     */
    public static function create(int $itemid = 0): handler {
        if (static::$singleton === null) {
            static::$singleton = new static(0);
        }
        return static::$singleton;
    }

    /**
     * Reset the shared instance between unit tests.
     */
    public static function reset_caches(): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('This feature is only intended for use in unit tests');
        }
        static::$singleton = null;
    }

    /**
     * Get the id of the member who logged an activity.
     *
     * @param int $instanceid Activity id.
     * @return int
     */
    protected function get_owner(int $instanceid): int {
        global $DB;

        if (!isset($this->owners[$instanceid])) {
            $this->owners[$instanceid] = (int)$DB->get_field(
                'local_cpdtracker_activity',
                'userid',
                ['id' => $instanceid],
                MUST_EXIST
            );
        }
        return $this->owners[$instanceid];
    }

    /**
     * Whether the current user can design the activity form.
     *
     * @return bool
     */
    public function can_configure(): bool {
        return has_capability('local/cpdtracker:manage', $this->get_configuration_context());
    }

    /**
     * Whether the current user can fill in the fields: only the activity's owner.
     *
     * @param field_controller $field
     * @param int $instanceid Activity id, 0 for a new activity.
     * @return bool
     */
    public function can_edit(field_controller $field, int $instanceid = 0): bool {
        global $USER;

        if (!has_capability('local/cpdtracker:logactivity', context_system::instance())) {
            return false;
        }
        return !$instanceid || $this->get_owner($instanceid) === (int)$USER->id;
    }

    /**
     * Whether the current user can see the answers: the owner, or staff who can view the report.
     *
     * @param field_controller $field
     * @param int $instanceid Activity id.
     * @return bool
     */
    public function can_view(field_controller $field, int $instanceid): bool {
        global $USER;

        if ($instanceid && $this->get_owner($instanceid) === (int)$USER->id) {
            return true;
        }
        return has_capability('local/cpdtracker:viewreport', context_system::instance());
    }

    /**
     * Context for the field configuration.
     *
     * @return context
     */
    public function get_configuration_context(): context {
        return context_system::instance();
    }

    /**
     * Page where the fields are designed.
     *
     * @return \moodle_url
     */
    public function get_configuration_url(): \moodle_url {
        return new \moodle_url('/local/cpdtracker/customfields.php');
    }

    /**
     * Context of an activity's answers: the owner's user context.
     *
     * @param int $instanceid Activity id, 0 for a new activity logged by the current user.
     * @return context
     */
    public function get_instance_context(int $instanceid = 0): context {
        global $USER;

        if ($instanceid > 0) {
            return context_user::instance($this->get_owner($instanceid));
        }
        return !empty($USER->id) ? context_user::instance($USER->id) : context_system::instance();
    }
}
