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

global $USER;

$download = optional_param('download', '', PARAM_ALPHA);

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/kickstart/templates.php'));
$PAGE->set_title(get_string('manage_templates', 'format_kickstart'));
$PAGE->set_heading(get_string('manage_templates', 'format_kickstart'));
$PAGE->set_button($OUTPUT->single_button(new moodle_url('/course/format/kickstart/template.php', ['action' => 'create']), get_string('create_template', 'format_kickstart')));
$PAGE->navbar->add(get_string('manage_templates', 'format_kickstart'));

require_login();
require_capability('format/kickstart:manage_templates', $context);

if (!format_kickstart_has_pro() && $DB->count_records('kickstart_template') >= 3) {
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
