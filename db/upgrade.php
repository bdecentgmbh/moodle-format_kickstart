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
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Upgrade script for format_kickstart
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_format_kickstart_upgrade($oldversion) {
    global $CFG, $DB;
    require_once($CFG->dirroot. "/course/format/kickstart/lib.php");
    $dbman = $DB->get_manager();

    if ($oldversion < 2019050800) {

        // Define table format_kickstart_template to be created.
        $table = new xmldb_table('kickstart_template');

        // Adding fields to table format_kickstart_template.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table format_kickstart_template.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for format_kickstart_template.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2019050800, 'format', 'kickstart');
    }

    if ($oldversion < 2019050900) {

        // Define field description_format to be added to format_kickstart_template.
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

        // Define field preview_url to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('preview_url', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description_format');

        // Conditionally launch add field preview_url.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2019061703, 'format', 'kickstart');
    }

    if ($oldversion < 2020051200) {

        // Define field restrictcohort to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('restrictcohort', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'preview_url');

        // Conditionally launch add field restrictcohort.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field restrictcategory to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('restrictcategory', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'restrictcohort');

        // Conditionally launch add field restrictcategory.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field restrictrole to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('restrictrole', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'restrictcategory');

        // Conditionally launch add field restrictrole.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field cohortids to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('cohortids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'restrictrole');

        // Conditionally launch add field cohortids.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field categoryids to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('categoryids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'cohortids');

        // Conditionally launch add field categoryids.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field roleids to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('roleids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'categoryids');

        // Conditionally launch add field roleids.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2020051200, 'format', 'kickstart');
    }

    if ($oldversion < 2020052502) {

        // Define field includesubcategories to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('includesubcategories', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'categoryids');

        // Conditionally launch add field includesubcategories.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2020052502, 'format', 'kickstart');
    }

    if ($oldversion < 2021092102) {
        // Define field sort to be added to format_kickstart_template.
        $table = new xmldb_table('kickstart_template');
        $field = new xmldb_field('sort', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'roleids');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'format_kickstart_template');
        }
        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2021092102, 'format', 'kickstart');
    }

    if ($oldversion < 2023021800) {
        // Define field sort to be added to format_kickstart_template.
        $table = new xmldb_table('format_kickstart_template');
        $field = new xmldb_field('courseformat', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'sort');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('format', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'courseformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('visible', XMLDB_TYPE_INTEGER, '1', null, null, null, 1, 'format');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2023021800, 'format', 'kickstart');
    }

    // Create kickstart format options table if not exist.
    if ($oldversion < 2023030101) {
        // Define table format_kickstart_options to be created.
        $table = new xmldb_table('format_kickstart_options');

        // Adding fields to table format_kickstart_options.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('format', XMLDB_TYPE_CHAR, '21', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table format_kickstart_options.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for format_kickstart_options.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2023030101, 'format', 'kickstart');
    }

    if ($oldversion < 2023032102) {
        $table = new xmldb_table('format_kickstart_template');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '1', null, null, null, 1, 'visible');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2023032102, 'format', 'kickstart');
    }

    if ($oldversion < 2023040300) {
        $DB->set_field('tag_instance', 'itemtype', 'format_kickstart_template',
            array('itemtype' => 'kickstart_template', 'component' => 'format_kickstart'));
        // Kickstart savepoint reached.
        upgrade_plugin_savepoint(true, 2023040300, 'format', 'kickstart');
    }

    format_kickstart_import_courseformat_template();

    return true;
}
