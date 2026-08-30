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

namespace block_fastlearning\local;

/**
 * Helper methods for the Fast learning resource assignment record.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ra {
    /**
     * The status value for a pending resource assignment.
     *
     * @var string
     */
    const STATUS_PENDING = 'pending';

    /**
     * The status value for a completed resource assignment.
     *
     * @var string
     */
    const STATUS_COMPLETED = 'completed';

    /**
     * The status value for a failed resource assignment.
     *
     * @var string
     */
    const STATUS_FAILED = 'failed';

    /**
     * Derive the resource status from a Moodle completion state.
     *
     * @param int|null $completionstate The module completion state.
     * @return string The Fast learning status.
     */
    public static function status_from_completion_state(?int $completionstate): string {
        if ($completionstate === null) {
            return self::STATUS_PENDING;
        }

        if ($completionstate === COMPLETION_COMPLETE_FAIL) {
            return self::STATUS_FAILED;
        }

        $completedstates = [
            COMPLETION_COMPLETE,
            COMPLETION_COMPLETE_PASS,
        ];

        return in_array($completionstate, $completedstates, true)
            ? self::STATUS_COMPLETED
            : self::STATUS_PENDING;
    }

    /**
     * Calculate the deadline for a resource using its assignment time and urgency minutes.
     *
     * @param int $timeassigned The assignment timestamp.
     * @param int $minutes The urgency window in minutes.
     * @return int The deadline timestamp.
     */
    public static function calculate_deadline(int $timeassigned, int $minutes): int {
        return $timeassigned + ($minutes * MINSECS);
    }

    /**
     * Resolve the human-readable resource name.
     *
     * @param object $ra The record object.
     * @return string The resource name to display.
     */
    public static function get_display_name($ra): string {
        if (!empty($ra->name)) {
            return (string) $ra->name;
        }

        if (!empty($ra->competencyname)) {
            return (string) $ra->competencyname;
        }

        return get_string('unknownresource', 'block_fastlearning');
    }
}
