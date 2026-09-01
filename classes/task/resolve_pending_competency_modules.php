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

namespace block_fastlearning\task;

use block_fastlearning\local\api;
use block_fastlearning\local\ra;
use core\task\scheduled_task;

/**
 * Resolves course modules for pending competency resource assignments.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resolve_pending_competency_modules extends scheduled_task {
    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_resolve_pending_competency_modules', 'block_fastlearning');
    }

    /**
     * Assign the first eligible module to each pending competency assignment.
     */
    public function execute(): void {
        global $DB;

        $assignments = $DB->get_records_select(
            'block_fastlearning_ra',
            'cmid IS NULL AND competencyid IS NOT NULL AND status = :status',
            ['status' => ra::STATUS_PENDING],
            'id ASC',
            'id, userid, courseid, competencyid'
        );

        foreach ($assignments as $assignment) {
            $module = api::resolve_competency_module(
                (int) $assignment->userid,
                (int) $assignment->competencyid,
                $assignment->courseid === null ? null : (int) $assignment->courseid
            );
            if ($module === null) {
                continue;
            }

            $now = time();
            $sql = 'UPDATE {block_fastlearning_ra}
                       SET cmid = :cmid,
                           courseid = :courseid,
                           timeassigned = :timeassigned,
                           timemodified = :timemodified
                     WHERE id = :id
                       AND cmid IS NULL
                       AND competencyid = :competencyid
                       AND status = :status';

            $DB->execute($sql, [
                'cmid' => (int) $module->cmid,
                'courseid' => (int) $module->courseid,
                'timeassigned' => $now,
                'timemodified' => $now,
                'id' => (int) $assignment->id,
                'competencyid' => (int) $assignment->competencyid,
                'status' => ra::STATUS_PENDING,
            ]);

            mtrace("    Resolved ID {$assignment->id} with module ID {$module->cmid}");
        }
    }
}
