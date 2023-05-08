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

global $USER, $DB;

$courseid = required_param('course_id', PARAM_INT);
$templateid = required_param('template_id', PARAM_INT);

$PAGE->set_context(\context_course::instance($courseid));
$PAGE->set_url(new moodle_url('/course/format/confirm.php', ['template_id' => $templateid, 'course_id' => $courseid]));
require_login();

require_capability('format/kickstart:import_from_template', $PAGE->context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$template = $DB->get_record('format_kickstart_template', ['id' => $templateid], '*', MUST_EXIST);

$PAGE->set_title($template->title);
$PAGE->set_heading($template->title);
$PAGE->navbar->add(get_string('courses'), new moodle_url('/course/index.php'));
$PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', ['id' => $courseid]));
$PAGE->navbar->add(get_string('usetemplate', 'format_kickstart'));

$continueurl = new moodle_url('/course/format/kickstart/import.php', ['template_id' => $templateid, 'course_id' => $courseid]);
$continuebutton = new single_button($continueurl, get_string('import'), 'post');
$confirmstr = \html_writer::tag('h4', $template->title);
$confirmstr .= \html_writer::tag('p', get_string('strconfirmtemplate', 'format_kickstart'));

echo $OUTPUT->header();
echo $OUTPUT->confirm($confirmstr, $continuebutton, new \moodle_url('/course/view.php', ['id' => $courseid]));
echo $OUTPUT->footer();
