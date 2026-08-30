<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_fastlearning\form;

use block_fastlearning\local\resource_options;

/**
 * Handles submission and server-side validation for the resource form template.
 *
 * The form markup is owned by the resource_form Mustache template.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ra_form {
    /**
     * Return submitted and cleaned form data, or null for a regular page request.
     *
     * @param int $courseid Assignment context course id.
     * @return object|null
     */
    public static function get_submitted_data(int $courseid): ?object {
        $action = optional_param('action', null, PARAM_ALPHA);

        if (empty($action)) {
            return null;
        }

        require_sesskey();
        if (!in_array($action, ['savewithcm', 'savewithoutcm'], true)) {
            throw new \invalid_parameter_exception('Invalid resource form action.');
        }
        $cmid = $action === 'savewithcm' ? optional_param('cmid', 0, PARAM_INT) : 0;

        return (object) [
            'action' => $action,
            'userid' => optional_param('userid', 0, PARAM_INT),
            'courseid' => $courseid,
            'modulecourseid' => optional_param('modulecourseid', 0, PARAM_INT),
            'cmid' => $cmid,
            'global' => optional_param('global', 0, PARAM_INT),
            'competencyid' => optional_param('competencyid', 0, PARAM_INT),
            'name' => trim(optional_param('name', '', PARAM_TEXT)),
            'urgencylevel' => optional_param('urgencylevel', -1, PARAM_INT),
        ];
    }

    /**
     * Validate a resource form submission.
     *
     * @param object $data Cleaned form data.
     * @param int $courseid Assignment context course id.
     * @return array Field name to error message.
     */
    public static function validate(object $data, int $courseid): array {
        global $DB;

        $errors = [];
        if (!$data->userid || !resource_options::user_is_available($courseid, $data->userid)) {
            $errors['userid'] = get_string('invalidrauser', 'block_fastlearning');
        }
        if ($data->competencyid && !$DB->record_exists('competency', ['id' => $data->competencyid])) {
            $errors['competencyid'] = get_string('invalidcompetency', 'block_fastlearning');
        }
        if ($data->name === '') {
            $errors['name'] = get_string('required');
        }
        if (!$DB->record_exists('block_fastlearning_levels', ['urgencylevel' => $data->urgencylevel])) {
            $errors['urgencylevel'] = get_string('invalidurgencylevel', 'block_fastlearning');
        }

        if ($data->action === 'savewithcm' && !$data->cmid) {
            $errors['cmid'] = get_string('required');
        }
        if ($data->cmid) {
            $modulecourseid = $courseid === (int) SITEID ? $data->modulecourseid : $courseid;
            if (
                !resource_options::course_module_is_available(
                    $modulecourseid,
                    $data->cmid,
                    $data->competencyid
                )
            ) {
                $errors['cmid'] = get_string('invalidcoursemodule', 'block_fastlearning');
            }
        }

        return $errors;
    }
}
