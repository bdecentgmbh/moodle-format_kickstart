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
 * Widget that displays course templates inside a course.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\output;

use core\context\course as context_course;
use core\context\system as context_system;
use core\output\renderer_base;
use core\url as moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/cohort/lib.php");
require_once("$CFG->dirroot/course/format/kickstart/lib.php");

/**
 * Widget that displays course templates inside a course.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_kickstart
 */
class course_template_list implements \renderable, \templatable {
    /**
     * @var \stdClass
     */
    private $course;

    /**
     * @var int
     */
    private $userid;

    /**
     *
     * @var array
     */
    private $params;

    /**
     * Constructor.
     *
     * @param \stdClass $course
     * @param int $userid
     * @param array $params
     */
    public function __construct(\stdClass $course, $userid, array $params = []) {
        $this->course = course_get_format($course)->get_course();
        $this->userid = $userid;
        $this->params = $params;
    }

    /**
     * Get templates available to user.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_templates() {
        global $DB, $COURSE, $CFG;
        $limit = format_kickstart_has_pro() ? 0 : 2 * 2;

        // Add search conditions.
        $searchconditions = [];
        $searchparams = [];
        if (!empty($this->params)) {
            $action = $this->params['action'];
            if ($action == 'searchtemplate') {
                $search = $this->params['value'];
                $searchconditions[] = $DB->sql_like('title', ':title', false);
                $searchconditions[] = $DB->sql_like('description', ':description', false);
                $searchparams['title'] = '%' . $search . '%';
                $searchparams['description'] = '%' . $search . '%';

                // Add tag search through a subquery.
                $searchconditions[] = "EXISTS (
                    SELECT 1 FROM {tag_instance} ti
                    JOIN {tag} t ON ti.tagid = t.id
                    WHERE ti.itemid = {format_kickstart_template}.id
                    AND ti.itemtype = 'format_kickstart_template'
                    AND " . $DB->sql_like('t.name', ':tagname', false) . ")";
                $searchparams['tagname'] = '%' . $search . '%';
            }
        }

        $templates = [];
        $listtemplates = [];
        $orders = format_kickstart_has_pro() ? format_kickstart_get_templates() : [];
        if (!empty($orders)) {
            $params = $searchparams;
            [$insql, $inparams] = $DB->get_in_or_equal($orders, SQL_PARAMS_NAMED);
            $params += $inparams;

            $cases = [];
            foreach ($orders as $order => $templateid) {
                $cases[] = "WHEN id = $templateid THEN $order";
            }
            $subquery = '(CASE ' . implode(' ', $cases) . ' END)';

            $whereconditions = ['visible = 1', 'status = 1', "id $insql"];
            if (!empty($searchconditions)) {
                $whereconditions[] = '(' . implode(' OR ', $searchconditions) . ')';
            }

            $sql = "SELECT * FROM {format_kickstart_template} WHERE " . implode(" AND ", $whereconditions) . " ORDER BY $subquery";
            $listtemplates = $DB->get_records_sql($sql, $params);
        } else {
            if (!empty($searchconditions)) {
                $sql = "SELECT * FROM {format_kickstart_template}
                        WHERE visible = 1 AND status = 1
                        AND (" . implode(" OR ", $searchconditions) . ")";
                $listtemplates = $DB->get_records_sql($sql, $searchparams);
            } else {
                $listtemplates = $DB->get_records('format_kickstart_template', ['visible' => 1, 'status' => 1]);
            }
        }

        $templatecount = 0;
        $restrictionsbypassed = format_kickstart_template_restrictions_bypassed(
            context_course::instance($this->course->id),
            $this->userid
        );
        if (!empty($listtemplates)) {
            foreach ($listtemplates as $template) {
                // Skip templates the user may not use.
                if (!format_kickstart_can_use_template($template, $this->course->id, $this->userid)) {
                    continue;
                }

                // Flag templates a privileged user only sees through the bypass,
                // so the list can mark them as not available to all users.
                $template->restricted = $restrictionsbypassed && (!empty($template->restrictcohort)
                    || !empty($template->restrictcategory) || !empty($template->restrictuser)
                    || !empty($template->restrictrole));

                $template->description_formatted = format_text(file_rewrite_pluginfile_urls(
                    $template->description,
                    'pluginfile.php',
                    context_system::instance()->id,
                    'format_kickstart',
                    'description',
                    $template->id
                ), $template->descriptionformat);
                $template->title = format_string($template->title);
                $tags = [];
                foreach (\core_tag_tag::get_item_tags('format_kickstart', 'format_kickstart_template', $template->id) as $tag) {
                    $tags[] = '#' . $tag->get_display_name(false);
                }
                $template->hashtags = implode(' ', $tags);
                $template->link = new moodle_url('/course/format/kickstart/confirm.php', [
                    'template_id' => $template->id,
                    'course_id' => $COURSE->id,
                ]);
                if (!$template->courseformat) {
                    $templatecount++;
                }
                if ($limit > 0 && $templatecount > $limit) {
                    break;
                }
                if (format_kickstart_has_pro()) {
                    require_once($CFG->dirroot . "/local/kickstart_pro/lib.php");
                    if (function_exists('local_kickstart_pro_get_template_backimages')) {
                        $template->backimages = local_kickstart_pro_get_template_backimages($template->id);
                        $template->isbackimages = count($template->backimages);
                        $template->showimageindicators = !empty($template->backimages) && count($template->backimages)
                            > 1 ? true : false;
                    }

                    $fs = get_file_storage();
                    $files = $fs->get_area_files(
                        context_system::instance()->id,
                        'format_kickstart',
                        'course_backups',
                        $template->id,
                        '',
                        false
                    );
                    $files = array_values($files);

                    if (!isset($files[0]) && !$template->courseformat) {
                        $template->waitingadhoctask = true;
                    }
                }
                $templates[] = $template;
            }
        }
        return $templates;
    }

    /**
     * Get variables for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $DB;
        $templates = $this->get_templates();
        if (!format_kickstart_has_pro() && is_siteadmin()) {
            $template = new \stdClass();
            $template->isplaceholder = true;
            $template->title = get_string('getpro', 'format_kickstart');
            $template->link = 'https://bdecent.de/kickstart/';
            $templates[] = $template;
        }
        $templateview = $DB->get_field(
            'course_format_options',
            'value',
            ['name' => 'templatesview', 'courseid' => $this->course->id]
        );
        return [
            'templates' => $templates,
            'has_pro' => format_kickstart_has_pro(),
            'ajaxscript' => (AJAX_SCRIPT) ? true : false,
            'teacherinstructions' => isset($this->course->teacherinstructions) ?
                format_text($this->course->teacherinstructions, $this->course->teacherinstructionsformat) : '',
            'templateclass' => isset($templateview) && ($templateview == 'list') ? 'kickstart-list-view' : 'kickstart-tile-view',
            'notemplates' => empty($templates),
            'canmanage' => has_capability('format/kickstart:manage_templates', context_system::instance()),
            'createtemplateurl' => new moodle_url('/course/format/kickstart/template.php', ['action' => 'create']),
            'managetemplateurl' => new moodle_url('/course/format/kickstart/templates.php'),
            'dataride' => 'data-bs-ride',
            'datainterval' => 'data-bs-interval',
            'dataslideto' => 'data-bs-slide-to',
            'datatarget' => 'data-bs-target',
        ];
    }

    /**
     * Move template array into groups for easier rendering in cards/columns.
     *
     * @param \stdClass[] $templates
     * @param int $pergroup
     * @return array
     */
    protected function get_groups($templates, $pergroup = 2) {
        $groups = [];
        $grouptemplates = [];
        $i = 0;
        foreach ($templates as $template) {
            $grouptemplates[] = $template;
            if (++$i % $pergroup == 0) {
                $groups[] = ['templates' => $grouptemplates];
                $grouptemplates = [];
            }
        }
        if (!empty($grouptemplates)) {
            $groups[] = ['templates' => $grouptemplates];
        }

        return $groups;
    }
}
