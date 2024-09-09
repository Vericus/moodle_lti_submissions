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
 * library file for the lti submissions
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2009 Marc Alier <marc.alier@upc.edu>, Jordi Piguillem, Nikolas Galanis
 * @copyright   2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author      Marc Alier
 * @author      Jordi Piguillem
 * @author      Nikolas Galanis
 * @author      Chris Scribner
 * @copyright   2015 Vital Source Technologies http://vitalsource.com
 * @author      Stephen Vickers
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Updates the urls to the existing variable.
 *
 * @param reference $urls
 */
function assignsubmission_ltisubmissions_urls(&$urls) {
    $url = new moodle_url('/mod/lti/certs.php');
    $urls['assignpublickeyset'] = $url->out();
    $url = new moodle_url('/mod/lti/token.php');
    $urls['assignaccesstoken'] = $url->out();
    $url = new moodle_url('/mod/assign/submission/ltisubmissions/auth.php');
    $urls['assignauthrequest'] = $url->out();
}

/**
 * Generate the form for initiating a login request for an LTI 1.3 message
 *
 * @param int            $courseid  Course ID
 * @param int            $cmid        LTI instance ID
 * @param stdClass|null  $instance  LTI instance
 * @param stdClass       $config    Tool type configuration
 * @param string         $messagetype   LTI message type
 * @param string         $title     Title of content item
 * @param string         $text      Description of content item
 * @param int            $foruserid Id of the user targeted by the launch
 *
 * @return string
 */
function assignsubmission_ltisubmissions_initiate_login($courseid, $cmid, $instance, $config,
    $messagetype = 'basic-lti-launch-request', $title = '', $text = '', $foruserid = 0) {

    $params = assignsubmission_ltisubmissions_build_login_request($courseid, $cmid, $instance, $config,
        $messagetype, $foruserid, $title, $text);

    $r = "<form action=\"" . $config->lti_initiatelogin .
        "\" name=\"ltiInitiateLoginForm\" id=\"ltiInitiateLoginForm\" method=\"post\" " .
        "encType=\"application/x-www-form-urlencoded\">\n";

    foreach ($params as $key => $value) {
        $key = htmlspecialchars($key, ENT_COMPAT);
        $value = htmlspecialchars($value, ENT_COMPAT);
        $r .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
    }
    $r .= "</form>\n";

    $r .= "<script type=\"text/javascript\">\n" .
        "//<![CDATA[\n" .
        "document.ltiInitiateLoginForm.submit();\n" .
        "//]]>\n" .
        "</script>\n";

    return $r;
}

/**
 * Prepares an LTI 1.3 login request
 *
 * @param int            $courseid  Course ID
 * @param int            $cmid        Course Module instance ID
 * @param stdClass|null  $instance  LTI instance
 * @param stdClass       $config    Tool type configuration
 * @param string         $messagetype   LTI message type
 * @param int            $foruserid Id of the user targeted by the launch
 * @param string         $title     Title of content item
 * @param string         $text      Description of content item
 *
 * @return array Login request parameters
 */
function assignsubmission_ltisubmissions_build_login_request($courseid, $cmid, $instance, $config,
    $messagetype, $foruserid = 0, $title = '', $text = '') {
    global $CFG, $SESSION;
    $ltihint = [];
    if (!empty($instance)) {
        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $config->lti_toolurl;
        $launchid = 'ltilaunch' . $instance->id . '_' . rand();
        $ltihint['cmid'] = $cmid;
        $SESSION->$launchid = "{$courseid},{$config->typeid},{$cmid},{$messagetype},{$foruserid},,";
    } else {
        $endpoint = $config->lti_toolurl;
        if (($messagetype === 'ContentItemSelectionRequest') && !empty($config->lti_toolurl_ContentItemSelectionRequest)) {
            $endpoint = $config->lti_toolurl_ContentItemSelectionRequest;
        }
        $launchid = "ltilaunch_$messagetype" . rand();
        $SESSION->$launchid =
            "{$courseid},{$config->typeid},{$cmid},{$messagetype},{$foruserid},"
            . base64_encode($title) . ',' . base64_encode($text);
    }
    $endpoint = trim($endpoint);
    $services = assignsubmission_ltisubmissions_get_services();
    foreach ($services as $service) {
        [$endpoint] = $service->override_endpoint($messagetype ?? 'basic-lti-launch-request', $endpoint, '', $courseid, $instance);
    }

    $ltihint['launchid'] = $launchid;
    // If SSL is forced make sure https is on the normal launch URL.
    if (isset($config->lti_forcessl) && ($config->lti_forcessl == '1')) {
        $endpoint = lti_ensure_url_is_https($endpoint);
    } else if (!strstr($endpoint, '://')) {
        $endpoint = 'http://' . $endpoint;
    }

    $params = [];
    $params['iss'] = $CFG->wwwroot;
    $params['target_link_uri'] = $endpoint;
    $params['login_hint'] = $foruserid;
    $params['lti_message_hint'] = json_encode($ltihint);
    $params['client_id'] = $config->lti_clientid;
    $params['lti_deployment_id'] = $config->typeid;
    return $params;
}

