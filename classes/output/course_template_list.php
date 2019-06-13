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
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * Widget that displays course templates inside a course.
 *
 * @copyright  2019 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package format_kickstart
 */
class course_template_list implements \templatable, \renderable {

    /**
     * @var \stdClass
     */
    private $course;

    /**
     * Constructor.
     *
     * @param \stdClass $course
     */
    public function __construct(\stdClass $course) {
        $this->course = course_get_format($course)->get_course();;
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
        global $DB, $COURSE;

        $limit = format_kickstart_has_pro() ? 0 : 3;

        $templates = $DB->get_records('kickstart_template', null, '', '*', 0, $limit);
        $notemplates = empty($templates);

        foreach ($templates as $template) {
            $template->description_formatted = format_text($template->description, $template->description_format);
            $tags = [];
            foreach (\core_tag_tag::get_item_tags('format_kickstart', 'kickstart_template', $template->id) as $tag) {
                $tags[] = '#' . $tag->get_display_name(false);
            }
            $template->hashtags = implode($tags, ' ');
            $template->link = new \moodle_url('/course/format/kickstart/confirm.php', [
                'template_id' => $template->id,
                'course_id' => $COURSE->id
            ]);
        }

        if (!format_kickstart_has_pro() && is_siteadmin()) {
            $template = new \stdClass();
            $template->isplaceholder = true;
            $template->title = get_string('getpro', 'format_kickstart');
            $template->link = 'https://bdecent.de/products/moodle-plugins/kickstart-course-wizard-pro/';
            $templates[] = $template;
        }

        return [
            'templates' => ['groups' => $this->get_groups($templates)],
            'has_pro' => format_kickstart_has_pro(),
            'teacherinstructions' => format_text($this->course->teacherinstructions['text'],
                $this->course->teacherinstructions['format']),
            'notemplates' => $notemplates,
            'canmanage' => has_capability('format/kickstart:manage_templates', \context_system::instance()),
            'createtemplateurl' => new \moodle_url('/course/format/kickstart/template.php', ['action' => 'create'])
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