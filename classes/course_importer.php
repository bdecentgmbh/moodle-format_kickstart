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
 * @package    format_kickstart
 * @copyright  2018 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart;

class course_importer
{
    public static function import($template_id, $course_id)
    {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot.'/course/format/kickstart/lib.php');
        require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');

        $course = $DB->get_record('course', ['id' => $course_id]);

        $template = $DB->get_record('kickstart_template', ['id' => $template_id], '*', MUST_EXIST);

        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'format_kickstart', 'course_backups', $template->id, '', false);
        $files = array_values($files);

        if (!isset($files[0])) {
            throw new \moodle_exception('coursebackupnotset', 'format_kickstart');
        }

        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course';
        $files[0]->extract_to_pathname($fp, $filepath);

        // Now restore the course.
        $rc = new \restore_controller('test-restore-course', $course_id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_EXISTING_DELETING);
        $rc->get_plan()->get_setting('overwrite_conf')->set_value(true);
        $rc->get_plan()->get_setting('users')->set_value(false);
        $rc->get_plan()->get_setting('course_shortname')->set_value($course->shortname);
        $rc->get_plan()->get_setting('course_fullname')->set_value($course->fullname);
        $rc->get_plan()->get_setting('course_startdate')->set_value($course->startdate);
        $rc->get_plan()->get_setting('keep_roles_and_enrolments')->set_value(false);
        $rc->get_plan()->get_setting('keep_groups_and_groupings')->set_value(false);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Reset some settings.
        $summary = $course->summary;
        $summaryformat = $course->summaryformat;
        $enddate = $course->enddate;
        $timecreated = $course->timecreated;
        // Reload course.
        $course = $DB->get_record('course', ['id' => $course_id]);
        $course->summary = $summary;
        $course->summaryformat = $summaryformat;
        $course->enddate = $enddate;
        $course->timecreated = $timecreated;
        $DB->update_record('course', $course);
    }
}