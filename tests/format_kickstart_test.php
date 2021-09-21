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
 * Kickstart course format testcases.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Test kickstart course format.
 *
 * @group      format_kickstart
 * @group      format_kickstart_test
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart_test extends advanced_testcase {
    /**
     * Set the admin user as User.
     *
     * @return void
     */
    public function setup(): void {
        $this->setAdminUser();
        $this->resetAfterTest(true);
    }

    /**
     * Testing the import template in the course.
     */
    public function test_importing() {
        global $DB, $CFG;
        $course = $this->getDataGenerator()->create_course([
            'startdate' => 1000,
            'enddate' => 1000,
            'sortorder' => 10001,
            'enablecompletion' => 1
        ]);
        $module = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id
        ]);
        $template = new \stdClass();
        $template->title = '';
        $template->description = '';
        $template->description_format = '';
        $template->id = $DB->insert_record('format_kickstart_template', $template);

        $fs = get_file_storage();

        $fileinfo = [
            'contextid' => \context_system::instance()->id,
            'component' => 'format_kickstart',
            'filearea'  => 'course_backups',
            'itemid'    => $template->id,
            'filepath'  => '/',
            'filename'  => 'course-10-online.mbz'];

        $fs->create_file_from_pathname($fileinfo, $CFG->dirroot . '/course/format/kickstart/tests/course-10-online.mbz');

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
            'coursedisplay',
            'showactivitydates'
        ];

        foreach ($course as $field => $value) {
            if (in_array($field, $excludefield)) {
                continue;
            }
            $this->assertEquals($course->$field, $updatecourse->$field,
                'Ensure course setting was not changed after import: ' . $field);
        }
    }

    /**
     * Case to test the external method to create template.
     *
     * @return void
     */
    public function test_create_template() {
        global $DB;
        $template = $this->format_format_kickstart_template_info();
        $context = \context_system::instance();
        format_kickstart_create_template($template, 1, $context, 'format_kickstart');
        $count = $DB->count_records('format_kickstart_template');
        $this->assertEquals(1, $count);
    }

    /**
     * Case to check the availablity of kickstart pro.
     */
    public function test_check_kickstart_has_pro() {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_kickstart_pro');
        $pluginstatus = false;
        if (!empty($plugininfo)) {
            $pluginstatus = true;
        }
        $this->assertEquals(format_kickstart_has_pro(), $pluginstatus);
    }

    /**
     * Get the template info.
     */
    public function format_format_kickstart_template_info() {
        $template = array(
            'id' => 0,
            'title' => 'demo test 1',
            'description' => '<p dir="ltr" style="text-align: left;">test content of the block content</p>',
            'tags' => [],
            'backupfile' => 'course-10-online.mbz',
            'preview_url' => '',
            'restrictcohort' => 0,
            'cohortids' => [],
            'restrictcategory' => 0,
            'categoryids' => [],
            'includesubcategories' => 0,
            'restrictrole' => 0,
            'roleids' => [],
            'description_format' => 1,
        );
        return (object) $template;
    }
}
