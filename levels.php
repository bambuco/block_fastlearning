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
 * Management page for Fast learning urgency levels.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('block/fastlearning:manage', $context);

$url = new moodle_url('/blocks/fastlearning/levels.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managelevels', 'block_fastlearning'));
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add(get_string('managelevels', 'block_fastlearning'), $url);

$levels = array_values(block_fastlearning\local\urgencylevel::get_levels());
$iconsystem = core\output\icon_system::instance();
$icons = [];

foreach ($iconsystem->get_icon_name_map() as $identifier => $unused) {
    $tokens = explode(':', $identifier, 2);
    if (count($tokens) !== 2 || strlen($identifier) > 100) {
        continue;
    }

    $icons[] = [
        'identifier' => $identifier,
        'html' => $iconsystem->render_pix_icon($OUTPUT, new pix_icon($tokens[1], '', $tokens[0])),
    ];
}

usort($icons, static fn(array $first, array $second): int => $first['identifier'] <=> $second['identifier']);

$PAGE->requires->js_call_amd('block_fastlearning/levels', 'init', [[
    'icons' => $icons,
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managelevels', 'block_fastlearning'), 2);


$renderer = $PAGE->get_renderer('block_fastlearning');
echo $renderer->render_levels($levels, $icons);
echo $OUTPUT->footer();
