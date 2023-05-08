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
 * Event observer function  definition and returns.
 *
 * @package   format_kickstart
 * @copyright bdecent GmbH 2021
 * @category  event
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_kickstart;

/**
 * Event observer class define.
 */
class observer {

    /**
     * Callback function will delete the course in the table.
     * @param object $event event data
     * @return void course focus deleted records action.
     */
    public static function format_kickstart_changeconfig($event) {
        global $DB, $CFG;
        $data = $event->get_data();
        $plugin = $data['other']['plugin'];
        $name = isset($data['other']['name']) ? $data['other']['name'] : '';
        if (preg_match("/^format_/", $plugin) && $name == 'disabled') {
            $templates = isset($CFG->kickstart_templates) ? explode(",", $CFG->kickstart_templates) : [];
            $disable = ($data['other']['value'] == 1) ? true : false;
            $format = substr($plugin, 7);
            if ($disable) {
                $removetemplates = $DB->get_records_menu('format_kickstart_template',
                    array('format' => $format, 'courseformat' => 1), '', 'id,id');
                if ($removetemplates) {
                    $removetemplates = array_keys($removetemplates);
                    $templates = array_diff($templates, $removetemplates);
                }
                $DB->set_field('format_kickstart_template', 'visible', 0, array('format' => $format, 'courseformat' => 1));
            } else {
                $addtemplates = $DB->get_records_menu('format_kickstart_template',
                    array('format' => $format, 'courseformat' => 1), '', 'id,id');
                if ($addtemplates) {
                    $addtemplates = array_keys($addtemplates);
                    $templates = array_merge($templates, $addtemplates);
                }
                $DB->set_field('format_kickstart_template', 'visible', 1, array('format' => $format, 'courseformat' => 1));
            }
            set_config('kickstart_templates', implode(',', $templates));
        }
    }
}
