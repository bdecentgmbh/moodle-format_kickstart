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
 * List of templates.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../../config.php');
require_once(__DIR__.'/lib.php');
require_once("$CFG->libdir/adminlib.php");

global $USER;

$download = optional_param('download', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_TEXT);
$templateid = optional_param('template', '', PARAM_TEXT);


$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/kickstart/templates.php'));

// Template sort action.
if ($action && $templateid) {

    if ($action == 'up') {

        $currenttemplate = $DB->get_record('format_kickstart_template', array('id' => $templateid));
        $prevtemplate = $DB->get_record('format_kickstart_template', array('sort' => $currenttemplate->sort - 1));
        if ($prevtemplate) {
            $DB->set_field('format_kickstart_template', 'sort', $prevtemplate->sort,
            array('id' => $currenttemplate->id));
            $DB->set_field('format_kickstart_template', 'sort', $currenttemplate->sort,
            array('id' => $prevtemplate->id));
        }

    } else if ($action = "down") {
        $currenttemplate = $DB->get_record('format_kickstart_template', array('id' => $templateid));
        $nexttemplate = $DB->get_record('format_kickstart_template', array('sort' => $currenttemplate->sort + 1));
        if ($nexttemplate) {
            $DB->set_field('format_kickstart_template', 'sort', $nexttemplate->sort,
            array('id' => $currenttemplate->id));
            $DB->set_field('format_kickstart_template', 'sort', $currenttemplate->sort,
            array('id' => $nexttemplate->id));
        }
    }
    redirect($PAGE->url);
}

admin_externalpage_setup('kickstarttemplates');

$PAGE->set_title(get_string('manage_templates', 'format_kickstart'));
$PAGE->set_heading(get_string('manage_templates', 'format_kickstart'));
$PAGE->set_button($OUTPUT->single_button(new moodle_url('/course/format/kickstart/template.php', ['action' => 'create']),
    get_string('create_template', 'format_kickstart')));

if (!format_kickstart_has_pro() && $DB->count_records('format_kickstart_template') >= 2 * 2) {
    \core\notification::warning(get_string('buypromaxtemplates', 'format_kickstart'));
}

$table = new \format_kickstart\template_table();
$table->define_baseurl($PAGE->url);
$table->is_downloadable(true);
$table->show_download_buttons_at([TABLE_P_BOTTOM]);

if ($download) {
    raise_memory_limit(MEMORY_EXTRA);
    $table->is_downloading($download, 'templates');
}

$form = new \format_kickstart\form\template_table_settings_form($PAGE->url);

$pagesize = get_user_preferences('template_table_pagesize', 25);

if ($data = $form->get_data()) {
    $pagesize = $data->pagesize;
    set_user_preference('template_table_pagesize', $data->pagesize);
} else {
    $form->set_data(['pagesize' => $pagesize]);
}

// If downloading get all records.
if ($table->is_downloading()) {
    $pagesize = -1;
}

ob_start();
$table->out($pagesize, true);
$tablehtml = ob_get_contents();
ob_end_clean();

echo $OUTPUT->header();
echo $tablehtml;
$form->display();
echo $OUTPUT->footer();
