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
 * Kickstart page handler.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\output;

use format_kickstart\output\kickstartHandler;
use html_writer;

/**
 * Kickstart page.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    format_kickstart
 */
trait kickstart_page {

    /**
     * Get the course template content.
     * @return string
     */
    public function get_coursetemplate_content() {
        global $OUTPUT, $USER, $PAGE, $DB;
        $course = get_course($this->courseid);
        $content = '';
        if (has_capability('format/kickstart:import_from_template', $this->context)) {
            format_kickstart_check_format_template();
            $output = $PAGE->get_renderer('format_kickstart');
            $templateview = $DB->get_field('course_format_options', 'value',
                ['name' => 'templatesview', 'courseid' => $this->courseid]);
            $content .= $OUTPUT->render_from_template('format_kickstart/course_template_header', ['managetemplateurl' =>
                new \moodle_url('/course/format/kickstart/templates.php'), 'canmanage' =>
                has_capability('format/kickstart:manage_templates', \context_system::instance()),
                'listview' => ($templateview == 'list') ? true : false,
            ]);
            $content .= $output->render(new course_template_list($course, $USER->id, $this->params));
        }
        return $content;
    }


    /**
     * Get the student view content.
     */
    public function get_studentview_content() {
        global $OUTPUT, $PAGE;
        $content = \html_writer::start_div('summary-view-block');
        if ($this->course->format == 'kickstart') {

            if (has_capability('format/kickstart:import_from_template', $this->context)) {
                $content .= $OUTPUT->notification(get_string('teacherstudentview', 'format_kickstart'), 'info');
            }

            if (format_kickstart_has_pro()) {
                $prorenderer = $PAGE->get_renderer('local_kickstart_pro');
                $content .= $prorenderer->render(new \local_kickstart_pro\output\default_view($this->course));
            } else {
                $course = course_get_format($this->course)->get_course();
                $content .= html_writer::tag('h2', get_string('welcometo', 'format_kickstart')
                    . " " . $this->course->fullname, ['class' => '"mt-3']);
                $content .= format_text($course->userinstructions['text'], $course->userinstructions['format']);
                $content .= \html_writer::end_div();
            }
            return $content;
        }

        $content .= $OUTPUT->notification(get_string('studentviewnotavailable', 'format_kickstart'), 'info');
        $content .= \html_writer::end_div();
        return $content;
    }


    /**
     * Get the help content.
     * @return string
     */
    public function get_help_content() {
        global $OUTPUT;
        $content = \html_writer::start_div('summary-view-block');
        $content .= $OUTPUT->render_from_template('format_kickstart/help', null);
        $content .= \html_writer::end_div();
        return $content;
    }
}
