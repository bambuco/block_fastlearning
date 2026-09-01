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

declare(strict_types=1);

namespace block_fastlearning\local;

use core_user;
use invalid_parameter_exception;
use moodle_exception;

/**
 * Public local API for the Fast learning block.
 *
 * Allows other components and subsystems to report resource needs and assign resources.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Report a learning resource need for a user and create an assignment record.
     *
     * @param int $userid Concrete Moodle user id.
     * @param int|string|null $competency Competency id (int) or idnumber (string), optional.
     * @param int|null $courseid Specific course id, optional.
     * @param int|null $cmid Specific course module id, optional.
     * @param bool|int $global Site-level assignment (1/true) or course-level (0/false).
     * @param int $urgencylevel Numeric priority level (0 is highest).
     * @param string|null $name Descriptive name override.
     * @param bool $autoresolvecm Whether to auto-resolve cmid from competency.
    * @param string|null $component Component that created the RA.
    * @param int|null $itemid Source item ID in the component.
     * @return object The created block_fastlearning_ra record.
    * @throws invalid_parameter_exception If user or source reference is invalid.
     */
    public static function report_resource_need(
        int $userid,
        int|string|null $competency = null,
        ?int $courseid = null,
        ?int $cmid = null,
        bool|int $global = 1,
        int $urgencylevel = 0,
        ?string $name = null,
        bool $autoresolvecm = true,
        ?string $component = null,
        ?int $itemid = null
    ): object {
        global $DB;

        if ($userid <= 0 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new invalid_parameter_exception(get_string('invaliduser', 'error'));
        }

        $component = $component !== null ? trim($component) : null;
        if ($component === '') {
            $component = null;
        }
        if (($component === null) !== ($itemid === null)) {
            throw new invalid_parameter_exception(get_string('invalidsource', 'block_fastlearning'));
        }

        $competencyid = null;
        if ($competency !== null && $competency !== '') {
            $competencyrecord = self::resolve_competency($competency);
            if ($competencyrecord) {
                $competencyid = (int) $competencyrecord->id;
            }
        }

        if ($cmid !== null && $cmid > 0) {
            $coursemodule = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
            if ($courseid === null || $courseid <= 0) {
                $courseid = (int) $coursemodule->course;
            }
        } else if ($autoresolvecm && $competencyid !== null) {
            $resolvedcm = self::resolve_competency_module($userid, $competencyid, $courseid);
            if ($resolvedcm !== null) {
                $cmid = (int) $resolvedcm->cmid;
                if ($courseid === null || $courseid <= 0) {
                    $courseid = (int) $resolvedcm->courseid;
                }
            }
        }

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid ?: null,
            'cmid' => $cmid ?: null,
            'global' => $global ? 1 : 0,
            'competencyid' => $competencyid,
            'name' => $name !== null ? trim($name) : null,
            'component' => $component,
            'itemid' => $itemid,
            'urgencylevel' => $urgencylevel,
            'status' => ra::STATUS_PENDING,
            'timeassigned' => $cmid ? $now : null,
            'timecompleted' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $record->id = (int) $DB->insert_record('block_fastlearning_ra', $record);

        return $record;
    }

    /**
     * Resolve a competency by its id (int) or idnumber (string).
     *
     * @param int|string $competency Competency id or idnumber.
     * @return object|null Competency record or null if not found.
     */
    public static function resolve_competency(int|string $competency): ?object {
        global $DB;

        if (is_int($competency) || (is_string($competency) && ctype_digit($competency))) {
            $record = $DB->get_record('competency', ['id' => (int) $competency]);
            if ($record) {
                return $record;
            }
        }

        return $DB->get_record('competency', ['idnumber' => (string) $competency]) ?: null;
    }

    /**
     * Resolve a course module linked to a competency for a given user or course.
     *
     * @param int $userid User id.
     * @param int $competencyid Competency id.
     * @param int|null $courseid Specific course id, optional.
     * @return object|null Object with cmid and courseid properties, or null if none found.
     */
    public static function resolve_competency_module(
        int $userid,
        int $competencyid,
        ?int $courseid = null
    ): ?object {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $allowedcourseids = [];
        if ($courseid !== null && $courseid > 0 && $courseid !== (int) SITEID) {
            $allowedcourseids = [$courseid];
        } else {
            $usercourses = enrol_get_users_courses($userid, true, ['id'], 'id ASC');
            foreach ($usercourses as $usercourse) {
                $allowedcourseids[] = (int) $usercourse->id;
            }
        }

        if (empty($allowedcourseids)) {
            return null;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($allowedcourseids, SQL_PARAMS_NAMED, 'crs');
        $sql = "SELECT cmc.cmid, cm.course AS courseid
                  FROM {competency_modulecomp} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.cmid
                 WHERE cmc.competencyid = :competencyid
                   AND cm.course {$insql}
                   AND cm.visible = 1
                   AND cm.deletioninprogress = 0
              ORDER BY cm.id ASC";

        $params = array_merge(['competencyid' => $competencyid], $inparams);
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        if (!empty($records)) {
            $first = reset($records);
            return (object) [
                'cmid' => (int) $first->cmid,
                'courseid' => (int) $first->courseid,
            ];
        }

        return null;
    }
}
