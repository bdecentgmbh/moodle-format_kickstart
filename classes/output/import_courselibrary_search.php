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
     * Cached total course count for the current search.
     * @var int|null
     */
    public $totalcountfull = null;

    /**
     * The course library search object.
     * @param array $config
     * @param mixed $currentcouseid
     * @param mixed $customfields
     * @param mixed $sorttype
     * @param mixed $page
     */
    public function __construct(
        array $config = [],
        $currentcouseid = null,
        $customfields = [],
        $sorttype = '',
        $page = 0
    ) {
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
            $method = 'set_' . $name;
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
     * The main query touches only {course}, {context} and {user_lastaccess}. Anything
     * search-related (course tags, activity name/intro, course-module tags, custom-field
     * filters) and capability filtering is folded in via subqueries so the main query
     * never multiplies rows or requires DISTINCT.
     *
     * @return array
     */
    public function get_searchsql() {
        global $USER;

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'currentuser'  => $USER->id,
        ];

        $ctxselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');

        // Include every {course} column that the render loop or core_course_list_element
        // touches directly so the list_element __get magic doesn't lazy-load each field
        // with a per-course DB query.
        $select = "SELECT c.id, c.category, c.fullname, c.shortname, c.idnumber, c.startdate,
                          c.summary, c.summaryformat, c.visible, c.sortorder,
                          COALESCE(ul.timeaccess, 0) AS timeaccess";

        $from = " FROM {course} c
                  LEFT JOIN {context} ctx
                         ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  LEFT JOIN {user_lastaccess} ul
                         ON ul.courseid = c.id AND ul.userid = :currentuser ";

        $where = " WHERE c.id > 1";

        if ($this->currentcourseid !== null) {
            $where .= " AND c.id <> :currentcourseid";
            $params['currentcourseid'] = $this->currentcourseid;
        }

        // Capability restriction (admins bypass).
        if (!is_siteadmin() && !empty($this->requiredcapabilities)) {
            [$capwhere, $capparams] = $this->get_capability_where();
            $where  .= $capwhere;
            $params += $capparams;
        }

        // Search term -> IN-clause of matching course IDs.
        if ($this->get_search() !== '') {
            [$searchwhere, $searchparams] = $this->get_search_where();
            $where  .= $searchwhere;
            $params += $searchparams;
        }

        // Custom-field filter -> EXISTS clause per filter.
        if (!empty($this->customfields)) {
            [$cfwhere, $cfparams] = $this->get_customfield_where();
            $where  .= $cfwhere;
            $params += $cfparams;
        }

        $orderby = $this->get_orderby_sql();
        $limit   = $this->get_limit_sql();

        $params += $this->sqlparams;

        return [$select . $ctxselect . $from . $where . $orderby . $limit, $params];
    }

    /**
     * Build the capability WHERE fragment using EXISTS subqueries.
     *
     * Replaces the previous LEFT JOIN role_capabilities / role_assignments pattern,
     * which fanned rows out and required DISTINCT.
     *
     * @return array [where, params]
     */
    private function get_capability_where() {
        global $USER;

        $conds  = [];
        $params = [];
        $i = 0;
        foreach ($this->requiredcapabilities as $cap) {
            $userid = (isset($cap['user']) && is_int($cap['user'])) ? $cap['user'] : $USER->id;
            $capparam  = "capability{$i}";
            $userparam = "capuser{$i}";
            $conds[] = "EXISTS (SELECT 1
                                  FROM {role_capabilities} rc{$i}
                                  JOIN {role_assignments}  ra{$i} ON ra{$i}.roleid = rc{$i}.roleid
                                 WHERE rc{$i}.capability = :{$capparam}
                                   AND ra{$i}.userid     = :{$userparam}
                                   AND ra{$i}.contextid  = ctx.id)";
            $params[$capparam]  = $cap['capability'];
            $params[$userparam] = $userid;
            $i++;
        }
        if (empty($conds)) {
            return ['', []];
        }
        return [' AND (' . implode(' OR ', $conds) . ')', $params];
    }

    /**
     * Build the search WHERE fragment as a single c.id IN (... UNION ...) clause.
     *
     * Each source (course fields, course tags, course-module tags, activity name/intro
     * per module table) is a small index-friendly query. The activity intro branch
     * respects the disableactivitydescriptionsearch setting.
     *
     * @return array [where, params]
     */
    private function get_search_where() {
        global $DB;

        $disableactivitydescriptionsearch =
            get_config('format_kickstart', 'disableactivitydescriptionsearch');

        $like = '%' . $this->get_search() . '%';
        $unions = [];
        $params = [];
        $i = 0;

        // Course fullname / shortname / summary.
        $unions[] = "SELECT id FROM {course} WHERE "
            . $DB->sql_like('fullname', ":s{$i}_fn", false) . " OR "
            . $DB->sql_like('shortname', ":s{$i}_sn", false) . " OR "
            . $DB->sql_like('summary', ":s{$i}_sm", false);
        $params["s{$i}_fn"] = $like;
        $params["s{$i}_sn"] = $like;
        $params["s{$i}_sm"] = $like;
        $i++;

        // Course tags.
        $unions[] = "SELECT ti.itemid AS id
                       FROM {tag_instance} ti
                       JOIN {tag} t ON t.id = ti.tagid
                      WHERE ti.itemtype = 'course'
                        AND ti.component = 'core'
                        AND " . $DB->sql_like('t.name', ":s{$i}_tag", false);
        $params["s{$i}_tag"] = $like;
        $i++;

        // Course-module tags.
        $unions[] = "SELECT cm.course AS id
                       FROM {course_modules} cm
                       JOIN {tag_instance} cmti
                            ON cmti.itemid = cm.id AND cmti.itemtype = 'course_modules'
                       JOIN {tag} cmt ON cmt.id = cmti.tagid
                      WHERE " . $DB->sql_like('cmt.name', ":s{$i}_cmtag", false);
        $params["s{$i}_cmtag"] = $like;
        $i++;

        // Activity name (+ intro unless disabled) -- one small query per module table.
        $modules = $DB->get_records_sql(
            "SELECT * FROM {modules} WHERE visible = 1 AND name != 'subsection'"
        );
        foreach ($modules as $module) {
            $tablename = clean_param($module->name, PARAM_ALPHANUMEXT);
            if (!$DB->get_manager()->table_exists($tablename)) {
                continue;
            }
            $hasintro = $DB->get_manager()->field_exists($tablename, 'intro');
            $cond = $DB->sql_like('m.name', ":s{$i}_name", false);
            $modparams = [
                "s{$i}_name" => $like,
                "s{$i}_mid"  => $module->id,
            ];
            if ($hasintro && !$disableactivitydescriptionsearch) {
                $cond .= " OR " . $DB->sql_like('m.intro', ":s{$i}_intro", false);
                $modparams["s{$i}_intro"] = $like;
            }
            $unions[] = "SELECT cm.course AS id
                           FROM {course_modules} cm
                           JOIN {" . $tablename . "} m ON m.id = cm.instance
                          WHERE cm.module = :s{$i}_mid AND ({$cond})";
            $params += $modparams;
            $i++;
        }

        return [' AND c.id IN (' . implode(' UNION ', $unions) . ')', $params];
    }

    /**
     * Build the custom-field WHERE fragment using EXISTS subqueries.
     *
     * @return array [where, params]
     */
    private function get_customfield_where() {
        $conds  = [];
        $params = [];
        $i = 0;
        foreach ($this->customfields as $shortname => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $conds[] = "EXISTS (SELECT 1
                                  FROM {customfield_data} cfd{$i}
                                  JOIN {customfield_field} cff{$i} ON cff{$i}.id = cfd{$i}.fieldid
                                 WHERE cfd{$i}.instanceid = c.id
                                   AND cff{$i}.shortname  = :cf{$i}_name
                                   AND cfd{$i}.value      = :cf{$i}_value)";
            $params["cf{$i}_name"]  = $shortname;
            $params["cf{$i}_value"] = $value;
            $i++;
        }
        if (empty($conds)) {
            return ['', []];
        }
        return [' AND (' . implode(' OR ', $conds) . ')', $params];
    }

    /**
     * Get the ORDER BY fragment.
     *
     * @return string
     */
    private function get_orderby_sql() {
        switch ($this->sorttype) {
            case 'alphabetical':
                return ' ORDER BY c.fullname ASC';
            case 'lastaccessed':
                return ' ORDER BY timeaccess DESC';
            default:
                return ' ORDER BY c.sortorder';
        }
    }

    /**
     * Get the LIMIT / OFFSET fragment for the current DB driver.
     *
     * @return string
     */
    private function get_limit_sql() {
        global $CFG;

        $perpage = get_config('format_kickstart', 'courselibraryperpage');
        [$limitfrom, $limitnum] = $this->normalise_limit_from_num($this->page * $perpage, $perpage);

        if ($CFG->dbtype == 'pgsql') {
            $limit = '';
            if ($limitnum) {
                $limit .= " LIMIT $limitnum";
            }
            if ($limitfrom) {
                $limit .= " OFFSET $limitfrom";
            }
            return $limit;
        }

        if ($limitfrom || $limitnum) {
            if ($limitnum < 1) {
                $limitnum = "18446744073709551615";
            }
            return " LIMIT $limitfrom, $limitnum";
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

        $this->results = [];
        $this->totalcount = 0;
        [$sql, $params] = $this->get_searchsql();

        // Count against the same WHERE without wrapping the SELECT in a subquery.
        // The new main query has no DISTINCT and no fan-out joins, so a direct count
        // is correct and much cheaper than SELECT COUNT(*) FROM ($sql) sel.
        $countsql = preg_replace('/ORDER BY.*/s', '', $sql);
        $countsql = preg_replace('/\sLIMIT\s.*/si', '', $countsql);
        $countsql = preg_replace('/^\s*SELECT\s.*?\sFROM\s/s', 'SELECT COUNT(*) FROM ', $countsql);
        $totalcourses = $DB->count_records_sql($countsql, $params);
        $this->totalcountfull = (int) $totalcourses;

        if ($totalcourses > 0) {
            // Iterate while we have records and haven't reached $this->maxresults.
            $resultset = $DB->get_recordset_sql($sql, $params);
            foreach ($resultset as $result) {
                \context_helper::preload_from_record($result);
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

        if ($this->sorttype == 'relevance' && !empty($this->results)) {
            // Compute all relevance scores in one batch (current course, tags,
            // favourites and customfield values are loaded once for the whole page
            // instead of per result).
            $this->calculate_relevance_scores_batch();

            usort($this->results, function ($a, $b) {
                $scorea = (float) ($a->similarityscore ?? 0);
                $scoreb = (float) ($b->similarityscore ?? 0);
                return $scoreb <=> $scorea;
            });
        }

        return $this->totalcount;
    }

    /**
     * Compute similarity scores for every course in $this->results in one batch.
     *
     * Replaces N+1 per-result calls to get_relevance_score() with a fixed number
     * of queries:
     *   - 1 query for the current course record
     *   - 1 query for tags of (current course + all result courses)
     *   - 1 query for the user's favourite courses
     *   - 1 query for customfield_data of (current course + all result courses)
     *
     * @return void
     */
    private function calculate_relevance_scores_batch() {
        global $DB, $COURSE, $USER;

        if (empty($this->results)) {
            return;
        }

        $resultids = array_keys($this->results);
        $allids = array_unique(array_merge([(int) $COURSE->id], $resultids));

        $currentcourse = $DB->get_record('course', ['id' => $COURSE->id]);
        if (!$currentcourse) {
            // Defensive: shouldn't happen but don't crash the page if it does.
            foreach ($this->results as $result) {
                $result->similarityscore = 0;
            }
            return;
        }

        // Batch tags.
        $tagsbycourse = [];
        if (!empty($this->weights['tags']) && $this->weights['tags'] > 0) {
            [$insql, $params] = $DB->get_in_or_equal($allids, SQL_PARAMS_NAMED);
            $sql = "SELECT ti.id, ti.itemid AS courseid, t.name
                      FROM {tag_instance} ti
                      JOIN {tag} t ON t.id = ti.tagid
                     WHERE ti.itemtype = 'course'
                       AND ti.component = 'core'
                       AND ti.itemid $insql";
            $tagrows = $DB->get_records_sql($sql, $params);
            foreach ($tagrows as $row) {
                $tagsbycourse[$row->courseid][] = $row->name;
            }
        }
        $currenttags = $tagsbycourse[$currentcourse->id] ?? [];

        // Batch favourites (one lookup for the user, then membership tests).
        $favouriteids = [];
        if (!empty($this->weights['starred']) && $this->weights['starred'] > 0) {
            $ufservice = \core_favourites\service_factory::get_service_for_user_context(
                \context_user::instance($USER->id)
            );
            $userfavourites = $ufservice->find_all_favourites('core_course', ['courses']);
            foreach ($userfavourites as $fav) {
                $favouriteids[(int) $fav->itemid] = true;
            }
        }

        // Batch customfield data for any text/select field with a configured weight > 0.
        $relevantfields = [];
        foreach ($this->weights as $key => $weight) {
            if (strpos($key, 'customfield_') === 0 && $weight > 0) {
                $relevantfields[substr($key, strlen('customfield_'))] = (int) $weight;
            }
        }
        $cfvaluesbycourse = [];
        if (!empty($relevantfields)) {
            [$insql, $params] = $DB->get_in_or_equal($allids, SQL_PARAMS_NAMED);
            [$fsql, $fparams] = $DB->get_in_or_equal(array_keys($relevantfields), SQL_PARAMS_NAMED, 'cffsn');
            $sql = "SELECT cfd.id, cfd.instanceid AS courseid, cff.shortname, cfd.value
                      FROM {customfield_data} cfd
                      JOIN {customfield_field} cff ON cff.id = cfd.fieldid
                     WHERE cfd.instanceid $insql
                       AND cff.shortname $fsql
                       AND (cff.type = 'text' OR cff.type = 'select')";
            $cfrows = $DB->get_records_sql($sql, array_merge($params, $fparams));
            foreach ($cfrows as $row) {
                $cfvaluesbycourse[$row->courseid][$row->shortname] = (string) $row->value;
            }
        }
        $currentcfvalues = $cfvaluesbycourse[$currentcourse->id] ?? [];

        foreach ($this->results as $result) {
            $score = 0;

            if (!empty($this->weights['fullname']) && $this->weights['fullname'] > 0) {
                similar_text(
                    strtolower($currentcourse->fullname),
                    strtolower($result->fullname),
                    $percent
                );
                $score += ($percent / 100 * $this->weights['fullname']);
            }

            if (!empty($this->weights['shortname']) && $this->weights['shortname'] > 0) {
                similar_text(
                    strtolower($currentcourse->shortname),
                    strtolower($result->shortname),
                    $percent
                );
                $score += ($percent / 100 * $this->weights['shortname']);
            }

            if (!empty($this->weights['tags']) && $this->weights['tags'] > 0) {
                $coursetags = $tagsbycourse[$result->id] ?? [];
                $intersect = array_intersect($currenttags, $coursetags);
                $union = array_unique(array_merge($currenttags, $coursetags));
                $sim = count($union) > 0 ? count($intersect) / count($union) : 0;
                $score += $sim * $this->weights['tags'];
            }

            if (
                !empty($this->weights['starred']) && $this->weights['starred'] > 0
                && isset($favouriteids[(int) $result->id])
            ) {
                $score += $this->weights['starred'];
            }

            if (!empty($relevantfields)) {
                $coursecfvalues = $cfvaluesbycourse[$result->id] ?? [];
                foreach ($relevantfields as $shortname => $weight) {
                    if (!isset($currentcfvalues[$shortname]) || !isset($coursecfvalues[$shortname])) {
                        continue;
                    }
                    similar_text(
                        strtolower($currentcfvalues[$shortname]),
                        strtolower($coursecfvalues[$shortname]),
                        $percent
                    );
                    $score += ($percent / 100 * $weight);
                }
            }

            $result->similarityscore = $score;
        }
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

            $currenttagnames = array_map(function ($tag) {
                return $tag->name;
            }, $currenttags);
            $coursetagnames = array_map(function ($tag) {
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
            $favcourseids = array_map(function ($fav) {
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
                    $currentdata = $DB->get_field(
                        'customfield_data',
                        'value',
                        ['instanceid' => $currentcourse->id, 'fieldid' => $field->get('id')]
                    );
                    $coursedata = $DB->get_field(
                        'customfield_data',
                        'value',
                        ['instanceid' => $course->id, 'fieldid' => $field->get('id')]
                    );
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
                debugging(
                    "Non-numeric limitfrom parameter detected: $strvalue, did you pass the correct arguments?",
                    DEBUG_DEVELOPER
                );
            } else if ($limitfrom < 0) {
                debugging(
                    "Negative limitfrom parameter detected: $limitfrom, did you pass the correct arguments?",
                    DEBUG_DEVELOPER
                );
            }

            if (!is_numeric($limitnum)) {
                $strvalue = var_export($limitnum, true);
                debugging(
                    "Non-numeric limitnum parameter detected: $strvalue, did you pass the correct arguments?",
                    DEBUG_DEVELOPER
                );
            } else if ($limitnum < 0) {
                debugging(
                    "Negative limitnum parameter detected: $limitnum, did you pass the correct arguments?",
                    DEBUG_DEVELOPER
                );
            }
        }

        $limitfrom = (int) $limitfrom;
        $limitnum  = (int) $limitnum;
        $limitfrom = max(0, $limitfrom);
        $limitnum  = max(0, $limitnum);

        return [$limitfrom, $limitnum];
    }


    /**
     * Get the total number of courses found by the search
     * @return int
     */
    public function get_total_course_count() {
        if ($this->totalcountfull === null) {
            $this->search();
        }
        return $this->totalcountfull;
    }
}
