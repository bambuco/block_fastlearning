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

namespace block_fastlearning;

use advanced_testcase;
use block_fastlearning\local\api as fastlearning_api;
use core_competency\api as core_competency_api;
use invalid_parameter_exception;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the public local API of the Fast learning block.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(fastlearning_api::class)]
final class api_test extends advanced_testcase {
    /**
     * Tests competency resolution by ID and IDNumber.
     *
     * Validates that competencies can be resolved using either their numeric ID or their
     * unique IDNumber, and that non-existent competencies return null.
     *
     * @covers \block_fastlearning\local\api::resolve_competency
     */
    public function test_resolve_competency_by_id_and_idnumber(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'COMP_ATTR_001',
            'shortname' => 'Competency 001',
        ]);

        $resolvedbyid = fastlearning_api::resolve_competency($competency->get('id'));
        $this->assertNotNull($resolvedbyid);
        $this->assertEquals($competency->get('id'), $resolvedbyid->id);

        $resolvedbycode = fastlearning_api::resolve_competency('COMP_ATTR_001');
        $this->assertNotNull($resolvedbycode);
        $this->assertEquals($competency->get('id'), $resolvedbycode->id);

        $notfound = fastlearning_api::resolve_competency('NON_EXISTENT_CODE');
        $this->assertNull($notfound);
    }

    /**
     * Tests competency module resolution and resource need reporting.
     *
     * Validates the ability to resolve learning modules associated with specific competencies
     * for a user, and tests the full workflow of reporting a resource need using competency codes
     * with automatic module resolution.
     *
     * @covers \block_fastlearning\local\api::resolve_competency_module
     * @covers \block_fastlearning\local\api::report_resource_need
     */
    public function test_resolve_competency_module_and_report_resource_need(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test course']);
        $student = $this->getDataGenerator()->create_user(['idnumber' => 'USR_001']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Study Page',
            'visible' => 1,
        ]);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'COMP_CODE_99',
            'shortname' => 'Resource Competency',
        ]);

        core_competency_api::add_competency_to_course($course->id, $competency->get('id'));
        core_competency_api::add_competency_to_course_module($page->cmid, $competency->get('id'));

        // Test resolving competency module.
        $resolvedmodule = fastlearning_api::resolve_competency_module($student->id, (int) $competency->get('id'));
        $this->assertNotNull($resolvedmodule);
        $this->assertEquals((int) $page->cmid, $resolvedmodule->cmid);
        $this->assertEquals((int) $course->id, $resolvedmodule->courseid);

        // Test report_resource_need with competency code and auto-resolve cm.
        $ra = fastlearning_api::report_resource_need(
            userid: (int) $student->id,
            competency: 'COMP_CODE_99',
            urgencylevel: 0,
            name: 'Customer Service Refinement',
            global: 1,
            autoresolvecm: true,
            component: 'local_alianza',
            itemid: 123
        );

        $this->assertNotEmpty($ra->id);
        $this->assertEquals($student->id, $ra->userid);
        $this->assertEquals($competency->get('id'), $ra->competencyid);
        $this->assertEquals($page->cmid, $ra->cmid);
        $this->assertEquals($course->id, $ra->courseid);
        $this->assertEquals('pending', $ra->status);
        $this->assertEquals(0, $ra->urgencylevel);
        $this->assertEquals('Customer Service Refinement', $ra->name);
        $this->assertEquals('local_alianza', $ra->component);
        $this->assertEquals(123, $ra->itemid);
        $this->assertNotNull($ra->timeassigned);

        // Verify in DB.
        $dbrecord = $DB->get_record('block_fastlearning_ra', ['id' => $ra->id]);
        $this->assertNotNull($dbrecord);
        $this->assertEquals($page->cmid, $dbrecord->cmid);
        $this->assertEquals('local_alianza', $dbrecord->component);
        $this->assertEquals(123, $dbrecord->itemid);
    }

    /**
     * Tests resource need reporting without competency module and with invalid user.
     *
     * Validates that resource needs can be created without module resolution and that
     * invalid user IDs are properly rejected with an exception.
     *
     * @covers \block_fastlearning\local\api::report_resource_need
     */
    public function test_report_resource_need_without_cm_and_invalid_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $student = $this->getDataGenerator()->create_user();

        // Report need without competency and without cm.
        $ra = fastlearning_api::report_resource_need(
            userid: (int) $student->id,
            urgencylevel: 1,
            name: 'General draft need',
            global: 1,
            autoresolvecm: false
        );

        $this->assertNotEmpty($ra->id);
        $this->assertNull($ra->cmid);
        $this->assertNull($ra->timeassigned);
        $this->assertNull($ra->component);
        $this->assertNull($ra->itemid);
        $this->assertEquals('pending', $ra->status);

        // Exception when user is invalid.
        $this->expectException(invalid_parameter_exception::class);
        fastlearning_api::report_resource_need(userid: 999999);
    }

    /**
     * Tests that unused modules are preferred over previously assigned ones.
     *
     * @covers \block_fastlearning\local\api::resolve_competency_module
     */
    public function test_resolve_competency_module_prefers_unassigned(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $firstpage = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'First page',
            'visible' => 1,
        ]);
        $secondpage = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Second page',
            'visible' => 1,
        ]);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ]);
        core_competency_api::add_competency_to_course($course->id, $competency->get('id'));
        core_competency_api::add_competency_to_course_module($firstpage->cmid, $competency->get('id'));
        core_competency_api::add_competency_to_course_module($secondpage->cmid, $competency->get('id'));

        $now = time();
        $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'cmid' => $firstpage->cmid,
            'global' => 1,
            'competencyid' => $competency->get('id'),
            'urgencylevel' => 0,
            'status' => 'pending',
            'timeassigned' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $resolved = fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $competency->get('id')
        );
        $this->assertNotNull($resolved);
        $this->assertEquals((int) $secondpage->cmid, $resolved->cmid);
    }

    /**
     * Tests reassignment rules for previously assigned competency modules.
     *
     * @covers \block_fastlearning\local\api::resolve_competency_module
     */
    public function test_resolve_competency_module_reassign_completed(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Tracked page',
            'visible' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $nocompletionpage = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'No completion page',
            'visible' => 1,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        /** @var \core_competency_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ]);
        $nocompletioncompetency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
        ]);
        core_competency_api::add_competency_to_course($course->id, $competency->get('id'));
        core_competency_api::add_competency_to_course($course->id, $nocompletioncompetency->get('id'));
        core_competency_api::add_competency_to_course_module($page->cmid, $competency->get('id'));
        core_competency_api::add_competency_to_course_module(
            $nocompletionpage->cmid,
            $nocompletioncompetency->get('id')
        );

        $now = time();
        $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'cmid' => $page->cmid,
            'global' => 1,
            'competencyid' => $competency->get('id'),
            'urgencylevel' => 0,
            'status' => 'pending',
            'timeassigned' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'cmid' => $nocompletionpage->cmid,
            'global' => 1,
            'competencyid' => $nocompletioncompetency->get('id'),
            'urgencylevel' => 0,
            'status' => 'pending',
            'timeassigned' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Setting disabled: previously assigned modules are never reused.
        set_config('reassign_completed', 0, 'block_fastlearning');
        $this->assertNull(fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $competency->get('id')
        ));

        set_config('reassign_completed', 1, 'block_fastlearning');

        // Completion tracking disabled on the module: cannot reassign.
        $this->assertNull(fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $nocompletioncompetency->get('id')
        ));

        $setcompletionstate = function (int $state) use ($DB, $page, $student): void {
            $existing = $DB->get_record('course_modules_completion', [
                'coursemoduleid' => $page->cmid,
                'userid' => $student->id,
            ]);
            if ($existing) {
                $existing->completionstate = $state;
                $existing->timemodified = time();
                $DB->update_record('course_modules_completion', $existing);
            } else {
                $DB->insert_record('course_modules_completion', (object) [
                    'coursemoduleid' => $page->cmid,
                    'userid' => $student->id,
                    'completionstate' => $state,
                    'timemodified' => time(),
                ]);
            }
            \cache::make('core', 'completion')->purge();
        };

        // Incomplete: cannot reassign.
        $this->assertNull(fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $competency->get('id')
        ));

        // Failed completion: cannot reassign.
        $setcompletionstate(COMPLETION_COMPLETE_FAIL);
        $this->assertNull(fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $competency->get('id')
        ));

        // Completed: can reassign.
        $setcompletionstate(COMPLETION_COMPLETE);
        $resolved = fastlearning_api::resolve_competency_module(
            (int) $student->id,
            (int) $competency->get('id')
        );
        $this->assertNotNull($resolved);
        $this->assertEquals((int) $page->cmid, $resolved->cmid);
        $this->assertEquals((int) $course->id, $resolved->courseid);
    }
}
