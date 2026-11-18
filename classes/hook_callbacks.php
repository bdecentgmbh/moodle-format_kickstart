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

namespace format_kickstart;

/**
 * Hook callbacks for format_kickstart.
 *
 * @package    format_kickstart
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Tell site admins that Kickstart Pro needs to be updated, when it is too old for this format.
     *
     * Shown on admin pages and in Kickstart courses.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/course/format/kickstart/lib.php');

        if (during_initial_install() || isset($CFG->kickstart_pro) || !is_siteadmin()) {
            return;
        }
        if ($PAGE->pagelayout !== 'admin' && ($PAGE->course->format ?? '') !== 'kickstart') {
            return;
        }
        if (!format_kickstart_pro_outdated()) {
            return;
        }
        $hook->add_html(\core\output\html_writer::div(
            $hook->renderer->notification(
                get_string('prooutdated', 'format_kickstart', FORMAT_KICKSTART_MIN_PRO_RELEASE),
                \core\output\notification::NOTIFY_WARNING,
                false
            ),
            'format-kickstart-prooutdated container-fluid mt-2'
        ));
    }
}
