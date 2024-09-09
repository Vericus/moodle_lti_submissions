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
 * Upgrade database for assignsubmission_ltisubmissions.
 *
 * @package    assignsubmission_ltisubmissions
 * @copyright  2024 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade assignsubmission_ltisubmissions.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_assignsubmission_ltisubmissions_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024090900) {
        // Rename assignsubmission_ltisub.
        $table = new xmldb_table('assignsubmission_ltisub');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'assignsubmission_ltisubmissions');
        }

        // Rename assignsubmission_lti_log.
        $table = new xmldb_table('assignsubmission_lti_log');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'assignsubmission_ltisubmissions_log');
        }

        upgrade_plugin_savepoint(true, 2024090900, 'assignsubmission', 'ltisubmissions');
    }

    return true;
}
