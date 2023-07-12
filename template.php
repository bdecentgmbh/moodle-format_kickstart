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
 * Template CRUD.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/lib.php');
require_once($CFG->libdir . "/formslib.php");

global $USER, $DB, $CFG;

$action = required_param('action', PARAM_TEXT);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/kickstart/template.php', ['action' => $action]));
$PAGE->navbar->add(get_string('manage_templates', 'format_kickstart'), new moodle_url('/course/format/kickstart/templates.php'));

// Prepare the template images.
$templatebgoptions = array('maxfiles' => 10, 'subdirs' => 0, 'accepted_types' => ['.jpg', '.png']);
require_login();
require_capability('format/kickstart:manage_templates', $context);
$templates = isset($CFG->kickstart_templates) ? explode(",", $CFG->kickstart_templates) : [];
if (format_kickstart_has_pro()) {
    require_once($CFG->dirroot."/local/kickstart_pro/lib.php");
}

$editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes,
'trusttext' => false, 'noclean' => true, 'context' => $context);


switch ($action) {
    case 'create':
        $PAGE->set_title(get_string('create_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('create_template', 'format_kickstart'));
        $PAGE->navbar->add(get_string('create_template', 'format_kickstart'));
        if (!format_kickstart_has_pro() && $DB->count_records('format_kickstart_template', array('courseformat' => 0)) >= 2 * 2) {
            redirect(new moodle_url('/course/format/kickstart/buypro.php'));
        }

        $form = new \format_kickstart\form\template_form($PAGE->url, ['templatebgoptions' => $templatebgoptions,
            'editoroptions' => $editoroptions]);

        if ($data = $form->get_data()) {
            $data->description = $data->description_editor['text'];
            $data->descriptionformat = $data->description_editor['format'];
            $counttemplate = $DB->count_records("format_kickstart_template");
            if (format_kickstart_has_pro()) {
                $data->cohortids = json_encode($data->cohortids);
                $data->categoryids = json_encode($data->categoryids);
                $data->roleids = json_encode($data->roleids);
            }
            $data->sort = (!empty($counttemplate)) ? $counttemplate + 1 : 1;
            $data->courseformat = 0;
            $id = $DB->insert_record('format_kickstart_template', $data);
            array_push($templates, $id);
            set_config('kickstart_templates', implode(',', $templates));
            core_tag_tag::set_item_tags('format_kickstart', 'format_kickstart_template', $id,
                context_system::instance(), $data->tags);
            file_save_draft_area_files($data->course_backup, $context->id, 'format_kickstart', 'course_backups',
                $id, ['subdirs' => 0, 'maxfiles' => 1]);
            if (format_kickstart_has_pro() && function_exists('local_kickstart_pro_get_template_backimages')) {
                file_save_draft_area_files($data->templatebackimg, $context->id, 'local_kickstart_pro', 'templatebackimg',
                $id, $templatebgoptions);
            }

            // Update the description editor.
            $data = file_postupdate_standard_editor($data, 'description', $editoroptions,
            $context, 'format_kickstart', 'description', $id);
            $upd = new stdClass();
            $upd->id                = $id;
            $upd->description       = $data->description;
            $upd->descriptionformat = $data->descriptionformat;
            $DB->update_record('format_kickstart_template', $upd);
            \core\notification::success(get_string('template_created', 'format_kickstart'));
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else {
            $template = new stdClass();
            // Get global settings bg and set the images.
            if (format_kickstart_has_pro() && function_exists('local_kickstart_pro_get_template_backimages')) {
                $templateoptions = array('maxfiles' => 10, 'subdirs' => 0, 'accepted_types' => ['.jpg', '.png']);
                $draftitem = file_get_submitted_draft_itemid('templatebackimages');
                file_prepare_draft_area($draftitem, \context_system::instance()->id,
                    'format_kickstart', 'templatebackimages', 0, $templateoptions);
                $template->templatebackimg = $draftitem;
            }
            $editoroptions['subdirs'] = false;
            $template = file_prepare_standard_editor($template, 'description', $editoroptions,
            $context, 'format_kickstart', 'description', null);
            $form->set_data($template);
        }

        break;

    case 'edit':
        $PAGE->set_title(get_string('edit_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('edit_template', 'format_kickstart'));
        $PAGE->navbar->add(get_string('edit_template', 'format_kickstart'));

        $id = required_param('id', PARAM_INT);

        $template = $DB->get_record('format_kickstart_template', ['id' => $id], '*', MUST_EXIST);
        $template->tags = core_tag_tag::get_item_tags_array('format_kickstart', 'format_kickstart_template', $template->id);

        $form = new \format_kickstart\form\template_form($PAGE->url, ['templatebgoptions' => $templatebgoptions,
            'template' => (array) $template, 'editoroptions' => $editoroptions]);

        if ($data = $form->get_data()) {
            if (isset($data->courseformatoptions)) {
                $params['format'] = $template->format;
                $params['id'] = '1';
                $courseformat = course_get_format((object) $params);
                $courseformat->update_course_format_options($data);
                format_kickstart_update_template_format_options($template);
            }
            $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context,
            'format_kickstart', 'description', $data->id);
            if (format_kickstart_has_pro()) {
                $data->cohortids = json_encode($data->cohortids);
                $data->categoryids = json_encode($data->categoryids);
                $data->roleids = json_encode($data->roleids);
            }
            $DB->update_record('format_kickstart_template', $data);

            core_tag_tag::set_item_tags('format_kickstart', 'format_kickstart_template', $id,
                context_system::instance(), $data->tags);
            if (isset($data->course_backup)) {
                file_save_draft_area_files($data->course_backup, $context->id, 'format_kickstart', 'course_backups',
                    $data->id, ['subdirs' => 0, 'maxfiles' => 1]);
            }

            if (format_kickstart_has_pro() && function_exists('local_kickstart_pro_get_template_backimages')) {
                file_save_draft_area_files($data->templatebackimg, $context->id, 'local_kickstart_pro', 'templatebackimg',
                $data->id, $templatebgoptions);
            }



            \core\notification::success(get_string('template_edited', 'format_kickstart'));
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else {
            $editoroptions['subdirs'] = file_area_contains_subdirs($context, 'format_kickstart', 'description', $template->id);
            $template = file_prepare_standard_editor($template, 'description', $editoroptions,
                $context, 'format_kickstart', 'description', $template->id);
            if (format_kickstart_has_pro()) {
                if (function_exists('local_kickstart_pro_get_template_backimages')) {
                    $drafteditor = file_get_submitted_draft_itemid('templatebackimg');
                    file_prepare_draft_area($drafteditor, $context->id, 'local_kickstart_pro',
                        'templatebackimg', $id, $templatebgoptions);
                    $template->templatebackimg = $drafteditor;
                }
                $template->cohortids = json_decode($template->cohortids, true);
                $template->categoryids = json_decode($template->categoryids, true);
                $template->roleids = json_decode($template->roleids, true);
            }
            // Check the template is normal or course format.
            if (!$template->courseformat) {
                $draftitemid = file_get_submitted_draft_itemid('course_backup');
                file_prepare_draft_area($draftitemid, $context->id, 'format_kickstart', 'course_backups', $id,
                    ['subdirs' => 0, 'maxfiles' => 1]);
                $template->course_backup = $draftitemid;
            } else {
                $params['format'] = $template->format;
                $params['id'] = '1';
                $records = format_kickstart_get_template_format_options($template);
                $template = array_merge((array) $records, (array) $template);
                if ($params['format'] == 'designer') {
                    require_once($CFG->dirroot."/course/format/designer/lib.php");
                    $coursetypes = format_kickstart_get_designer_coursetypes();
                    $template['coursetype'] = array_search($template['title'], $coursetypes);
                }
            }
            $form->set_data($template);
        }

        break;

    case 'delete':
        $PAGE->set_title(get_string('delete_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('delete_template', 'format_kickstart'));
        $PAGE->navbar->add(get_string('delete_template', 'format_kickstart'));

        $id = required_param('id', PARAM_INT);

        $template = $DB->get_record('format_kickstart_template', ['id' => $id], '*', MUST_EXIST);

        $form = new \format_kickstart\form\template_delete_form($PAGE->url);

        if ($data = $form->get_data()) {
            format_kickstart_remove_kickstart_templates($data->id);
            \core\notification::success(get_string('template_deleted', 'format_kickstart'));
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else {
            $form->set_data($template);
        }

        break;
}


echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