/**
 * Echoes the launch tool info for the assignment submission.
 * @param stdClass     $psuedolti      Assignment object
 * @param int          $foruserid      User id for whom the launch is made.
 */
function assignsubmission_ltisubmissions_launch_tool($psuedolti, $foruserid) {
    [$endpoint, $parms] = assignsubmission_ltisubmissions_get_launch_data($psuedolti, '', '', $foruserid);

    $debuglaunch = ($psuedolti->debuglaunch == 1);

    $content = lti_post_launch_html($parms, $endpoint, $debuglaunch);

    echo $content;
}

/**
 * Return the launch data required for opening the external tool.
 *
 * @param  stdClass $instance the external tool activity settings
 * @param  string $nonce  the nonce value to use (applies to LTI 1.3 only)
 * @param  string $messagetype  the messagetype for launch
 * @param  integer $foruserid  the userid for launch
 *
 * @return array the endpoint URL and parameters (including the signature)
 * @since  Moodle 3.0
 */
function assignsubmission_ltisubmissions_get_launch_data($instance, $nonce = '',
    $messagetype = 'basic-lti-launch-request', $foruserid = 0) {
    global $PAGE, $USER;
    $messagetype = $messagetype ? $messagetype : 'basic-lti-launch-request';
    $tool = lti_get_instance_type($instance);
    if ($tool) {
        $typeid = $tool->id;
        $ltiversion = $tool->ltiversion;
    } else {
        $typeid = null;
        $ltiversion = LTI_VERSION_1;
    }

    if ($typeid) {
        $typeconfig = lti_get_type_config($typeid);
    }

    if (isset($tool->toolproxyid)) {
        $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
        $key = $toolproxy->guid;
        $secret = $toolproxy->secret;
    } else {
        $toolproxy = null;
        if (!empty($instance->resourcekey)) {
            $key = $instance->resourcekey;
        } else if ($ltiversion === LTI_VERSION_1P3) {
            $key = $tool->clientid;
        } else if (!empty($typeconfig['resourcekey'])) {
            $key = $typeconfig['resourcekey'];
        } else {
            $key = '';
        }
        if (!empty($instance->password)) {
            $secret = $instance->password;
        } else if (!empty($typeconfig['password'])) {
            $secret = $typeconfig['password'];
        } else {
            $secret = '';
        }
    }

    $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
    $endpoint = trim($endpoint);

    // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
    if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
        if (!empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        if ($endpoint !== '') {
            $endpoint = lti_ensure_url_is_https($endpoint);
        }
    } else if ($endpoint !== '' && !strstr($endpoint, '://')) {
        $endpoint = 'http://' . $endpoint;
    }

    $orgid = lti_get_organizationid($typeconfig);

    $course = $PAGE->course;
    $islti2 = isset($tool->toolproxyid);
    $allparams = assignsubmission_ltisubmissions_build_request($instance, $typeconfig, $course, $typeid, $islti2,
        $messagetype, $foruserid);
    if ($islti2) {
        $requestparams = lti_build_request_lti2($tool, $allparams);
    } else {
        $requestparams = $allparams;
    }
    $requestparams = array_merge($requestparams, lti_build_standard_message($instance, $orgid, $ltiversion, $messagetype));
    $customstr = '';
    if (isset($typeconfig['customparameters'])) {
        $customstr = $typeconfig['customparameters'];
    }
    $services = assignsubmission_ltisubmissions_get_services();
    foreach ($services as $service) {
        [$endpoint, $customstr] = $service->override_endpoint($messagetype,
            $endpoint, $customstr, $instance->course, $instance);
    }
    $instance->instructorcustomparameters = '';
    $requestparams = array_merge($requestparams, lti_build_custom_parameters($toolproxy, $tool, $instance, $allparams, $customstr,
        $instance->instructorcustomparameters, $islti2));

    $launchcontainer = lti_get_launch_container($instance, $typeconfig);
    $returnurlparams = ['course' => $course->id,
        'launch_container' => $launchcontainer,
        'instanceid' => $instance->id,
        'sesskey' => sesskey(),
    ];

    // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
    $url = new \moodle_url('/mod/assign/submission/ltisubmissions/return.php', $returnurlparams);
    $returnurl = $url->out(false);

    if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
        $returnurl = lti_ensure_url_is_https($returnurl);
    }

    $target = 'window';

    if (!empty($target)) {
        $requestparams['launch_presentation_document_target'] = $target;
    }

    $requestparams['launch_presentation_return_url'] = $returnurl;

    // Add the parameters configured by the LTI services.
    if ($typeid && !$islti2) {
        $services = assignsubmission_ltisubmissions_get_services();
        foreach ($services as $service) {
            $serviceparameters = $service->get_launch_parameters('basic-lti-launch-request',
                $course->id, $USER->id, $typeid, $instance->id);
            foreach ($serviceparameters as $paramkey => $paramvalue) {
                $requestparams['custom_' . $paramkey] = assignsubmission_ltisubmissions_parse_custom_parameter($toolproxy,
                    $tool, $requestparams, $paramvalue, $islti2);
            }
        }
    }

    if ((!empty($key) && !empty($secret)) || ($ltiversion === LTI_VERSION_1P3)) {
        if ($ltiversion !== LTI_VERSION_1P3) {
            $parms = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
        } else {
            $parms = lti_sign_jwt($requestparams, $endpoint, $key, $typeid, $nonce);
        }

        $endpointurl = new \moodle_url($endpoint);
        $endpointparams = $endpointurl->params();

        // Strip querystring params in endpoint url from $parms to avoid duplication.
        if (!empty($endpointparams) && !empty($parms)) {
            foreach (array_keys($endpointparams) as $paramname) {
                if (isset($parms[$paramname])) {
                    unset($parms[$paramname]);
                }
            }
        }

    } else {
        // If no key and secret, do the launch unsigned.
        $returnurlparams['unsigned'] = '1';
        $parms = $requestparams;
    }

    return [$endpoint, $parms];
}

