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
        global $CFG, $PAGE;

        $mform = $this->_form;
        $templatebgoptions = $this->_customdata['templatebgoptions'];
        $template = isset($this->_customdata['template']) ? $this->_customdata['template'] : [];
        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $attributes = [];
        $checkformat = !empty($template) && isset($template['courseformat']) && $template['courseformat'];
        if ($checkformat) {
            $attributes = array('disabled' => true);
        }
        $mform->addElement('text', 'title', get_string('title', 'format_kickstart'), $attributes);
        $mform->setType('title', PARAM_TEXT);
        if (!$checkformat) {
            $mform->addRule('title', get_string('required'), 'required');
        }
        $mform->addElement('editor', 'description_editor', get_string('description', 'format_kickstart'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('tags', 'tags', get_string('tags'),
            ['itemtype' => 'format_kickstart_template', 'component' => 'format_kickstart']);

        if (!$checkformat) {

            $mform->addElement('filemanager', 'course_backup',
                get_string('course_backup', 'format_kickstart'), null, [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => ['.mbz'],
                'return_types' => FILE_INTERNAL | FILE_EXTERNAL
                ]);
            $mform->addHelpButton('course_backup', 'course_backup', 'format_kickstart');
            $mform->addRule('course_backup', get_string('required'), 'required');
        }

        $mform->addElement('text', 'preview_url', get_string('previewurl', 'format_kickstart'));
        $mform->setType('preview_url', PARAM_URL);
        $mform->addHelpButton('preview_url', 'previewurl', 'format_kickstart');

        if (format_kickstart_has_pro() ) {
            require_once($CFG->dirroot."/local/kickstart_pro/lib.php");
            if (function_exists('local_kickstart_pro_get_template_backimages')) {
                // Template background images.
                $mform->addElement('filemanager', 'templatebackimg',
                    get_string('templatebackimg', 'format_kickstart'), null, $templatebgoptions);
                $mform->addHelpButton('templatebackimg', 'templatebackimg', 'format_kickstart');
            }

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

        if ($checkformat) {

            $PAGE->add_body_class('template-'.$template['format']."-format");
            $mform->addElement('header', 'formatoptions', get_string('courseformatoptions', 'format_kickstart'));

            // Just a placeholder for the course format options.
            $mform->addElement('hidden', 'courseformatoptions');
            $mform->setType('courseformatoptions', PARAM_BOOL);
            $mform->setDefault('courseformatoptions', 1);

            if ($template['format'] == 'weeks') {
                $mform->addElement('hidden', 'idnumber');
                $mform->setType('idnumber', PARAM_RAW);
            }

            $params['format'] = $template['format'];
            $params['id'] = '1';
            $courseformat = course_get_format((object)$params);
            $elements = $courseformat->create_edit_form_elements($mform, false);
            for ($i = 0; $i < count($elements); $i++) {
                $mform->insertElementBefore($mform->removeElement($elements[$i]->getName(), false),
                        'courseformatoptions');
            }

            if ($mform->elementExists('numsections')) {
                $mform->removeElement('numsections');
            }

        }

        $this->add_action_buttons();
    }
}
