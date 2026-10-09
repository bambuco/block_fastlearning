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
 * Settings page for the Fast Learning block.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings = new admin_category('block_fastlearning_settings', get_string('pluginname', 'block_fastlearning'));
$generalsettings = new admin_settingpage('block_fastlearning', get_string('settings', 'block_fastlearning'));

if ($ADMIN->fulltree) {
    $generalsettings->add(new admin_setting_configselect(
        'block_fastlearning/cm_open_mode',
        get_string('cm_open_mode', 'block_fastlearning'),
        get_string('cm_open_mode_desc', 'block_fastlearning'),
        'newwindow',
        [
            'modal' => get_string('cm_open_mode_modal', 'block_fastlearning'),
            'newwindow' => get_string('cm_open_mode_newwindow', 'block_fastlearning'),
            'selfwindow' => get_string('cm_open_mode_selfwindow', 'block_fastlearning'),
        ]
    ));

    $generalsettings->add(new admin_setting_configduration(
        'block_fastlearning/refresh_interval',
        get_string('refresh_interval', 'block_fastlearning'),
        get_string('refresh_interval_desc', 'block_fastlearning'),
        60,
        MINSECS
    ));

    $generalsettings->add(new admin_setting_configcheckbox(
        'block_fastlearning/reassign_completed',
        get_string('reassign_completed', 'block_fastlearning'),
        get_string('reassign_completed_desc', 'block_fastlearning'),
        0
    ));
}

$settings->add('block_fastlearning_settings', $generalsettings);

$externalpage = new admin_externalpage(
    'block_fastlearning_levels',
    get_string('managelevels', 'block_fastlearning'),
    new moodle_url("/blocks/fastlearning/levels.php"),
    'block/fastlearning:manage'
);
$settings->add('block_fastlearning_settings', $externalpage);
