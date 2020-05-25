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

admin_externalpage_setup('kickstartcreatecourse');
$PAGE->set_title(get_string('createcoursefromtemplate', 'format_kickstart'));
$PAGE->set_heading(get_string('createcoursefromtemplate', 'format_kickstart'));

$form = new course_form();

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