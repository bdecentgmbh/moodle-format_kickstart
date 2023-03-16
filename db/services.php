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
 * Define plugin services.
 *
 * @package   format_kickstart
 * @copyright bdecent GmbH 2021
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'format_kickstart_import_template' => array(
        'classname' => 'format_kickstart\external',
        'methodname' => 'import_template',
        'description' => 'Import the template',
        'type' => 'write',
        'capabilities' => 'format/kickstart:import_from_template',
        'ajax' => true,
        'loginrequired' => true,
    ),
);
