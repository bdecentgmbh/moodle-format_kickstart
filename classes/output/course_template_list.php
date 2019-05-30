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

namespace format_kickstart\output;

use renderer_base;

class course_template_list implements \templatable, \renderable
{
    public function export_for_template(renderer_base $output)
    {
        global $DB, $COURSE;

        $templates = $DB->get_records('kickstart_template');

        foreach ($templates as $template) {
            $template->description_formatted = format_text($template->description, $template->description_format);
            $tags = [];
            foreach (\core_tag_tag::get_item_tags('format_kickstart', 'kickstart_template', $template->id) as $tag) {
                $tags[] = '#' . $tag->get_display_name(false);
            }
            $template->hashtags = implode($tags, ' ');
            $template->link = new \moodle_url('/course/format/kickstart/merge.php', ['template_id' => $template->id, 'course_id' => $COURSE->id]);
        }

        if (!format_kickstart_has_pro()) {
            $template = new \stdClass();
            $template->isplaceholder = true;
            $template->title = get_string('getpro', 'format_kickstart');
            $template->link = '#';
            $templates[] = $template;
        }

        return [
            'templates' => ['groups' => $this->get_groups($templates)],
            'has_pro' => format_kickstart_has_pro()
        ];
    }

    /**
     * Move template array into groups for easier rendering in cards/columns.
     *
     * @param \stdClass[] $templates
     * @param int $pergroup
     * @return array
     */
    protected function get_groups($templates, $pergroup = 3)
    {
        $groups = [];
        $group_templates = [];
        $i = 0;
        foreach ($templates as $template) {
            $group_templates[] = $template;
            if (++$i % $pergroup == 0) {
                $groups[] = ['templates' => $group_templates];
                $group_templates = [];
            }
        }
        if (!empty($group_templates)) {
            $groups[] = ['templates' => $group_templates];
        }

        return $groups;
    }
}