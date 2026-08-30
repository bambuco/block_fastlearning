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

use context_course;
use core_user\fields;

/**
 * Provides the eligible options used by the resource assignment wizard.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_options {
    /** Maximum number of AJAX suggestions. */
    private const MAX_RESULTS = 100;

    /**
     * Search users available in the assignment context.
     *
     * @param int $courseid Context course id, or SITEID.
     * @param string $query Search text.
     * @return array[] User id and display name.
     */
    public static function search_users(int $courseid, string $query): array {
        return array_values(self::get_users($courseid, $query, null, self::MAX_RESULTS));
    }

    /**
     * Check whether a user can receive an assignment in the context.
     *
     * @param int $courseid Context course id, or SITEID.
     * @param int $userid User id.
     * @return bool
     */
    public static function user_is_available(int $courseid, int $userid): bool {
        global $CFG, $DB;

        if ($courseid === (int) SITEID) {
            return $userid !== (int) $CFG->siteguest && $DB->record_exists('user', [
                'id' => $userid,
                'deleted' => 0,
                'suspended' => 0,
            ]);
        }
        return !empty(self::get_users($courseid, '', $userid, 1));
    }

    /**
     * Return active courses in which the user is enrolled.
     *
     * @param int $userid User id.
     * @param string $query Search text.
     * @return array[] Course id and display name.
     */
    public static function search_user_courses(int $userid, string $query): array {
        global $CFG;

        require_once($CFG->libdir . '/enrollib.php');
        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname', 'shortname'], 'fullname ASC');
        $query = \core_text::strtolower(trim($query));
        $results = [];

        foreach ($courses as $course) {
            $name = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
            if ($query !== '' && !str_contains(\core_text::strtolower($name . ' ' . $course->shortname), $query)) {
                continue;
            }
            $results[] = ['id' => (int) $course->id, 'name' => $name];
            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return $results;
    }

    /**
     * Check whether a course is available to the selected user.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return bool
     */
    public static function course_is_available(int $userid, int $courseid): bool {
        foreach (self::search_user_courses($userid, '') as $course) {
            if ($course['id'] === $courseid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search visible course modules, optionally linked to a competency.
     *
     * This checks the module's direct visible flag, not user visibility.
     *
     * @param int $courseid Course containing the modules.
     * @param int $competencyid Optional competency id.
     * @param string $query Search text.
     * @return array[] Module id and display name.
     */
    public static function search_course_modules(int $courseid, int $competencyid, string $query): array {
        global $DB;

        $allowedcmids = null;
        if ($competencyid) {
            $allowedcmids = $DB->get_fieldset_select(
                'competency_modulecomp',
                'cmid',
                'competencyid = :competencyid',
                ['competencyid' => $competencyid]
            );
            $allowedcmids = array_fill_keys(array_map('intval', $allowedcmids), true);
        }

        $query = \core_text::strtolower(trim($query));
        $results = [];
        foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
            if (!$cm->visible || $cm->deletioninprogress || ($allowedcmids !== null && !isset($allowedcmids[$cm->id]))) {
                continue;
            }
            $name = format_string($cm->name, true, ['context' => $cm->context]);
            if ($query !== '' && !str_contains(\core_text::strtolower($name), $query)) {
                continue;
            }
            $results[] = ['id' => (int) $cm->id, 'name' => $name];
            if (count($results) >= self::MAX_RESULTS) {
                break;
            }
        }

        return $results;
    }

    /**
     * Check whether a module matches the wizard restrictions.
     *
     * @param int $courseid Course containing the module.
     * @param int $cmid Course module id.
     * @param int $competencyid Optional competency id.
     * @return bool
     */
    public static function course_module_is_available(int $courseid, int $cmid, int $competencyid): bool {
        foreach (self::search_course_modules($courseid, $competencyid, '') as $cm) {
            if ($cm['id'] === $cmid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Query users for site or course context.
     *
     * @param int $courseid Context course id, or SITEID.
     * @param string $query Search text.
     * @param int|null $userid Optional exact user id.
     * @param int $limit Maximum records.
     * @return array User options keyed by id.
     */
    private static function get_users(int $courseid, string $query, ?int $userid, int $limit): array {
        global $CFG, $DB;

        $namefields = fields::get_name_fields();
        $selectfields = implode(', ', array_map(static fn(string $field): string => "u.{$field}", $namefields));
        $params = [];
        $joins = [];
        $conditions = ['u.deleted = 0', 'u.suspended = 0', 'u.id <> :guestid'];
        $params['guestid'] = (int) $CFG->siteguest;

        if ($courseid !== (int) SITEID) {
            require_once($CFG->libdir . '/enrollib.php');
            $context = context_course::instance($courseid);
            [$enrolledsql, $enrolledparams] = get_enrolled_sql($context, '', 0, true);
            [$rolesql, $roleparams] = $DB->get_in_or_equal(
                array_filter(array_map('intval', explode(',', $CFG->gradebookroles))),
                SQL_PARAMS_NAMED,
                'gradebookrole'
            );
            [$contextsql, $contextparams] = $DB->get_in_or_equal(
                $context->get_parent_context_ids(true),
                SQL_PARAMS_NAMED,
                'gradedcontext'
            );
            $joins[] = "JOIN ({$enrolledsql}) enrolled ON enrolled.id = u.id";
            $joins[] = "JOIN (
                            SELECT DISTINCT ra.userid
                              FROM {role_assignments} ra
                             WHERE ra.roleid {$rolesql}
                               AND ra.contextid {$contextsql}
                        ) gradeduser ON gradeduser.userid = u.id";
            $params += $enrolledparams + $roleparams + $contextparams;
        }

        if ($userid !== null) {
            $conditions[] = 'u.id = :userid';
            $params['userid'] = $userid;
        } else if (($query = trim($query)) !== '') {
            $searchconditions = [];
            foreach (array_merge($namefields, ['username', 'email']) as $index => $field) {
                $paramname = 'usersearch' . $index;
                $searchconditions[] = $DB->sql_like("u.{$field}", ":{$paramname}", false);
                $params[$paramname] = '%' . $DB->sql_like_escape($query) . '%';
            }
            $conditions[] = '(' . implode(' OR ', $searchconditions) . ')';
        }

        $sql = "SELECT u.id, {$selectfields}
                  FROM {user} u
                       " . implode("\n", $joins) . '
                 WHERE ' . implode(' AND ', $conditions) . '
              ORDER BY u.lastname, u.firstname, u.id';

        $results = [];
        foreach ($DB->get_records_sql($sql, $params, 0, $limit) as $user) {
            $results[$user->id] = ['id' => (int) $user->id, 'name' => fullname($user)];
        }
        return $results;
    }
}
