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
 * Kickstart create templates.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context\system as context_system;

/**
 * Install templates
 */
function install_templates() {
    require_once(__DIR__ . '/freetemplates.php');
    require_once(__DIR__ . '/lib.php');

    $context = context_system::instance();
    $cnt = format_kickstart_get_next_template_sort();
    foreach (format_kickstart_get_default_templates() as $template) {
        $template = (object) $template;
        // Create template and add it to the configured templates list.
        format_kickstart_create_template($template, $cnt, $context, 'format_kickstart');
        $cnt++;
    }
}
