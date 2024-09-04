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
 * This file defines the admin settings for this plugin
 *
 * @package   assignsubmission_ltisubmissions
 * @copyright 2023 Moodle India {@link https://moodle.com/in/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $DB;

$settings = new admin_settingpage('assignsubmission_ltisubmissions', new lang_string('pluginname', 'assignsubmission_ltisubmissions'));

$settings->add(new admin_setting_configcheckbox('assignsubmission_ltisubmissions/default',
    new lang_string('default', 'assignsubmission_ltisubmissions'),
    new lang_string('default_help', 'assignsubmission_ltisubmissions'),
    1));

$settings->add(new admin_setting_configtext('assignsubmission_ltisubmissions/draftfiles',
    new lang_string('draftfiles', 'assignsubmission_ltisubmissions'),
    new lang_string('draftfiles_help', 'assignsubmission_ltisubmissions'),
    1,
    PARAM_INT));

$settings->add(new admin_setting_configtext('assignsubmission_ltisubmissions/finalfiles',
    new lang_string('finalfiles', 'assignsubmission_ltisubmissions'),
    new lang_string('finalfiles_help', 'assignsubmission_ltisubmissions'),
    1,
    PARAM_INT));

$query = "SELECT id, name
            FROM {lti_types}
           WHERE course = 1
             AND state = 1
        ORDER BY name ASC";
$options = $DB->get_records_sql_menu($query);

$settings->add(new admin_setting_configmultiselect('assignsubmission_ltisubmissions/defaulttypeids',
    new lang_string('defaulttypeids', 'assignsubmission_ltisubmissions'),
    new lang_string('defaulttypeids_help', 'assignsubmission_ltisubmissions'), [], $options));
