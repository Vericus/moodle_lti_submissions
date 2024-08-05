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
 * Contains the event tests for the plugin.
 *
 * @package   assignsubmission_ltisubmissions
 * @copyright 2023 Moodle India {@link https://moodle.com/in/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ltisubmissions\event;

use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * The events_test test event class.
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Test that the assessable_uploaded event is fired when a draft file submission has been made.
     * @covers \assignsubmission_ltisubmissions\event\assessable_uploaded
     */
    public function test_draft_assessable_uploaded() {
        $this->verify_submission('draft', 'Test Draft submission');
    }
    /**
     * Test that the assessable_uploaded event is fired when a final file submission has been made.
     * @covers \assignsubmission_ltisubmissions\event\assessable_uploaded
     */
    public function test_final_assessable_uploaded() {
        $this->verify_submission('final', 'Test Final submission');
    }
    /**
     * Creating suporting submission.
     *
     * @param string $submissiontype submission type
     * @param string $title title of file
     */
    private function verify_submission($submissiontype, $title) {
        global $CFG;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_instance($course, ['assignsubmission_ltisubmissions_enabled' => 1,
            'final_maxfiles' => 1,
            'draft_maxfiles' => 1,
            'typeid' => 1,
        ]);
        $context = $assign->get_context();

        $this->setUser($student->id);
        $submission = $assign->get_user_submission($student->id, true);
        $submissioninfo = new \stdClass();
        $submissioninfo->{'https://api.cadmus.io/lti/submission'} = (object) ['submission_type' => $submissiontype,
            'content_items' => [
                (object) [
                    'url' => $CFG->dirroot . '/mod/assign/submission/ltisubmissions/tests/fixtures/submission.pdf',
                    'title' => $title,
                ],
            ],
        ];
        $submissioninfo->userid = $student->id;
        $plugin = $assign->get_submission_plugin_by_type('ltisubmissions');
        $sink = $this->redirectEvents();
        $plugin->save($submission, $submissioninfo);
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\assignsubmission_ltisubmissions\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($submission->id, $event->objectid);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEventContextNotUsed($event);
    }

}
