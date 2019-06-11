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
 * This file contains main class for the course format Kickstart
 *
 * @package    format_kickstart
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the Kickstart course format
 *
 * @package    format_kickstart
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart extends format_base {

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Kickstart format uses the following options:
     * - userinstructions
     * - userinstructions_format
     * - teacherinstructions
     * - teacherinstructions_format
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'userinstructions' => [
                    'label' => new lang_string('userinstructions', 'format_kickstart'),
                    'help' => 'userinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => get_config('format_kickstart', 'defaultuserinstructions'),
                        'format' => FORMAT_HTML
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'userinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden'
                ],
                'teacherinstructions' => [
                    'label' => new lang_string('teacherinstructions', 'format_kickstart'),
                    'help' => 'teacherinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => get_config('format_kickstart', 'defaultteacherinstructions'),
                        'format' => FORMAT_HTML
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'teacherinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden'
                ]
            ];
        }

        return $courseformatoptions;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;

        if (isset($data['userinstructions']) && is_array($data['userinstructions'])) {
            $data['userinstructions_format'] = $data['userinstructions']['format'];
            $data['userinstructions'] = $data['userinstructions']['text'];
        }
        if (isset($data['teacherinstructions']) && is_array($data['teacherinstructions'])) {
            $data['teacherinstructions_format'] = $data['teacherinstructions']['format'];
            $data['teacherinstructions'] = $data['teacherinstructions']['text'];
        }

        return $this->update_format_options($data);
    }

    /**
     * Returns a record from course database table plus additional fields
     * that course format defines
     *
     * @return stdClass
     */
    public function get_course() {
        $course = parent::get_course();

        if (is_string($course->userinstructions)) {
            $course->userinstructions = [
                'text' => $course->userinstructions,
                'format' => $course->userinstructions_format
            ];
        }
        if (is_string($course->teacherinstructions)) {
            $course->teacherinstructions = [
                'text' => $course->teacherinstructions,
                'format' => $course->teacherinstructions_format
            ];
        }

        return $course;
    }
}

/**
 * Check if Kickstart Pro is installed.
 *
 * @return bool
 */
function format_kickstart_has_pro() {
    global $CFG;

    if (isset($CFG->kickstart_pro)) {
        return $CFG->kickstart_pro;
    }
    return array_key_exists('kickstart_pro', core_component::get_plugin_list('local'));
}