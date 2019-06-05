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

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

global $USER, $DB;

$course_id = required_param('course_id', PARAM_INT);
$template_id = required_param('template_id', PARAM_INT);

$PAGE->set_context(\context_course::instance($course_id));

require_login();

if (!$PAGE->user_allowed_editing()) {
    throw new moodle_exception('notallowed', 'format_kickstart');
}

\format_kickstart\course_importer::import($template_id, $course_id);

redirect(new moodle_url('/course/view.php', ['id' => $course_id]));