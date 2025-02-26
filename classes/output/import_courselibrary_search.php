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
 * Widget that displays courses to import inside course.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\output;

// Require both the backup and restore libs.
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/backup/util/ui/restore_ui_components.php');



/**
 * Widget that displays courses to import inside course.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_kickstart
 */
class import_courselibrary_search extends \restore_course_search {

    protected $customfields;

    public $customresults;

    public function __construct(array $config = array(), $currentcouseid = null, $customfields = array()) {
        parent::__construct($config);
        $this->setup_restrictions();
        $this->currentcourseid = $currentcouseid;
        $this->includecurrentcourse = false;
        $this->customfields = $customfields;
    }

    /**
     * Sets up any access restrictions for the courses to be displayed in the search.
     *
     * This will typically call $this->require_capability().
     */
    protected function setup_restrictions() {
        $this->require_capability('moodle/backup:backuptargetimport');
    }


    /**
     * Get the search SQL.
     * @global moodle_database $DB
     * @return array
     */
    protected function get_searchsql() {
        global $DB, $CFG;

        $ctxselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params = array(
            'contextlevel' => CONTEXT_COURSE,
            'fullnamesearch' => '%'.$this->get_search().'%',
            'shortnamesearch' => '%'.$this->get_search().'%',
            'descriptionsearch' => '%'.$this->get_search().'%',
            'tagsearch' => '%'.$this->get_search().'%',
            'activitynamesearch' => '%'.$this->get_search().'%',
            'activitytagsearch' => '%'.$this->get_search().'%',
            'activitydescriptionsearch' => '%'.$this->get_search().'%',
        );

        $modules = $DB->get_records_sql("SELECT * FROM {modules} WHERE visible = 1 AND name != 'subsection'");
        $moduleunions = array();
        foreach ($modules as $module) {
            // Clean the module name and verify table exists
            $tablename = clean_param($module->name, PARAM_ALPHANUMEXT);
            if ($DB->get_manager()->table_exists($tablename)) {
                // Use proper Moodle DB table name formatting
                $moduleunions[] = "SELECT '".$DB->sql_compare_text($module->name)."' as modname, id, name, intro FROM {".$tablename."}";
            }
        }
        $modulesql = implode(" UNION ALL ", $moduleunions);

        $select = " SELECT DISTINCT c.id, c.fullname, c.shortname, c.visible, c.sortorder ";
        $from   = " FROM {course} c
                    LEFT JOIN {tag_instance} ti ON ti.itemid = c.id
                    LEFT JOIN {tag} t ON t.id = ti.tagid
                    LEFT JOIN {course_modules} cm ON cm.course = c.id
                    LEFT JOIN {modules} m ON m.id = cm.module
                    LEFT JOIN (
                        ".$modulesql."
                    ) modinfo ON modinfo.modname = m.name AND modinfo.id = cm.instance
                    LEFT JOIN {tag_instance} cmti ON cmti.itemid = cm.id AND cmti.itemtype = 'course_modules'
                    LEFT JOIN {tag} cmt ON cmt.id = cmti.tagid
                    LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id
                    LEFT JOIN {customfield_field} cff ON cff.id = cfd.fieldid ";

        $where  = " WHERE (".$DB->sql_like('c.fullname', ':fullnamesearch', false)." OR ".
                $DB->sql_like('c.shortname', ':shortnamesearch', false). " OR ".
                $DB->sql_like('c.summary', ':descriptionsearch', false). " OR ".
                $DB->sql_like('t.name', ':tagsearch', false). " OR ".
                $DB->sql_like('modinfo.name', ':activitynamesearch', false). " OR ".
                $DB->sql_like('modinfo.intro', ':activitydescriptionsearch', false). " OR ".
                $DB->sql_like('cmt.name', ':activitytagsearch', false). ")";


        if (!empty($this->customfields)) {
            $customfieldconditions = [];
            foreach ($this->customfields as $fieldshortname => $value) {
                if ($value) {
                    $paramname = 'customfield_' . $fieldshortname;
                    $customfieldconditions[] = "(cff.shortname = :{$paramname}_name AND cfd.value = :{$paramname}_value)";
                    $params[$paramname . '_name'] = $fieldshortname;
                    $params[$paramname . '_value'] = $value;
                }
            }
            if ($customfieldconditions) {
                $where .= " AND (" . implode(" OR ", $customfieldconditions) . ")";
            }
        }


        $orderby    = " ORDER BY c.sortorder";

        if ($this->currentcourseid !== null && !$this->includecurrentcourse) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }

        $limit = '';

