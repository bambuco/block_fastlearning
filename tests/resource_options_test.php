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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_fastlearning;

use advanced_testcase;
use block_fastlearning\local\resource_options;
use core_competency\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the options available in the resource assignment wizard.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(resource_options::class)]
final class resource_options_test extends advanced_testcase {
    public function test_users_follow_site_and_course_eligibility_rules(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user([
            'firstname' => 'Eligible',
            'username' => 'eligible-fastlearning-unique',
        ]);
        $other = $this->getDataGenerator()->create_user(['firstname' => 'Other']);
        $suspended = $this->getDataGenerator()->create_user(['firstname' => 'Suspended', 'suspended' => 1]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $siteids = array_column(resource_options::search_users(SITEID, $student->username), 'id');
        $courseids = array_column(resource_options::search_users($course->id, ''), 'id');

        $this->assertTrue(resource_options::user_is_available(SITEID, $student->id));
        $this->assertContains((int) $student->id, $siteids);
        $this->assertTrue(resource_options::user_is_available(SITEID, $other->id));
        $this->assertFalse(resource_options::user_is_available(SITEID, $suspended->id));
        $this->assertContains((int) $student->id, $courseids);
        $this->assertNotContains((int) $other->id, $courseids);
    }

    public function test_courses_and_modules_follow_wizard_dependencies(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Assigned course']);
        $othercourse = $this->getDataGenerator()->create_course(['fullname' => 'Other course']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $visible = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Visible page']);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Hidden page',
            'visible' => 0,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        api::add_competency_to_course($course->id, $competency->get('id'));
        api::add_competency_to_course_module($visible->cmid, $competency->get('id'));

        $courses = array_column(resource_options::search_user_courses($student->id, ''), 'id');
        $modules = array_column(resource_options::search_course_modules($course->id, 0, ''), 'id');
        $competencymodules = array_column(
            resource_options::search_course_modules($course->id, $competency->get('id'), ''),
            'id'
        );

        $this->assertContains((int) $course->id, $courses);
        $this->assertNotContains((int) $othercourse->id, $courses);
        $this->assertSame([(int) $visible->cmid], $modules);
        $this->assertSame([(int) $visible->cmid], $competencymodules);
    }
}