/**
 * Parse a custom parameter to replace any substitution variables
 *
 * @param object    $toolproxy      Tool proxy instance object
 * @param object    $tool           Tool instance object
 * @param array     $params         LTI launch parameters
 * @param string    $value          Custom parameter value
 * @param boolean   $islti2         True if an LTI 2 tool is being launched
 *
 * @return string Parsed value of custom parameter
 */
function assignsubmission_ltisubmissions_parse_custom_parameter($toolproxy, $tool, $params, $value, $islti2) {
    // This is required as {${$valarr[0]}->{$valarr[1]}}" may be using the USER or COURSE var.
    if ($value) {
        if (substr($value, 0, 1) == '\\') {
            $value = substr($value, 1);
        } else if (substr($value, 0, 1) == '$') {
            $value1 = substr($value, 1);
            $enabledcapabilities = lti_get_enabled_capabilities($tool);
            if (!$islti2 || in_array($value1, $enabledcapabilities)) {
                $capabilities = lti_get_capabilities();
                if (array_key_exists($value1, $capabilities)) {
                    $val = $capabilities[$value1];
                    if ($val) {
                        if (substr($val, 0, 1) != '$') {
                            $value = $params[$val];
                        } else {
                            $valarr = explode('->', substr($val, 1), 2);
                            $value = "{${$valarr[0]}->{$valarr[1]} }";
                            $value = str_replace('<br />', ' ', $value);
                            $value = str_replace('<br>', ' ', $value);
                            $value = format_string($value);
                        }
                    } else {
                        $value = lti_calculate_custom_parameter($value1);
                    }
                } else {
                    $val = $value;
                    $services = assignsubmission_ltisubmissions_get_services();
                    foreach ($services as $service) {
                        $service->set_tool_proxy($toolproxy);
                        $service->set_type($tool);
                        $value = $service->parse_value($val);
                        if ($val != $value) {
                            break;
                        }
                    }
                }
            }
        }
    }
    return $value;
}

/**
 * Initializes an array with the services supported by the LTI module
 *
 * @return array List of services
 */
function assignsubmission_ltisubmissions_get_services() {

    global $CFG;
    $services = [];
    $serviceclasses = scandir($CFG->dirroot . '/mod/assign/submission/ltisubmissions/classes/service');
    foreach ($serviceclasses as $serviceclass) {
        if (!in_array($serviceclass, ['.', '..'])) {
            $classname = "\\assignsubmission_ltisubmissions\\service\\{$serviceclass}\\local\\service\\{$serviceclass}";
            if (class_exists($classname)) {
                $services[] = new $classname();
            }
        }
    }
    return $services;
}

