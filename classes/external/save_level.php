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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * AJAX service for creating and updating urgency levels.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_level extends external_api {
    /**
     * Describe the service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Record id, or zero for a new level'),
            'name' => new external_value(PARAM_TEXT, 'Level name'),
            'minutes' => new external_value(PARAM_INT, 'Minutes allowed for completion'),
            'color' => new external_value(PARAM_TEXT, 'Hexadecimal colour'),
            'icon' => new external_value(PARAM_TEXT, 'Moodle pix icon identifier'),
        ]);
    }

    /**
     * Create or update an urgency level.
     *
     * @param int $id Record id.
     * @param string $name Level name.
     * @param int $minutes Minutes allowed.
     * @param string $color Hexadecimal colour.
     * @param string $icon Moodle pix icon identifier.
     * @return object Saved record.
     */
    public static function execute(int $id, string $name, int $minutes, string $color, string $icon): object {
        $params = self::validate_parameters(self::execute_parameters(), compact('id', 'name', 'minutes', 'color', 'icon'));
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/fastlearning:manage', $context);

        $params['name'] = trim($params['name']);
        if ($params['name'] === '' || $params['minutes'] < 0) {
            throw new \invalid_parameter_exception('The name is required and minutes cannot be negative.');
        }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $params['color'])) {
            throw new \moodle_exception('invalidcolor', 'block_fastlearning');
        }

        $availableicons = \core\output\icon_system::instance()->get_icon_name_map();
        if (!array_key_exists($params['icon'], $availableicons)) {
            throw new \moodle_exception('invalidicon', 'block_fastlearning');
        }

        $level = urgencylevel::save(
            $params['id'],
            $params['name'],
            $params['minutes'],
            strtolower($params['color']),
            $params['icon']
        );

        $level->formatedminutes = urgencylevel::format_minutes($level->minutes);
        $level->iconinfo = urgencylevel::icon_info($level->icon);

        return $level;
    }

    /**
     * Describe the service result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Record id'),
            'urgencylevel' => new external_value(PARAM_INT, 'Numeric priority'),
            'name' => new external_value(PARAM_TEXT, 'Level name'),
            'minutes' => new external_value(PARAM_INT, 'Minutes allowed'),
            'formatedminutes' => new external_value(PARAM_TEXT, 'Formatted minutes for display'),
            'color' => new external_value(PARAM_TEXT, 'Hexadecimal colour'),
            'icon' => new external_value(PARAM_TEXT, 'Moodle pix icon identifier'),
            'iconinfo' => new external_single_structure([
                'component' => new external_value(PARAM_COMPONENT, 'Moodle component for the icon'),
                'name' => new external_value(PARAM_TEXT, 'Moodle pix icon name'),
            ]),
            'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
            'timemodified' => new external_value(PARAM_INT, 'Modification timestamp'),
        ]);
    }
}
