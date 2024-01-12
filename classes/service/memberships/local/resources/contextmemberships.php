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
 * This file contains a class definition for the Context Memberships resource
 *
 * @package    assignsubmission_ltisubmissions
 * @copyright 2023 Moodle India {@link https://moodle.com/in/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace assignsubmission_ltisubmissions\service\memberships\local\resources;
use mod_lti\local\ltiservice\resource_base;
use assignsubmission_ltisubmissions\service\memberships\local\service\memberships;
use core_availability\info_module;

/**
 * A resource extending Context Memberships.
 *
 * @package    assignsubmission_ltisubmissions
 * @copyright 2023 Moodle India {@link https://moodle.com/in/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contextmemberships extends \ltiservice_memberships\local\resources\contextmemberships {
    /**
     * Execute the request for this resource.
     *
     * @param \mod_lti\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $DB;

        $params = $this->parse_template();
        $role = optional_param('role', '', PARAM_TEXT);
        $limitnum = optional_param('limit', 0, PARAM_INT);
        $limitfrom = optional_param('from', 0, PARAM_INT);
        $linkid = optional_param('rlid', '', PARAM_TEXT);
        $lti = null;
        $modinfo = null;

        if ($limitnum <= 0) {
            $limitfrom = 0;
        }

        try {
            if (!$this->check_tool($params['tool_code'], $response->get_request_data(),
                [memberships::SCOPE_MEMBERSHIPS_READ])) {
                throw new \Exception(null, 401);
            }
            if (!($course = $DB->get_record('course', ['id' => $params['context_id']], 'id,shortname,fullname',
                IGNORE_MISSING))) {
                throw new \Exception("Not Found: Course {$params['context_id']} doesn't exist", 404);
            }
            if (!$this->get_service()->is_allowed_in_context($params['tool_code'], $course->id)) {
                throw new \Exception(null, 404);
            }
            if (!($context = \context_course::instance($course->id))) {
                throw new \Exception("Not Found: Course instance {$course->id} doesn't exist", 404);
            }
            if (!empty($linkid)) {
                $lti = $DB->get_record_sql('SELECT ma.id, ma.course, apc.value AS typeid
                    FROM {assign} ma
                    JOIN {assign_plugin_config} apc ON apc.assignment = ma.id WHERE apc.plugin like :plugin
                    AND apc.name LIKE :name AND apc.subtype LIKE :subtype AND apc.name LIKE :subtype
                    AND ma.id = :assignment ',
                    ['plugin' => 'ltisubmissions', 'name' => 'typeid', 'subtype' => 'assignsubmission', 'assignment' => $linkid]);
                $lti->servicesalt = $DB->get_field('assign_plugin_config', 'value',
                    ['plugin' => 'ltisubmissions', 'name' => 'servicesalt',
                    'subtype' => 'assignsubmission', 'assignment' => $lti->id,
                ]);
                if (!($lti)) {
                    throw new \Exception("Not Found: LTI link {$linkid} doesn't exist", 404);
                }
                $modinfo = get_fast_modinfo($course);
                $cm = get_coursemodule_from_instance('assign', $linkid, $lti->course, false, MUST_EXIST);
                $cm = $modinfo->get_cm($cm->id);
                $modinfo = new info_module($cm);
                if ($modinfo->is_available_for_all()) {
                    $modinfo = null;
                }
            }

            $json = $this->get_service()->get_members_json($this, $context, $course, $role, $limitfrom, $limitnum, $lti,
                $modinfo, $response);

            $response->set_body($json);

        } catch (\Exception $e) {
            $response->set_code($e->getCode());
            $response->set_reason($e->getMessage());
        }
    }
}
