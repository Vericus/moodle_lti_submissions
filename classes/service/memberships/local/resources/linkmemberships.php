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
 * This file contains a class definition for the Link Memberships resource
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace assignsubmission_ltisubmissions\service\memberships\local\resources;
use mod_lti\local\ltiservice\resource_base;
use assignsubmission_ltisubmissions\service\memberships\local\service\memberships;
use core_availability\info_module;

/**
 * A resource extending Link Memberships.
 *
 * The link membership is no longer defined in the published
 * version of the LTI specification. It is replaced by the
 * rlid parameter in the context membership URL.
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linkmemberships extends \ltiservice_memberships\local\resources\linkmemberships {
    /**
     * Execute the request for this resource.
     * @package    assignsubmission_ltisubmissions
     * @copyright 2023 Moodle India {@link https://moodle.com/in/}
     * @param \mod_lti\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $DB;

        $params = $this->parse_template();
        $linkid = $params['link_id'];
        $role = optional_param('role', '', PARAM_TEXT);
        $limitnum = optional_param('limit', 0, PARAM_INT);
        $limitfrom = optional_param('from', 0, PARAM_INT);

        if ($limitnum <= 0) {
            $limitfrom = 0;
        }

        if (empty($linkid)) {
            $response->set_code(404);
            return;
        }
        $lti = $DB->get_record_sql('SELECT ma.id, ma.course, apc.value AS typeid
            FROM {assign} ma
            JOIN {assign_plugin_config} apc ON apc.assignment = ma.id
            WHERE apc.plugin like :plugin AND apc.name LIKE :name AND apc.subtype LIKE :subtype
            AND apc.name LIKE :subtype AND ma.id = :assignment ',
            ['plugin' => 'ltisubmissions', 'name' => 'typeid', 'subtype' => 'assignsubmission', 'assignment' => $linkid]);
        $lti->servicesalt = $DB->get_field('assign_plugin_config', 'value',
            ['plugin' => 'ltisubmissions', 'name' => 'servicesalt',
                'subtype' => 'assignsubmission', 'assignment' => $lti->id,
            ]);
        if (!($lti)) {
            $response->set_code(404);
            return;
        }
        if (!$this->check_tool($lti->typeid, $response->get_request_data(), [memberships::SCOPE_MEMBERSHIPS_READ])) {
            $response->set_code(403);
            return;
        }
        if (!($course = $DB->get_record('course', ['id' => $lti->course], 'id', IGNORE_MISSING))) {
            $response->set_code(404);
            return;
        }
        if (!($context = \context_course::instance($lti->course))) {
            $response->set_code(404);
            return;
        }
        $modinfo = get_fast_modinfo($course);
        $cm = get_coursemodule_from_instance('assign', $linkid, $lti->course, false, MUST_EXIST);
        $cm = $modinfo->get_cm($cm->id);
        $info = new info_module($cm);
        if ($info->is_available_for_all()) {
            $info = null;
        }
        $json = $this->get_service()->get_members_json($this, $context, $course, $role,
            $limitfrom, $limitnum, $lti, $info, $response);

        $response->set_content_type($this->formats[0]);
        $response->set_body($json);
    }
}