/**
 * This function builds the request that must be sent to the tool producer
 *
 * @param object    $instance       Basic LTI instance object
 * @param array     $typeconfig     Basic LTI tool configuration
 * @param object    $course         Course object
 * @param int|null  $typeid         Basic LTI tool ID
 * @param boolean   $islti2         True if an LTI 2 tool is being launched
 * @param string    $messagetype    LTI Message Type for this launch
 * @param int       $foruserid      User targeted by this launch
 *
 * @return array                    Request details
 */
function assignsubmission_ltisubmissions_build_request($instance, $typeconfig, $course, $typeid = null, $islti2 = false,
    $messagetype = 'basic-lti-launch-request', $foruserid = 0) {
    global $USER, $CFG;

    if (empty($instance->cmid)) {
        $instance->cmid = 0;
    }
    if ($foruserid != 0 && $foruserid != $USER->id) {
        $userinfo = \core_user::get_user($foruserid);
    } else {
        $userinfo = $USER;
    }
    $role = assignsubmission_ltisubmissions_get_ims_role($userinfo, $instance->cmid, $instance->course, $islti2);
    $requestparams = [
        'user_id' => $userinfo->id,
        'lis_person_sourcedid' => $userinfo->idnumber,
        'roles' => $role,
        'context_id' => $course->id,
        'context_label' => trim(html_to_text($course->shortname, 0)),
        'context_title' => trim(html_to_text($course->fullname, 0)),
    ];
    if ($foruserid) {
        $requestparams['for_user_id'] = $foruserid;
    }
    if ($messagetype) {
        $requestparams['lti_message_type'] = $messagetype;
    }
    if (!empty($instance->name)) {
        $requestparams['resource_link_title'] = trim(html_to_text($instance->name, 0));
    }
    if (!empty($instance->cmid)) {// Sending Description information to Cadmus.
        $intro = format_module_intro('assign', $instance, $instance->cmid);
        $intro = trim(html_to_text($intro, 0, false));

        // This may look weird, but this is required for new lines.
        // so we generate the same OAuth signature as the tool provider.
        $intro = str_replace("\n", "\r\n", $intro);
        $requestparams['resource_link_description'] = $intro;
    }
    if (!empty($instance->id)) { // Sending The Assignment ID.
        $requestparams['resource_link_id'] = $instance->id;
    }
    if (!empty($instance->resource_link_id)) {
        $requestparams['resource_link_id'] = $instance->resource_link_id;
    }
    if ($course->format == 'site') {
        $requestparams['context_type'] = 'Group';
    } else {
        $requestparams['context_type'] = 'CourseSection';
        $requestparams['lis_course_section_sourcedid'] = $course->idnumber;
    }

    if (!empty($instance->id) && !empty($instance->servicesalt) && ($islti2 ||
        $typeconfig['acceptgrades'] == LTI_SETTING_ALWAYS ||
        ($typeconfig['acceptgrades'] == LTI_SETTING_DELEGATE && $instance->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS))
    ) {
        $placementsecret = $instance->servicesalt;
        $sourcedid = json_encode(lti_build_sourcedid($instance->id, $userinfo->id, $placementsecret, $typeid));
        $requestparams['lis_result_sourcedid'] = $sourcedid;

        // Add outcome service URL.
        $serviceurl = new \moodle_url('/mod/assign/submission/ltisubmissions/service.php');
        $serviceurl = $serviceurl->out();

        $forcessl = false;
        if (!empty($CFG->mod_lti_forcessl)) {
            $forcessl = true;
        }

        if ((isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) || $forcessl) {
            $serviceurl = lti_ensure_url_is_https($serviceurl);
        }

        $requestparams['lis_outcome_service_url'] = $serviceurl;
    }

    // Send user's name and email data if appropriate.
    if ($islti2 || $typeconfig['sendname'] == LTI_SETTING_ALWAYS ||
        ($typeconfig['sendname'] == LTI_SETTING_DELEGATE && isset($instance->instructorchoicesendname)
            && $instance->instructorchoicesendname == LTI_SETTING_ALWAYS)
    ) {
        $requestparams['lis_person_name_given'] = $userinfo->firstname;
        $requestparams['lis_person_name_family'] = $userinfo->lastname;
        $requestparams['lis_person_name_full'] = fullname($userinfo);
        $requestparams['ext_user_username'] = $userinfo->username;
    }

    if ($islti2 || $typeconfig['sendemailaddr'] == LTI_SETTING_ALWAYS ||
        ($typeconfig['sendemailaddr'] == LTI_SETTING_DELEGATE && isset($instance->instructorchoicesendemailaddr)
            && $instance->instructorchoicesendemailaddr == LTI_SETTING_ALWAYS)
    ) {
        $requestparams['lis_person_contact_email_primary'] = $userinfo->email;
    }

    return $requestparams;
}

