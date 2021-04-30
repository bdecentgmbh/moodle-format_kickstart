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
 * format_kickstart data generator
 *
 * @package    format_kickstart
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * format_kickstart data generator class
 *
 * @package    format_kickstart
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart_generator extends component_generator_base {
    
    public function create_template($record = []) {
        global $DB;

        $record = $this->datagenerator->combine_defaults_and_record([
            'title' => 'New template',
            'description' => '',
            'description_format' => FORMAT_PLAIN,
            'preview_url' => null,
            'restrictcohort' => null,
            'restrictcategory' => null,
            'restrictrole' => null,
            'cohortids' => null,
            'categoryids' => null,
            'includesubcategories' => null,
            'roleids' => null,
        ], $record);

        $record['id'] = $DB->insert_record('kickstart_template', $record);
        return $DB->get_record('kickstart_template', ['id' => $record['id']]);
    }
}
