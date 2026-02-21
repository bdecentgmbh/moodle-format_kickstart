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

namespace format_kickstart\event;

use moodle_url;
use coding_exception;
use moodle_exception;

/**
 * Event course_imported
 *
 * @package    format_kickstart
 * @copyright  2025 Stephan Lorbek <stephan.lorbek@uni-graz.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_imported extends \core\event\base {
    /**
     * Set basic properties for the event.
     */
    protected function init() {
        $this->data['objecttable'] = 'course';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * get_description function
     * @return string
     */
    public function get_description(): string {
        return "Course with id '{$this->objectid}' was imported using the Kickstart format by user with id '{$this->userid}'.";
    }

    /**
     * get_name function
     * @return string
     * @throws coding_exception
     */
    public static function get_name(): string {
        return get_string("course_imported", "format_kickstart");
    }

    /**
     * get_url function
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_url(): moodle_url {
        return new moodle_url('/course/view.php', ['id' => $this->objectid]);
    }
}
