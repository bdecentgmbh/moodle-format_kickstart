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
 * Kickstart course format.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../../config.php');

defined('MOODLE_INTERNAL') || die();

$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('buypro', 'format_kickstart'));
$PAGE->set_heading(get_string('buypro', 'format_kickstart'));
$PAGE->set_url(new moodle_url('/course/format/kickstart/buypro.php'));

$PAGE->navbar->add(get_string('buypro', 'format_kickstart'));

require_login();

echo $OUTPUT->header();
echo \html_writer::div(get_string('buyprosummary', 'format_kickstart'), 'mb-2');
echo \html_writer::link('http://bdecent.de/kickstart',
    get_string('learnmore', 'format_kickstart'), ['target' => '_blank', 'class' => 'btn btn-primary']);
echo $OUTPUT->footer();