/**
 * Gets the IMS role string for the specified user and LTI course module.
 *
 * @param mixed    $user      User object or user id
 * @param int      $cmid      The course module id of the LTI activity
 * @param int      $courseid  The course id of the LTI activity
 * @param boolean  $islti2    True if an LTI 2 tool is being launched
 *
 * @return string A role string suitable for passing with an LTI launch
 */
function assignsubmission_ltisubmissions_get_ims_role($user, $cmid, $courseid, $islti2) {
    $roles = [];

    if (empty($cmid)) {
        // If no cmid is passed, check if the user is a teacher in the course
        // This allows other modules to programmatically "fake" a launch without
        // a real LTI instance.
        $context = context_course::instance($courseid);

        if (has_capability('moodle/course:manageactivities', $context, $user)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    } else {
        $context = context_module::instance($cmid);

        if (has_capability('mod/lti:manage', $context, $user)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    }
    if (!is_role_switched($courseid) && (is_siteadmin($user)) || has_capability('mod/lti:admin', $context, $user)) {
        // Make sure admins do not have the Learner role, then set admin role.
        $roles = array_diff($roles, ['Learner']);
        if (!$islti2) {
            array_push($roles, 'urn:lti:sysrole:ims/lis/Administrator', 'urn:lti:instrole:ims/lis/Administrator');
        } else {
            array_push($roles, 'http://purl.imsglobal.org/vocab/lis/v2/person#Administrator');
        }
    }

    return join(',', $roles);
}

/**
 * Serves assignment submissions and other files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options - List of options affecting file serving.
 *
 * @return bool|null false if file not found, does not return if found - just send the file
 */
function assignsubmission_ltisubmissions_pluginfile($course,
    $cm,
    context $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    $itemid = (int) array_shift($args);
    $record = $DB->get_record('assign_submission',
        ['id' => $itemid],
        'userid, assignment, groupid',
        MUST_EXIST);
    $userid = $record->userid;
    $groupid = $record->groupid;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $assign = new assign($context, $cm, $course);

    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }

    if ($assign->get_instance()->teamsubmission &&
        !$assign->can_view_group_submission($groupid)) {
        return false;
    }

    if (!$assign->get_instance()->teamsubmission &&
        !$assign->can_view_submission($userid)) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/assignsubmission_ltisubmissions/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }
    send_file($file, $file->get_filename(), null, 0, false, $forcedownload, '', false, $options);
}

/**
 * Function to get the LTI type id for the assignment.
 * @param StdClass      $psuedoltiinstance  Assignment instance considering as LTI
 *
 * @return int The type id value associated to the activity
 */
function assignsubmission_ltisubmissions_get_psuedoltitypeid($psuedoltiinstance) {
    global $DB;
    return $DB->get_field('assign_plugin_config', 'value',
        ['plugin' => 'ltisubmissions', 'name' => 'typeid',
            'subtype' => 'assignsubmission',
            'assignment' => $psuedoltiinstance->id,
        ]);
}

/**
 * Function to update the grade values.
 *
 * @param StdClass       $ltiinstance    Assignment object
 * @param integer        $userid         Associated Userid
 * @param float          $gradeval       Awarded Grades
 *
 * @return boolean status of grade update.
 */
function assignsubmission_ltisubmissions_update_grade($ltiinstance, $userid, $gradeval) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [];
    $params['itemname'] = $ltiinstance->name;

    $gradeval = $gradeval * floatval($ltiinstance->grade);

    $grade = new stdClass();
    $grade->userid = $userid;
    $grade->rawgrade = $gradeval;

    $status = grade_update('mod/assign', $ltiinstance->course, LTI_ITEM_TYPE, 'assign', $ltiinstance->id, 0, $grade, $params);
    return $status == GRADE_UPDATE_OK;
}

/**
 * Function to read grade values.
 *
 * @param stdClass     $ltiinstance
 * @param integer      $userid
 *
 * @return float|null  grade value if available.
 */
function assignsubmission_ltisubmissions_read_grade($ltiinstance, $userid) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = grade_get_grades($ltiinstance->course, 'mod', 'assign', $ltiinstance->id, $userid);

    $ltigrade = floatval($ltiinstance->grade);

    if (!empty($ltigrade) && isset($grades) && isset($grades->items[0]) && is_array($grades->items[0]->grades)) {
        foreach ($grades->items[0]->grades as $agrade) {
            $grade = $agrade->grade;
            if (isset($grade)) {
                return $grade / $ltigrade;
            }
        }
    }
}
