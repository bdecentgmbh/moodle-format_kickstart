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
        $templatebgoptions = ['maxfiles' => 10, 'subdirs' => 0, 'accepted_types' => ['.jpg', '.png']];
        $settings->add(new admin_setting_configstoredfile(
            'format_kickstart/templatebackimages',
            get_string('default_templatebackground', 'format_kickstart'),
            get_string('default_templatebackground_desc', 'format_kickstart'),
            'templatebackimages',
            0,
            $templatebgoptions
        ));

        $settings->add(new admin_setting_configtext('format_kickstart/modtrimlength',
                get_string('modtrimlength', 'format_kickstart'),
                get_string('modtrimlength_desc', 'format_kickstart'),
                23, PARAM_INT));

        $settings->add(new admin_setting_configtext('format_kickstart/courselibraryperpage',
        get_string('courselibraryperpage', 'format_kickstart'),
        get_string('courselibraryperpage_desc', 'format_kickstart'),
        10, PARAM_INT));

        $options = [
            "fullname" => get_string('course_fullname', 'format_kickstart'),
            "categorypath" => get_string('categorypath', 'format_kickstart'),
            "tags" => get_string('coursetags', 'format_kickstart'),
            "idnumber" => get_string('courseidnumber', 'format_kickstart'),
            "startdate" => get_string('coursestartdate', 'format_kickstart'),
            "importcourse" => get_string('importcourse', 'format_kickstart'),
            "showcontents" => get_string('showcontents', 'format_kickstart'),
        ];
        $customfields = [];
        if (class_exists('\core_course\customfield\course_handler')) {
            $handler = \core_course\customfield\course_handler::create();
            $fields = $handler->get_fields();
            foreach ($fields as $field) {
                $options["customfield_{$field->get('shortname')}"] = $field->get('name');
                if ($field->get('type') == 'select' || $field->get('type') == 'text') {
                    $customfields["customfield_{$field->get('shortname')}"] = $field->get('name');
                }
            }
        }

        $defaultoptions = [
            "fullname" => 1,
            "categorypath" => 1,
            "importcourse" => 1,
            "showcontents" => 1,
        ];

        $settings->add(new admin_setting_configmulticheckbox('format_kickstart/displaycourselibraryfields',
                get_string('displaycourselibraryfields', 'format_kickstart'),
                get_string('displaycourselibraryfields_desc', 'format_kickstart'),
                $defaultoptions, $options));

        $settings->add(new admin_setting_configmultiselect('format_kickstart/courselibraryfilterscf',
        get_string('courselibraryfilterscf', 'format_kickstart'),
        get_string('courselibraryfilterscf_desc', 'format_kickstart'),
        $defaultoptions, $customfields));
    }

    $settings->add(new admin_setting_configselect('format_kickstart/importtarget',
        get_string('importtarget', 'format_kickstart'),
        get_string('importtarget_desc', 'format_kickstart'),
        \backup::TARGET_EXISTING_DELETING, [
            \backup::TARGET_EXISTING_DELETING => get_string('restoretoexistingcoursedeleting', 'format_kickstart'),
            \backup::TARGET_EXISTING_ADDING => get_string('restoretoexistingcourseadding', 'format_kickstart'),
        ]));

    $settings->add(new admin_setting_configselect('format_kickstart/defaulttemplatesview',
        get_string('defaulttemplatesview', 'format_kickstart'),
        get_string('defaulttemplatesview_desc', 'format_kickstart'),
        'tile', [
            'tile' => get_string('strtile', 'format_kickstart'),
            'list' => get_string('strlist', 'format_kickstart'),
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
        2 => get_string('usedefault', 'format_kickstart'),
    ];

    $settings->add(new admin_setting_heading(
        'courselibsortsettings',
        get_string('courselibsortsettings', 'format_kickstart'),
        get_string('courselibsortsettings_help', 'format_kickstart'),
    ));


    // Settings for the course library sort weight.
    // Add relevance weight settings.
    $settings->add(new admin_setting_configtext(
        'format_kickstart/weight_fullname',
        get_string('weight_fullname', 'format_kickstart'),
        get_string('weight_fullname_desc', 'format_kickstart'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_kickstart/weight_shortname',
        get_string('weight_shortname', 'format_kickstart'),
        get_string('weight_shortname_desc', 'format_kickstart'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_kickstart/weight_tags',
        get_string('weight_tags', 'format_kickstart'),
        get_string('weight_tags_desc', 'format_kickstart'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'format_kickstart/weight_starred',
        get_string('weight_starred', 'format_kickstart'),
        get_string('weight_starred_desc', 'format_kickstart'),
        5,
        PARAM_INT
    ));


    if (class_exists('\core_course\customfield\course_handler')) {
        $handler = \core_course\customfield\course_handler::create();
        $fields = $handler->get_fields();
        foreach ($fields as $field) {
            if ($field->get('type') == 'text' || $field->get('type') == 'select') {
                $fieldname = $field->get('name');
                $fieldshortname = $field->get('shortname');
                $settings->add(new admin_setting_configtext(
                    'format_kickstart/weight_customfield_' . $fieldshortname,
                    get_string('weight_customfield', 'format_kickstart', $fieldname),
                    get_string('weight_customfield_desc', 'format_kickstart', $fieldname),
                    5, // Default value.
                    PARAM_INT
                ));
            }
        }
    }

    $settings->add(new admin_setting_heading(
        'restoresettings',
        get_string('generalrestoresettings', 'backup'),
        get_string('usedefault_help', 'format_kickstart'),
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

