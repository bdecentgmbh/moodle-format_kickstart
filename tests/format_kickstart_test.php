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

defined('MOODLE_INTERNAL') || die();

/**
 * Test course importing.
 *
 * @group      format_kickstart
 * @group      format_kickstart_test
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart_test extends advanced_testcase {
    public function test_importing() {
        global $DB, $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'startdate' => 1000,
            'enddate' => 1000,
            'sortorder' => 10001,
            'enablecompletion' => 1
        ]);
        $module = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id
        ]);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->setAdminUser();

        $template = new \stdClass();
        $template->title = '';
        $template->description = '';
        $template->description_format = '';

        $template->id = $DB->insert_record('kickstart_template', $template);

        $fs = get_file_storage();

        $fileinfo = [
            'contextid' => \context_system::instance()->id,
            'component' => 'format_kickstart',
            'filearea'  => 'course_backups',
            'itemid'    => $template->id,
            'filepath'  => '/',
            'filename'  => 'course.mbz'];

        $fs->create_file_from_pathname($fileinfo, $CFG->dirroot . '/course/format/kickstart/tests/course.mbz');

        \format_kickstart\course_importer::import_from_template($template->id, $course->id);

        $updatecourse = $DB->get_record('course', ['id' => $course->id]);

        $excludefield = [
            'format',
            'sortorder',
            'newsitems',
            'timemodified',
            'enabledcompletion',
            'cacherev',
            'hiddensections',
            'coursedisplay'
        ];

        foreach ($course as $field => $value) {
            if (in_array($field, $excludefield)) {
                continue;
            }
            $this->assertEquals($course->$field, $updatecourse->$field,
                'Ensure course setting was not changed after import: ' . $field);
        }
    }
}