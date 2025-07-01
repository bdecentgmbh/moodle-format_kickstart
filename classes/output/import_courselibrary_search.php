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
     * Cache for search results
     * @var array
     */
    private static $searchcache = [];

    /**
     * Cache for relevance scores
     * @var array
     */
    private static $relevancecache = [];

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
        // Cache weights to avoid repeated config calls.
        static $cachedweights = null;

        if ($cachedweights === null) {
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
            $cachedweights = $weights;
        }

        return $cachedweights;
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

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'currentuser' => $USER->id,
            'currentuserid' => $USER->id,
        ];

        // Base course query - much simpler and faster.
        $select = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.visible, c.sortorder,
                   COALESCE(ul.timeaccess, 0) AS timeaccess";

        $from = "FROM {course} c
                 LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)
                 LEFT JOIN {user_lastaccess} ul ON ul.courseid = c.id AND ul.userid = :currentuser";

        $where = "WHERE c.id > 1";

        // Add current course exclusion.
        if ($this->currentcourseid !== null) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }

        // Handle search terms efficiently.
        if (!empty($this->search)) {
            $searchconditions = [];
            $searchterm = '%' . $this->search . '%';

            // Direct course fields search.
            $searchconditions[] = $DB->sql_like('c.fullname', ':fullnamesearch', false);
            $searchconditions[] = $DB->sql_like('c.shortname', ':shortnamesearch', false);
            $searchconditions[] = $DB->sql_like('c.summary', ':descriptionsearch', false);

            $params['fullnamesearch'] = $searchterm;
            $params['shortnamesearch'] = $searchterm;
            $params['descriptionsearch'] = $searchterm;

            // Get course IDs that match tags (separate query for performance).
            $tagmatchids = $this->get_courses_matching_tags($this->search);
            if (!empty($tagmatchids)) {
                $tagids = array_keys($tagmatchids);
                list($taginsql, $tagparams) = $DB->get_in_or_equal($tagids, SQL_PARAMS_NAMED, 'tagmatch');
                $searchconditions[] = "c.id $taginsql";
                $params = array_merge($params, $tagparams);
            }

            // Get course IDs that match activities (separate query for performance).
            $activitymatchids = $this->get_courses_matching_activities($this->search);
            if (!empty($activitymatchids)) {
                $activityids = array_keys($activitymatchids);
                list($actinsql, $actparams) = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED, 'actmatch');
                $searchconditions[] = "c.id $actinsql";
                $params = array_merge($params, $actparams);
            }

            if (!empty($searchconditions)) {
                $where .= " AND (" . implode(" OR ", $searchconditions) . ")";
            } else {
                // If no search conditions match, ensure we return no results.
                $where .= " AND 1=0";
            }
        }

        // Add capability restrictions.
        if (!is_siteadmin() && !empty($this->requiredcapabilities)) {
            list($capjoin, $capwhere, $capparams) = $this->get_capability_sql();
            $from .= $capjoin;
            $where .= $capwhere;
            $params = array_merge($params, $capparams);
        }

        // Add custom field filters.
        if (!empty($this->customfields)) {
            list($cfjoin, $cfwhere, $cfparams) = $this->get_customfield_sql();
            $from .= $cfjoin;
            $where .= $cfwhere;
            $params = array_merge($params, $cfparams);
        }

        // Add sorting.
        $orderby = $this->get_orderby_sql();

        // Add pagination.
        $limit = $this->get_limit_sql();

        $sql = $select . " " . $from . " " . $where . " " . $orderby . " " . $limit;

        return [$sql, $params];
    }

    /**
     * Get courses matching tags
     * @param string $search
     * @return array
     */
    private function get_courses_matching_tags($search) {
        global $DB;

        $cachekey = 'tags_' . md5($search);
        if (isset(self::$searchcache[$cachekey])) {
            return self::$searchcache[$cachekey];
        }

        $sql = "SELECT DISTINCT ti.itemid as courseid
                FROM {tag} t
                JOIN {tag_instance} ti ON ti.tagid = t.id
                WHERE ti.itemtype = 'course'
                AND ti.component = 'core'
                AND " . $DB->sql_like('t.name', ':tagsearch', false);

        $params = ['tagsearch' => '%' . $search . '%'];
        $results = $DB->get_records_sql($sql, $params);

        self::$searchcache[$cachekey] = $results;
        return $results;
    }

    /**
     * Get courses matching activities
     * @param string $search
     * @return array
     */
    private function get_courses_matching_activities($search) {
        global $DB;

        $cachekey = 'activities_' . md5($search);
        if (isset(self::$searchcache[$cachekey])) {
            return self::$searchcache[$cachekey];
        }

        $searchterm = '%' . $search . '%';
        $results = [];

        // Get visible modules (use cache if available).
        if (isset(self::$searchcache['modules'])) {
            $modules = self::$searchcache['modules'];
        } else {
            $modules = $DB->get_records_sql("SELECT * FROM {modules} WHERE visible = 1 AND name != 'subsection'");
            self::$searchcache['modules'] = $modules;
        }

        foreach ($modules as $module) {
            $tablename = clean_param($module->name, PARAM_ALPHANUMEXT);
            if ($DB->get_manager()->table_exists($tablename)) {
                $columns = $DB->get_columns($tablename);
                $hasintro = isset($columns['intro']);

                // Build the SELECT clause based on whether intro field exists.
                $select = "SELECT DISTINCT cm.course as courseid" . ($hasintro ? ", m.intro" : ", '' as intro");

                // Build the WHERE clause - only search intro if it exists.
                $whereconditions = [$DB->sql_like('m.name', ':namesearch', false)];
                if ($hasintro) {
                    $whereconditions[] = $DB->sql_like('m.intro', ':introsearch', false);
                }

                $sql = "$select
                        FROM {course_modules} cm
                        JOIN {" . $tablename . "} m ON m.id = cm.instance
                        WHERE cm.module = :moduleid
                        AND (" . implode(" OR ", $whereconditions) . ")";

                $params = [
                    'moduleid' => $module->id,
                    'namesearch' => $searchterm,
                ];

                // Only add intro search param if the field exists.
                if ($hasintro) {
                    $params['introsearch'] = $searchterm;
                }

                try {
                    $moduleresults = $DB->get_records_sql($sql, $params);
                    foreach ($moduleresults as $record) {
                        $results[$record->courseid] = $record;
                    }
                } catch (Exception $e) {
                    // Skip this module if there's an error (e.g., table structure issues).
                    debugging("Error searching activities in module {$module->name}: " . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
            }
        }

        self::$searchcache[$cachekey] = $results;
        return $results;
    }

    /**
     * Get capability SQL components
     * @return array [join, where, params]
     */
    private function get_capability_sql() {
        $join = '';
        $where = '';
        $params = [];

        if (!empty($this->requiredcapabilities)) {
            $capconditions = [];
            $capindex = 0;

            foreach ($this->requiredcapabilities as $cap) {
                $capindex++;
                $join .= " LEFT JOIN {role_capabilities} rc{$capindex} ON rc{$capindex}.capability = :capability{$capindex}
                          LEFT JOIN {role_assignments} ra{$capindex} ON ra{$capindex}.roleid = rc{$capindex}.roleid
                          AND ra{$capindex}.contextid = ctx.id";

                $params["capability{$capindex}"] = $cap['capability'];

                if (isset($cap['user']) && is_int($cap['user'])) {
                    $capconditions[] = "(ra{$capindex}.userid = :capuser{$capindex})";
                    $params["capuser{$capindex}"] = $cap['user'];
                } else {
                    $capconditions[] = "(ra{$capindex}.userid = :currentuserid)";
                }
            }

            if (!empty($capconditions)) {
                $where = " AND (" . implode(" OR ", $capconditions) . ")";
            }
        }

        return [$join, $where, $params];
    }

    /**
     * Get custom field SQL components
     * @return array [join, where, params]
     */
    private function get_customfield_sql() {
        $join = '';
        $where = '';
        $params = [];

        if (!empty($this->customfields)) {
            $join = " LEFT JOIN {customfield_data} cfd ON cfd.instanceid = c.id
                      LEFT JOIN {customfield_field} cff ON cff.id = cfd.fieldid";

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
                $where = " AND (" . implode(" OR ", $customfieldconditions) . ")";
            }
        }

        return [$join, $where, $params];
    }

    /**
     * Get ORDER BY SQL
     * @return string
     */
    private function get_orderby_sql() {
        switch($this->sorttype) {
            case 'alphabetical':
                return "ORDER BY c.fullname ASC";
            case 'lastaccessed':
                return "ORDER BY timeaccess DESC";
            case 'relevance':
                return "ORDER BY c.sortorder"; // Will be sorted after relevance calculation.
            default:
                return "ORDER BY c.sortorder";
        }
    }

    /**
     * Get LIMIT SQL
     * @return string
     */
    private function get_limit_sql() {
        global $CFG;

        $perpage = get_config("format_kickstart", "courselibraryperpage");
        list($limitfrom, $limitnum) = $this->normalise_limit_from_num($this->page * $perpage, $perpage);

        if ($CFG->dbtype == 'pgsql') {
            $limit = '';
            if ($limitnum) {
                $limit .= " LIMIT $limitnum";
            }
            if ($limitfrom) {
                $limit .= " OFFSET $limitfrom";
            }
            return $limit;
        } else {
            if ($limitfrom || $limitnum) {
                if ($limitnum < 1) {
                    $limitnum = "18446744073709551615";
                }
                return " LIMIT $limitfrom, $limitnum";
            }
        }
        return '';
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

        // Create cache key based on search parameters.
        $cachekey = $this->get_cache_key();
        if (isset(self::$searchcache[$cachekey])) {
            $cached = self::$searchcache[$cachekey];
            $this->results = $cached['results'];
            $this->totalcount = $cached['totalcount'];
            $this->hasmoreresults = $cached['hasmoreresults'];
            return $this->results;
        }

        $this->results = [];
        $this->totalcount = 0;

        list($sql, $params) = $this->get_searchsql();

        // Get total count efficiently.
        $countsql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(DISTINCT c.id) FROM', $sql);
        $countsql = preg_replace('/ORDER BY.*/', '', $countsql);
        $countsql = preg_replace('/LIMIT.*/', '', $countsql);

        $totalcourses = $DB->count_records_sql($countsql, $params);

        if ($totalcourses > 0) {
            $resultset = $DB->get_recordset_sql($sql, $params);
            foreach ($resultset as $result) {
                \context_helper::preload_from_record($result);

                if ($this->totalcount + 1 > $this->maxresults) {
                    $this->hasmoreresults = true;
                    break;
                }

                $this->totalcount++;
                $this->results[$result->id] = $result;
            }
            $resultset->close();

            // Calculate relevance scores efficiently if needed.
            if ($this->sorttype == 'relevance' && !empty($this->results)) {
                $this->calculate_relevance_scores_batch();

                usort($this->results, function($a, $b) {
                    $scorea = (float)($a->similarityscore ?? 0);
                    $scoreb = (float)($b->similarityscore ?? 0);
                    return $scoreb <=> $scorea;
                });
            }
        }

        // Cache results.
        self::$searchcache[$cachekey] = [
            'results' => $this->results,
            'totalcount' => $this->totalcount,
            'hasmoreresults' => $this->hasmoreresults,
        ];

        return $this->totalcount;
    }

    /**
     * Calculate relevance scores in batch for better performance
     */
    private function calculate_relevance_scores_batch() {
        global $DB, $COURSE, $USER;

        if (empty($this->results)) {
            return;
        }

        $courseids = array_keys($this->results);
        $currentcourse = $DB->get_record('course', ['id' => $COURSE->id]);

        // Batch fetch course data.
        list($insql, $params) = $DB->get_in_or_equal($courseids);
        $courses = $DB->get_records_sql("SELECT * FROM {course} WHERE id $insql", $params);

        // Batch fetch tags if needed.
        $coursetags = [];
        $currenttags = [];
        if ($this->weights['tags'] > 0) {
            $currenttags = $this->get_course_tags($currentcourse->id);
            $coursetags = $this->get_multiple_course_tags($courseids);
        }

        // Batch fetch favorites if needed.
        $favorites = [];
        if ($this->weights['starred'] > 0) {
            $favorites = $this->get_user_favorite_courses($USER->id);
        }

        // Batch fetch custom field data if needed.
        $customfielddata = [];
        if ($this->has_custom_field_weights()) {
            $customfielddata = $this->get_multiple_course_customfields($courseids);
            $currentcustomfields = $this->get_course_customfields($currentcourse->id);
        }

        // Calculate scores.
        foreach ($this->results as $courseid => $result) {
            $cachekey = "relevance_{$currentcourse->id}_{$courseid}";

            if (isset(self::$relevancecache[$cachekey])) {
                $result->similarityscore = self::$relevancecache[$cachekey];
                continue;
            }

            $course = $courses[$courseid] ?? null;
            if (!$course) {
                continue;
            }

            $score = 0;

            // Fullname similarity.
            if ($this->weights['fullname'] > 0) {
                similar_text(strtolower($currentcourse->fullname), strtolower($course->fullname), $percent);
                $score += ($percent / 100 * $this->weights['fullname']);
            }

            // Shortname similarity.
            if ($this->weights['shortname'] > 0) {
                similar_text(strtolower($currentcourse->shortname), strtolower($course->shortname), $percent);
                $score += ($percent / 100 * $this->weights['shortname']);
            }

            // Tags similarity.
            if ($this->weights['tags'] > 0 && isset($coursetags[$courseid])) {
                $score += $this->calculate_tag_similarity($currenttags, $coursetags[$courseid]);
            }

            // Starred weight.
            if ($this->weights['starred'] > 0 && in_array($courseid, $favorites)) {
                $score += $this->weights['starred'];
            }

            // Custom fields similarity.
            if (!empty($customfielddata[$courseid]) && !empty($currentcustomfields)) {
                $score += $this->calculate_customfield_similarity($currentcustomfields, $customfielddata[$courseid]);
            }

            $result->similarityscore = $score;
            self::$relevancecache[$cachekey] = $score;
        }
    }

    /**
     * Get cache key for search results
     * @return string
     */
    private function get_cache_key() {
        $keydata = [
            'search' => $this->search,
            'sorttype' => $this->sorttype,
            'page' => $this->page,
            'currentcourseid' => $this->currentcourseid,
            'customfields' => $this->customfields,
            'capabilities' => $this->requiredcapabilities,
        ];
        return md5(serialize($keydata));
    }

    /**
     * Get course tags efficiently
     * @param int $courseid
     * @return array
     */
    private function get_course_tags($courseid) {
        global $DB;

        $sql = "SELECT t.name
                FROM {tag} t
                JOIN {tag_instance} ti ON ti.tagid = t.id
                WHERE ti.itemid = ? AND ti.itemtype = 'course' AND ti.component = 'core'";

        return $DB->get_fieldset_sql($sql, [$courseid]);
    }

    /**
     * Get tags for multiple courses efficiently
     * @param array $courseids
     * @return array
     */
    private function get_multiple_course_tags($courseids) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids);
        $sql = "SELECT CONCAT(ti.itemid, '_', t.id) as uniqueid, ti.itemid as courseid, t.name
                FROM {tag} t
                JOIN {tag_instance} ti ON ti.tagid = t.id
                WHERE ti.itemid $insql AND ti.itemtype = 'course' AND ti.component = 'core'";

        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $record) {
            if (!isset($result[$record->courseid])) {
                $result[$record->courseid] = [];
            }
            $result[$record->courseid][] = $record->name;
        }

        return $result;
    }

    /**
     * Get user's favorite courses
     * @param int $userid
     * @return array
     */
    private function get_user_favorite_courses($userid) {
        $ufservice = \core_favourites\service_factory::get_service_for_user_context(\context_user::instance($userid));
        $userfavorites = $ufservice->find_all_favourites('core_course', ['courses']);

        return array_map(function($fav) {
            return $fav->itemid;
        }, $userfavorites);
    }

    /**
     * Check if any custom field weights are configured
     * @return bool
     */
    private function has_custom_field_weights() {
        foreach ($this->weights as $key => $weight) {
            if (strpos($key, 'customfield_') === 0 && $weight > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get custom field data for a course
     * @param int $courseid
     * @return array
     */
    private function get_course_customfields($courseid) {
        global $DB;

        $sql = "SELECT CONCAT(cff.shortname, '_', cfd.id) as uniqueid, cff.shortname, cfd.value
                FROM {customfield_data} cfd
                JOIN {customfield_field} cff ON cff.id = cfd.fieldid
                WHERE cfd.instanceid = ? AND (cff.type = 'text' OR cff.type = 'select')";

        $records = $DB->get_records_sql($sql, [$courseid]);

        $result = [];
        foreach ($records as $record) {
            $result[$record->shortname] = $record->value;
        }

        return $result;
    }

    /**
     * Get custom field data for multiple courses
     * @param array $courseids
     * @return array
     */
    private function get_multiple_course_customfields($courseids) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids);
        $sql = "SELECT CONCAT(cfd.instanceid, '_', cff.id) as uniqueid, cfd.instanceid as courseid, cff.shortname, cfd.value
                FROM {customfield_data} cfd
                JOIN {customfield_field} cff ON cff.id = cfd.fieldid
                WHERE cfd.instanceid $insql AND (cff.type = 'text' OR cff.type = 'select')";

        $records = $DB->get_records_sql($sql, $params);

        $result = [];
        foreach ($records as $record) {
            if (!isset($result[$record->courseid])) {
                $result[$record->courseid] = [];
            }
            $result[$record->courseid][$record->shortname] = $record->value;
        }

        return $result;
    }

    /**
     * Calculate tag similarity efficiently
     * @param array $currenttags
     * @param array $coursetags
     * @return float
     */
    private function calculate_tag_similarity($currenttags, $coursetags) {
        $commontags = array_intersect($currenttags, $coursetags);
        $totaltags = array_unique(array_merge($currenttags, $coursetags));

        $similarity = count($totaltags) > 0 ? count($commontags) / count($totaltags) : 0;
        return $similarity * $this->weights['tags'];
    }

    /**
     * Calculate custom field similarity efficiently
     * @param array $currentfields
     * @param array $coursefields
     * @return float
     */
    private function calculate_customfield_similarity($currentfields, $coursefields) {
        $score = 0;

        foreach ($currentfields as $shortname => $currentvalue) {
            if (!isset($coursefields[$shortname])) {
                continue;
            }

            $weight = $this->weights['customfield_' . $shortname] ?? 0;
            if ($weight <= 0) {
                continue;
            }

            $coursevalue = $coursefields[$shortname];

            similar_text(
                strtolower($currentvalue),
                strtolower($coursevalue),
                $percent
            );
            $score += ($percent / 100 * $weight);
        }

        return $score;
    }

    /**
     * Get calculated relevance score for a course (legacy method for backward compatibility)
     * @param mixed $courseid
     * @return float|int
     */
    public function get_relevance_score($courseid) {
        global $COURSE, $DB, $USER;

        $cachekey = "relevance_{$COURSE->id}_{$courseid}";
        if (isset(self::$relevancecache[$cachekey])) {
            return self::$relevancecache[$cachekey];
        }

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

        self::$relevancecache[$cachekey] = $similarityscore;
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

        // We explicitly treat these cases as 0.
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

        // Use a more efficient count query.
        list($sql, $params) = $this->get_searchsql();

        // Convert to count query.
        $countsql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(DISTINCT c.id) FROM', $sql);
        $countsql = preg_replace('/ORDER BY.*/', '', $countsql);
        $countsql = preg_replace('/LIMIT.*/', '', $countsql);

        return $DB->count_records_sql($countsql, $params);
    }

    /**
     * Clear search cache (useful for testing or when data changes)
     */
    public static function clear_cache() {
        self::$searchcache = [];
        self::$relevancecache = [];
    }

    /**
     * Preload commonly needed data to reduce database queries
     */
    public function preload_data() {
        global $DB, $USER;

        // Preload user favorites.
        if ($this->weights['starred'] > 0) {
            $this->get_user_favorite_courses($USER->id);
        }

        // Preload module information.
        $modules = $DB->get_records_sql("SELECT * FROM {modules} WHERE visible = 1 AND name != 'subsection'");
        // Store in static cache for reuse.
        self::$searchcache['modules'] = $modules;
    }
}
