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
 * External service definitions for Fast learning.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_fastlearning_save_level' => [
        'classname' => block_fastlearning\external\save_level::class,
        'description' => 'Create or update a Fast learning urgency level',
        'type' => 'write',
        'ajax' => true,
    ],
    'block_fastlearning_reorder_levels' => [
        'classname' => block_fastlearning\external\reorder_levels::class,
        'description' => 'Reorder Fast learning urgency levels',
        'type' => 'write',
        'ajax' => true,
    ],
    'block_fastlearning_delete_level' => [
        'classname' => block_fastlearning\external\delete_level::class,
        'description' => 'Delete an unused Fast learning urgency level',
        'type' => 'write',
        'ajax' => true,
    ],
    'block_fastlearning_search_users' => [
        'classname' => block_fastlearning\external\search_users::class,
        'description' => 'Search users eligible for a Fast learning resource assignment',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
    ],
    'block_fastlearning_search_courses' => [
        'classname' => block_fastlearning\external\search_courses::class,
        'description' => 'Search active courses for the selected user',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
    ],
    'block_fastlearning_search_course_modules' => [
        'classname' => block_fastlearning\external\search_course_modules::class,
        'description' => 'Search course modules eligible for a Fast learning resource assignment',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
    ],
];
