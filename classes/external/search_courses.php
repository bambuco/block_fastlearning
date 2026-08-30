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

declare(strict_types=1);

namespace block_fastlearning\external;

use block_fastlearning\local\resource_options;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX service for finding courses of the selected user.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_courses extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Selected user id'),
            'query' => new external_value(PARAM_RAW, 'Search text'),
        ]);
    }

    /**
     * Search the selected user's courses.
     *
     * @param int $userid Selected user id.
     * @param string $query Search text.
     * @return array
     */
    public static function execute(int $userid, string $query): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('userid', 'query'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('block/fastlearning:manage', $context);
        if (!resource_options::user_is_available((int) SITEID, $params['userid'])) {
            throw new \invalid_parameter_exception('The selected user is not available.');
        }

        return resource_options::search_user_courses($params['userid'], $params['query']);
    }

    /**
     * Define result structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course id'),
            'name' => new external_value(PARAM_TEXT, 'Course display name'),
        ]));
    }
}
