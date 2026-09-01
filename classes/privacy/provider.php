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
 * Privacy Subsystem implementation for format_kickstart.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\writer;
use core_privacy\local\request\user_preference_provider;

/**
 * Privacy Subsystem for format_kickstart.
 *
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, user_preference_provider {
    /**
     * Describe the metadata stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('template_table_pagesize', 'privacy:metadata:template_table_pagesize');
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid): void {
        $pagesize = get_user_preferences('template_table_pagesize', null, $userid);
        if ($pagesize !== null) {
            $preference = (object) [
                'value' => $pagesize,
                'description' => get_string('privacy:metadata:template_table_pagesize', 'format_kickstart'),
            ];
            writer::export_user_preference(
                'format_kickstart',
                'template_table_pagesize',
                $pagesize,
                $preference
            );
        }
    }
}
