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
 * Upgrade scripts for course format Kickstart
 *
 * @package    format_kickstart
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for format_weeks
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_kickstart_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019050800) {

        // Define table kickstart_template to be created.
        $table = new xmldb_table('kickstart_template');

        // Adding fields to table kickstart_template.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table kickstart_template.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for kickstart_template.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2019050800, 'format', 'kickstart');
    }

    if ($oldversion < 2019050900) {

        // Define field description_format to be added to kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('description_format', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description');

        // Conditionally launch add field description_format.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2019050900, 'format', 'kickstart');
    }

    if ($oldversion < 2019061703) {

        // Define field preview_url to be added to kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('preview_url', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description_format');

        // Conditionally launch add field preview_url.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2019061703, 'format', 'kickstart');
    }

    return true;
}
