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

namespace block_fastlearning\reportbuilder\local\entities;

use block_fastlearning\local\ra;
use block_fastlearning\local\urgencylevel;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use stdClass;

/**
 * Report builder entity for learning resource assignments.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_assignment extends base {
    /**
     * Return the tables used by this entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'block_fastlearning_ra',
            'block_fastlearning_levels',
            'competency',
            'course',
            'course_modules',
        ];
    }

    /**
     * Return the entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('resourceassignment', 'block_fastlearning');
    }

    /**
     * Initialise columns and filters.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter);
        }

        return $this;
    }

    /**
     * Return all entity columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $raalias = $this->get_table_alias('block_fastlearning_ra');
        $levelalias = $this->get_table_alias('block_fastlearning_levels');
        $competencyalias = $this->get_table_alias('competency');
        $coursealias = $this->get_table_alias('course');

        $columns[] = (new column(
            'name',
            new lang_string('raname', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("COALESCE({$raalias}.name, {$competencyalias}.shortname)", 'name')
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $name): string =>
                $name !== null && $name !== '' ? format_string($name) : get_string('unknownresource', 'block_fastlearning'));

        $columns[] = (new column(
            'global',
            new lang_string('global', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$raalias}.global")
            ->set_type(column::TYPE_INTEGER)
            ->add_callback(static fn(?int $global): string => $global ? get_string('yes') : get_string('no'));

        $columns[] = (new column(
            'course',
            new lang_string('course'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$coursealias}.fullname")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $course): string => $course ? format_string($course) : '-');

        $columns[] = (new column(
            'cmid',
            new lang_string('cmid', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$raalias}.cmid, {$raalias}.courseid")
            ->set_type(column::TYPE_INTEGER)
            ->add_callback(static function (?int $cmid, stdClass $row): string {
                if (!$cmid) {
                    return '-';
                }

                static $modinfocache = [];

                $courseid = (int) $row->courseid;
                if (!isset($modinfocache[$courseid])) {
                    $modinfocache[$courseid] = get_fast_modinfo($courseid);
                }

                try {
                    $cm = $modinfocache[$courseid]->get_cm($cmid);
                    $name = format_string($cm->name, true, ['context' => $cm->context]);

                    return \html_writer::link($cm->url, $name);
                } catch (\Throwable $exception) {
                    return get_string('unknowncm', 'block_fastlearning');
                }
            });

        $columns[] = (new column(
            'competency',
            new lang_string('competencyid', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$competencyalias}.shortname")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $competency): string => $competency ? format_string($competency) : '-');

        $columns[] = (new column(
            'urgencylevel',
            new lang_string('urgencylevel', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_fields("{$raalias}.urgencylevel, {$levelalias}.name AS levelname")
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback(static function (int $level, stdClass $row): string {
                return !empty($row->levelname)
                    ? format_string($row->levelname)
                    : get_string('unknownlevel', 'block_fastlearning');
            });

        $columns[] = (new column(
            'status',
            new lang_string('status', 'block_fastlearning'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$raalias}.status")
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_callback(static function (string $status): string {
                return match ($status) {
                    ra::STATUS_PENDING => get_string('status_pending', 'block_fastlearning'),
                    ra::STATUS_COMPLETED => get_string('status_completed', 'block_fastlearning'),
                    default => format_string($status),
                };
            });

        foreach (['timeassigned', 'timecompleted'] as $timefield) {
            $columns[] = (new column(
                $timefield,
                new lang_string($timefield, 'block_fastlearning'),
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field("{$raalias}.{$timefield}")
                ->set_type(column::TYPE_TIMESTAMP)
                ->set_is_sortable(true)
                ->add_callback(static fn(?int $time): string => $time ? userdate($time) : '-');
        }

        return $columns;
    }

    /**
     * Return all entity filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $raalias = $this->get_table_alias('block_fastlearning_ra');
        $competencyalias = $this->get_table_alias('competency');

        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('raname', 'block_fastlearning'),
            $this->get_entity_name(),
            "COALESCE({$raalias}.name, {$competencyalias}.shortname)"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status', 'block_fastlearning'),
            $this->get_entity_name(),
            "{$raalias}.status"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                ra::STATUS_PENDING => get_string('status_pending', 'block_fastlearning'),
                ra::STATUS_COMPLETED => get_string('status_completed', 'block_fastlearning'),
            ]);

        $filters[] = (new filter(
            select::class,
            'global',
            new lang_string('global', 'block_fastlearning'),
            $this->get_entity_name(),
            "{$raalias}.global"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                0 => get_string('no'),
                1 => get_string('yes'),
            ]);

        $filters[] = (new filter(
            select::class,
            'urgencylevel',
            new lang_string('urgencylevel', 'block_fastlearning'),
            $this->get_entity_name(),
            "{$raalias}.urgencylevel"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                $options = [];
                foreach (urgencylevel::get_levels() as $level) {
                    $options[$level->urgencylevel] = format_string($level->name);
                }
                return $options;
            });

        foreach (['timeassigned', 'timecompleted'] as $timefield) {
            $filters[] = (new filter(
                date::class,
                $timefield,
                new lang_string($timefield, 'block_fastlearning'),
                $this->get_entity_name(),
                "{$raalias}.{$timefield}"
            ))->add_joins($this->get_joins());
        }

        $filters[] = (new filter(
            boolean_select::class,
            'hascompetency',
            new lang_string('hascompetency', 'block_fastlearning'),
            $this->get_entity_name(),
            "CASE WHEN {$raalias}.competencyid IS NULL THEN 0 ELSE 1 END"
        ))->add_joins($this->get_joins());

        return $filters;
    }
}
