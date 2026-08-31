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

use core\url as moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG, $DB;

require_once("$CFG->dirroot/course/format/kickstart/lib.php");
require_once("$CFG->dirroot/backup/util/includes/backup_includes.php");

if ($ADMIN->fulltree) {
    if (format_kickstart_has_pro()) {
        require_once($CFG->dirroot . "/local/kickstart_pro/lib.php");
        $settings->add(new admin_setting_configcheckbox(
            'format_kickstart/coursecreatorredirect',
            new lang_string('coursecreatorredirect', 'format_kickstart'),
            new lang_string('coursecreatorredirect_desc', 'format_kickstart'),
            0
        ));

        $settings->add(new admin_setting_confightmleditor(
            'format_kickstart/coursecreatorinstructions',
            new lang_string('coursecreatorinstructions', 'format_kickstart'),
            new lang_string('coursecreatorinstructions_desc', 'format_kickstart'),
            new lang_string('coursecreatorinstructions_default', 'format_kickstart')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'format_kickstart/automatictemplate',
            new lang_string('automatictemplate', 'format_kickstart'),
            new lang_string('automatictemplate_desc', 'format_kickstart'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'format_kickstart/autotemplateoncreation',
            new lang_string('autotemplateoncreation', 'format_kickstart'),
            new lang_string('autotemplateoncreation_desc', 'format_kickstart'),
            0
        ));

        // Course custom field used to select the template to automatically apply.
        // Only locked and hidden select/text fields qualify - the field controls
        // course content, so ordinary users must not be able to set or see it.
        $autotemplatefields = ['' => new lang_string('none')];
        $customfields = $DB->get_records_sql("
            SELECT f.id, f.name, f.shortname, f.type, f.configdata
              FROM {customfield_field} f
              JOIN {customfield_category} c ON c.id = f.categoryid
             WHERE c.component = 'core_course' AND c.area = 'course'
          ORDER BY f.name ASC");
        foreach ($customfields as $customfield) {
            if (!in_array($customfield->type, ['select', 'text'])) {
                continue;
            }
            $configdata = json_decode($customfield->configdata, true) ?: [];
            if (empty($configdata['locked']) || !empty($configdata['visibility'])) {
                continue;
            }
            $autotemplatefields[$customfield->shortname] = format_string($customfield->name) .
                ' (' . $customfield->shortname . ')';
        }
        $settings->add(new admin_setting_configselect(
            'format_kickstart/autotemplatecustomfield',
            new lang_string('autotemplatecustomfield', 'format_kickstart'),
            new lang_string('autotemplatecustomfield_desc', 'format_kickstart'),
            '',
            $autotemplatefields
        ));
        $templatebgoptions = ['maxfiles' => 10, 'subdirs' => 0, 'accepted_types' => ['.jpg', '.png']];
        $settings->add(new admin_setting_configstoredfile(
            'format_kickstart/templatebackimages',
            new lang_string('default_templatebackground', 'format_kickstart'),
            new lang_string('default_templatebackground_desc', 'format_kickstart'),
            'templatebackimages',
            0,
            $templatebgoptions
        ));

        $settings->add(new admin_setting_configtext(
            'format_kickstart/modtrimlength',
            new lang_string('modtrimlength', 'format_kickstart'),
            new lang_string('modtrimlength_desc', 'format_kickstart'),
            23,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'format_kickstart/courselibraryperpage',
            new lang_string('courselibraryperpage', 'format_kickstart'),
            new lang_string('courselibraryperpage_desc', 'format_kickstart'),
            10,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configcheckbox(
            'format_kickstart/disableactivitydescriptionsearch',
            new lang_string('disableactivitydescriptionsearch', 'format_kickstart'),
            new lang_string('disableactivitydescriptionsearch_desc', 'format_kickstart'),
            0
        ));

        $options = [
            "fullname" => new lang_string('course_fullname', 'format_kickstart'),
            "categorypath" => new lang_string('categorypath', 'format_kickstart'),
            "tags" => new lang_string('coursetags', 'format_kickstart'),
            "idnumber" => new lang_string('courseidnumber', 'format_kickstart'),
            "startdate" => new lang_string('coursestartdate', 'format_kickstart'),
            "importcourse" => new lang_string('importcourse', 'format_kickstart'),
            "showcontents" => new lang_string('showcontents', 'format_kickstart'),
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

        $settings->add(new admin_setting_configmulticheckbox(
            'format_kickstart/displaycourselibraryfields',
            new lang_string('displaycourselibraryfields', 'format_kickstart'),
            new lang_string('displaycourselibraryfields_desc', 'format_kickstart'),
            $defaultoptions,
            $options
        ));

        if (!empty($customfields)) {
            $settings->add(new admin_setting_configmultiselect(
                'format_kickstart/courselibraryfilterscf',
                new lang_string('courselibraryfilterscf', 'format_kickstart'),
                new lang_string('courselibraryfilterscf_desc', 'format_kickstart'),
                [],
                $customfields
            ));
        }

        $settings->add(new admin_setting_configselect(
            'format_kickstart/importtarget',
            new lang_string('importtarget', 'format_kickstart'),
            new lang_string('importtarget_desc', 'format_kickstart'),
            \backup::TARGET_EXISTING_DELETING,
            [
                \backup::TARGET_EXISTING_DELETING => new lang_string('restoretoexistingcoursedeleting', 'format_kickstart'),
                \backup::TARGET_EXISTING_ADDING => new lang_string('restoretoexistingcourseadding', 'format_kickstart'),
            ]
        ));
    }

    $settings->add(new admin_setting_configselect(
        'format_kickstart/defaulttemplatesview',
        new lang_string('defaulttemplatesview', 'format_kickstart'),
        new lang_string('defaulttemplatesview_desc', 'format_kickstart'),
        'tile',
        [
            'tile' => new lang_string('strtile', 'format_kickstart'),
            'list' => new lang_string('strlist', 'format_kickstart'),
        ]
    ));

    $settings->add(new admin_setting_confightmleditor(
        'format_kickstart/defaultuserinstructions',
        new lang_string('defaultuserinstructions', 'format_kickstart'),
        new lang_string('defaultuserinstructions_desc', 'format_kickstart'),
        new lang_string('defaultuserinstructions_default', 'format_kickstart')
    ));

    $settings->add(new admin_setting_confightmleditor(
        'format_kickstart/defaultteacherinstructions',
        new lang_string('defaultteacherinstructions', 'format_kickstart'),
        new lang_string('defaultteacherinstructions_desc', 'format_kickstart'),
        new lang_string('defaultteacherinstructions_default', 'format_kickstart')
    ));

    // Order and visibility of the pages in the Kickstart navigation dropdown.
    // The page with the lowest order is the default; an order of 0 hides the page.
    $settings->add(new admin_setting_heading(
        'pageordersettings',
        new lang_string('pageordersettings', 'format_kickstart'),
        new lang_string('pageordersettings_desc', 'format_kickstart')
    ));

    $defaultpageorders = [
        'coursetemplate' => 1,
        'studentview' => 2,
        'help' => 3,
        'courselibrary' => 4,
        'createtemplaefromcourse' => 5,
    ];
    $pages = format_kickstart_get_all_pages();
    // Build the position dropdown: "Hide" for 0, then one entry per available page.
    $pageorderoptions = [0 => new lang_string('hide', 'format_kickstart')];
    for ($position = 1; $position <= count($pages); $position++) {
        $pageorderoptions[$position] = $position;
    }
    foreach ($pages as $pagekey => $pagelabel) {
        $settings->add(new admin_setting_configselect(
            'format_kickstart/pageorder_' . $pagekey,
            new lang_string('pageorder', 'format_kickstart', $pagelabel),
            new lang_string('pageorder_desc', 'format_kickstart', $pagelabel),
            $defaultpageorders[$pagekey] ?? 0,
            $pageorderoptions
        ));
    }

    if (format_kickstart_has_pro()) {
        $options = [
            0 => new lang_string('no'),
            1 => new lang_string('yes'),
            2 => new lang_string('usedefault', 'format_kickstart'),
        ];

        $settings->add(new admin_setting_heading(
            'courselibsortsettings',
            new lang_string('courselibsortsettings', 'format_kickstart'),
            new lang_string('courselibsortsettings_help', 'format_kickstart'),
        ));


        // Settings for the course library sort weight.
        // Add relevance weight settings.
        $settings->add(new admin_setting_configtext(
            'format_kickstart/weight_fullname',
            new lang_string('weight_fullname', 'format_kickstart'),
            new lang_string('weight_fullname_desc', 'format_kickstart'),
            5,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'format_kickstart/weight_shortname',
            new lang_string('weight_shortname', 'format_kickstart'),
            new lang_string('weight_shortname_desc', 'format_kickstart'),
            5,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'format_kickstart/weight_tags',
            new lang_string('weight_tags', 'format_kickstart'),
            new lang_string('weight_tags_desc', 'format_kickstart'),
            5,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'format_kickstart/weight_starred',
            new lang_string('weight_starred', 'format_kickstart'),
            new lang_string('weight_starred_desc', 'format_kickstart'),
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
                        new lang_string('weight_customfield', 'format_kickstart', $fieldname),
                        new lang_string('weight_customfield_desc', 'format_kickstart', $fieldname),
                        5, // Default value.
                        PARAM_INT
                    ));
                }
            }
        }

        $settings->add(new admin_setting_heading(
            'restoresettings',
            new lang_string('generalrestoresettings', 'backup'),
            new lang_string('usedefault_help', 'format_kickstart'),
        ));

        $settings->add(new admin_setting_configselect(
            'format_kickstart/restore_general_users',
            new lang_string('generalusers', 'backup'),
            new lang_string('configrestoreusers', 'backup'),
            0,
            $options
        ));

        $settings->add(new admin_setting_configselect(
            'format_kickstart/restore_replace_keep_roles_and_enrolments',
            new lang_string('setting_keep_roles_and_enrolments', 'backup'),
            new lang_string('config_keep_roles_and_enrolments', 'backup'),
            0,
            $options
        ));

        $settings->add(new admin_setting_configselect(
            'format_kickstart/restore_replace_keep_groups_and_groupings',
            new lang_string('setting_keep_groups_and_groupings', 'backup'),
            new lang_string('config_keep_groups_and_groupings', 'backup'),
            0,
            $options
        ));
    }
}
$settings->visiblename = new lang_string('general_settings', 'format_kickstart');
$ADMIN->add('formatsettings', new admin_category('format_kickstart', new lang_string('pluginname', 'format_kickstart')));

$ADMIN->add('format_kickstart', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('courses', new admin_externalpage(
    'kickstarttemplates',
    new lang_string('course_templates', 'format_kickstart'),
    new moodle_url('/course/format/kickstart/templates.php'),
    'format/kickstart:manage_templates'
));

$ADMIN->add('format_kickstart', new admin_externalpage(
    'managetemplates',
    new lang_string('manage_templates', 'format_kickstart'),
    new moodle_url('/course/format/kickstart/templates.php')
));
