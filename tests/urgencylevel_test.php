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
use block_fastlearning\external\delete_level;
use block_fastlearning\external\reorder_levels;
use block_fastlearning\external\save_level;
use block_fastlearning\local\urgencylevel;

/**
 * Tests for urgency-level management.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class urgencylevel_test extends advanced_testcase {
    /**
     * The AJAX service creates and updates a level.
     */
    public function test_save_level_service_creates_and_updates_level(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $created = save_level::execute(0, 'Critical', 30, '#FF0000', 'core:i/warning');
        $this->assertSame(0, (int) $created->urgencylevel);
        $this->assertSame('#ff0000', $created->color);

        $updated = save_level::execute((int) $created->id, 'Important', 45, '#00ff00', 'core:i/warning');
        $this->assertSame('Important', $updated->name);
        $this->assertSame(45, (int) $updated->minutes);
        $this->assertSame(1, $DB->count_records('block_fastlearning_levels'));
    }

    /**
     * Reordering persists sequential priorities without violating the unique index.
     */
    public function test_reorder_levels_service_updates_priorities(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $first = urgencylevel::save(0, 'First', 10, '#111111', 'core:i/warning');
        $second = urgencylevel::save(0, 'Second', 20, '#222222', 'core:i/warning');
        $third = urgencylevel::save(0, 'Third', 30, '#333333', 'core:i/warning');

        $this->assertTrue(reorder_levels::execute([$third->id, $first->id, $second->id]));
        $ordered = array_values(urgencylevel::get_levels());

        $this->assertSame([$third->id, $first->id, $second->id], array_column($ordered, 'id'));
        $this->assertSame([0, 1, 2], array_map('intval', array_column($ordered, 'urgencylevel')));
    }

    /**
     * Deleting a level compacts priorities while preserving assignments to later levels.
     */
    public function test_delete_level_service_compacts_priorities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $first = urgencylevel::save(0, 'First', 10, '#111111', 'core:i/warning');
        $second = urgencylevel::save(0, 'Second', 20, '#222222', 'core:i/warning');
        $third = urgencylevel::save(0, 'Third', 30, '#333333', 'core:i/warning');
        $assignmentid = $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $user->id,
            'courseid' => SITEID,
            'global' => 1,
            'urgencylevel' => 2,
            'status' => 'pending',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->assertTrue(delete_level::execute((int) $second->id));
        $ordered = array_values(urgencylevel::get_levels());
        $this->assertSame([$first->id, $third->id], array_column($ordered, 'id'));
        $this->assertSame([0, 1], array_map('intval', array_column($ordered, 'urgencylevel')));
        $this->assertSame(1, (int) $DB->get_field('block_fastlearning_ra', 'urgencylevel', ['id' => $assignmentid]));
    }

    /**
     * A level assigned to a resource cannot be deleted.
     */
    public function test_delete_level_service_rejects_level_in_use(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $level = urgencylevel::save(0, 'Used', 10, '#111111', 'core:i/warning');
        $DB->insert_record('block_fastlearning_ra', (object) [
            'userid' => $user->id,
            'courseid' => SITEID,
            'global' => 1,
            'urgencylevel' => 0,
            'status' => 'pending',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('levelinuse', 'block_fastlearning'));
        delete_level::execute((int) $level->id);
    }
}
