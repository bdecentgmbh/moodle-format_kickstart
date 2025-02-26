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

namespace format_kickstart\output;

use moodle_url;
use core\output\select_menu;

/**
 * Renderable class for the general action bar in the gradebook pages.
 *
 * This class is responsible for rendering the general navigation select menu in the gradebook pages.
 *
 * @package    core_grades
 * @copyright  2021 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_action_bar {

    /** @var moodle_url $activeurl The URL that should be set as active in the URL selector element. */
    protected $activeurl;

    /**
     * The type of the current gradebook page (report, settings, import, export, scales, outcomes, letters).
     *
     * @var string $activetype
     */
    protected $activetype;

    /** @var string $activeplugin The plugin of the current gradebook page (grader, fullview, ...). */
    protected $activeplugin;

    protected $context;

    /**
     * The class constructor.
     *
     * @param \context $context The context object.
     * @param moodle_url $activeurl The URL that should be set as active in the URL selector element.
     * @param string $activetype The type of the current gradebook page (report, settings, import, export, scales,
     *                           outcomes, letters).
     * @param string $activeplugin The plugin of the current gradebook page (grader, fullview, ...).
     */
    public function __construct(\context $context, moodle_url $activeurl, string $activetype, string $activeplugin) {
        $this->activeurl = $activeurl;
        $this->activetype = $activetype;
        $this->activeplugin = $activeplugin;
        $this->context = $context;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $selectmenu = $this->get_action_selector();

        if (is_null($selectmenu)) {
            return [];
        }

        return [
            'generalnavselector' => $selectmenu->export_for_template($output),
        ];
    }

    /**
     * Returns the template for the action bar.
     *
     * @return string
     */
    public function get_template(): string {
        return 'format_kickstart/general_action_bar';
    }

    /**
     * Returns the URL selector object.
     *
     * @return \select_menu|null The URL select object.
     */
    private function get_action_selector(): ?select_menu {
        if ($this->context->contextlevel !== CONTEXT_COURSE) {
            return null;
        }

        $courseid = $this->context->instanceid;
        $menu = [];
        $menu = format_kickstart_get_action_selector_menus($courseid, $this->activeurl);
        $selectmenu = new select_menu('kickstartactionselect', $menu, $this->activeurl->out(false));
        $selectmenu->set_label(get_string('kickstartnavigationmenu', 'format_kickstart'), ['class' => 'sr-only']);

        return $selectmenu;
    }
}
