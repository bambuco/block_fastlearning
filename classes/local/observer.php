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

use core\event\course_module_completion_updated;

/**
 * Observer class for handling events in the fastlearning block.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handles the event when a course module's completion status is updated.
     *
     * @param course_module_completion_updated $event The event object containing information about the course module completion.
     * @return void
     */
    public static function course_module_completion_updated(course_module_completion_updated $event): void {
        global $DB;

        $pendingrecords = $DB->get_records(
            'block_fastlearning_ra',
            ['cmid' => $event->coursemoduleid, 'status' => ra::STATUS_PENDING]
        );

        foreach ($pendingrecords as $record) {
            if (!$event->completionstate) {
                continue; // Skip this record if the completion state is not set.
            }
            $completionstate = $event->completionstate;
            $status = ra::status_from_completion_state((int) $completionstate);

            if ($status !== ra::STATUS_PENDING) {
                $record->status = $status;
                $record->timecompleted = time();
                $record->timemodified = $record->timecompleted;
                $DB->update_record('block_fastlearning_ra', $record);
            }
        }
    }
}
