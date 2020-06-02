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
 * Create a simple course with the Kickstart format as default.
 *
 * @package    format_kickstart
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use format_kickstart\form\course_form;

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/adminlib.php");

global $USER, $PAGE, $OUTPUT;

$categoryid = optional_param('category', null, PARAM_INT);

if (is_siteadmin()) {
    admin_externalpage_setup('kickstartcreatecourse');
} else {
    if ($categoryid) {
        $PAGE->set_context(context_coursecat::instance($categoryid));
    } else {
        $PAGE->set_context(context_system::instance());
    }
    $PAGE->set_url(new moodle_url('/course/format/kickstart/createcourse.php'));
    require_login();
    require_capability('format/kickstart:import_from_template', $PAGE->context);
    $PAGE->navbar->add(get_string('courses'), new moodle_url('/course'));
    $PAGE->navbar->add(get_string('createcoursefromtemplate', 'format_kickstart'));
}
$PAGE->set_title(get_string('createcoursefromtemplate', 'format_kickstart'));
$PAGE->set_heading(get_string('createcoursefromtemplate', 'format_kickstart'));

$form = new course_form(null, ['categoryid' => $categoryid]);

if ($data = $form->get_data()) {
    $data->format = 'kickstart';
    try {
        $course = create_course($data);
        \core\notification::info(get_string('enrollmenthelp', 'format_kickstart'));
        redirect(new moodle_url('/user/index.php', ['id' => $course->id, 'newcourse' => 1]));
    } catch (moodle_exception $e) {
        notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();