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

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/lib.php');

global $USER, $DB;

$action = required_param('action', PARAM_TEXT);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/kickstart/template.php', ['action' => $action]));

require_login();
require_capability('format/kickstart:manage_templates', $context);

switch ($action) {
    case 'create':
        $PAGE->set_title(get_string('create_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('create_template', 'format_kickstart'));

        $form = new \format_kickstart\form\template_form($PAGE->url);

        if ($data = $form->get_data()) {
            $id = $DB->insert_record('kickstart_template', $data);

            core_tag_tag::set_item_tags('format_kickstart', 'kickstart_template', $id, context_system::instance(), $data->tags);
        } else if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/format/kickstart/templates.php'));
        }

        break;

    case 'edit':
        $PAGE->set_title(get_string('edit_template', 'format_kickstart'));
        $PAGE->set_heading(get_string('edit_template', 'format_kickstart'));

        $id = required_param('id', PARAM_INT);

        $template = $DB->get_record('kickstart_template', ['id' => $id], '*', MUST_EXIST);
        $template->tags = core_tag_tag::get_item_tags_array('format_kickstart', 'kickstart_template', $template->id);

        $form = new \format_kickstart\form\template_form($PAGE->url);

        $form->set_data($template);

        break;
}


echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
