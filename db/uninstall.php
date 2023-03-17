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
 * format kickstart plugin uninstallation.
 *
 * @package    format_kickstart
 * @copyright  bdecent GmbH 2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall script for format_kickstart
 * @return void
 */
function xmldb_format_kickstart_uninstall() {
    global $DB, $SITE;
    $DB->delete_records_select(
        'course_format_options',
        "courseid = :siteid AND format != :site",
        array("siteid" => $SITE->id, 'site' => 'site')
    );
    unset_config('kickstart_templates');
    return true;
}
