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

namespace format_kickstart\output;
use context_course;
class kickstartHandler  {

    use kickstart_page;

    /**
     * @var int
     */
    public $courseid;

    public $course;

    /**
     *
     */
    public $menuid;

    public $context;

    public $params;

    public function __construct(object $course, string $menuid, array $params = []) {
        $this->courseid = $course->id;
        $this->course = $course;
        $this->menuid = $menuid;
        $this->params = $params;
        $this->context = context_course::instance($this->courseid);
    }

    public function get_content() {
        // get menu id based the called function
        $method = 'get_' .$this->menuid . '_content';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}