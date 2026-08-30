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
 * Learning resource assignments report.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_fastlearning\local\ra;
use block_fastlearning\reportbuilder\local\systemreports\resources;
use core_reportbuilder\system_report_factory;

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

require_login($courseid ?: null);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot view learning resources.');
}

$context = $courseid ? context_course::instance($courseid) : context_system::instance();
$urlparams = $courseid ? ['courseid' => $courseid] : [];
$url = new moodle_url('/blocks/fastlearning/resources.php', $urlparams);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('resources', 'block_fastlearning'));
$PAGE->set_heading($courseid ? format_string($COURSE->fullname) : format_string($SITE->fullname));
$PAGE->navbar->add(get_string('resources', 'block_fastlearning'), $url);

if ($deleteid) {
    require_capability('block/fastlearning:manage', $context);
    require_sesskey();
    $record = $DB->get_record('block_fastlearning_ra', ['id' => $deleteid], '*', MUST_EXIST);
    if (($courseid && (int) $record->courseid !== $courseid) || $record->status !== ra::STATUS_PENDING) {
        throw new moodle_exception('cannotdeletera', 'block_fastlearning');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('deleteraconfirm', 'block_fastlearning', format_string(ra::get_display_name($record))),
            new moodle_url($url, ['delete' => $deleteid, 'confirm' => 1, 'sesskey' => sesskey()]),
            $url
        );
        echo $OUTPUT->footer();
        exit;
    }

    $DB->delete_records('block_fastlearning_ra', ['id' => $deleteid]);
    redirect($url, get_string('radeleted', 'block_fastlearning'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('resources', 'block_fastlearning'));

if (has_capability('block/fastlearning:manage', $context)) {
    $createurl = new moodle_url('/blocks/fastlearning/manage.php', $urlparams);
    echo $OUTPUT->single_button($createurl, get_string('createra', 'block_fastlearning'), 'get');
}

$report = system_report_factory::create(resources::class, $context, '', '', 0, ['courseid' => $courseid]);
echo $report->output();

echo $OUTPUT->footer();
