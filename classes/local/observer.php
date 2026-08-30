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

class observer {
    public static function course_module_completion_updated(course_module_completion_updated $event): void {
        global $DB;

        $pendingrecords = $DB->get_records('block_fastlearning_ra', ['cmid' => $event->coursemoduleid, 'status' => 'pending']);
        foreach ($pendingrecords as $record) {
            $completionstate = $event->completionstate ?? completion_info::get_completion_state();
            $status = ra::status_from_completion_state((int) $completionstate);

            if ($status === 'completed') {
                $record->status = 'completed';
                $record->timecompleted = time();
                $record->timemodified = time();
                $DB->update_record('block_fastlearning_ra', $record);
            }
        }
    }
}
