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
}
