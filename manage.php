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
 * Management page for Fast learning resource assignments.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/fastlearning/classes/form/ra_form.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$record = null;
if ($id) {
    $record = $DB->get_record('block_fastlearning_ra', ['id' => $id], '*', MUST_EXIST);
}

if (!$courseid && isset($record)) {
    $courseid = (int) $record->courseid;
} else if (!$courseid) {
    $courseid = SITEID;
}

$issitelevel = $courseid == SITEID;

require_login($courseid);
$context = !$issitelevel ? context_course::instance($courseid) : context_system::instance();
require_capability('block/fastlearning:manage', $context);

$urlparams = !$issitelevel ? ['courseid' => $courseid] : [];
if ($id) {
    $urlparams['id'] = $id;
}
$url = new moodle_url('/blocks/fastlearning/manage.php', $urlparams);
$returnurl = new moodle_url('/blocks/fastlearning/resources.php', $issitelevel ? ['courseid' => $courseid] : []);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('manage', 'block_fastlearning'));
$PAGE->set_heading($issitelevel ? format_string($SITE->fullname) : format_string($COURSE->fullname));
$PAGE->set_pagelayout('admin');

if ($record) {
    if (
        (int) $record->courseid !== $courseid
        || $record->status !== block_fastlearning\local\ra::STATUS_PENDING
    ) {
        throw new moodle_exception('cannoteditra', 'block_fastlearning');
    }
}

$submitted = block_fastlearning\form\ra_form::get_submitted_data($courseid);
$errors = $submitted ? block_fastlearning\form\ra_form::validate($submitted, $courseid) : [];

if ($submitted && !$errors) {
    $now = time();
    $cmid = $submitted->action === 'savewithoutcm' ? null : $submitted->cmid;

    $courseidcm = null;
    if ($cmid) {
        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
        $courseidcm = (int) $cm->course;
    }

    $data = (object) [
        'userid' => $submitted->userid,
        'courseid' => $courseidcm,
        'cmid' => $cmid,
        'global' => $issitelevel ? 1 : 0,
        'competencyid' => $submitted->competencyid ?: null,
        'name' => $submitted->name,
        'urgencylevel' => $submitted->urgencylevel,
        'timemodified' => $now,
    ];

    if ($record) {
        $data->id = $record->id;
        if ((int) $record->cmid !== (int) $data->cmid) {
            $data->timeassigned = $data->cmid ? $now : null;
            $data->timecompleted = null;
        }
        $DB->update_record('block_fastlearning_ra', $data);
        $message = get_string('raupdated', 'block_fastlearning');
    } else {
        $data->status = block_fastlearning\local\ra::STATUS_PENDING;
        $data->timeassigned = $data->cmid ? $now : null;
        $data->timecompleted = null;
        $data->timecreated = $now;
        $DB->insert_record('block_fastlearning_ra', $data);
        $message = get_string('racreated', 'block_fastlearning');
    }
    redirect($returnurl, $message);
}

$values = $submitted ?? $record ?? (object) [];
$values->userid = (int) ($values->userid ?? 0);
$values->competencyid = (int) ($values->competencyid ?? 0);
$values->name = (string) ($values->name ?? '');
$values->urgencylevel = isset($values->urgencylevel) ? (int) $values->urgencylevel : -1;
$values->cmid = (int) ($values->cmid ?? 0);
$values->global = (int) ($values->global ?? 0);
$values->modulecourseid = (int) ($values->modulecourseid ?? 0);
if (!$submitted && $record && $record->cmid) {
    $values->modulecourseid = (int) get_coursemodule_from_id('', $record->cmid, 0, false, MUST_EXIST)->course;
} else if (!$issitelevel) {
    $values->modulecourseid = $courseid;
}

$users = [];
if ($values->userid && ($user = core_user::get_user($values->userid))) {
    $users[] = ['id' => (int) $user->id, 'name' => fullname($user), 'selected' => true];
}

$competencies = [];
foreach ($DB->get_records('competency', null, 'shortname ASC', 'id, shortname') as $competency) {
    $competencies[] = [
        'id' => (int) $competency->id,
        'name' => format_string($competency->shortname),
        'selected' => (int) $competency->id === $values->competencyid,
    ];
}

$courses = [];
if (
    $issitelevel && $values->modulecourseid
        && ($modulecourse = $DB->get_record('course', ['id' => $values->modulecourseid]))
) {
    $courses[] = [
        'id' => (int) $modulecourse->id,
        'name' => format_string($modulecourse->fullname),
        'selected' => true,
    ];
}

$coursemodules = [];
if ($values->cmid && ($cm = get_coursemodule_from_id('', $values->cmid))) {
    $coursemodules[] = ['id' => (int) $cm->id, 'name' => format_string($cm->name), 'selected' => true];
}

$levels = array_values(block_fastlearning\local\urgencylevel::get_levels());
if (!$submitted && !$record && $levels) {
    $values->urgencylevel = (int) reset($levels)->urgencylevel;
}
$leveloptions = [];
foreach ($levels as $level) {
    $icon = block_fastlearning\local\urgencylevel::icon_info($level->icon);
    $leveloptions[] = [
        'value' => (int) $level->urgencylevel,
        'name' => format_string($level->name),
        'color' => $level->color,
        'iconhtml' => $OUTPUT->pix_icon($icon->name, '', $icon->component),
        'selected' => (int) $level->urgencylevel === $values->urgencylevel,
    ];
}

$firststeperrors = array_intersect_key($errors, array_flip(['userid', 'competencyid', 'name', 'urgencylevel']));
$templatecontext = [
    'action' => $url->out(false),
    'cancelurl' => $returnurl->out(false),
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'issite' => $issitelevel,
    'users' => $users,
    'courses' => $courses,
    'coursemodules' => $coursemodules,
    'competencies' => $competencies,
    'hascompetencies' => !empty($competencies),
    'levels' => $leveloptions,
    'haslevels' => !empty($leveloptions),
    'name' => $values->name,
    'modulecourseid' => $values->modulecourseid,
    'errors' => array_values(array_map(static fn(string $message): array => ['message' => $message], $errors)),
    'haserrors' => !empty($errors),
    'initialstep' => $submitted && !$firststeperrors ? 2 : 1,
];

$PAGE->requires->js_call_amd('block_fastlearning/manage_wizard', 'init', [[
    'courseid' => $courseid,
    'issite' => $issitelevel,
    'haslevels' => !empty($leveloptions),
    'initialstep' => $templatecontext['initialstep'],
]]);
if ($competencies) {
    $autocompleteargs = [false, '', get_string('search'), false, true, get_string('noselection', 'form')];
    $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', ['#id_competencyid', ...$autocompleteargs]);
}
$PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
    '#id_userid',
    false,
    'block_fastlearning/form-user-selector',
    get_string('search'),
    false,
    true,
    get_string('noselection', 'form'),
]);
if ($issitelevel) {
    $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
        '#id_modulecourseid',
        false,
        'block_fastlearning/form-course-selector',
        get_string('search'),
        false,
        true,
        get_string('noselection', 'form'),
    ]);
}
$PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
    '#id_cmid',
    false,
    'block_fastlearning/form-course-module-selector',
    get_string('search'),
    false,
    true,
    get_string('noselection', 'form'),
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_fastlearning/resource_form', $templatecontext);
echo $OUTPUT->footer();
