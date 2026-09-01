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

namespace block_fastlearning\output;

use plugin_renderer_base;
use block_fastlearning\local\urgencylevel;

/**
 * Renderer for the Fast learning block.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Build the template context shared by the server render and the AJAX refresh service.
     *
     * @param array $items Pending resource items.
     * @return array The block_content template context.
     */
    public function get_block_content_context(array $items): array {
        foreach ($items as $key => $item) {
            $icon = explode(':', $item['levelicon'], 2);
            if (count($icon) !== 2) {
                $icon = ['core', 'i/warning'];
            }
            $items[$key]['leveliconhtml'] = $this->pix_icon($icon[1], '', $icon[0]);
        }

        $courseid = !empty($this->page->course->id) && (int) $this->page->course->id !== (int) SITEID
            ? (int) $this->page->course->id
            : 0;
        $resourcesurl = new \moodle_url(
            '/blocks/fastlearning/resources.php',
            $courseid ? ['courseid' => $courseid] : []
        );

        return [
            'items' => array_values($items),
            'emptymessage' => get_string('nothingpending', 'block_fastlearning'),
            'openlabel' => get_string('open', 'block_fastlearning'),
            'resourcesurl' => $resourcesurl->out(false),
        ];
    }

    /**
     * Render the block pending items.
     *
     * @param array $items Pending resource items.
     * @return string HTML rendered by the Mustache template.
     */
    public function render_block_content(array $items): string {
        return parent::render_from_template('block_fastlearning/block_content', $this->get_block_content_context($items));
    }

    /**
     * Render the urgency levels management page.
     *
     * @param array $levels The urgency levels.
     * @return string HTML rendered by the Mustache template.
     */
    public function render_levels(array $levels): string {
        foreach ($levels as $key => $level) {
            $level->iconinfo = urgencylevel::icon_info($level->icon);
            $level->formatedminutes = urgencylevel::format_minutes($level->minutes);
        }

        $data = [
            'levels' => $levels,
            'haslevels' => !empty($levels),
        ];

        return parent::render_from_template('block_fastlearning/levels', $data);
    }
}
