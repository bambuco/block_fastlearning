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

use block_fastlearning\local\controller;
use block_fastlearning\output\renderer;

/**
 * Block Fast learning.
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/blocks}
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_fastlearning extends block_base {
    /**
     * Configure block title.
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_fastlearning');
    }

    /**
     * Restrict block contexts.
     *
     * @return array The valid page formats.
     */
    public function applicable_formats(): array {
        return [
            'all' => true,
            'course-view' => true,
            'site' => true,
            'my' => true,
        ];
    }

    /**
     * Indicate the block has a configuration page.
     *
     * @return bool True because the block exposes settings.
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Build block content for the current user.
     *
     * @return stdClass The rendered block content object.
     */
    public function get_content() {
        global $USER, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $courseid = $COURSE->id ?? SITEID;
        $items = controller::get_pending_for_user($USER->id, $courseid);

        $renderer = $this->page->get_renderer('block_fastlearning');
        $containerid = 'block-fastlearning-' . $this->instance->id;
        $this->content->text = html_writer::div(
            $renderer->render_block_content($items),
            'block-fastlearning-instance',
            ['id' => $containerid]
        );

        $this->page->requires->js_call_amd('block_fastlearning/main', 'init', [[
            'containerid' => $containerid,
            'courseid' => $courseid,
            'refreshinterval' => (int) (get_config('block_fastlearning', 'refresh_interval') ?: 0),
        ]]);

        return $this->content;
    }
}
