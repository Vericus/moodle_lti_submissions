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
 * This file contains a class definition for the Context Settings resource
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ltisubmissions\service\toolsettings\local\resources;

use assignsubmission_ltisubmissions\service\toolsettings\local\service\toolsettings;

/**
 * A resource extending the Context-level (ToolProxyBinding) Settings.
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linksettings extends \ltiservice_toolsettings\local\resources\linksettings {
    /**
     * Execute the request for this resource.
     *
     * @param \mod_lti\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $DB, $COURSE;

        $params = $this->parse_template();
        $linkid = $params['link_id'];
        $bubble = optional_param('bubble', '', PARAM_ALPHA);
        $contenttype = $response->get_accept();
        $simpleformat = !empty($contenttype) && ($contenttype == $this->formats[1]);
        $ok = (empty($bubble) || ((($bubble == 'distinct') || ($bubble == 'all')))) &&
            (!$simpleformat || empty($bubble) || ($bubble != 'all')) &&
            (empty($bubble) || ($response->get_request_method() == self::HTTP_GET));
        if (!$ok) {
            $response->set_code(406);
        }

        $systemsetting = null;
        $contextsetting = null;
        $lti = null;
        if ($ok) {
            $ok = !empty($linkid);
            if ($ok) {
                $lti = $DB->get_record_sql('SELECT ma.course, apc.value AS typeid
                    FROM {assign} ma
                    JOIN {assign_plugin_config} apc ON apc.assignment = ma.id
                    WHERE apc.plugin like :plugin AND apc.name LIKE :name AND apc.subtype LIKE :subtype
                    AND apc.name LIKE :subtype AND ma.id = :assignment ',
                    ['plugin' => 'ltisubmissions', 'name' => 'typeid',
                        'subtype' => 'assignsubmission', 'assignment' => $linkid,
                    ], MUST_EXIST);
                $ok = $this->check_tool($lti->typeid, $response->get_request_data(),
                    [toolsettings::SCOPE_TOOL_SETTINGS]);
            }
            if (!$ok) {
                $response->set_code(401);
            }
        }
        if ($ok) {
            if (!empty($this->get_service()->get_tool_proxy())) {
                $id = $this->get_service()->get_tool_proxy()->id;
            } else {
                $id = -$this->get_service()->get_type()->id;
            }
            if ($response->get_request_method() == 'GET') {
                $linksettings = lti_get_tool_settings($id, $lti->course, $linkid);
                if (!empty($bubble)) {
                    $contextsetting = new contextsettings($this->get_service());
                    if ($COURSE == 'site') {
                        $contextsetting->params['context_type'] = 'Group';
                    } else {
                        $contextsetting->params['context_type'] = 'CourseSection';
                    }
                    $contextsetting->params['context_id'] = $lti->course;
                    if ($id >= 0) {
                        $contextsetting->params['vendor_code'] = $this->get_service()->get_tool_proxy()->vendorcode;
                    } else {
                        $contextsetting->params['vendor_code'] = 'tool';
                    }
                    $contextsetting->params['product_code'] = abs($id);
                    $contextsettings = lti_get_tool_settings($id, $lti->course);
                    $systemsetting = new systemsettings($this->get_service());
                    if ($id >= 0) {
                        $systemsetting->params['config_type'] = 'toolproxy';
                    } else {
                        $systemsetting->params['config_type'] = 'tool';
                    }
                    $systemsetting->params['tool_proxy_id'] = abs($id);
                    $systemsettings = lti_get_tool_settings($id);
                    if ($bubble == 'distinct') {
                        toolsettings::distinct_settings($systemsettings, $contextsettings, $linksettings);
                    }
                } else {
                    $contextsettings = null;
                    $systemsettings = null;
                }
                $json = '';
                if ($simpleformat) {
                    $response->set_content_type($this->formats[1]);
                    $json .= "{";
                } else {
                    $response->set_content_type($this->formats[0]);
                    $json .= "{\n  \"@context\":\"http://purl.imsglobal.org/ctx/lti/v2/ToolSettings\",\n  \"@graph\":[\n";
                }
                $settings = toolsettings::settings_to_json($systemsettings, $simpleformat, 'ToolProxy', $systemsetting);
                $json .= $settings;
                $isfirst = strlen($settings) <= 0;
                $settings = toolsettings::settings_to_json($contextsettings, $simpleformat, 'ToolProxyBinding', $contextsetting);
                if (strlen($settings) > 0) {
                    if (!$isfirst) {
                        $json .= ",";
                        if (!$simpleformat) {
                            $json .= "\n";
                        }
                    }
                    $isfirst = false;
                }
                $json .= $settings;
                $settings = toolsettings::settings_to_json($linksettings, $simpleformat, 'LtiLink', $this);
                if ((strlen($settings) > 0) && !$isfirst) {
                    $json .= ",";
                    if (!$simpleformat) {
                        $json .= "\n";
                    }
                }
                $json .= $settings;
                if ($simpleformat) {
                    $json .= "\n}";
                } else {
                    $json .= "\n  ]\n}";
                }
                $response->set_body($json);
            } else { // PUT.
                $settings = null;
                if ($response->get_content_type() == $this->formats[0]) {
                    $json = json_decode($response->get_request_data());
                    $ok = !empty($json);
                    if ($ok) {
                        $ok = isset($json->{"@graph"}) && is_array($json->{"@graph"}) && (count($json->{"@graph"}) == 1) &&
                            ($json->{"@graph"}[0]->{"@type"} == 'LtiLink');
                    }
                    if ($ok) {
                        $settings = $json->{"@graph"}[0]->custom;
                        unset($settings->{'@id'});
                    }
                } else {  // Simple JSON.
                    $json = json_decode($response->get_request_data(), true);
                    $ok = !empty($json);
                    if ($ok) {
                        $ok = is_array($json);
                    }
                    if ($ok) {
                        $settings = $json;
                    }
                }
                if ($ok) {
                    lti_set_tool_settings($settings, $id, $lti->course, $linkid);
                } else {
                    $response->set_code(406);
                }
            }
        }
    }

    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     *
     * @return string
     */
    public function parse_value($value) {

        if (strpos($value, '$LtiLink.custom.url') !== false) {
            $id = optional_param('id', 0, PARAM_INT); // Course Module ID.
            if (!empty($id)) {
                $cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
                $this->params['link_id'] = $cm->instance;
            }
            $value = str_replace('$LtiLink.custom.url', parent::get_endpoint(), $value);
        }
        return $value;

    }

}
