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
 * Settings for format_kickstart
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once("$CFG->dirroot/course/format/kickstart/lib.php");
require_once("$CFG->dirroot/backup/util/includes/backup_includes.php");

if ($ADMIN->fulltree) {
    if (format_kickstart_has_pro()) {
        require_once($CFG->dirroot."/local/kickstart_pro/lib.php");
        $settings->add(new admin_setting_configcheckbox('format_kickstart/coursecreatorredirect',
            get_string('coursecreatorredirect', 'format_kickstart'),
            get_string('coursecreatorredirect_desc', 'format_kickstart'),
            0));

        $settings->add(new admin_setting_confightmleditor('format_kickstart/coursecreatorinstructions',
            get_string('coursecreatorinstructions', 'format_kickstart'),
            get_string('coursecreatorinstructions_desc', 'format_kickstart'),
            get_string('coursecreatorinstructions_default', 'format_kickstart')));

        $settings->add(new admin_setting_configcheckbox('format_kickstart/automatictemplate',
            get_string('automatictemplate', 'format_kickstart'),
            get_string('automatictemplate_desc', 'format_kickstart'),
            1));
        if (function_exists('local_kickstart_pro_get_template_backimages')) {
            $templatebgoptions = array('maxfiles' => 10, 'subdirs' => 0, 'accepted_types' => ['.jpg', '.png']);
            $settings->add(new admin_setting_configstoredfile(
                'format_kickstart/templatebackimages',
                get_string('default_templatebackground', 'format_kickstart'),
                get_string('default_templatebackground_desc', 'format_kickstart'),
                'templatebackimages',
                0,
                $templatebgoptions
            ));
        }
    }

    $settings->add(new admin_setting_configselect('format_kickstart/importtarget',
        get_string('importtarget', 'format_kickstart'),
        get_string('importtarget_desc', 'format_kickstart'),
        \backup::TARGET_EXISTING_DELETING, [
            \backup::TARGET_EXISTING_DELETING => get_string('restoretoexistingcoursedeleting', 'format_kickstart'),
            \backup::TARGET_EXISTING_ADDING => get_string('restoretoexistingcourseadding', 'format_kickstart')
        ]));

    $settings->add(new admin_setting_configselect('format_kickstart/defaulttemplatesview',
        get_string('defaulttemplatesview', 'format_kickstart'),
        get_string('defaulttemplatesview_desc', 'format_kickstart'),
        'tile', [
            'tile' => get_string('strtile', 'format_kickstart'),
            'list' => get_string('strlist', 'format_kickstart')
        ]));

    $settings->add(new admin_setting_confightmleditor('format_kickstart/defaultuserinstructions',
        get_string('defaultuserinstructions', 'format_kickstart'),
        get_string('defaultuserinstructions_desc', 'format_kickstart'),
        get_string('defaultuserinstructions_default', 'format_kickstart')));

    $settings->add(new admin_setting_confightmleditor('format_kickstart/defaultteacherinstructions',
        get_string('defaultteacherinstructions', 'format_kickstart'),
        get_string('defaultteacherinstructions_desc', 'format_kickstart'),
        get_string('defaultteacherinstructions_default', 'format_kickstart')));

    $options = [
        0 => get_string('no'),
        1 => get_string('yes'),
        2 => get_string('usedefault', 'format_kickstart')
    ];

    $settings->add(new admin_setting_heading(
        'restoresettings',
        get_string('generalrestoresettings', 'backup'),
        get_string('usedefault_help', 'format_kickstart')
    ));

    $settings->add(new admin_setting_configselect(
        'format_kickstart/restore_general_users',
        get_string('generalusers', 'backup'),
        get_string('configrestoreusers', 'backup'),
        0,
        $options
    ));

    $settings->add(new admin_setting_configselect(
        'format_kickstart/restore_replace_keep_roles_and_enrolments',
        get_string('setting_keep_roles_and_enrolments', 'backup'),
        get_string('config_keep_roles_and_enrolments', 'backup'),
        0,
        $options
    ));

    $settings->add(new admin_setting_configselect(
        'format_kickstart/restore_replace_keep_groups_and_groupings',
        get_string('setting_keep_groups_and_groupings', 'backup'),
        get_string('config_keep_groups_and_groupings', 'backup'),
        0,
        $options
    ));
}
$settings->visiblename = get_string('general_settings', 'format_kickstart');
$ADMIN->add('formatsettings', new admin_category('format_kickstart', get_string('pluginname', 'format_kickstart')));

$ADMIN->add('format_kickstart', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('courses', new admin_externalpage('kickstarttemplates', get_string('course_templates', 'format_kickstart'),
    new moodle_url('/course/format/kickstart/templates.php'), 'format/kickstart:manage_templates'));

$ADMIN->add('format_kickstart', new admin_externalpage('managetemplates', get_string('manage_templates', 'format_kickstart'),
new moodle_url('/course/format/kickstart/templates.php')));

