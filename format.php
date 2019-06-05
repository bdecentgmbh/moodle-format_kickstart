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
 * Kickstart course format.
 *
 * @package    format_kickstart
 * @copyright  2018 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$output = $PAGE->get_renderer('format_kickstart');

if ($PAGE->user_allowed_editing()) {
    $instructions = format_text($course->userinstructions['text'], $course->userinstructions['format']);

    echo $instructions;
    echo \html_writer::empty_tag('hr');
    echo $output->render(new \format_kickstart\output\course_template_list());
    if (has_capability('moodle/restore:restoretargetimport', $context)) {
        echo \html_writer::empty_tag('hr');
        echo $output->render(new \format_kickstart\output\import_course_list());
    }
} else {
    echo get_string('studentinstructions', 'format_kickstart');
}