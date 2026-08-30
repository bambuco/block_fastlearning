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

namespace block_fastlearning\reportbuilder\local\systemreports;

use block_fastlearning\local\ra;
use block_fastlearning\reportbuilder\local\entities\resource_assignment;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * System report for learning resource assignments.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resources extends system_report {
    /** @var bool Whether the current user can manage assignments. */
    private bool $canmanage = false;

    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        global $USER;

        $resourceentity = new resource_assignment();
        $raalias = $resourceentity->get_table_alias('block_fastlearning_ra');
        $levelalias = $resourceentity->get_table_alias('block_fastlearning_levels');
        $competencyalias = $resourceentity->get_table_alias('competency');
        $coursealias = $resourceentity->get_table_alias('course');
        $cma = $resourceentity->get_table_alias('course_modules');
        $resourceentity->add_joins([
            "LEFT JOIN {block_fastlearning_levels} {$levelalias}
                    ON {$levelalias}.urgencylevel = {$raalias}.urgencylevel",
            "LEFT JOIN {competency} {$competencyalias} ON {$competencyalias}.id = {$raalias}.competencyid",
            "LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$raalias}.courseid",
            "LEFT JOIN {course_modules} {$cma} ON {$cma}.id = {$raalias}.cmid",
        ]);

        $this->set_main_table('block_fastlearning_ra', $raalias);
        $this->add_entity($resourceentity);

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity->add_join(
            "JOIN {user} {$useralias} ON {$useralias}.id = {$raalias}.userid"
        ));

        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        $conditions = [];
        $params = [];
        if ($courseid) {
            $courseparam = database::generate_param_name();
            $conditions[] = "{$raalias}.courseid = :{$courseparam}";
            $params[$courseparam] = $courseid;
        }

        $this->canmanage = has_capability('block/fastlearning:manage', $this->get_context());
        if (!$this->canmanage) {
            $userparam = database::generate_param_name();
            $conditions[] = "{$raalias}.userid = :{$userparam}";
            $params[$userparam] = $USER->id;
        }
        if ($conditions) {
            $this->add_base_condition_sql(implode(' AND ', $conditions), $params);
        }

        $this->add_columns();
        $this->add_filters();
        $this->add_base_fields("{$raalias}.id, {$raalias}.status");
        $this->set_initial_sort_column('resource_assignment:timeassigned', SORT_DESC);
        $this->set_downloadable(true, get_string('resources', 'block_fastlearning'));

        if ($this->canmanage) {
            $this->add_actions($courseid);
        }
    }

    /**
     * Validate access to this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        $courseid = $this->get_parameter('courseid', 0, PARAM_INT);
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if (!$courseid) {
            return $this->get_context()->id === \context_system::instance()->id;
        }

        return $this->get_context()->id === \context_course::instance($courseid)->id;
    }

    /**
     * Add report columns.
     */
    private function add_columns(): void {
        $columns = [
            'resource_assignment:name',
            'user:fullnamewithlink',
            'resource_assignment:course',
            'resource_assignment:cmid',
            'resource_assignment:global',
            'resource_assignment:competency',
            'resource_assignment:urgencylevel',
            'resource_assignment:status',
            'resource_assignment:timeassigned',
            'resource_assignment:timecompleted',
        ];
        $this->add_columns_from_entities($columns);
    }

    /**
     * Add report filters.
     */
    private function add_filters(): void {
        $filters = [
            'resource_assignment:name',
            'resource_assignment:status',
            'resource_assignment:global',
            'resource_assignment:urgencylevel',
            'resource_assignment:timeassigned',
            'resource_assignment:timecompleted',
            'resource_assignment:hascompetency',
        ];
        if ($this->canmanage) {
            array_splice($filters, 1, 0, ['user:fullname']);
        }
        $this->add_filters_from_entities($filters);
    }

    /**
     * Add actions available for pending records.
     *
     * @param int $courseid Course id, or zero for site context.
     */
    private function add_actions(int $courseid): void {
        $params = $courseid ? ['courseid' => $courseid] : [];
        $pendingcallback = static fn(stdClass $row): bool => $row->status === ra::STATUS_PENDING;

        $this->add_action((new action(
            new moodle_url('/blocks/fastlearning/manage.php', ['id' => ':id'] + $params),
            new pix_icon('t/edit', ''),
            [],
            false,
            new lang_string('edit')
        ))->add_callback($pendingcallback));

        $this->add_action((new action(
            new moodle_url('/blocks/fastlearning/resources.php', [
                'delete' => ':id',
                'sesskey' => sesskey(),
            ] + $params),
            new pix_icon('t/delete', ''),
            ['class' => 'text-danger'],
            false,
            new lang_string('delete')
        ))->add_callback($pendingcallback));
    }
}
