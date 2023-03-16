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
 * External functions definition and returns.
 *
 * @package   format_kickstart
 * @copyright bdecent GmbH 2021
 * @category  event
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_kickstart;

defined('MOODLE_INTERNAL') || die();

use \format_kickstart\course_importer;

require_once($CFG->libdir.'/externallib.php');

/**
 * define external class.
 */
class external extends \external_api {
    /**
     * Parameters defintion to import the template.
     *
     * @return array list of option parameters
     */
    public static function import_template_parameters() {

        return new \external_function_parameters(
            array(
                'templateid' => new \external_value(PARAM_INT, 'Kickstart Template id'),
                'courseid' => new \external_value(PARAM_INT, 'Course id')
            )
        );
    }

    /**
     * Import the template.
     * @param int $templateid template id
     * @param mixed $courseid course id
     * @return bool
     */
    public static function import_template($templateid, $courseid) {
        global $CFG;
        require_login();
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $context = \context_course::instance($courseid);
        require_capability('format/kickstart:import_from_template', $context);
        $params = self::validate_parameters(self::import_template_parameters(),
                        array('templateid' => $templateid, 'courseid' => $courseid));
        course_importer::import_from_template($templateid, $courseid);
        return true;
    }

    /**
     * Return parameters define for import the template.
     */
    public static function import_template_returns() {

        return new \external_value(PARAM_BOOL, 'Import status');
    }
}
