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
 * Form for editing/creating a template.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\form;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->dirroot/cohort/lib.php");
require_once("$CFG->dirroot/course/format/kickstart/lib.php");

/**
 * Form for editing/creating a template.
 *
 * @package format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {

    /**
     * Define form elements.
     *
     * @throws \coding_exception
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'format_kickstart'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required');

        $mform->addElement('editor', 'description', get_string('description', 'format_kickstart'));
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('tags', 'tags', get_string('tags'),
            ['itemtype' => 'format_kickstart_template', 'component' => 'format_kickstart']);

        $mform->addElement('filemanager', 'course_backup',
            get_string('course_backup', 'format_kickstart'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.mbz'],
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
            ]);
        $mform->addHelpButton('course_backup', 'course_backup', 'format_kickstart');

        $mform->addElement('text', 'preview_url', get_string('previewurl', 'format_kickstart'));
        $mform->setType('preview_url', PARAM_URL);
        $mform->addHelpButton('preview_url', 'previewurl', 'format_kickstart');

        if (format_kickstart_has_pro()) {
            $mform->addElement('header', 'templateaccess', get_string('templateaccess', 'format_kickstart'));

            $mform->addElement('advcheckbox', 'restrictcohort', get_string('restrictcohort', 'format_kickstart'));
            $mform->setType('restrictcohort', PARAM_BOOL);

            $cohortdata = cohort_get_all_cohorts(0, 0);
            $options = [];
            foreach ($cohortdata['cohorts'] as $cohort) {
                $options[$cohort->id] = $cohort->name;
            }

            $mform->addElement('autocomplete', 'cohortids', get_string('cohorts', 'cohort'), $options, [
                'multiple' => true
            ]);
            $mform->hideIf('cohortids', 'restrictcohort');

            $mform->addElement('html', '<hr>');

            $mform->addElement('advcheckbox', 'restrictcategory', get_string('restrictcategory', 'format_kickstart'));
            $mform->setType('restrictcategory', PARAM_BOOL);
            $categories = \core_course_category::make_categories_list('moodle/course:create');
            $mform->addElement('autocomplete', 'categoryids', get_string('categories'), $categories,
                ['multiple' => true]);
            $mform->hideIf('categoryids', 'restrictcategory');

            $mform->addElement('advcheckbox', 'includesubcategories', get_string('includesubcategories', 'format_kickstart'));
            $mform->setType('includesubcategories', PARAM_BOOL);
            $mform->addHelpButton('includesubcategories', 'includesubcategories', 'format_kickstart');
            $mform->hideIf('includesubcategories', 'restrictcategory');

            $mform->addElement('html', '<hr>');

            $mform->addElement('advcheckbox', 'restrictrole', get_string('restrictrole', 'format_kickstart'));
            $mform->setType('restrictcategory', PARAM_BOOL);

            $roleoptions = [];
            foreach (role_get_names(\context_system::instance()) as $role) {
                $roleoptions[$role->id] = $role->localname;
            }

            $mform->addElement('autocomplete', 'roleids', get_string('roles'), $roleoptions, ['multiple' => true]);
            $mform->hideIf('roleids', 'restrictrole');
        }

        $this->add_action_buttons();
    }
}
