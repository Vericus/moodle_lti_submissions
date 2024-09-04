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
 * This file contains a class definition for the LineItem resource
 *
 * @package     assignsubmission_ltisubmissions
 * @copyright   2023 Moodle India {@link https://moodle.com/in/}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_ltisubmissions\service\gradebookservices\local\resources;

use assignsubmission_ltisubmissions\service\gradebookservices\local\service\gradebookservices;
use assignsubmission_ltisubmissions\resource_base;


/**
 * A resource implementing LineItem.
 *
 * @package    assignsubmission_ltisubmissions
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineitem extends \ltiservice_gradebookservices\local\resources\lineitem {
    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     *
     * @return string
     */
    public function parse_value($value) {
        global $COURSE, $CFG;
        if (strpos($value, '$LineItem.url') !== false) {
            $resolved = '';
            require_once($CFG->libdir . '/gradelib.php');

            $this->params['context_id'] = $COURSE->id;
            if ($tool = $this->get_service()->get_type()) {
                $this->params['tool_code'] = $tool->id;
            }
            $id = optional_param('id', 0, PARAM_INT); // Course Module ID.
            if (empty($id)) {
                $hint = optional_param('lti_message_hint', "", PARAM_TEXT);
                if ($hint) {
                    $hintdec = json_decode($hint);
                    if (isset($hintdec->cmid)) {
                        $id = $hintdec->cmid;
                    }
                }
            }
            if (!empty($id)) {
                $cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
                $id = $cm->instance;
                $item = grade_get_grades($COURSE->id, 'mod', 'assign', $id);
                if ($item && $item->items) {
                    $this->params['item_id'] = $item->items[0]->id;
                    $resolved = parent::get_endpoint();
                    $resolved .= "?type_id={$tool->id}";
                }
            }
            $value = str_replace('$LineItem.url', $resolved, $value);
        }
        return $value;
    }
}
