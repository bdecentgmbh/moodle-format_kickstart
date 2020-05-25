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
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('format_kickstart/automatictemplate',
        get_string('automatictemplate', 'format_kickstart'),
        get_string('automatictemplate_desc', 'format_kickstart'),
        1));
    $settings->add(new admin_setting_confightmleditor('format_kickstart/defaultuserinstructions',
        get_string('defaultuserinstructions', 'format_kickstart'),
        get_string('defaultuserinstructions_desc', 'format_kickstart'),
        get_string('defaultuserinstructions_default', 'format_kickstart')));

    $settings->add(new admin_setting_confightmleditor('format_kickstart/defaultteacherinstructions',
        get_string('defaultteacherinstructions', 'format_kickstart'),
        get_string('defaultteacherinstructions_desc', 'format_kickstart'),
        get_string('defaultteacherinstructions_default', 'format_kickstart')));
}
$settings->visiblename = get_string('general_settings', 'format_kickstart');
$ADMIN->add('formatsettings', new admin_category('format_kickstart', get_string('pluginname', 'format_kickstart')));

$ADMIN->add('format_kickstart', $settings);
// Tell core we already added the settings structure.
$settings = null;

$ADMIN->add('format_kickstart', new admin_externalpage('kickstarttemplates', get_string('manage_templates', 'format_kickstart'),
    new moodle_url('/course/format/kickstart/templates.php')));

$ADMIN->add('courses', new admin_externalpage('kickstartcreatecourse', get_string('createcoursefromtemplate', 'format_kickstart'),
    new moodle_url('/course/format/kickstart/createcourse.php'), 'moodle/course:create'));