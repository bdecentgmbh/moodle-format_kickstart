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

use renderer_base;

/**
 * Widget that displays courses to import inside course.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_kickstart
 */
class import_course_list implements \renderable, \templatable {
    /**
     * Summary of filtercustomfields
     * @var array
     */
    public $filtercustomfields;

    /**
     * Summary of sorttype
     * @var string
     */
    public $sorttype;

    /**
     * Summary of page
     * @var int
     */
    public $page;

    /**
     * Constructor for improt course list.
     * @param array $filtercustomfields
     * @param string $sorttype
     * @param int $page
     */
    public function __construct(array $filtercustomfields = [], string $sorttype = '', int $page = 0) {
        $this->filtercustomfields = $filtercustomfields;
        $this->sorttype = $sorttype;
        $this->page = $page;
    }

    /**
     * Get variables for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $COURSE, $PAGE, $OUTPUT, $SITE;

        // Obviously not... show the selector so one can be chosen.
        $url = new \moodle_url('/local/kickstart_pro/import.php', ['id' => $COURSE->id]);
        $component = new import_courselibrary_search(
            ['url' => $url],
            $COURSE->id,
            $this->filtercustomfields,
            $this->sorttype,
            $this->page
        );
        $courses = [];
        $html = '';

        $displaycourselibraryfields = get_config('format_kickstart', 'displaycourselibraryfields');
        $displaycourselibraryfields = explode(",", $displaycourselibraryfields);

        $tagrenderer = $PAGE->get_renderer('core', 'tag');
        if ($component->get_count() === 0) {
            $html .= $OUTPUT->notification(get_string('nomatchingcourses', 'backup'));
        } else {
            $target = get_config('format_kickstart', 'importtarget') ?: \backup::TARGET_EXISTING_DELETING;

            // Pre-load per-page metadata in batch so we don't issue N+1 queries
            // inside the loop below.
            $pagecourseids = [];
            foreach ($component->get_results() as $course) {
                if ($course->id != $SITE->id && $course->id != $COURSE->id) {
                    $pagecourseids[] = $course->id;
                }
            }
            $tagsbycourse        = $this->load_tags_for_courses($pagecourseids);
            $customfieldsbycourse = $this->load_customfields_for_courses($pagecourseids);

            foreach ($component->get_results() as $course) {
                if ($course->id == $SITE->id || $course->id == $COURSE->id) {
                    continue;
                }
                $course->url = new \moodle_url('/course/view.php', ['id' => $course->id]);

                if (in_array("fullname", $displaycourselibraryfields)) {
                    $course->fullnamecourse = format_string($course->fullname, true, [
                        'context' => \context_course::instance($course->id),
                    ]);
                }

                $courseinfo = new \core_course_list_element($course);
                $customfields = [];
                if (!empty($customfieldsbycourse[$course->id])) {
                    $customfieldoutput = $PAGE->get_renderer('core_customfield');
                    foreach ($customfieldsbycourse[$course->id] as $data) {
                        $field = $data->get_field();
                        if (in_array('customfield_' . $field->get('shortname'), $displaycourselibraryfields)) {
                            $fd = new \core_customfield\output\field_data($data);
                            $customfields[] = ["value" => $customfieldoutput->render($fd)];
                        }
                    }
                }
                $course->customfields = $customfields;

                if (in_array("importcourse", $displaycourselibraryfields)) {
                    $course->importurl = new \moodle_url('/local/kickstart_pro/import.php', [
                        'id' => $COURSE->id,
                        'importid' => $course->id,
                        'target' => $target,
                    ]);
                }

                if (in_array("tags", $displaycourselibraryfields)) {
                    $course->tags = implode(', ', $tagsbycourse[$course->id] ?? []);
                }

                if (in_array("idnumber", $displaycourselibraryfields)) {
                    $course->idnumber = $courseinfo->idnumber;
                }

                if (in_array("startdate", $displaycourselibraryfields)) {
                    $course->startdate = userdate($courseinfo->startdate, get_string('strftimedatetime', 'langconfig'));
                }

                if (in_array("categorypath", $displaycourselibraryfields)) {
                    // core_course_category::get() is statically cached, so this is cheap on
                    // subsequent calls within the request. get_nested_name() walks the parents
                    // using the same cache.
                    $category = \core_course_category::get($courseinfo->category, MUST_EXIST, true);
                    $course->categorypath = $category->get_nested_name(false, ' > ') . " > "
                        . $courseinfo->get_formatted_shortname();
                }

                // Course contents are loaded lazily via the
                // format_kickstart_output_fragment_get_library_coursecontents fragment
                // when the user clicks "Show contents".
                $course->maincourse = $COURSE->id;
                $courses[] = $course;
            }
        }
        $page = $this->page;
        $pagination = $OUTPUT->paging_bar(
            $component->get_total_course_count(),
            $page,
            get_config('format_kickstart', 'courselibraryperpage'),
            $PAGE->url
        );
        return [
            'searchterm' => $component->get_search() ?
                get_string('searchterm', 'format_kickstart', ['term' => $component->get_search()]) : null,
            'searchurl' => $PAGE->url,
            'html' => $html,
            'courses' => $courses,
            'nocourseslabel' => $OUTPUT->notification(get_string('nocoursesexists', 'format_kickstart'), 'info', false),
            'haspro' => format_kickstart_has_pro(),
            'searchlabel' => get_string('showing', 'format_kickstart', ['count' => $component->get_count()]),
            'moreresults' => $component->has_more_results(),
            'prourl' => 'https://bdecent.de/products/moodle-plugins/kickstart-course-wizard-pro/',
            'courseurl' => new \moodle_url('/course/view.php', ['id' => $COURSE->id]),
            'pagination' => $pagination,
            'showcontents' => in_array("showcontents", $displaycourselibraryfields) ? true : false,
        ];
    }

    /**
     * Batch-load tag names for a set of course IDs.
     *
     * Returns array keyed by courseid, each value an array of tag display names.
     * One DB query instead of one per course.
     *
     * @param int[] $courseids
     * @return array<int,string[]>
     */
    private function load_tags_for_courses(array $courseids) {
        global $DB;
        if (empty($courseids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT ti.id, ti.itemid AS courseid, t.rawname, t.name
                  FROM {tag_instance} ti
                  JOIN {tag} t ON t.id = ti.tagid
                 WHERE ti.itemtype = 'course'
                   AND ti.component = 'core'
                   AND ti.itemid $insql
              ORDER BY ti.itemid, ti.ordering, t.name";
        $rows = $DB->get_records_sql($sql, $params);
        $result = array_fill_keys($courseids, []);
        foreach ($rows as $row) {
            $result[$row->courseid][] = !empty($row->rawname) ? $row->rawname : $row->name;
        }
        return $result;
    }

    /**
     * Batch-load customfield instance data for a set of course IDs.
     *
     * Returns array keyed by courseid, each value an array of \core_customfield\data_controller
     * instances suitable for rendering via \core_customfield\output\field_data.
     * Uses the customfield handler once and then groups the records in PHP.
     *
     * @param int[] $courseids
     * @return array<int,\core_customfield\data_controller[]>
     */
    private function load_customfields_for_courses(array $courseids) {
        if (empty($courseids)) {
            return [];
        }
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $result = array_fill_keys($courseids, []);
        // The handler caches the field schema; get_instance_data() per course is one
        // query for the data rows. Moodle does not expose a batch API, so we still
        // call it per course but only for the page (default 10), not for every
        // course in the result set.
        foreach ($courseids as $courseid) {
            $result[$courseid] = $handler->get_instance_data($courseid);
        }
        return $result;
    }

    /**
     * Trim the summary of the section.
     * @param mixed $summary
     * @param mixed $trimchar
     */
    public function sectionsummary_trim_char($summary, $trimchar = 25) {

        if (str_word_count($summary) < $trimchar) {
            return '';
        }
        $arrstr = explode(" ", $summary);
        $slicearr = array_slice($arrstr, 0, $trimchar);
        $strarr = implode(" ", $slicearr);
        $strarr .= '...';
        return $strarr;
    }

    /**
     * Get the course library course section contents.
     * @param mixed $courseid
     * @return array
     */
    public function get_course_contents($courseid) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');
        // Create return value.
        $coursecontents = [];
        $course = get_course($courseid);
        $coursecontext = \context_course::instance($course->id);
        $modinfo = get_fast_modinfo($course);
        $modinfosections = $modinfo->get_sections();
        $sections = $modinfo->get_section_info_all();
        $hassections = count($sections) > 1; // More than 1 because the general section is always present.
        foreach ($sections as $key => $section) {
            $sectioncontents = [];
            $sectionvalues = [
                'id' => $section->id,
                'name' => get_string("section") . " " . ($section->section + 1) . ": " . get_section_name($course, $section),
                'visible' => $section->visible,
                'section' => $section->section,
                'uservisible' => $section->uservisible,
                'notgeneral' => $section->section != 0 ? 1 : 0,
                'expanded' => (!$hassections && $section->section == 0) ? 1 : 0,
                'collapsible' => ($hassections || $section->section != 0),
                'datatoggle' => ($CFG->branch >= 500) ? 'data-bs-toggle' : 'data-toggle',
                'datatarget' => ($CFG->branch >= 500) ? 'data-bs-target' : 'data-target',
            ];

            $options = (object) ['noclean' => true];

            [$sectionvalues['summary'], $sectionvalues['summaryformat']] =
            external_format_text(
                $section->summary,
                $section->summaryformat,
                $coursecontext->id,
                'course',
                'section',
                $section->id,
                $options
            );
            $modtrimlength = !empty(get_config('format_kickstart', 'modtrimlength')) ?
                get_config('format_kickstart', 'modtrimlength') : 25;
            $sectionvalues['trimsummary'] = $this->sectionsummary_trim_char(
                format_string($sectionvalues['summary']),
                $modtrimlength
            );
            $sectionmodulenames = [];
            if (!empty($modinfosections[$section->section])) {
                foreach ($modinfosections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];
                    if (!$cm->uservisible) {
                        continue;
                    }

                    $module = [];
                    $modcontext = \context_module::instance($cm->id);

                    $module['id'] = $cm->id;
                    $module['name'] = external_format_string($cm->name, $modcontext->id);
                    $module['instance'] = $cm->instance;
                    $module['contextid'] = $modcontext->id;
                    $module['modname'] = (string) $cm->modname;
                    $module['modplural'] = (string) $cm->modplural;
                    $module['modicon'] = $cm->get_icon_url()->out(false);
                    $sectionmodulenames[$cm->modname] = isset($sectionmodulenames[$cm->modname]) ?
                        $sectionmodulenames[$cm->modname] + 1 : 1;
                    // Url of the module.
                    $url = $cm->url;
                    if ($url) {
                        $module['url'] = $url->out(false);
                    } else {
                        $module['url'] = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
                    }

                    $module['editurl'] = (new \moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false);

                    $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $modcontext);

                    $module['visible'] = $cm->visible;
                    $module['visibleoncoursepage'] = $cm->visibleoncoursepage;
                    $module['uservisible'] = $cm->uservisible;

                    // Availability date (also send to user who can see hidden module).
                    if ($CFG->enableavailability && ($canviewhidden || $canupdatecourse)) {
                        $module['availability'] = $cm->availability;
                    }

                    $sectioncontents[] = $module;
                }
            }

            $formattedstring = [];
            foreach ($sectionmodulenames as $module => $count) {
                if ($count == 1) {
                    $formattedstring[]['value'] = $count . ' ' . get_string('pluginname', $module);
                } else {
                    $formattedstring[]['value'] = $count . ' ' . get_string('modulenameplural', $module);
                }
            }

            $sectionvalues['sectionmodulenames'] = $formattedstring;
            $sectionvalues['modules'] = $sectioncontents;
            $sectionvalues['nomdoules'] = count($sectioncontents) == 0 ? true : false;
            $sectionvalues['courseid'] = $courseid;
            $coursecontents[$key] = $sectionvalues;
        }
        return $coursecontents;
    }
}
