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
class import_course_list implements \templatable, \renderable {

    public $filtercustomfields;

    public $sorttype;

    public $page;

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
        $component = new import_courselibrary_search(['url' => $url], null, $this->filtercustomfields, $this->sorttype, $this->page);
        $courses = [];
        $html = '';

        $displaycourselibraryfields = get_config('format_kickstart', 'displaycourselibraryfields');
        $displaycourselibraryfields = explode(",", $displaycourselibraryfields);


        $tagrenderer = $PAGE->get_renderer('core', 'tag');
        if ($component->get_count() === 0) {
            $html .= $OUTPUT->notification(get_string('nomatchingcourses', 'backup'));
        } else {
            $target = get_config('format_kickstart', 'importtarget') ?: \backup::TARGET_EXISTING_DELETING;
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
                if ($courseinfo->has_custom_fields()) {
                    $handler = \core_customfield\handler::get_handler('core_course', 'course');
                    $fieldsdata = $handler->get_instance_data($course->id);
                    $customfieldoutput = $PAGE->get_renderer('core_customfield');
                    foreach ($fieldsdata as $data) {
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

                $coursetags = \core_tag_tag::get_item_tags_array('core', 'course', $course->id);
                if (in_array("tags", $displaycourselibraryfields)) {
                    $course->tags = implode(', ', $coursetags);
                }

                if (in_array("idnumber", $displaycourselibraryfields)) {
                    $course->idnumber = $courseinfo->idnumber;
                }

                if (in_array("startdate", $displaycourselibraryfields)) {
                    $course->startdate = userdate($courseinfo->startdate, get_string('strftimedatetime', 'langconfig'));
                }

                // Get category path.
                $category = \core_course_category::get($courseinfo->category);
                $categorypath = $category->get_nested_name(false, ' > ');

                $path = $categorypath. " > ".$courseinfo->get_formatted_shortname();
                if (in_array("categorypath", $displaycourselibraryfields)) {
                    $course->categorypath = $path;
                }

                $course->contents = $this->get_course_contents($course->id);
                $course->maincourse = $COURSE->id;
                $courses[] = $course;
            }
        }
        $page = $this->page;
        $paginationurl = new \moodle_url($PAGE->url, ['page' => $page]);
        $pagination =  $OUTPUT->paging_bar($component->get_total_course_count(), $page, get_config('format_kickstart', 'courselibraryperpage'), $PAGE->url);
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
            'showcontents' => in_array("showcontents", $displaycourselibraryfields) ? true : false
        ];
    }

    public function sectionsummary_trim_char($summary, $trimchar = 25) {

        if (str_word_count($summary) < $trimchar) {
            return $summary;
        }
        $arrstr = explode(" ", $summary);
        $slicearr = array_slice($arrstr, 0, $trimchar);
        $strarr = implode(" ", $slicearr);
        $strarr .= '...';
        return $strarr;
    }


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
                'name' => get_string("section") . " " . $section->section + 1 . ": " .get_section_name($course, $section),
                'visible' => $section->visible,
                'section' => $section->section,
                'uservisible' => $section->uservisible,
                'notgeneral' => $section->section != 0 ? 1 : 0,
                'expanded' => (!$hassections && $section->section == 0) ? 1 : 0,
                'collapsible' => ($hassections || $section->section != 0),
            ];

            $options = (object) ['noclean' => true];

            list($sectionvalues['summary'], $sectionvalues['summaryformat']) =
            external_format_text($section->summary, $section->summaryformat,$coursecontext->id,
                'course', 'section', $section->id, $options);
            $modtrimlength = !empty(get_config('format_kickstart', 'modtrimlength')) ? get_config('format_kickstart', 'modtrimlength') : 25;
            $sectionvalues['trimsummary'] = $this->sectionsummary_trim_char(format_string($sectionvalues['summary']), $modtrimlength);
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
                    $moduleplugname = get_string('pluginname', $module['modname']);
                    $sectionmodulenames[$moduleplugname] = isset($sectionmodulenames[$moduleplugname]) ? $sectionmodulenames[$moduleplugname] + 1 : 1;
                    // Url of the module.
                    $url = $cm->url;
                    if ($url) {
                        $module['url'] = $url->out(false);
                    } else {
                        $module['url'] = (new \moodle_url('/mod/'.$cm->modname.'/view.php', ['id' => $cm->id]))->out(false);
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

            $formattedString = [];
            foreach ($sectionmodulenames as $module => $count) {
                $formattedString[]['value'] = $count . ' ' . $module;
            }

            $sectionvalues['sectionmodulenames'] = $formattedString;
            $sectionvalues['modules'] = $sectioncontents;
            $sectionvalues['nomdoules'] = count($sectioncontents) == 0 ? true : false;
            $sectionvalues['courseid'] = $courseid;
            $coursecontents[$key] = $sectionvalues;
        }
        return $coursecontents;
    }
}
