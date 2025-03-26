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

$nav = optional_param('nav', 'coursetemplate', PARAM_TEXT);

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$output = $PAGE->get_renderer('format_kickstart');

$pageurl = new moodle_url($PAGE->url, ['nav' => $nav]);

$PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'init',
    ['contextid' => $context->id, 'courseid' => $course->id, 'menuid' => $nav, 'filteroptions' => true]);

// Print header.
$actionbar = new format_kickstart\output\general_action_bar($context, $pageurl, 'kickstart', 'coursetemplate');

$renderer = $PAGE->get_renderer('format_kickstart');

echo html_writer::start_div('kickstart-page');

if (has_capability('format/kickstart:import_from_template', $context)) {
    echo $renderer->render_action_bar($actionbar);
} else {
    $nav = 'studentview';
}


if (file_exists($CFG->dirroot . '/local/kickstart_pro/classes/output/kickstartProHandler.php')) {
    require_once($CFG->dirroot . '/local/kickstart_pro/classes/output/kickstartProHandler.php');
    $kickstartpage = new \local_kickstart_pro\output\kickstartHandlerWithPropage($course, $nav);
} else {
    $kickstartpage = new format_kickstart\output\kickstartHandler($course, $nav);
}

echo $renderer->render_kickstart_page($kickstartpage);

echo html_writer::end_div();

