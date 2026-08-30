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

namespace block_fastlearning\external;

use block_fastlearning\local\urgencylevel;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * AJAX service for reordering urgency levels.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reorder_levels extends external_api {
    /**
     * Describe the service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Urgency level record id')),
        ]);
    }

    /**
     * Save the new urgency-level order.
     *
     * @param int[] $ids Record ids in priority order.
     * @return bool
     */
    public static function execute(array $ids): bool {
        $params = self::validate_parameters(self::execute_parameters(), ['ids' => $ids]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/fastlearning:manage', $context);

        urgencylevel::reorder($params['ids']);
        return true;
    }

    /**
     * Describe the service result.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the order was saved');
    }
}
