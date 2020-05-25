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
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_kickstart\output\course_template_list;

defined('MOODLE_INTERNAL') || die();

/**
 * @group      format_kickstart
 * @group      format_kickstart_course_test
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart_course_test extends advanced_testcase
{


    public function test_course_template_list()
    {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign(5, $teacher->id, context_system::instance()->id);
        $this->setUser($teacher);

        /** @var format_kickstart_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('format_kickstart');

        $category = $this->getDataGenerator()->create_category();
        $subcategory = $this->getDataGenerator()->create_category(['parent' => $category->id]);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $subcategorycourse = $this->getDataGenerator()->create_course(['category' => $subcategory->id]);

        $generator->create_template([
            'restrictcategory' => 1,
            'categoryids' => json_encode([$category->id])
        ]);

        $list = new course_template_list($course, $teacher->id);
        $this->assertCount(1, $list->get_templates());

        $list = new course_template_list($subcategorycourse, $teacher->id);
        $this->assertCount(0, $list->get_templates());

        $generator->create_template([
            'restrictcategory' => 1,
            'categoryids' => json_encode([$category->id]),
            'includesubcategories' => 1
        ]);

        $this->assertCount(1, $list->get_templates());
    }
}