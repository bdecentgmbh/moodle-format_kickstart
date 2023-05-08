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
 * Install scripts for course format Kickstart
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install script for format_kickstart
 * @return void
 */
function xmldb_format_kickstart_install() {
    global $CFG;
    require_once($CFG->dirroot. "/course/format/kickstart/lib.php");
    if (method_exists('core_plugin_manager', 'reset_caches')) {
        core_plugin_manager::reset_caches();
    }
    format_kickstart_import_courseformat_template();
    $file = $CFG->dirroot.'/course/format/kickstart/createtemplates.php';
    if (file_exists($file)) {
        require_once($file);
        // Install templates automatically.
        install_templates();
    }
    return true;
}
