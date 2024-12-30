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
 * AMD module for the lti form in assignsubmission_ltisubmissions page.
 *
 * @module     assignsubmission_ltisubmissions/ltiform
 * @copyright  2023 Moodle India
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import { get_string as getString } from 'core/str';
export const init = (hideoptions) => {
    // var skipClientValidation = false;
    $(document).on('click', '#id_assignsubmission_ltisubmissions_enabled', function () {
        var ltisubmissionname = $(this).attr('name');
        if ($(this).is(':checked')) {
            $('#fgroup_id_submissionplugins').find(':checkbox').each(function () {
                var elementname = $(this).attr('name');
                if (ltisubmissionname != elementname &&
                    elementname.startsWith('assignsubmission_') && elementname.endsWith('_enabled')) {
                    if ($(this).is(':checked')) {
                        $(this).trigger('click');
                    }
                    $(this).attr('disabled', true);
                }
            });
        } else {
            $('#fgroup_id_submissionplugins').find(':checkbox').each(function () {
                var elementname = $(this).attr('name');
                if (ltisubmissionname != elementname && elementname.startsWith('assignsubmission_')
                    && elementname.endsWith('_enabled')) {
                    $(this).attr('disabled', false);
                }
            });
        }
    });
    $(document).ready(function () {
        if ($('#id_assignsubmission_ltisubmissions_enabled').is(':checked')) {
            if (hideoptions) {// cannot edit if the activity is once defined as of type ltisubmission
                $('#fgroup_id_submissionplugins').hide();
            }
            var ltisubmissionname = $('#id_assignsubmission_ltisubmissions_enabled').attr('name');
            $('#fgroup_id_submissionplugins').find(':checkbox').each(function () {
                var elementname = $(this).attr('name');
                if (ltisubmissionname != elementname && elementname.startsWith('assignsubmission_')
                    && elementname.endsWith('_enabled')) {
                    if ($(this).is(':checked')) {
                        $(this).trigger('click');
                    }
                    $(this).attr('disabled', true);
                }
            });
        }
    });
    $(document).find("form[action='modedit.php']").on("submit", function (e) {
        if ($(e.originalEvent.submitter).data('skipValidation') == 1) {
            return true;
        }
        var formData = $(this).serializeArray();
        var data = [];
        $.each(formData, function (index, field) {
            data[field.name] = field.value;
        });
        if (data.assignsubmission_ltisubmissions_enabled == 1) {
            var scrollindex = '';
            var expandheader = false;
            if (typeof (data.maxattempts) !== 'undefined' && data.maxattempts != -1) {
                $('#id_maxattempts').addClass('is-invalid');
                getString('maxattemptserror', 'assignsubmission_ltisubmissions').then(function (error) {
                    $('#id_error_maxattempts').html(error);
                }.bind(this));
                scrollindex = 'id_maxattempts';
                expandheader = true;
            } else if ($('#id_maxattempts').hasClass('is-invalid')) {
                $('#id_maxattempts').removeClass('is-invalid');
                $('#id_error_maxattempts').html('');
            }
            if (typeof (data.attemptreopenmethod) !== 'undefined' &&
            ['automatic', 'untilpass'].indexOf(data.attemptreopenmethod) === -1) {
                $('#id_attemptreopenmethod').addClass('is-invalid');
                getString('attemptreopenmethoderror', 'assignsubmission_ltisubmissions').then(function (error) {
                    $('#id_error_attemptreopenmethod').html(error);
                }.bind(this));
                scrollindex = 'id_attemptreopenmethod';
                expandheader = true;
            } else if ($('#id_attemptreopenmethod').hasClass('is-invalid')) {
                $('#id_attemptreopenmethod').removeClass('is-invalid');
                $('#id_error_attemptreopenmethod').html('');
            }
            if (typeof (data.requiresubmissionstatement) !== 'undefined' && data.requiresubmissionstatement != 0) {
                $('#id_requiresubmissionstatement').addClass('is-invalid');
                getString('requiresubmissionstatementerror', 'assignsubmission_ltisubmissions').then(function (error) {
                    $('#id_error_requiresubmissionstatement').html(error);
                }.bind(this));
                scrollindex = 'id_requiresubmissionstatement';
                expandheader = true;
            } else if ($('#id_requiresubmissionstatement').hasClass('is-invalid')) {
                $('#id_requiresubmissionstatement').removeClass('is-invalid');
                $('#id_error_requiresubmissionstatement').html('');
            }
            if (typeof (data.submissiondrafts) !== 'undefined' && data.submissiondrafts != 0) {
                $('#id_submissiondrafts').addClass('is-invalid');
                getString('submissiondraftserror', 'assignsubmission_ltisubmissions').then(function (error) {
                    $('#id_error_submissiondrafts').html(error);
                }.bind(this));
                scrollindex = 'id_submissiondrafts';
                expandheader = true;
            } else if ($('#id_submissiondrafts').hasClass('is-invalid')) {
                $('#id_submissiondrafts').removeClass('is-invalid');
                $('#id_error_submissiondrafts').html('');
            }
            if (data.typeid == 0) {
                $('#id_typeid').addClass('is-invalid');
                getString('invalidtypeid', 'assignsubmission_ltisubmissions').then(function (error) {
                    $('#id_error_typeid').html(error);
                }.bind(this));
                scrollindex = 'id_typeid';
            } else if ($('#id_typeid').hasClass('is-invalid')) {
                $('#id_typeid').removeClass('is-invalid');
                $('#id_error_typeid').html('');
            }
            if (scrollindex != '') {
                if (expandheader && $('#id_submissionsettings [data-toggle="collapse"]').attr('aria-expanded') == 'false') {
                    $('#id_submissionsettings [data-toggle="collapse"]').trigger('click');
                }
                document.getElementById(scrollindex).scrollIntoView();
                return false;
            } else {
                return true;
            }
        }
    });
};
