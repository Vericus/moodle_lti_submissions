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
 * This file contains the definition for the library class for LTI submission plugin
 *
 * This class provides all the functionality for the new assign module.
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// File areas for file submission assignment.
define('ASSIGNSUBMISSION_LTISUBMISSIONS_MAXSUMMARYFILES', 5);
define('ASSIGNSUBMISSION_LTISUBMISSIONS_FINAL_FILEAREA', 'ltisubmission_final_files');
define('ASSIGNSUBMISSION_LTISUBMISSIONS_DRAFT_FILEAREA', 'ltisubmission_draft_files');
require_once($CFG->dirroot . '/mod/assign/submission/ltisubmissions/lib.php');
require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * library class for lti submission.
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_ltisubmissions extends \assign_submission_plugin {
    /**
     * Get the name of the LTI submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('ltisubmissions', 'assignsubmission_ltisubmissions');
    }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE, $PAGE, $OUTPUT, $DB;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $hideoptions = 0;
        if ($this->assignment->has_instance()) {
            $typeidsubmissions = $this->get_config('typeid');
            $hideoptions = 1;
        } else {
            $typeidsubmissions = 0;
        }
        $mform->addElement('hidden', 'showtitlelaunch', 1);
        $mform->setType('showtitlelaunch', PARAM_INT);
        $mform->addElement('hidden', 'showdescriptionlaunch', 1);
        $mform->setType('showdescriptionlaunch', PARAM_INT);

        // Tool settings.
        $toolproxy = [];
        // Array of tool type IDs that don't support ContentItemSelectionRequest.
        $noncontentitemtypes = [];

        $defaulttypeids = get_config('assignsubmission_ltisubmissions', 'defaulttypeids');
        $defaulttypeids = array_filter(explode(',', $defaulttypeids));
        if (!empty($defaulttypeids)) {
            list($typesql, $typesparams) = $DB->get_in_or_equal($defaulttypeids, SQL_PARAMS_NAMED, 'types');
        } else {
            $typesql = ' = id ';
            $typesparams = [];
        }
        $query = "SELECT id, name
                FROM {lti_types}
               WHERE course = 1 AND id {$typesql}
            ORDER BY name ASC";
        $defaultselect = [0 => get_string('select_tool', 'assignsubmission_ltisubmissions')];
        $options = $defaultselect + $DB->get_records_sql_menu($query, $typesparams);
        $tooltypes = $mform->addElement('select', 'typeid', get_string('external_tool_type', 'lti'), $options);
        $mform->addHelpButton('typeid', 'external_tool_type', 'lti');
        $mform->setDefault('typeid', $typeidsubmissions);
        $mform->hideIf('typeid', 'assignsubmission_ltisubmissions_enabled', 'notchecked');

        $mform->addElement('advcheckbox', 'instructorchoiceacceptgrades', get_string('accept_grades', 'lti'));
        $mform->setDefault('instructorchoiceacceptgrades', '1');
        $mform->hideIf('instructorchoiceacceptgrades', 'showtitlelaunch', 'eq', '1');

        $draftfilesubmissions = $this->get_config('draft_maxfiles');
        $draftoptions = [];
        for ($i = 0; $i <= get_config('assignsubmission_ltisubmissions', 'draftfiles'); $i++) {
            $draftoptions[$i] = $i;
        }
        $mform->addElement('select', 'draft_maxfiles',
            get_string('maxdraftfilessubmission', 'assignsubmission_ltisubmissions'), $draftoptions);
        $mform->addHelpButton('draft_maxfiles',
            'maxdraftfilessubmission',
            'assignsubmission_ltisubmissions');
        $mform->setDefault('draft_maxfiles', $draftfilesubmissions);
        $mform->hideIf('draft_maxfiles', 'assignsubmission_ltisubmissions_enabled', 'notchecked');

        $finalfilesubmissions = $this->get_config('final_maxfiles');
        $finaloptions = [];
        for ($i = 1; $i <= get_config('assignsubmission_ltisubmissions', 'finalfiles'); $i++) {
            $finaloptions[$i] = $i;
        }
        $mform->addElement('select', 'final_maxfiles',
            get_string('maxfinalfilessubmission', 'assignsubmission_ltisubmissions'), $finaloptions);
        $mform->addHelpButton('final_maxfiles',
            'maxfinalfilessubmission',
            'assignsubmission_ltisubmissions');
        $mform->setDefault('final_maxfiles', $finalfilesubmissions);
        $mform->hideIf('final_maxfiles', 'assignsubmission_ltisubmissions_enabled', 'notchecked');
        $PAGE->requires->js_call_amd('assignsubmission_ltisubmissions/ltiform', 'init', [$hideoptions]);
    }

    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        global $DB;
        $fields = ['typeid', 'draft_maxfiles', 'final_maxfiles', 'servicesalt'];
        $authuserroleid = $DB->get_field('role', 'id', ['archetype' => 'user']);
        assign_capability('mod/assign:submit', CAP_ALLOW,
            $authuserroleid, $this->assignment->get_context()->id, true);

        $data->servicesalt = uniqid('', true);
        foreach ($fields as $field) {
            if (isset($data->{$field})) {
                $this->set_config($field, $data->{$field});
            }
        }
        $services = assignsubmission_ltisubmissions_get_services();
        $assignmentinstance = $this->assignment->get_instance();
        $assignmentinstance->typeid = $data->typeid;
        foreach ($services as $service) {
            $service->instance_updated($assignmentinstance);
        }
        return true;
    }

    /**
     * Basic LTI launch for assignment submission.
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $DB;
        // Function to redirect to the lti pages.
        $psuedolti = $DB->get_record('assign', ['id' => $submission->assignment], '*');
        if (empty($psuedolti)) {
            $psuedolti = new stdClass();
        }
        $typeid = $this->get_config('typeid');
        $psuedolti->typeid = $typeid;
        $cm = $this->assignment->get_course_module();
        $psuedolti->cmid = $cm->id;

        $config = lti_get_type_type_config($typeid);
        if ($config->lti_ltiversion === LTI_VERSION_1P3) {
            if (!isset($SESSION->lti_initiatelogin_status)) {
                $msgtype = 'basic-lti-launch-request';
                echo assignsubmission_ltisubmissions_initiate_login($cm->course, $cm->id, $psuedolti, $config,
                    $msgtype, '', '', $data->userid);
                die();
            } else {
                unset($SESSION->lti_initiatelogin_status);
            }
        }
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $ltiassignsubid = $DB->get_field('assignsubmission_ltisub', 'id',
            ['submission' => $submission->id]);
        $ltisubmissionobj = new \stdClass();
        $ltisubmissionobj->submission = $submission->id;
        if ($ltiassignsubid) {
            $ltisubmissionobj->id = $ltiassignsubid;
            $DB->update_record('assignsubmission_ltisub', $ltisubmissionobj);
        } else {
            $DB->insert_record('assignsubmission_ltisub', $ltisubmissionobj);
        }
        $submissioninfo = $data->{'https://api.cadmus.io/lti/submission'};
        $fs = get_file_storage();
        $url = $submissioninfo->content_items[0]->url;
        if (strtolower($submissioninfo->submission_type) == 'draft') {
            $filearea = 'ltisubmission_draft_files';
            $fileslength = (int) $this->get_config('draft_maxfiles');
        } else {
            $filearea = 'ltisubmission_final_files';
            $fileslength = (int) $this->get_config('final_maxfiles');
        }
        $filerecord = [
            'contextid' => $this->assignment->get_context()->id,
            'component' => 'assignsubmission_ltisubmissions',
            'filearea' => $filearea,
            'userid' => $data->userid,
        ];
        $oldfilerecords = $DB->get_records_sql("SELECT * FROM {files}
            WHERE contextid = :contextid AND component = :component
            AND filearea = :filearea AND userid = :userid AND filename <> '.' ORDER BY id ASC ", $filerecord);
        if (count($oldfilerecords) >= $fileslength) {
            $needsdeletion = array_splice($oldfilerecords, 0, count($oldfilerecords) - ($fileslength - 1));
            if ($needsdeletion) {
                foreach ($needsdeletion as $oldfilerecord) {
                    $fs->get_file_instance($oldfilerecord)->delete();
                }
            }
        }
        $return = false;
        if ($fileslength != 0) {
            $filerecord['itemid'] = $submission->id;
            $filerecord['filepath'] = '/';
            $filerecord['filename'] = $submissioninfo->content_items[0]->title;

            $content = file_get_contents($url);
            $file = $fs->create_file_from_string($filerecord, $content);

            $params = [
                'context' => \context_module::instance($this->assignment->get_course_module()->id),
                'courseid' => $this->assignment->get_course()->id,
                'objectid' => $submission->id,
                'other' => [
                    'content' => '',
                    'pathnamehashes' => [$file->get_pathnamehash()],
                ],
            ];
            $params['relateduserid'] = $data->userid;
            if ($this->assignment->is_blind_marking()) {
                $params['anonymous'] = 1;
            }
            $event = \assignsubmission_ltisubmissions\event\assessable_uploaded::create($params);
            $event->set_legacy_files([$file]);
            $event->trigger();
            $return = true;
        }
        return $return;
    }

    /**
     * Save the files and trigger plagiarism plugin, if enabled,
     * to scan the uploaded files via events trigger
     *
     * @param stdClass $ltiscore
     * @param Integer $userid
     * @return bool false in case of missing attachment / non allowed attachment.
     */
    public function save_lti_submission($ltiscore, $userid) {
        global $USER;
        // Hack to Set the $USER object.
        if (!$USER->id) {
            $userobject = \core_user::get_user($userid);
            if ($userobject) {
                \core\session\manager::set_user($userobject);
            } else {
                $this->set_error(get_string('usernotfound', 'assignsubmission_ltisubmissions'));
            }
        }
        // Need submit permission to submit an assignment.
        $notices = [];
        if (!$this->assignment->submissions_open($userid)) {
            $notices[] = get_string('duedatereached', 'assign');
            return false;
        }
        $ltiscore->userid = $userid;
        return $this->assignment->save_submission($ltiscore, $notices);
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        $return = true;
        if (isset($data->{'https://api.cadmus.io/lti/submission'}->content_items[0]->url)) {
            try {
                $content = file_get_contents($data->{'https://api.cadmus.io/lti/submission'}->content_items[0]->url);
                if (!empty($content)) {
                    $return = false;
                }
            } catch (\Exception $e) {
                $return = true;
            }
        }
        return $return;
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     */
    public function remove(stdClass $submission) {
        $fs = get_file_storage();

        $fs->delete_area_files($this->assignment->get_context()->id,
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_FINAL_FILEAREA,
            $submission->id);
        $fs->delete_area_files($this->assignment->get_context()->id,
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_DRAFT_FILEAREA,
            $submission->id);
        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = [];
        $fs = get_file_storage();
        // Final Files.
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_FINAL_FILEAREA,
            $submission->id,
            'timemodified DESC',
            false);

        foreach ($files as $file) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[$file->get_filepath() . $file->get_filename()] = $file;
            }
            if (isset($submission->sendfinalsubmission) && $submission->sendfinalsubmission) {
                return $result;
                break;
            }
        }
        // Draft Files.
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_DRAFT_FILEAREA,
            $submission->id,
            'timemodified DESC',
            false);

        foreach ($files as $file) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[$file->get_filepath() . $file->get_filename()] = $file;
            }
            if (isset($submission->sendfinalsubmission) && $submission->sendfinalsubmission) {
                return $result;
                break;
            }
        }
        return $result;
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     */
    private function count_files($submissionid, $area) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
            'assignsubmission_ltisubmissions',
            $area,
            $submissionid,
            'id',
            false);
        return count($files);
    }

    /**
     * Return true if there are no submission files
     * @param stdClass $submission
     */
    public function is_empty(stdClass $submission) {
        $draftfiles = $this->count_files($submission->id, ASSIGNSUBMISSION_LTISUBMISSIONS_DRAFT_FILEAREA);
        $finalfiles = $this->count_files($submission->id, ASSIGNSUBMISSION_LTISUBMISSIONS_FINAL_FILEAREA);
        return $draftfiles + $finalfiles == 0;
    }

    /**
     * Display the list of files  in the submission status table
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        return $this->view($submission);
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view($submission) {
        $return = $this->assignment->render_area_files(
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_DRAFT_FILEAREA,
            $submission->id
        );
        $return .= $this->assignment->render_area_files(
            'assignsubmission_ltisubmissions',
            ASSIGNSUBMISSION_LTISUBMISSIONS_FINAL_FILEAREA,
            $submission->id
        );
        return $return;
    }
}
