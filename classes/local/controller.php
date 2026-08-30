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

namespace block_fastlearning\local;

use block_fastlearning\local\ra;
use context_system;
use moodle_url;

/**
 * Controller for Fast learning pending resource handling.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controller {
    /**
     * Return the pending resources assigned to a user.
     *
     * @param int $userid The user id.
     * @param int|null $courseid Optional course filter.
     * @return array The pending records ready for the block renderer.
     */
    public static function get_pending_for_user(int $userid, ?int $courseid = null): array {
        global $DB;

        $sql = "SELECT r.*
                  FROM {block_fastlearning_ra} r
                  LEFT JOIN {course_modules} cm ON cm.id = r.cmid
                 WHERE r.userid = :userid
                   AND r.status = :status
                   AND r.cmid IS NOT NULL
                   AND cm.id IS NOT NULL";

        $params = [
            'userid' => $userid,
            'status' => ra::STATUS_PENDING,
        ];

        if ($courseid && $courseid !== (int) SITEID) {
            $sql .= ' AND cm.course = :courseid';
            $params['courseid'] = $courseid;
        } else {
            $sql .= ' AND r.global = 1';
        }

        $sql .= ' ORDER BY r.urgencylevel ASC, r.timeassigned ASC';

        $records = $DB->get_records_sql($sql, $params);
        $items = [];
        $now = time();
        $openmode = get_config('block_fastlearning', 'cm_open_mode') === 'modal' ? 'modal' : 'window';

        foreach ($records as $record) {
            $cm = get_coursemodule_from_id('', (int) $record->cmid, 0, false, MUST_EXIST);
            $level = urgencylevel::get_level((int) $record->urgencylevel) ?: (object) [
                'name' => get_string('unknownlevel', 'block_fastlearning'),
                'color' => '#6c757d',
                'icon' => 'core:i/warning',
                'minutes' => 0,
            ];

            $timeassigned = (int) ($record->timeassigned ?: $record->timecreated);
            $deadline = ra::calculate_deadline($timeassigned, urgencylevel::get_level_minutes((int) $record->urgencylevel));
            $remainingseconds = $deadline - $now;
            $linkattributes = $openmode === 'modal'
                ? 'data-fastlearning-open="modal"'
                : 'target="_blank" rel="noopener noreferrer"';

            $items[] = [
                'id' => (int) $record->id,
                'name' => ra::get_display_name((object) [
                    'name' => $record->name,
                    'competencyname' => $record->competencyname ?? null,
                ]),
                'cmname' => format_string($cm->name ?? get_string('unknowncm', 'block_fastlearning')),
                'cmurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
                'remaining' => self::format_remaining($remainingseconds),
                'remainingseconds' => $remainingseconds,
                'isoverdue' => $remainingseconds < 0,
                'levelname' => format_string($level->name),
                'levelcolor' => $level->color ?: '#6c757d',
                'levelicon' => $level->icon ?: 'core:i/warning',
                'openmode' => $openmode,
                'linkattributes' => $linkattributes,
                'status' => $record->status,
            ];
        }

        return $items;
    }

    /**
     * Format a remaining time value for the UI.
     *
     * @param int $seconds Number of seconds to format.
     * @return string Formatted time string.
     */
    public static function format_remaining(int $seconds): string {
        $abs = abs($seconds);
        $hours = intdiv($abs, HOURSECS);
        $minutes = intdiv($abs % HOURSECS, MINSECS);
        $secs = $abs % MINSECS;

        return ($seconds < 0 ? '-' : '') . sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Check whether the user can manage the current context.
     *
     * @param int $contextid The context id.
     * @return bool True if the user has the block management capability.
     */
    public static function can_manage_context(int $contextid): bool {
        try {
            $context = $contextid > 0 ? \context::instance_by_id($contextid, IGNORE_MISSING) : context_system::instance();
        } catch (\Throwable $e) {
            return false;
        }

        return has_capability('block/fastlearning:manage', $context);
    }
}
