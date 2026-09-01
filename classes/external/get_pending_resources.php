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

use block_fastlearning\local\controller;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX service used by the block to refresh the current user pending resources.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_pending_resources extends external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id shown in the block, or SITEID'),
        ]);
    }

    /**
     * Fetch the pending resources for the logged in user.
     *
     * @param int $courseid Course id shown in the block, or SITEID.
     * @return array The block_content template context.
     */
    public static function execute(int $courseid): array {
        global $USER, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), compact('courseid'));
        $context = $params['courseid'] === (int) SITEID
            ? \context_system::instance()
            : \context_course::instance($params['courseid']);
        self::validate_context($context);

        $items = controller::get_pending_for_user((int) $USER->id, $params['courseid']);
        $renderer = $PAGE->get_renderer('block_fastlearning');

        return $renderer->get_block_content_context($items);
    }

    /**
     * Define result structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Resource assignment id'),
                'name' => new external_value(PARAM_TEXT, 'Resource name'),
                'cmname' => new external_value(PARAM_TEXT, 'Course module name'),
                'cmurl' => new external_value(PARAM_URL, 'Course module url'),
                'remaining' => new external_value(PARAM_RAW, 'Formatted remaining/overdue time'),
                'remainingseconds' => new external_value(PARAM_INT, 'Signed remaining seconds, negative if overdue'),
                'isoverdue' => new external_value(PARAM_BOOL, 'Whether the deadline has passed'),
                'levelname' => new external_value(PARAM_TEXT, 'Urgency level name'),
                'levelcolor' => new external_value(PARAM_RAW, 'Urgency level colour'),
                'leveliconhtml' => new external_value(PARAM_RAW, 'Rendered urgency level icon'),
                'openmode' => new external_value(PARAM_ALPHA, 'Configured module open mode'),
                'linkattributes' => new external_value(PARAM_RAW, 'Extra HTML attributes for the open link'),
                'status' => new external_value(PARAM_ALPHA, 'Resource assignment status'),
            ])),
            'emptymessage' => new external_value(PARAM_TEXT, 'Message shown when there are no pending resources'),
            'openlabel' => new external_value(PARAM_TEXT, 'Open button label'),
            'resourcesurl' => new external_value(PARAM_URL, 'Url to the full resources list'),
        ]);
    }
}
