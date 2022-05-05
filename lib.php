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
 * This file contains main class for the course format Kickstart
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the Kickstart course format
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart extends core_courseformat\base {

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Kickstart format uses the following options:
     * - userinstructions
     * - userinstructions_format
     * - teacherinstructions
     * - teacherinstructions_format
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'userinstructions' => [
                    'label' => new lang_string('userinstructions', 'format_kickstart'),
                    'help' => 'userinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => get_config('format_kickstart', 'defaultuserinstructions'),
                        'format' => FORMAT_HTML
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'userinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden'
                ],
                'teacherinstructions' => [
                    'label' => new lang_string('teacherinstructions', 'format_kickstart'),
                    'help' => 'teacherinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => get_config('format_kickstart', 'defaultteacherinstructions'),
                        'format' => FORMAT_HTML
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'teacherinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden'
                ]
            ];
        }

        return $courseformatoptions;
    }

    /**
     * Override: Allow editor element types to be saved properly.
     *
     * Updates format options for a course or section
     *
     * If $data does not contain property with the option name, the option will not be updated
     *
     * @param stdClass|array $data return value from {moodleform::get_data()} or array with data
     * @param null|int $sectionid  if these are options for course or section id (course_sections.id)
     *     if these are options for section
     * @return bool whether there were any changes to the options values
     * @throws dml_exception
     */
    protected function update_format_options($data, $sectionid = null) {
        global $DB;
        // Moodle 3.5 compatibility.
        if (method_exists($this, 'validate_format_options')) {
            $data = $this->validate_format_options((array)$data, $sectionid);
        }
        if (!$sectionid) {
            $allformatoptions = $this->course_format_options();
            $sectionid = 0;
        } else {
            $allformatoptions = $this->section_format_options();
        }
        if (empty($allformatoptions)) {
            // Nothing to update anyway.
            return false;
        }
        $defaultoptions = array();
        $cached = array();
        foreach ($allformatoptions as $key => $option) {
            $defaultoptions[$key] = null;
            if (array_key_exists('default', $option)) {
                $defaultoptions[$key] = $option['default'];
            }
            $cached[$key] = ($sectionid === 0 || !empty($option['cache']));
        }
        $records = $DB->get_records('course_format_options',
            array('courseid' => $this->courseid,
                'format' => $this->format,
                'sectionid' => $sectionid
            ), '', 'name,id,value');
        $changed = $needrebuild = false;
        foreach ($defaultoptions as $key => $value) {
            if (isset($records[$key])) {
                if (array_key_exists($key, $data) && $records[$key]->value !== $data[$key]) {
                    $DB->set_field('course_format_options', 'value',
                        $data[$key], array('id' => $records[$key]->id));
                    $changed = true;
                    $needrebuild = $needrebuild || $cached[$key];
                }
            } else {
                if (array_key_exists($key, $data) && $data[$key] !== $value) {
                    $newvalue = $data[$key];
                    $changed = true;
                    $needrebuild = $needrebuild || $cached[$key];
                } else {
                    $newvalue = $value;
                    // We still insert entry in DB, but there are no changes from user point of view.
                    // No need to call rebuild_course_cache().
                }

                $newvalue = !is_array($newvalue) ? $newvalue : $newvalue['text'];

                $DB->insert_record('course_format_options', array(
                    'courseid' => $this->courseid,
                    'format' => $this->format,
                    'sectionid' => $sectionid,
                    'name' => $key,
                    'value' => $newvalue
                ));
            }
        }
        if ($needrebuild) {
            rebuild_course_cache($this->courseid, true);
        }
        if ($changed) {
            // Reset internal caches.
            if (!$sectionid) {
                $this->course = false;
            }
            unset($this->formatoptions[$sectionid]);
        }
        return $changed;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'topics', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;

        if (isset($data['userinstructions']) && is_array($data['userinstructions'])) {
            $data['userinstructions_format'] = $data['userinstructions']['format'];
            $data['userinstructions'] = $data['userinstructions']['text'];
        }
        if (isset($data['teacherinstructions']) && is_array($data['teacherinstructions'])) {
            $data['teacherinstructions_format'] = $data['teacherinstructions']['format'];
            $data['teacherinstructions'] = $data['teacherinstructions']['text'];
        }

        return $this->update_format_options($data);
    }

    /**
     * Returns a record from course database table plus additional fields
     * that course format defines
     *
     * @return stdClass
     */
    public function get_course() {
        $course = parent::get_course();

        if (is_string($course->userinstructions)) {
            $course->userinstructions = [
                'text' => $course->userinstructions,
                'format' => $course->userinstructions_format
            ];
        }
        if (is_string($course->teacherinstructions)) {
            $course->teacherinstructions = [
                'text' => $course->teacherinstructions,
                'format' => $course->teacherinstructions_format
            ];
        }

        return $course;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     * @throws \coding_exception
     */
    public function get_section_name($section) {
        if (get_string_manager()->string_exists('sectionname', 'format_' . $this->format)) {
            return get_string('sectionname', 'format_' . $this->format);
        }

        return '';
    }
}

/**
 * Check if Kickstart Pro is installed.
 *
 * @return bool
 */
function format_kickstart_has_pro() {
    global $CFG;

    if (isset($CFG->kickstart_pro)) {
        return $CFG->kickstart_pro;
    }
    return array_key_exists('kickstart_pro', core_component::get_plugin_list('local'));
}

/**
 * Automatically create the template.
 * @param object $template template info
 * @param int $sort sort position
 * @param object $context page context
 * @param string $component
 * @return void
 */
function format_kickstart_create_template($template, $sort, $context, $component) {

    global $DB, $CFG, $USER;
    if (!isguestuser() && isloggedin()) {
        $fs = get_file_storage();
        $draftidattach = file_get_unused_draft_itemid();
        $template->sort = $sort;
        $template->course_backup = $draftidattach;
        $template->cohortids = json_encode($template->cohortids);
        $template->categoryids = json_encode($template->categoryids);
        $template->roleids = json_encode($template->roleids);
        $id = $DB->insert_record('format_kickstart_template', $template);
        core_tag_tag::set_item_tags('format_kickstart', 'format_kickstart_template', $id, $context, $template->tags);
        if (isset($template->backupfile) && !empty($template->backupfile)) {
            $filerecord = new stdClass();
            $filerecord->component = 'format_kickstart';
            $filerecord->contextid = $context->id;
            $filerecord->filearea = "course_backups";
            $filerecord->filepath = '/';
            $filerecord->itemid = $id;
            $filerecord->filename = $template->backupfile;
            $exist = check_record_exsist($filerecord);
            if ($exist != 1) {
                if ($component == 'format_kickstart') {
                    $backuppath = $CFG->dirroot . "/course/format/kickstart/assets/templates/$template->backupfile";
                } else if ($component == 'local_kickstart_pro') {
                    $backuppath = $CFG->dirroot . "/local/kickstart_pro/assets/templates/$template->backupfile";
                }
                $fs->create_file_from_pathname($filerecord, $backuppath);
            }
        }
        return $id;
    }
}

/**
 * Does this file exist
 * @param object $filerecord
 * @return bool
 */
function check_record_exsist($filerecord) {

    $fs = get_file_storage();
    $exist = $fs->file_exists($filerecord->contextid, $filerecord->component, $filerecord->filearea,
        $filerecord->itemid, $filerecord->filepath, $filerecord->filename);
    return $exist;
}
