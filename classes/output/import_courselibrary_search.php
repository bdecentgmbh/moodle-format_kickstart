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

defined('MOODLE_INTERNAL') || die();

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
class import_courselibrary_search {
    /**
     * Custom fields to search on.
     * @var array
     */
    protected $customfields;

    /**
     * Total number of courses found.
     * @var int
     */
    public $totalcount;

    /**
     * Required capabilities to search.
     * @var array
     */
    public $requiredcapabilities = [];

    /**
     * The current course id.
     * @var int
     */
    protected $currentcourseid = null;

    /**
     * The current search string
     * @var string|null
     */
    public $search = null;

    /**
     * Max number of courses to return in a search.
     * @var int
     */
    public $maxresults = null;


    /**
     * The URL for this page including required params to return to it
     * @var moodle_url
     */
    public $url = null;


    /**
     * The results of the search
     * @var array|null
     */
    public $results = null;

    /**
     * Weights for the relevance of the sort results.
     * @var array
     */
    public $weights = [];

    /**
     * Summary of sqlparams
     * @var array
     */
    public $sqlparams = [];


    /**
     * Indicates if we have more than maxresults found.
     * @var bool
     */
    public $hasmoreresults = false;

    /**
     * Sort type.
     * @var string
     */
    public $sorttype = '';

    /**
     * Current page number.
     * @var int
     */
    public $page = 0;

    /**
     * The course library search object.
     * @param array $config
     * @param mixed $currentcouseid
     * @param mixed $customfields
     * @param mixed $sorttype
     * @param mixed $page
     */
    public function __construct(array $config = [], $currentcouseid = null, $customfields = [],
        $sorttype = '', $page = 0) {
        $this->search = optional_param('search', '', PARAM_NOTAGS);
        $this->search = trim($this->search);
        $this->maxresults = get_config('format_kickstart', 'courselibraryperpage');
        $this->setup_restrictions();
        $this->currentcourseid = $currentcouseid;
        $this->customfields = $customfields;
        $this->totalcount = null;
        $this->sorttype = $sorttype;
        $this->weights = $this->get_relevance_weights();
        $this->sqlparams = [];

        $this->page = $page;

        foreach ($config as $name => $value) {
            $method = 'set_'.$name;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    /**
     * Get the relevance weights.
     * @return array
     */
    private function get_relevance_weights() {
        $weights = [
            'fullname' => get_config('format_kickstart', 'weight_fullname'),
            'shortname' => get_config('format_kickstart', 'weight_shortname'),
            'tags' => get_config('format_kickstart', 'weight_tags'),
            'starred' => get_config('format_kickstart', 'weight_starred'),
        ];

        // Add weights for text and select type custom fields.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $fields = $handler->get_fields();

        foreach ($fields as $field) {
            if ($field->get('type') === 'text' || $field->get('type') === 'select') {
                $shortname = $field->get('shortname');
                $weights['customfield_' . $shortname] = get_config('format_kickstart', 'weight_customfield_' . $shortname);
            }
        }

        return $weights;
    }


    /**
     * Sets the page URL
     * @param moodle_url $url
     */
    public function set_url(\moodle_url $url) {
        $this->url = $url;
    }


    /**
     * Returns true if there are more search results.
     * @return bool
     */
    public function has_more_results() {
        if ($this->results === null) {
            $this->search();
        }
        return $this->hasmoreresults;
    }

    /**
     * Sets up any access restrictions for the courses to be displayed in the search.
     *
     * This will typically call $this->require_capability().
     */
    public function setup_restrictions() {
        $this->require_capability('moodle/backup:backuptargetimport');
    }

    /**
     * Adds a required capability which all results will be checked against
     * @param string $capability
     * @param int|null $user
     */
    public function require_capability($capability, $user = null) {
        if (!is_int($user)) {
            $user = null;
        }
        $this->requiredcapabilities[] = [
            'capability' => $capability,
            'user' => $user,
        ];
    }

    /**
     * The total number of results
     * @return int
     */
    public function get_count() {
        if ($this->totalcount === null) {
            $this->search();
        }
        return $this->totalcount;
    }


    /**
     * Get the search SQL.
     *
     * @return array
     */
    public function get_searchsql() {
        global $DB, $CFG, $USER;

        $ctxselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'fullnamesearch' => '%'.$this->get_search().'%',
            'shortnamesearch' => '%'.$this->get_search().'%',
            'descriptionsearch' => '%'.$this->get_search().'%',
            'tagsearch' => '%'.$this->get_search().'%',
            'activitynamesearch' => '%'.$this->get_search().'%',
            'activitytagsearch' => '%'.$this->get_search().'%',
            'activitydescriptionsearch' => '%'.$this->get_search().'%',
            'currentuser' => $USER->id,
        ];

        $modules = $DB->get_records_sql("SELECT * FROM {modules} WHERE visible = 1 AND name != 'subsection'");
        $moduleunions = [];
        foreach ($modules as $module) {
            // Clean the module name and verify table exists.
            $tablename = clean_param($module->name, PARAM_ALPHANUMEXT);
            if ($DB->get_manager()->table_exists($tablename)) {
                // Use proper Moodle DB table name formatting.
                $moduleunions[] = "SELECT '".$DB->sql_compare_text($module->name).
                    "' as modname, id, name, intro FROM {".$tablename."}";
            }
        }
        $modulesql = implode(" UNION ALL ", $moduleunions);

        $select = " SELECT DISTINCT c.id, c.fullname, c.shortname, c.visible, c.sortorder,
            COALESCE(ul.timeaccess, 0) AS timeaccess ";
        $from   = " FROM {course} c
                    LEFT JOIN {user_lastaccess} ul ON ul.courseid = c.id AND ul.userid = :currentuser
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

        $where  = " WHERE c.id > 1 AND (".$DB->sql_like('c.fullname', ':fullnamesearch', false)." OR ".
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

        $orderby = " ORDER BY c.sortorder";

        // Add sorting.
        switch($this->sorttype) {
            case 'alphabetical':
                $orderby = " ORDER BY c.fullname ASC";
                break;
            case 'lastaccessed':
                $orderby = " ORDER BY timeaccess DESC";
                break;
        }

        if ($this->currentcourseid !== null && !$this->includecurrentcourse) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }

        $limit = '';

        $limitfrom = $this->page;
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
            // If mysqli.
            if ($limitfrom || $limitnum) {
                if ($limitnum < 1) {
                    $limitnum = "18446744073709551615";
                }
                $limit .= " LIMIT $limitfrom, $limitnum";
            }
        }

