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
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_kickstart\output\course_template_list;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');



$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$output = $PAGE->get_renderer('format_kickstart');

$PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'init',
    array('contextid' => $context->id, 'courseid' => $course->id));

if (has_capability('format/kickstart:import_from_template', $context)) {
    // Check the template add or remove.
    format_kickstart_check_format_template();
    echo $output->render(new course_template_list($course, $USER->id));
}
if (format_kickstart_has_pro()) {
    if (has_capability('local/kickstart_pro:import_other_courses', $context)) {
        echo \html_writer::empty_tag('hr');
        echo $output->render(new \format_kickstart\output\import_course_list());
    }
}

if (!has_capability('format/kickstart:import_from_template', $context)) {
    if (format_kickstart_has_pro() && !has_capability('local/kickstart_pro:import_other_courses', $context)) {
        $prorenderer = $PAGE->get_renderer('local_kickstart_pro');
        echo $prorenderer->render(new \local_kickstart_pro\output\default_view($course));
    } else {
        echo format_text($course->userinstructions['text'], $course->userinstructions['format']);
    }
}

