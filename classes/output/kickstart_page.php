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

namespace format_kickstart\output;
use format_kickstart\output\kickstartHandler;
use html_writer;

trait kickstart_page {
    public function get_coursetemplate_content() {
        global $OUTPUT, $USER, $PAGE, $DB;
        $course = get_course($this->courseid);
        $content = '';
        if (has_capability('format/kickstart:import_from_template', $this->context)) {
            format_kickstart_check_format_template();
            $output = $PAGE->get_renderer('format_kickstart');
            $templateview = $DB->get_field('course_format_options', 'value', ['name' => 'templatesview', 'courseid' => $this->courseid]);
            $content .= $OUTPUT->render_from_template('format_kickstart/course_template_header', ['managetemplateurl' =>
                new \moodle_url('/course/format/kickstart/templates.php'), 'canmanage' =>
                has_capability('format/kickstart:manage_templates', \context_system::instance()),
                'listview' => ($templateview == 'list') ? true : false,
            ]);
            $content .= $output->render(new course_template_list($course, $USER->id, $this->params));
        }
        return $content;
    }

    public function get_studentview_content() {
        global $OUTPUT;
        $content = \html_writer::start_div('summary-view-block');
        if ($this->course->format == 'kickstart') {
            $course = course_get_format($this->course)->get_course();
            $content .= format_text($course->userinstructions['text'], $course->userinstructions['format']);
            $content .= \html_writer::end_div();
            return $content;
        }

        $content .= $OUTPUT->notification(get_string('studentviewnotavailable', 'format_kickstart'), 'warning');
        $content .= \html_writer::end_div();
        return $content;
    }

    public function get_help_content() {
        global $OUTPUT;
        echo "help content";
    }
}