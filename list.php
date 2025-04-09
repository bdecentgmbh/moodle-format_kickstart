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
 * Confirm user template selection.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->dirroot . '/course/format/kickstart/classes/output/general_action_bar.php');

global $USER, $DB;

$id = required_param('id', PARAM_INT);
$nav = optional_param('nav', 'coursetemplate', PARAM_TEXT);

$context = \context_course::instance($id);
$PAGE->set_context($context);
$pageurl = new moodle_url('/course/format/kickstart/list.php', ['id' => $id, 'nav' => $nav]);
$PAGE->set_url($pageurl);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);

$PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'init',
    ['contextid' => $context->id, 'courseid' => $course->id, 'nav' => $nav, 'filteroptions' => true]);

// Print header.
$actionbar = new format_kickstart\output\general_action_bar($context, $pageurl, 'kickstart', 'coursetemplate');

$PAGE->set_secondary_active_tab('kickstart-nav');

$menus = format_kickstart_get_breadcump_menus();
$uniquetitle = $menus[$nav];

$titlecomponents = [
    $uniquetitle,
    $context->get_context_name(false),
];
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titlecomponents));
$PAGE->set_heading($PAGE->course->fullname);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('format_kickstart');

echo html_writer::start_div('kickstart-page');

echo $renderer->render_action_bar($actionbar);

if (file_exists($CFG->dirroot . '/local/kickstart_pro/classes/output/kickstartProHandler.php')) {
    require_once($CFG->dirroot . '/local/kickstart_pro/classes/output/kickstartProHandler.php');
    $kickstartpage = new \local_kickstart_pro\output\kickstartHandlerWithPropage($course, $nav);
} else {
    $kickstartpage = new format_kickstart\output\kickstartHandler($course, $nav);
}

echo $renderer->render_kickstart_page($kickstartpage);

echo html_writer::end_div();

echo $OUTPUT->footer();

