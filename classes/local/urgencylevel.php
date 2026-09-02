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

/**
 * Utility methods for urgency-level configuration.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class urgencylevel {
    /**
     * Create or update an urgency level.
     *
     * New records are appended to the end of the configured priority list.
     *
     * @param int $id Record id, or zero to create a record.
     * @param string $name Display name.
     * @param int $minutes Minutes allowed for completion.
     * @param string $color CSS hexadecimal colour.
     * @param string $icon Moodle pix icon identifier.
     * @return object The saved record.
     */
    public static function save(int $id, string $name, int $minutes, string $color, string $icon): object {
        global $DB;

        $now = time();
        $record = (object) [
            'name' => $name,
            'minutes' => $minutes,
            'color' => $color,
            'icon' => $icon,
            'timemodified' => $now,
        ];

        if ($id) {
            $existing = $DB->get_record('block_fastlearning_levels', ['id' => $id], '*', MUST_EXIST);
            $record->id = $existing->id;
            $DB->update_record('block_fastlearning_levels', $record);
        } else {
            $record->urgencylevel = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(urgencylevel), -1) + 1 FROM {block_fastlearning_levels}'
            );
            $record->timecreated = $now;
            $record->id = $DB->insert_record('block_fastlearning_levels', $record);
        }

        return $DB->get_record('block_fastlearning_levels', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Delete an unused urgency level and compact subsequent priorities.
     *
     * Existing resource assignments are updated to keep pointing to the same
     * level after its numeric priority changes.
     *
     * @param int $id Record id.
     */
    public static function delete(int $id): void {
        global $DB;

        $record = $DB->get_record('block_fastlearning_levels', ['id' => $id], '*', MUST_EXIST);
        $priority = (int) $record->urgencylevel;
        if ($DB->record_exists('block_fastlearning_ra', ['urgencylevel' => $priority])) {
            throw new \moodle_exception('levelinuse', 'block_fastlearning');
        }

        $transaction = $DB->start_delegated_transaction();
        $maxpriority = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(urgencylevel), 0) FROM {block_fastlearning_levels}'
        );
        $DB->delete_records('block_fastlearning_levels', ['id' => $id]);

        if ($priority < $maxpriority) {
            $remaining = array_values(self::get_levels());
            $temporarypriority = $maxpriority + count($remaining) + 1;
            foreach ($remaining as $offset => $level) {
                $DB->set_field(
                    'block_fastlearning_levels',
                    'urgencylevel',
                    $temporarypriority + $offset,
                    ['id' => $level->id]
                );
            }
            foreach ($remaining as $newpriority => $level) {
                $DB->set_field('block_fastlearning_levels', 'urgencylevel', $newpriority, ['id' => $level->id]);
            }

            $DB->execute(
                'UPDATE {block_fastlearning_ra}
                    SET urgencylevel = urgencylevel - 1
                  WHERE urgencylevel > :priority AND urgencylevel <= :maxpriority',
                ['priority' => $priority, 'maxpriority' => $maxpriority]
            );
        }

        $transaction->allow_commit();
    }

    /**
     * Assign sequential priorities according to the supplied record ids.
     *
     * @param int[] $ids Record ids in their new order.
     */
    public static function reorder(array $ids): void {
        global $DB;

        $records = self::get_levels();
        $currentids = array_map('intval', array_keys($records));
        $ids = array_map('intval', $ids);

        $sortedcurrentids = $currentids;
        $sortedids = $ids;
        sort($sortedcurrentids);
        sort($sortedids);

        if ($sortedids !== $sortedcurrentids) {
            throw new \moodle_exception('invalidlevelorder', 'block_fastlearning');
        }

        $transaction = $DB->start_delegated_transaction();
        $temporarypriority = count($ids) + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(urgencylevel), 0) FROM {block_fastlearning_levels}'
        );

        foreach ($ids as $offset => $id) {
            $DB->set_field('block_fastlearning_levels', 'urgencylevel', $temporarypriority + $offset + 1, ['id' => $id]);
        }
        foreach ($ids as $priority => $id) {
            $DB->set_field('block_fastlearning_levels', 'urgencylevel', $priority, ['id' => $id]);
        }

        $transaction->allow_commit();
    }

    /**
     * Return all urgency levels ordered by priority.
     *
     * @return array The urgency level records.
     */
    public static function get_levels(): array {
        global $DB;

        return $DB->get_records('block_fastlearning_levels', null, 'urgencylevel ASC');
    }

    /**
     * Fetch a single urgency level by its numeric priority.
     *
     * @param int $levelid The urgency priority id.
     * @return object|null The urgency record or null.
     */
    public static function get_level(int $levelid): ?object {
        global $DB;

        return $DB->get_record('block_fastlearning_levels', ['urgencylevel' => $levelid], '*', IGNORE_MISSING);
    }

    /**
     * Retrieve the grace period in minutes for a level.
     *
     * @param int $levelid The urgency priority id.
     * @return int The configured minutes.
     */
    public static function get_level_minutes(int $levelid): int {
        $record = self::get_level($levelid);

        return $record ? (int) $record->minutes : 0;
    }

    /**
     * Return a formatted string for the minutes of a level.
     *
     * If minutes > 60 it will return hours and minutes.
     * If minutes > 1440 it will return days, hours and minutes.
     *
     * @param int $minutes The number of minutes.
     * @return string The formatted string.
     */
    public static function format_minutes(int $minutes): string {
        if ($minutes < 60) {
            return get_string('minutesformat', 'block_fastlearning', $minutes);
        } else if ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $remainingminutes = $minutes % 60;
            return get_string('hoursandminutesformat', 'block_fastlearning', ['hours' => $hours, 'minutes' => $remainingminutes]);
        } else {
            $days = floor($minutes / 1440);
            $remaininghours = floor(($minutes % 1440) / 60);
            $remainingminutes = $minutes % 60;
            return get_string(
                'dayshoursandminutesformat',
                'block_fastlearning',
                ['days' => $days, 'hours' => $remaininghours, 'minutes' => $remainingminutes]
            );
        }
    }

    /**
     * Return the component and name of a Moodle pix icon.
     *
     * @param string $icon The Moodle pix icon identifier.
     * @return object An object with 'component' and 'name' properties.
     */
    public static function icon_info(string $icon): object {
        $iconparts = explode(':', $icon);
        if (count($iconparts) !== 2) {
            $iconparts = ['core', 'e/question'];
        }
        return (object) [
            'component' => $iconparts[0],
            'name' => $iconparts[1],
        ];
    }
}
