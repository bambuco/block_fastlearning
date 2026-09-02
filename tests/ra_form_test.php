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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_fastlearning;

use advanced_testcase;
use block_fastlearning\form\ra_form;
use block_fastlearning\local\urgencylevel;
use context_system;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the resource assignment form submission contract.
 *
 * @package    block_fastlearning
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ra_form::class)]
final class ra_form_test extends advanced_testcase {
    /**
     * Test that the competency field is optional during form validation.
     *
     * @covers \block_fastlearning\form\ra_form::validate
     * @return void
     */
    public function test_competency_is_optional_during_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        urgencylevel::save(0, 'Normal', 60, '#0f6cbf', 'core:i/info');
        $data = (object) [
            'action' => 'savewithoutcm',
            'userid' => (int) $user->id,
            'modulecourseid' => 0,
            'cmid' => 0,
            'competencyid' => 0,
            'name' => 'Editable resource name',
            'urgencylevel' => 0,
        ];

        $this->assertSame([], ra_form::validate($data, (int) SITEID));
    }

    /**
     * Test that the Mustache template renders the form markup correctly without the competency toggle.
     *
     * @covers \block_fastlearning\form\ra_form
     * @return void
     */
    public function test_mustache_owns_the_form_markup_without_competency_toggle(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(context_system::instance());

        $html = $OUTPUT->render_from_template('block_fastlearning/resource_form', [
            'action' => 'manage.php',
            'cancelurl' => 'resources.php',
            'sesskey' => sesskey(),
            'courseid' => (int) SITEID,
            'issite' => true,
            'hascompetencies' => true,
            'competencies' => [['id' => 1, 'name' => 'Communication']],
            'haslevels' => false,
            'name' => '',
            'modulecourseid' => 0,
            'haserrors' => false,
        ]);

        $this->assertStringContainsString('id="block-fastlearning-ra-wizard"', $html);
        $this->assertStringContainsString('name="competencyid"', $html);
        $this->assertStringNotContainsString('name="hascompetency"', $html);
    }
}
