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
 * @package    format_kickstart
 * @copyright  2018 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart;

require_once($CFG->libdir . '/tablelib.php');

/**
 * List of templates
 *
 * @package format_kickstart
 */
class template_table extends \table_sql
{
    /**
     * @throws \coding_exception
     */
    public function __construct() {
        parent::__construct('templates');

        // Define the headers and columns.
        $headers = [];
        $columns = [];

        $headers[] = get_string('title', 'format_kickstart');
        $columns[] = 'title';
        $headers[] = get_string('description', 'format_kickstart');
        $columns[] = 'description';
        $headers[] = get_string('actions');
        $columns[] = 'actions';

        $this->define_columns($columns);
        $this->define_headers($headers);
    }

    public function col_actions($data)
    {
        global $OUTPUT;
        return $OUTPUT->single_button(new \moodle_url('/course/format/kickstart/template.php', ['action' => 'edit', 'id' => $data->id]), get_string('edit', 'format_kickstart'));
    }

    /**
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true)
    {
        global $DB;

        list($wsql, $params) = $this->get_sql_where();
        if ($wsql) {
            $wsql = 'AND ' . $wsql;
        }
        $sql = 'SELECT *
                FROM {kickstart_template} t
                '.$wsql;

        $sort = $this->get_sql_sort();
        if ($sort) {
            $sql = $sql . ' ORDER BY ' . $sort;
        }

        if ($pagesize != -1) {
            $total = $DB->count_records('kickstart_template');
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