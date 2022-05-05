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

global $USER, $DB, $CFG;

$action = required_param('action', PARAM_TEXT);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/kickstart/template.php', ['action' => $action]));
$PAGE->navbar->add(get_string('manage_templates', 'format_kickstart'), new moodle_url('/course/format/kickstart/templates.php'));

require_login();
require_capability('format/kickstart:manage_templates', $context);
$templates = $CFG->kickstart_templates ? explode(",", $CFG->kickstart_templates) : [];
$templatesflip = array_flip($templates);
switch ($action) {
    case 'create':
        $PAGE->set_title(get_string('create_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('create_template', 'format_kickstart'));
        $PAGE->navbar->add(get_string('create_template', 'format_kickstart'));

        if (!format_kickstart_has_pro() && $DB->count_records('format_kickstart_template') >= 2 * 2) {
            redirect(new moodle_url('/course/format/kickstart/buypro.php'));
        }

        $form = new \format_kickstart\form\template_form($PAGE->url);

        if ($data = $form->get_data()) {
            $data->description_format = $data->description['format'];
            $data->description = $data->description['text'];
            if (format_kickstart_has_pro()) {
                $counttemplate = $DB->count_records("format_kickstart_template");
                $data->cohortids = json_encode($data->cohortids);
                $data->categoryids = json_encode($data->categoryids);
                $data->roleids = json_encode($data->roleids);
                $data->sort = (!empty($counttemplate)) ? $counttemplate + 1 : 1;
            }
            $id = $DB->insert_record('format_kickstart_template', $data);
            array_push($templates, $id);
            set_config('kickstart_templates', implode(',', $templates));
            core_tag_tag::set_item_tags('format_kickstart', 'format_kickstart_template', $id,
                context_system::instance(), $data->tags);
            file_save_draft_area_files($data->course_backup, $context->id, 'format_kickstart', 'course_backups',
                $id, ['subdirs' => 0, 'maxfiles' => 1]);

            \core\notification::success(get_string('template_created', 'format_kickstart'));
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        }

        break;

    case 'edit':
        $PAGE->set_title(get_string('edit_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('edit_template', 'format_kickstart'));
        $PAGE->navbar->add(get_string('edit_template', 'format_kickstart'));

        $id = required_param('id', PARAM_INT);

        $template = $DB->get_record('format_kickstart_template', ['id' => $id], '*', MUST_EXIST);
        $template->tags = core_tag_tag::get_item_tags_array('format_kickstart', 'format_kickstart_template', $template->id);

        $form = new \format_kickstart\form\template_form($PAGE->url);

        if ($data = $form->get_data()) {
            $data->description_format = $data->description['format'];
            $data->description = $data->description['text'];
            if (format_kickstart_has_pro()) {
                $data->cohortids = json_encode($data->cohortids);
                $data->categoryids = json_encode($data->categoryids);
                $data->roleids = json_encode($data->roleids);
            }
            $DB->update_record('format_kickstart_template', $data);

            core_tag_tag::set_item_tags('format_kickstart', 'format_kickstart_template', $id,
                context_system::instance(), $data->tags);
            file_save_draft_area_files($data->course_backup, $context->id, 'format_kickstart', 'course_backups',
                $data->id, ['subdirs' => 0, 'maxfiles' => 1]);

            \core\notification::success(get_string('template_edited', 'format_kickstart'));
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        } else {

            $draftitemid = file_get_submitted_draft_itemid('course_backup');

            file_prepare_draft_area($draftitemid, $context->id, 'format_kickstart', 'course_backups', $id,
                ['subdirs' => 0, 'maxfiles' => 1]);

            $template->course_backup = $draftitemid;
            $template->description = [
                'text' => $template->description,
                'format' => $template->description_format
            ];
            if (format_kickstart_has_pro()) {
                $template->cohortids = json_decode($template->cohortids, true);
                $template->categoryids = json_decode($template->categoryids, true);
                $template->roleids = json_decode($template->roleids, true);
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
            $DB->delete_records('format_kickstart_template', ['id' => $data->id]);
            unset($templatesflip[$data->id]);
            $templatesinfo = array_flip($templatesflip);
            sort($templatesflip);
            set_config('kickstart_templates', implode(',', $templatesinfo));
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
