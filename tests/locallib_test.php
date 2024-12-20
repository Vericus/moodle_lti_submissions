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
 * Tests for mod/assign/submission/ltisubmissions/locallib.php
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace assignsubmission_ltisubmissions;

use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * Unit tests for mod/assign/submission/file/locallib.php
 *
 * @copyright  2016 Cameron Ball
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Test submission_is_empty
     * @covers ::submission_is_empty
     * @dataProvider submission_is_empty_testcases
     * @param string $data The file submission data
     * @param bool $expected The expected return value
     */
    public function test_submission_is_empty($data, $expected): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_instance($course, [
            'assignsubmission_ltisubmission_enabled' => 1,
        ]);

        $this->setUser($student->id);

        $plugin = $assign->get_submission_plugin_by_type('ltisubmissions');

        $result = $plugin->submission_is_empty($data);

        $this->assertTrue($result === $expected);
    }
    /**
     * Dataprovider for the test_submission_is_empty testcase
     *
     * @return array of testcases
     */
    public static function submission_is_empty_testcases(): array {
        global $CFG;
        return [
            'With allowed draft file' => [
                (object) [
                    'https://api.cadmus.io/lti/submission' => (object) ['submission_type' => 'draft',
                        'content_items' => [
                            (object) [
                                'url' => $CFG->dirroot . '/mod/assign/submission/ltisubmissions/tests/fixtures/submission.pdf',
                                'title' => 'submission.pdf',
                            ],
                        ],
                    ],
                ],
                false,
            ],
            'With non allowed draft file' => [
                (object) [
                    'https://api.cadmus.io/lti/submission' => (object) ['submission_type' => 'draft',
                        'content_items' => [
                            (object) [
                                'url' => $CFG->dirroot . '/mod/assign/submission/ltisubmissions/tests/fixtures/nosubmission.pdf',
                                'title' => 'submission.pdf',
                            ],
                        ],
                    ],
                ],
                true,
            ],
            'With allowed final file' => [
                (object) [
                    'https://api.cadmus.io/lti/submission' => (object) ['submission_type' => 'final',
                        'content_items' => [
                            (object) [
                                'url' => $CFG->dirroot . '/mod/assign/submission/ltisubmissions/tests/fixtures/submission.pdf',
                                'title' => 'submission.pdf',
                            ],
                        ],
                    ],
                ],
                false,
            ],
            'With non allowed final file' => [
                (object) [
                    'https://api.cadmus.io/lti/submission' => (object) ['submission_type' => 'final',
                        'content_items' => [
                            (object) [
                                'url' => $CFG->dirroot . '/mod/assign/submission/ltisubmissions/tests/fixtures/nosubmission.pdf',
                                'title' => 'submission.pdf',
                            ],
                        ],
                    ],
                ],
                true,
            ],
        ];
    }
}
