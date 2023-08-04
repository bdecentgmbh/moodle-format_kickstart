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
 * Import course mbz into existing course.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart;

defined('MOODLE_INTERNAL') || die();

use stdClass;

require_once($CFG->dirroot.'/course/format/kickstart/lib.php');
require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');

/**
 * Import course mbz into existing course.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_kickstart
 */
class course_importer {

    /**
     * Import template into course.
     *
     * @param int $templateid
     * @param int $courseid
     * @throws \base_plan_exception
     * @throws \base_setting_exception
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public static function import_from_template($templateid, $courseid) {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot."/course/lib.php");
        $PAGE->set_context(\context_course::instance($courseid));
        $template = $DB->get_record('format_kickstart_template', ['id' => $templateid], '*', MUST_EXIST);

        if (!$template->courseformat) {
            $fs = get_file_storage();
            $files = $fs->get_area_files(\context_system::instance()->id, 'format_kickstart', 'course_backups',
                $template->id, '', false);
            $files = array_values($files);

            if (!isset($files[0])) {
                throw new \moodle_exception('coursebackupnotset', 'format_kickstart');
            }

            $fp = get_file_packer('application/vnd.moodle.backup');
            $backuptempdir = make_backup_temp_directory('template' . $templateid);
            $files[0]->extract_to_pathname($fp, $backuptempdir);

            self::import('template' . $templateid, $courseid);
        } else {
            $course = (array) $DB->get_record('course', array('id' => $courseid));
            $course['format'] = $template->format;
            // Get format opitions.
            $params['format'] = $template->format;
            $params['id'] = '1';
            $formatoptions = format_kickstart_get_template_format_options($template);
            // Check the coursetype exist or not If not set the designer type.
            if ($template->format == 'designer') {
                if (empty($formatoptions) || !isset($formatoptions['coursetype'])) {
                    require_once($CFG->dirroot."/course/format/designer/lib.php");
                    $coursetypes = format_kickstart_get_designer_coursetypes();
                    $coursetype = array_search($template->title, $coursetypes);
                    $formatoptions['coursetype'] = $coursetype;
                    $data = new stdClass();
                    $data->templateid = $template->id;
                    $data->displayname = $template->title;
                    $data->format = $template->format;
                    $data->name = 'coursetype';
                    $data->value = $coursetype;
                    $DB->insert_record('format_kickstart_options', $data);
                }
            }
            $data = array_merge($course, $formatoptions);
            update_course((object)$data);
        }
    }

    /**
     * Import course from backup directory.
     *
     * @param string $backuptempdir
     * @param int $courseid
     * @throws \base_plan_exception
     * @throws \base_setting_exception
     * @throws \dml_exception
     * @throws \restore_controller_exception
     */
    public static function import($backuptempdir, $courseid) {
        global $USER, $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        $details = \backup_general_helper::get_backup_information($backuptempdir);
        $settings = [
            'overwrite_conf' => true,
            'course_shortname' => $course->shortname,
            'course_fullname' => $course->fullname,
            'course_startdate' => $course->startdate,
        ];

        if (get_config('format_kickstart', 'restore_general_users') < 2) {
            if (isset($details->root_settings['users']) && $details->root_settings['users']) {
                $settings['users'] = (bool) get_config('format_kickstart', 'restore_general_users');
            } else {
                $settings['users'] = \backup::ENROL_NEVER;
            }
        }

        if (get_config('format_kickstart', 'restore_replace_keep_roles_and_enrolments') < 2) {
            $settings['role_assignments'] =
                (bool)get_config('format_kickstart', 'restore_replace_keep_roles_and_enrolments');
            $settings['enrolments'] = (bool) (get_config('format_kickstart', 'restore_replace_keep_roles_and_enrolments') == 1)
                ? \backup::ENROL_ALWAYS : \backup::ENROL_NEVER;
        }

        if (get_config('format_kickstart', 'restore_replace_keep_groups_and_groupings') < 2) {
            $settings['groups'] =
                (bool)get_config('format_kickstart', 'restore_replace_keep_groups_and_groupings');
        }

        try {
            // Now restore the course.
            $target = get_config('format_kickstart', 'importtarget') ?: \backup::TARGET_EXISTING_DELETING;
            $rc = new \restore_controller($backuptempdir, $course->id, \backup::INTERACTIVE_NO,
                \backup::MODE_GENERAL, $USER->id, $target);

            foreach ($settings as $settingname => $value) {
                $setting = $rc->get_plan()->get_setting($settingname);
                if ($setting->get_status() == \base_setting::LOCKED_BY_PERMISSION) {
                    $setting->set_status(\base_setting::NOT_LOCKED);
                }
                $setting->set_value($value);
            }
            $rc->execute_precheck();
            $rc->execute_plan();
            $rc->destroy();
        } catch (\Exception $e) {
            if ($rc) {
                \core\notification::error('Restore failed with status: ' . $rc->get_status());
            }
            throw $e;
        } finally {
            // Reset some settings.
            $summary = $course->summary;
            $summaryformat = $course->summaryformat;
            $enddate = $course->enddate;
            $timecreated = $course->timecreated;
            // Reload course.
            $course = $DB->get_record('course', ['id' => $courseid]);
            $course->summary = $summary;
            $course->summaryformat = $summaryformat;
            $course->enddate = $enddate;
            $course->timecreated = $timecreated;
            $DB->update_record('course', $course);
        }
    }
}
