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

namespace format_kickstart\output;

use renderer_base;

class import_course_list implements \templatable, \renderable
{
    public function export_for_template(renderer_base $output)
    {
        global $CFG, $COURSE, $PAGE, $OUTPUT;

        // Require both the backup and restore libs
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');


        // Prepare the backup renderer
        $renderer = $PAGE->get_renderer('core','backup');

        // Obviously not... show the selector so one can be chosen
        $url = new \moodle_url('/backup/import.php', array('id'=>$COURSE->id));
        $component = new \import_course_search(array('url'=>$url));

        $courses = [];

        $url = $component->get_url();

        $html = '';

        if ($component->get_count() === 0) {
            $html .= $OUTPUT->notification(get_string('nomatchingcourses', 'backup'));
        } else {
            foreach ($component->get_results() as $course) {
                $course->url = new \moodle_url('/course/view.php', ['id' => $course->id]);
                $course->fullname = format_string($course->fullname, true, array('context' => \context_course::instance($course->id)));
                $course->importurl = new \moodle_url('/backup/import.php', ['id' => $COURSE->id, 'target' => $course->id, 'importid' => $COURSE->id]);;
                $courses[] = $course;
            }
            if ($component->has_more_results()) {
                $html .= $OUTPUT->notification(get_string('moreresults', 'backup'), 'info');
            }
        }

        return [
            'searchurl' => $PAGE->url,
            'html' => $html,
            'courses' => $courses,
            'haspro' => format_kickstart_has_pro(),
            'coursecount' => $component->get_count(),
            'coursecountlabel' => $component->get_count() == 1 ? get_string('course') : get_string('courses'),
            'prourl' => 'https://bdecent.de/products/moodle-plugins/kickstart-course-wizard-pro/'
        ];
    }
}