        $params += $this->sqlparams;

        return [$select.$ctxselect.$from.$ctxjoin.$where.$orderby.$limit, $params];
    }

    /**
     * Summary of search
     * @return array|int|null
     */
    public function search() {
        global $DB;
        if (!is_null($this->results)) {
            return $this->results;
        }

        $this->results = [];
        $this->totalcount = 0;
        $contextlevel = $this->get_itemcontextlevel();
        list($sql, $params) = $this->get_searchsql();

        // Get total number, to avoid some incorrect iterations.
        $countsql = preg_replace('/ORDER BY.*/', '', $sql);
        $totalcourses = $DB->count_records_sql("SELECT COUNT(*) FROM ($countsql) sel", $params);

        if ($totalcourses > 0) {
            // User to be checked is always the same (usually null, get it from first element).
            $firstcap = reset($this->requiredcapabilities);
            $userid = isset($firstcap['user']) ? $firstcap['user'] : null;
            // Extract caps to check, this saves us a bunch of iterations.
            $requiredcaps = [];
            foreach ($this->requiredcapabilities as $cap) {
                $requiredcaps[] = $cap['capability'];
            }
            // Iterate while we have records and haven't reached $this->maxresults.
            $resultset = $DB->get_recordset_sql($sql, $params);
            foreach ($resultset as $result) {
                \context_helper::preload_from_record($result);
                $classname = \context_helper::get_class_for_level($contextlevel);
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
                if ($this->sorttype == 'relevance') {
                    $result->similarityscore = $this->get_relevance_score($result->id);
                }
                $this->results[$result->id] = $result;
            }
            $resultset->close();
        }

        if ($this->sorttype == 'relevance') {

            usort($this->results, function($a, $b) {
                // Convert similarity scores to float for accurate comparison.
                $scorea = (float)$a->similarityscore;
                $scoreb = (float)$b->similarityscore;

                // Compare scores with higher precision.
                if ($scoreb > $scorea) {
                    return 1;
                } else if ($scoreb < $scorea) {
                    return -1;
                }
                return 0;
            });
        }

        return $this->totalcount;
    }

    /**
     * Get calculated relevance score for a course.
     * @param mixed $courseid
     * @return float|int
     */
    public function get_relevance_score($courseid) {
        global $COURSE, $DB, $DB, $USER;

        $currentcourse = $DB->get_record('course', ['id' => $COURSE->id]);

        $course = get_course($courseid);
        $similarityscore = 0;

        // Calculate fullname similarity.
        if ($this->weights['fullname'] > 0) {
            similar_text(strtolower($currentcourse->fullname), strtolower($course->fullname), $percent);
            $similarityscore += ($percent / 100 * $this->weights['fullname']);
        }

        // Calculate shortname similarity.
        if ($this->weights['shortname'] > 0) {
            similar_text(strtolower($currentcourse->shortname), strtolower($course->shortname), $percent);
            $similarityscore += ($percent / 100 * $this->weights['shortname']);
        }

        // Calculate tags similarity.
        if ($this->weights['tags'] > 0) {
            $currenttags = \core_tag_tag::get_item_tags('core', 'course', $currentcourse->id);
            $coursetags = \core_tag_tag::get_item_tags('core', 'course', $course->id);

            $currenttagnames = array_map(function($tag) {
                return $tag->name;
            }, $currenttags);
            $coursetagnames = array_map(function($tag) {
                return $tag->name;
            }, $coursetags);

            $commontags = array_intersect($currenttagnames, $coursetagnames);
            $totaltags = array_unique(array_merge($currenttagnames, $coursetagnames));

            $tagsimilarity = count($totaltags) > 0 ? count($commontags) / count($totaltags) : 0;
            $similarityscore += ($tagsimilarity * $this->weights['tags']);
        }

        if ($this->weights['starred'] > 0) {
            $ufservice = \core_favourites\service_factory::get_service_for_user_context(\context_user::instance($USER->id));

            // Get all favorite courses for current user.
            $userfavorites = $ufservice->find_all_favourites('core_course', ['courses']);
            // Get favorite course IDs.
            $favcourseids = array_map(function($fav) {
                return $fav->itemid;
            }, $userfavorites);

            // If current user has favorited both the current course and the compared course.
            if (in_array($course->id, $favcourseids)) {
                $similarityscore += $this->weights['starred'];
            }
        }

        // Calculate custom fields similarity.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $fields = $handler->get_fields();
        foreach ($fields as $field) {
            if ($field->get('type') === 'text' || $field->get('type') === 'select') {
                $shortname = $field->get('shortname');
                $weight = $this->weights['customfield_' . $shortname];
                if ($weight > 0) {

                    $currentdata = $DB->get_field('customfield_data', 'value',
                    ['instanceid' => $currentcourse->id, 'fieldid' => $field->get('id')]);
                    $coursedata = $DB->get_field('customfield_data', 'value',
                    ['instanceid' => $course->id, 'fieldid' => $field->get('id')]);
                    if ($currentdata && $coursedata) {
                        similar_text(
                            strtolower($currentdata),
                            strtolower($coursedata),
                            $percent
                        );
                        $similarityscore += ($percent / 100 * $weight);
                    }
                }
            }
        }
        return $similarityscore;
    }


    /**
     * Gets the context level for the search result items.
     * @return CONTEXT_|int
     */
    public function get_itemcontextlevel() {
        return CONTEXT_COURSE;
    }


    /**
     * Returns an array of results from the search
     * @return array
     */
    public function get_results() {
        if ($this->results === null) {
            $this->search();
        }
        return $this->results;
    }

    /**
     * The current search string
     * @return string
     */
    public function get_search() {
        return ($this->search !== null) ? $this->search : '';
    }


    /**
     * Summary of normalise_limit_from_num
     * @param mixed $limitfrom
     * @param mixed $limitnum
     * @return int[]
     */
    public function normalise_limit_from_num($limitfrom, $limitnum) {
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

        return [$limitfrom, $limitnum];
    }


    /**
     * Get the total number of courses found by the search
     * @return int
     */
    public function get_total_course_count() {
        global $DB;
        list($sql, $params) = $this->get_searchsql();
        // Get total number, to avoid some incorrect iterations.
        $countsql = preg_replace('/ORDER BY.*/', '', $sql);
        $totalcourses = $DB->count_records_sql("SELECT COUNT(*) FROM ($countsql) sel", $params);
        return $totalcourses;
    }

}
