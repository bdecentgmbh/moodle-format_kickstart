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
 * Template list table.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * List of templates.
 *
 * @package format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_table extends \table_sql {

    /**
     * Setup table.
     *
     * @throws \coding_exception
     */
    public function __construct() {
        global $DB;
        parent::__construct('templates');

        // Define the headers and columns.
        $headers = [];
        $columns = [];

        $headers[] = get_string('title', 'format_kickstart');
        $headers[] = get_string('description', 'format_kickstart');
        $headers[] = get_string('tags');
        $columns[] = 'title';
        $columns[] = 'description';
        $columns[] = 'tags';
        if (format_kickstart_has_pro()) {
            $headers[] = get_string('up') .'/'. get_string('down');
            $columns[] = 'updown';
            $this->no_sorting('updown');
            $this->cnt = 0;
        }
        $headers[] = get_string('actions');
        $columns[] = 'actions';
        $this->totaltemplates = $DB->count_records('format_kickstart_template', null);
        $this->no_sorting('tags');
        $this->no_sorting('actions');
        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    /**
     * Generate tag list.
     *
     * @param \stdClass $data
     * @return mixed
     */
    public function col_tags($data) {
        global $OUTPUT;
        return $OUTPUT->tag_list(\core_tag_tag::get_item_tags('format_kickstart', 'format_kickstart_template', $data->id),
            null, 'template-tags');
    }

    /**
     * Generate sort list for the templates.
     *
     * @param \stdClass $data
     * @return mixed
     */
    public function col_updown($data) {
        global $OUTPUT;
        $templateurl = new \moodle_url('/course/format/kickstart/templates.php');
        $updown = '';
        $strup = get_string('up');
        $strdown = get_string('down');
        $spacer = $OUTPUT->pix_icon('spacer', '', 'moodle', array('class' => 'iconsmall'));
        if ($this->cnt) {
            $updown .= \html_writer::link($templateurl->out(false,
            array('action' => 'up', 'template' => $data->id)),
            $OUTPUT->pix_icon('t/up', $strup, 'moodle', array('class' => 'iconsmall')),
                array('id' => "sort-template-up-action")). '';
        } else {
            $updown .= $spacer;
        }

        if ($this->cnt < ($this->totaltemplates - 1)) {
            $updown .= '&nbsp;'. \html_writer::link($templateurl->out(false,
            array('action' => 'down', 'template' => $data->id)),
            $OUTPUT->pix_icon('t/down', $strdown, 'moodle',
                array('class' => 'iconsmall')), array('id' => "sort-template-down-action"));
        } else {
            $updown .= $spacer;
        }
        $this->cnt++;
        return $updown;
    }


    /**
     * Actions for tags.
     *
     * @param \stdClass $data
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions($data) {
        global $OUTPUT;

        return $OUTPUT->single_button(
            new \moodle_url('/course/format/kickstart/template.php', ['action' => 'edit', 'id' => $data->id]),
            get_string('edit', 'format_kickstart'), 'get') .
            $OUTPUT->single_button(
                new \moodle_url('/course/format/kickstart/template.php', ['action' => 'delete', 'id' => $data->id]),
                get_string('delete', 'format_kickstart'), 'get');
    }

    /**
     * Get the templates.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB, $CFG;

        list($wsql, $params) = $this->get_sql_where();
        if ($wsql) {
            $wsql = 'AND ' . $wsql;
        }
        $sql = 'SELECT *
                FROM {format_kickstart_template} t
                '.$wsql;

        $sort = $this->get_sql_sort();
        if ($sort) {
            $sql = $sql . ' ORDER BY ' . $sort;
        } else if (format_kickstart_has_pro()) {
            if ($CFG->kickstart_templates) {
                $orders = explode(",", $CFG->kickstart_templates);
                array_unshift($orders, 'id');
                $sql = $sql . 'ORDER BY FIELD ('. implode(",", $orders) . ')';
            }
        }

        if ($pagesize != -1) {
            $total = $DB->count_records('format_kickstart_template');
            $this->pagesize($pagesize, $total);
        } else {
            $this->pageable(false);
        }

        if ($useinitialsbar && !$this->is_downloading()) {
            $this->initialbars(true);
        }

        $this->rawdata = $DB->get_recordset_sql($sql, $params, $this->get_page_start(), $this->get_page_size());
    }
}
