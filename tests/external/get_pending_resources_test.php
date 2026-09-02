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

use advanced_testcase;
use block_fastlearning\external\get_pending_resources;
use block_fastlearning\local\ra;
use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the AJAX service used by the block to poll for pending resources.
 *
 * @covers     \block_fastlearning\external\get_pending_resources::execute
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_pending_resources::class)]
final class get_pending_resources_test extends advanced_testcase {
    /**
     * Tests that only the current user's pending resources for the specified course are returned.
     *
     * @covers \block_fastlearning\external\get_pending_resources::execute
     */
    public function test_returns_only_the_current_user_pending_resources_for_the_course(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $this->getDataGenerator()->enrol_user($other->id, $course->id);

        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Study page']);

        $now = time();
        $DB->insert_record('block_fastlearning_levels', (object) [
            'urgencylevel' => 0,
            'name' => 'Critical',
            'minutes' => 60,
            'color' => '#dc3545',
            'icon' => 'core:i/warning',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $raid = $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $student->id,
            'courseid' => $course->id,
            'cmid' => $module->cmid,
            'global' => 0,
            'urgencylevel' => 0,
            'status' => ra::STATUS_PENDING,
            'timeassigned' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $this->setUser($student);
        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('block_fastlearning_get_pending_resources', [
            'courseid' => $course->id,
        ]);

        $this->assertFalse($result['error']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame((int) $raid, $result['data']['items'][0]['id']);
        $this->assertFalse($result['data']['items'][0]['isoverdue']);
        $this->assertGreaterThan(0, $result['data']['items'][0]['remainingseconds']);

        $this->setUser($other);
        $_POST['sesskey'] = sesskey();
        $emptyresult = external_api::call_external_function('block_fastlearning_get_pending_resources', [
            'courseid' => $course->id,
        ]);
        $this->assertFalse($emptyresult['error']);
        $this->assertCount(0, $emptyresult['data']['items']);
    }
}