        $limitfrom = optional_param('page', 0, PARAM_INT);
        $perpage = get_config("format_kickstart", "courselibraryperpage");
        list($limitfrom, $limitnum) = $this->normalise_limit_from_num($limitfrom * $perpage, $perpage);

        if ($CFG->dbtype == 'pgsql') {

            // If pgsql.
            if ($limitnum) {
                $limit .= " LIMIT $limitnum";
            }
            if ($limitfrom) {
                $limit .= " OFFSET $limitfrom";
            }
        } else {
             // if mysqli.
            if ($limitfrom or $limitnum) {
                if ($limitnum < 1) {
                    $limitnum = "18446744073709551615";
                }
                $limit .= " LIMIT $limitfrom, $limitnum";
            }
        }

        return array($select.$ctxselect.$from.$ctxjoin.$where.$orderby.$limit, $params);
    }

    public function get_custom_search() {
        global $DB;
        if (!is_null($this->customresults)) {
            return $this->customresults;
        }

        $this->customresults = array();
        $this->totalcount = 0;
        $contextlevel = $this->get_itemcontextlevel();
        list($sql, $params) = $this->get_searchsql();
        // Get total number, to avoid some incorrect iterations.
        $countsql = preg_replace('/ORDER BY.*/', '', $sql);
        $totalcourses = $DB->count_records_sql("SELECT COUNT(*) FROM ($countsql) sel", $params);
        //echo $totalcourses;
        if ($totalcourses > 0) {
            // User to be checked is always the same (usually null, get it from first element).
            $firstcap = reset($this->requiredcapabilities);
            $userid = isset($firstcap['user']) ? $firstcap['user'] : null;
            // Extract caps to check, this saves us a bunch of iterations.
            $requiredcaps = array();
            foreach ($this->requiredcapabilities as $cap) {
                $requiredcaps[] = $cap['capability'];
            }
            // Iterate while we have records and haven't reached $this->maxresults.
            $resultset = $DB->get_recordset_sql($sql, $params);
            foreach ($resultset as $result) {
                context_helper::preload_from_record($result);
                $classname = context_helper::get_class_for_level($contextlevel);
                $context = $classname::instance($result->id);
                if (count($requiredcaps) > 0) {
                    if (!has_all_capabilities($requiredcaps, $context, $userid)) {
                        continue;
                    }
                }
                // Check if we are over the limit.
                if ($this->totalcount + 1 > $this->maxresults) {
                    $this->hasmoreresults = true;
                    break;
                }
                // If not, then continue.
                $this->totalcount++;
                $this->results[$result->id] = $result;
            }
            $resultset->close();
        }

        //print_object($this->results);exit;

        return $this->totalcount;
    }


    /**
     * Returns an array of results from the search
     * @return array
     */
    public function get_custom_results() {
        if ($this->customresults === null) {
            $this->get_custom_search();
        }
        return $this->customresults;
    }



    protected function normalise_limit_from_num($limitfrom, $limitnum) {
        global $CFG;

        // We explicilty treat these cases as 0.
        if ($limitfrom === null || $limitfrom === '' || $limitfrom === -1) {
            $limitfrom = 0;
        }
        if ($limitnum === null || $limitnum === '' || $limitnum === -1) {
            $limitnum = 0;
        }

        if ($CFG->debugdeveloper) {
            if (!is_numeric($limitfrom)) {
                $strvalue = var_export($limitfrom, true);
                debugging("Non-numeric limitfrom parameter detected: $strvalue, did you pass the correct arguments?",
                    DEBUG_DEVELOPER);
            } else if ($limitfrom < 0) {
                debugging("Negative limitfrom parameter detected: $limitfrom, did you pass the correct arguments?",
                    DEBUG_DEVELOPER);
            }

            if (!is_numeric($limitnum)) {
                $strvalue = var_export($limitnum, true);
                debugging("Non-numeric limitnum parameter detected: $strvalue, did you pass the correct arguments?",
                    DEBUG_DEVELOPER);
            } else if ($limitnum < 0) {
                debugging("Negative limitnum parameter detected: $limitnum, did you pass the correct arguments?",
                    DEBUG_DEVELOPER);
            }
        }

        $limitfrom = (int)$limitfrom;
        $limitnum  = (int)$limitnum;
        $limitfrom = max(0, $limitfrom);
        $limitnum  = max(0, $limitnum);

        return array($limitfrom, $limitnum);
    }



    public function get_total_course_count() {
        global $DB;
        list($sql, $params) = $this->get_searchsql();
        // Get total number, to avoid some incorrect iterations.
        $countsql = preg_replace('/ORDER BY.*/', '', $sql);
        $totalcourses = $DB->count_records_sql("SELECT COUNT(*) FROM ($countsql) sel", $params);
        return $totalcourses;
    }

}