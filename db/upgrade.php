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

/**
 * Upgrade steps for Fast learning.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the Fast learning block.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_block_fastlearning_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026083100) {
        $table = new xmldb_table('block_fastlearning_ra');
        $componentfield = new xmldb_field('component', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'name');
        $itemidfield = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'component');
        $index = new xmldb_index('component_itemid_idx', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemid']);

        if (!$dbman->field_exists($table, $componentfield)) {
            $dbman->add_field($table, $componentfield);
        }

        if (!$dbman->field_exists($table, $itemidfield)) {
            $dbman->add_field($table, $itemidfield);
        }

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_block_savepoint(true, 2026083100, 'fastlearning');
    }

    return true;
}
