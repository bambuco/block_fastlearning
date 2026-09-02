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
use block_fastlearning\local\ra;
use block_fastlearning\reportbuilder\local\systemreports\resources;
use context_course;
use context_system;
use core_reportbuilder\system_report_factory;
use core_reportbuilder\testable_system_report_table;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests learning resource assignment behaviour and reporting.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ra::class)]
#[CoversClass(resources::class)]
final class ra_management_test extends advanced_testcase {
    /**
     * Load completion state constants used by the resource assignment helper.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
    }

    /**
     * Tests that resource assignment status is correctly derived from completion states.
     *
     * Verifies the mapping between Moodle completion states and internal RA status values.
     *
     * @covers \block_fastlearning\local\ra::status_from_completion_state
     */
    public function test_status_is_derived_from_completion_state(): void {
        $this->assertSame('completed', ra::status_from_completion_state(COMPLETION_COMPLETE));
        $this->assertSame('completed', ra::status_from_completion_state(COMPLETION_COMPLETE_PASS));
        $this->assertSame('pending', ra::status_from_completion_state(COMPLETION_INCOMPLETE));
        $this->assertSame('pending', ra::status_from_completion_state(null));
    }

    /**
     * Tests that resource assignment deadlines are correctly calculated.
     *
     * Validates the deadline calculation formula based on start time and urgency level minutes.
     *
     * @covers \block_fastlearning\local\ra::calculate_deadline
     */
    public function test_deadline_is_calculated_from_start_and_urgency_minutes(): void {
        $start = 1_700_000_000;
        $minutes = 120;

        $this->assertSame($start + (120 * 60), ra::calculate_deadline($start, $minutes));
    }

    /**
     * Tests that display names prioritize editable RA names over competency names.
     *
     * Verifies that when a resource assignment has a manually edited name, it takes precedence
     * over the competency name for display purposes.
     *
     * @covers \block_fastlearning\local\ra::get_display_name
     */
    public function test_uses_editable_name_before_competency_name(): void {
        $ra = (object) [
            'name' => 'Manual RA name',
            'competencyname' => 'Competency name',
        ];

        $this->assertSame('Manual RA name', ra::get_display_name($ra));
    }

    /**
     * The report applies course and user visibility restrictions.
     *
     * @covers \block_fastlearning\reportbuilder\local\systemreports\resources
     */
    public function test_resources_report_visibility(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/reportbuilder/tests/fixtures/testable_system_report_table.php');

        $this->resetAfterTest();

        $courseone = $this->getDataGenerator()->create_course();
        $coursetwo = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $now = time();

        foreach (
            [
            [$userone->id, $courseone->id, 'First resource'],
            [$usertwo->id, $courseone->id, 'Second resource'],
            [$userone->id, $coursetwo->id, 'Other course resource'],
            ] as [$userid, $courseid, $name]
        ) {
            $DB->insert_record('block_fastlearning_ra', (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'name' => $name,
                'urgencylevel' => 0,
                'status' => ra::STATUS_PENDING,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        $this->setAdminUser();
        $coursereport = system_report_factory::create(
            resources::class,
            context_course::instance($courseone->id),
            '',
            '',
            0,
            ['courseid' => $courseone->id]
        );
        $coursetable = new testable_system_report_table(
            'system-report-table-' . $coursereport->get_report_persistent()->get('id'),
            ['courseid' => $courseone->id]
        );
        $this->assertCount(2, $coursetable->get_table_rows());
        $this->assertCount(2, $coursereport->get_actions());

        $this->setUser($userone);
        $sitereport = system_report_factory::create(resources::class, context_system::instance(), '', '', 0, ['courseid' => 0]);
        $sitetable = new testable_system_report_table(
            'system-report-table-' . $sitereport->get_report_persistent()->get('id'),
            ['courseid' => 0]
        );
        $this->assertCount(2, $sitetable->get_table_rows());
        $this->assertCount(0, $sitereport->get_actions());
    }
}
