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

namespace block_fastlearning\tests;

use advanced_testcase;
use block_fastlearning\local\ra;
use block_fastlearning\task\resolve_pending_competency_modules;
use core_competency\api as core_competency_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the pending competency module resolution task.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(resolve_pending_competency_modules::class)]
final class resolve_pending_competency_modules_test extends advanced_testcase {
    public function test_resolves_pending_assignments_without_overwriting_other_records(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $othercourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $othercourse->id);
        $this->getDataGenerator()->enrol_user($student->id, $targetcourse->id);

        $othermodule = $this->getDataGenerator()->create_module('page', [
            'course' => $othercourse->id,
            'visible' => 1,
        ]);
        $firsttargetmodule = $this->getDataGenerator()->create_module('page', [
            'course' => $targetcourse->id,
            'visible' => 1,
        ]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $targetcourse->id,
            'visible' => 1,
        ]);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ]);
        $unmatchedcompetency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ]);

        foreach ([$othercourse, $targetcourse] as $course) {
            core_competency_api::add_competency_to_course($course->id, $competency->get('id'));
        }
        core_competency_api::add_competency_to_course_module($othermodule->cmid, $competency->get('id'));
        core_competency_api::add_competency_to_course_module($firsttargetmodule->cmid, $competency->get('id'));

        $now = time();
        $createassignment = function(?int $courseid, ?int $cmid, ?int $competencyid, string $status) use ($DB, $student, $now): int {
            return (int) $DB->insert_record('block_fastlearning_ra', (object) [
                'userid' => $student->id,
                'courseid' => $courseid,
                'cmid' => $cmid,
                'global' => 1,
                'competencyid' => $competencyid,
                'urgencylevel' => 0,
                'status' => $status,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        };

        $restrictedassignmentid = $createassignment($targetcourse->id, null, $competency->get('id'), ra::STATUS_PENDING);
        $globalassignmentid = $createassignment(null, null, $competency->get('id'), ra::STATUS_PENDING);
        $unmatchedassignmentid = $createassignment(null, null, $unmatchedcompetency->get('id'), ra::STATUS_PENDING);
        $completedassignmentid = $createassignment(null, null, $competency->get('id'), ra::STATUS_COMPLETED);
        $assignedassignmentid = $createassignment(null, $othermodule->cmid, $competency->get('id'), ra::STATUS_PENDING);
        $withoutcompetencyassignmentid = $createassignment(null, null, null, ra::STATUS_PENDING);

        $task = new resolve_pending_competency_modules();
        $this->expectOutputString(
            "    Resolved ID {$restrictedassignmentid} with module ID {$firsttargetmodule->cmid}\n" .
            "    Resolved ID {$globalassignmentid} with module ID {$othermodule->cmid}\n"
        );
        $task->execute();

        $restrictedassignment = $DB->get_record('block_fastlearning_ra', ['id' => $restrictedassignmentid], '*', MUST_EXIST);
        $this->assertEquals($firsttargetmodule->cmid, $restrictedassignment->cmid);
        $this->assertEquals($targetcourse->id, $restrictedassignment->courseid);
        $this->assertNotNull($restrictedassignment->timeassigned);
        $this->assertGreaterThanOrEqual($now, $restrictedassignment->timemodified);

        $globalassignment = $DB->get_record('block_fastlearning_ra', ['id' => $globalassignmentid], '*', MUST_EXIST);
        $this->assertEquals($othermodule->cmid, $globalassignment->cmid);
        $this->assertEquals($othercourse->id, $globalassignment->courseid);

        foreach ([$unmatchedassignmentid, $completedassignmentid, $withoutcompetencyassignmentid] as $assignmentid) {
            $assignment = $DB->get_record('block_fastlearning_ra', ['id' => $assignmentid], '*', MUST_EXIST);
            $this->assertNull($assignment->cmid);
            $this->assertNull($assignment->timeassigned);
        }

        $assignedassignment = $DB->get_record('block_fastlearning_ra', ['id' => $assignedassignmentid], '*', MUST_EXIST);
        $this->assertEquals($othermodule->cmid, $assignedassignment->cmid);
    }
}