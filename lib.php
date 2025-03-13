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


use format_kickstart\output\course_template_list;

/**
 * Main class for the Kickstart course format
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_kickstart extends core_courseformat\base {

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return false;
    }


    /**
     * Course-specific information to be output on any course page (usually above navigation bar)
     *
     * Example of usage:
     * define
     * class format_FORMATNAME_XXX implements renderable {}
     *
     * create format renderer in course/format/FORMATNAME/renderer.php, define rendering function:
     * class format_FORMATNAME_renderer extends plugin_renderer_base {
     *     protected function render_format_FORMATNAME_XXX(format_FORMATNAME_XXX $xxx) {
     *         return html_writer::tag('div', 'This is my header/footer');
     *     }
     * }
     *
     * Return instance of format_FORMATNAME_XXX in this function, the appropriate method from
     * plugin renderer will be called
     *
     * @return null|\renderable null for no output or object with data for plugin renderer
     */
    public function course_header() {
        global $CFG;
        if (format_kickstart_has_pro()) {
            require_once($CFG->dirroot. "/local/kickstart_pro/lib.php");
            if (function_exists('local_kickstart_pro_redirect_automatictemplate')) {
                local_kickstart_pro_redirect_automatictemplate();
            }
        }
    }

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
            $defaultuserinstructions = get_config('format_kickstart', 'defaultuserinstructions');
            $defaultteacherinstructions = get_config('format_kickstart', 'defaultteacherinstructions');
            $courseformatoptions = [
                'templatesview' => [
                    'label' => new lang_string('templatesview', 'format_kickstart'),
                    'help' => 'templatesview',
                    'help_component' => 'format_kickstart',
                    'type' => PARAM_TEXT,
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            'tile' => new lang_string('strtile', 'format_kickstart'),
                            'list' => new lang_string('strlist', 'format_kickstart'),
                        ],
                    ],
                    'default' => get_config('format_kickstart', 'defaulttemplatesview'),
                ],
                'userinstructions' => [
                    'label' => new lang_string('userinstructions', 'format_kickstart'),
                    'help' => 'userinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => !empty($defaultuserinstructions) ? $defaultuserinstructions : '',
                        'format' => FORMAT_HTML,
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'userinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden',
                ],
                'teacherinstructions' => [
                    'label' => new lang_string('teacherinstructions', 'format_kickstart'),
                    'help' => 'teacherinstructions',
                    'help_component' => 'format_kickstart',
                    'default' => [
                        'text' => !empty($defaultteacherinstructions) ? $defaultteacherinstructions : '',
                        'format' => FORMAT_HTML,
                    ],
                    'type' => PARAM_RAW,
                    'element_type' => 'editor',
                ],
                'teacherinstructions_format' => [
                    'element_type' => 'hidden',
                    'type' => PARAM_INT,
                    'label' => 'hidden',
                ],
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
        $defaultoptions = [];
        $cached = [];
        foreach ($allformatoptions as $key => $option) {
            $defaultoptions[$key] = null;
            if (array_key_exists('default', $option)) {
                $defaultoptions[$key] = $option['default'];
            }
            $cached[$key] = ($sectionid === 0 || !empty($option['cache']));
        }
        $records = $DB->get_records('course_format_options',
            ['courseid' => $this->courseid,
                'format' => $this->format,
                'sectionid' => $sectionid,
            ], '', 'name,id,value');
        $changed = $needrebuild = false;
        foreach ($defaultoptions as $key => $value) {
            if (isset($records[$key])) {
                if (array_key_exists($key, $data) && $records[$key]->value !== $data[$key]) {
                    $DB->set_field('course_format_options', 'value',
                        $data[$key], ['id' => $records[$key]->id]);
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

                $DB->insert_record('course_format_options', [
                    'courseid' => $this->courseid,
                    'format' => $this->format,
                    'sectionid' => $sectionid,
                    'name' => $key,
                    'value' => $newvalue,
                ]);
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
                'format' => $course->userinstructions_format,
            ];
        }
        if (is_string($course->teacherinstructions)) {
            $course->teacherinstructions = [
                'text' => $course->teacherinstructions,
                'format' => $course->teacherinstructions_format,
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
        $template->courseformat = 0;
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
        if (format_kickstart_has_pro() && isset($template->templatebackimg) && !empty($template->templatebackimg)) {
            $filerecord = new stdClass();
            $filerecord->component = 'local_kickstart_pro';
            $filerecord->contextid = $context->id;
            $filerecord->filearea = "templatebackimg";
            $filerecord->filepath = '/';
            $filerecord->itemid = $id;
            $filerecord->filename = $template->templatebackimg;
            $exist = check_record_exsist($filerecord);
            if ($exist != 1) {
                $imagepath = $CFG->dirroot . "/local/kickstart_pro/assets/$template->templatebackimg";
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

/**
 * Import the course format template
 * @return void
 */
function format_kickstart_import_courseformat_template() {
    global $DB, $CFG;
    $formats = core_plugin_manager::instance()->get_plugins_of_type('format');
    $counttemplate = $DB->count_records("format_kickstart_template");
    foreach ($formats as $format) {
        $counttemplate++;
        if ($format->name == 'designer') {
            require_once($CFG->dirroot."/course/format/designer/lib.php");
            $coursetypes = format_kickstart_get_designer_coursetypes();
            foreach ($coursetypes as $type) {
                format_kickstart_add_couseformat_template($type, $format->name, $counttemplate, $format->is_enabled());
                if ($type != end($coursetypes)) {
                    $counttemplate++;
                }
            }
        } else {
            format_kickstart_add_couseformat_template($format->displayname, $format->name, $counttemplate, $format->is_enabled());
        }
    }
}

/**
 * Add the course format template.
 * @param string $templatename
 * @param string $format
 * @param int $counttemplate
 * @param bool $isenabled
 */
function format_kickstart_add_couseformat_template($templatename, $format, $counttemplate, $isenabled) {
    global $DB, $CFG;
    $templates = isset($CFG->kickstart_templates) ? explode(",", $CFG->kickstart_templates) : [];
    if (!$DB->record_exists('format_kickstart_template', ['title' => $templatename, 'courseformat' => 1])) {
        $template = new stdClass();
        $template->title = $templatename;
        $template->sort = $counttemplate;
        $template->courseformat = 1;
        $template->format = $format;
        if (!defined('BEHAT_SITE_RUNNING')) {
            $template->visible = ($isenabled) ? 1 : 0;
        } else {
            $template->visible = 1;
        }
        $templateid = $DB->insert_record('format_kickstart_template', $template);
        if ($isenabled) {
            array_push($templates, $templateid);
            set_config('kickstart_templates', implode(',', $templates));
        }
    }
}

/**
 * Update the course format template.
 * @param object $template
 * @return void
 */
function format_kickstart_update_template_format_options($template) {
    global $DB, $SITE, $CFG;
    $isdesignerformat = ($template->format == 'designer') ? true : false;
    $records = $DB->get_records('course_format_options',
        [
            'courseid' => $SITE->id,
            'format' => $template->format,
        ]
    );
    if ($records) {
        $courseformat = $template->format;
        if ($isdesignerformat) {
            $coursetypes = format_kickstart_get_designer_coursetypes();
            $coursetype = array_search($template->title, $coursetypes);
            $courseformat = strtolower($template->title);
        }
        foreach ($records as $record) {
            if (!$existrecord = $DB->get_record('format_kickstart_options', ['format' => $courseformat,
                'templateid' => $template->id, 'name' => $record->name ])) {
                $data = new stdClass();
                $data->templateid = $template->id;
                $data->displayname = $template->title;
                $data->format = $courseformat;
                $data->name = $record->name;
                $data->value = $record->value;
                if ($isdesignerformat && $record->name == 'coursetype') {
                    $data->value = $coursetype;
                }
                $DB->insert_record('format_kickstart_options', $data);
            } else {
                if ($isdesignerformat && $record->name == 'coursetype') {
                    $record->value = $coursetype;
                }
                if ($existrecord->value != $record->value) {
                    $existrecord->value = $record->value;
                    $DB->update_record('format_kickstart_options', $existrecord);
                }
            }
        }
    }
}

/**
 * Get the course format options.
 * @param object $template
 * @return object
 */
function format_kickstart_get_template_format_options($template) {
    global $DB;
    $courseformat = $template->format;
    if ($template->format == 'designer') {
        $courseformat = strtolower($template->title);
    }
    $records = $DB->get_records_menu('format_kickstart_options',
        [
            'templateid' => $template->id,
            'format' => $courseformat,
        ],
        '',
        'name,value'
    );
    $params['format'] = $template->format;
    $params['id'] = '1';
    $courseformat = course_get_format((object) $params);
    // Check course format has editor type.
    $iseditors = array_column($courseformat->course_format_options(), 'element_type');
    if (in_array('editor', $iseditors)) {
        $editors = array_keys($iseditors, 'editor');
        $courseformatcourse = $courseformat->get_course();
        foreach ($editors as $editor) {
            $elementname = array_keys($courseformat->course_format_options())[$editor];
            $records[$elementname] = $courseformatcourse->{$elementname};
        }
    }
    return $records;
}

/**
 * Check the format status remove or add.
 * @return void
 */
function format_kickstart_check_format_template() {
    global $DB, $SITE, $CFG;
    $templates = isset($CFG->kickstart_templates) ? explode(",", $CFG->kickstart_templates) : [];
    // Add the kickstart templates to visible template remove the store config.
    $records = $DB->get_records_menu('format_kickstart_template', ['visible' => 1], '', 'id,id');
    if ($records) {
        $records = array_keys($records);
        $addtemplates = array_diff($records, $templates);
        $templates = array_merge($templates, $addtemplates);
    }
    set_config('kickstart_templates', implode(',', $templates));

    $cache = cache::make('format_kickstart', 'templates');
    if (!$cache->get('templateformat')) {
        $records = $DB->get_records_menu('format_kickstart_template', ['courseformat' => 1], '', 'id,format');
        $records = array_unique($records);
        $formats = core_plugin_manager::instance()->get_plugins_of_type('format');
        $formats = array_keys($formats);
        $removeformats = array_diff($records, $formats);
        $addformats = array_diff($formats, $records);

        // Remove the formats.
        if ($removeformats) {
            foreach ($removeformats as $removeformat) {
                $template = $DB->get_record('format_kickstart_template', ['format' => $removeformat]);
                format_kickstart_remove_kickstart_templates($template->id);
            }
        }

        // Add the formats.
        if ($addformats) {
            foreach ($addformats as $addformat) {
                format_kickstart_import_courseformat_template();
            }
        }
        $cache->set('templateformat', true);
    }
}

/**
 * Remove the kickstart template settings.
 * @param int $templateid
 */
function format_kickstart_remove_kickstart_templates($templateid) {
    global $CFG, $SITE, $DB;
    $fs = get_file_storage();
    $context = context_system::instance();
    $templates = [];
    if (isset($CFG->kickstart_templates) && $CFG->kickstart_templates) {
        $templates = explode(",", $CFG->kickstart_templates);
    }
    $template = $DB->get_record('format_kickstart_template', ['id' => $templateid]);
    // Delete the template bg.
    $fs->delete_area_files($context->id, 'local_kickstart_pro', 'templatebackimg', $templateid);
    if ($template->courseformat) {
        $DB->delete_records('format_kickstart_options', ['templateid' => $templateid]);
        $DB->delete_records('course_format_options', ['courseid' => $SITE->id, 'format' => $template->format]);
    } else {
        $fs->delete_area_files($context->id, 'format_kickstart', 'course_backups', $templateid);
    }
    $DB->delete_records('format_kickstart_template', ['id' => $templateid]);
    unset($templates[array_search($templateid, $templates)]);
    set_config('kickstart_templates', implode(',', $templates));
}

/**
 * Get the designer format course types.
 */
function format_kickstart_get_designer_coursetypes() {
    if (function_exists('format_designer_get_coursetypes')) {
        return format_designer_get_coursetypes();
    } else {
        $coursetypes = [
            0 => get_string('normal'),
            DESIGNER_TYPE_KANBAN => get_string('kanbanboard', 'format_designer'),
            DESIGNER_TYPE_COLLAPSIBLE => get_string('collapsiblesections', 'format_designer'),
            DESIGNER_TYPE_FLOW => get_string('type_flow', 'format_designer'),
        ];
        return $coursetypes;
    }
}


/**
 * Serves file from.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context Context used in the file.
 * @param string $filearea Filearea the file stored
 * @param array $args Arguments
 * @param bool $forcedownload Force download the file instead of display.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function format_kickstart_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    require_login();
    if ($context->contextlevel != CONTEXT_SYSTEM && $filearea != 'description') {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'format_kickstart', $filearea, $args[0], '/', $args[1]);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, 0, $options);
}


/**
 * Add the link in course secondary navigation menu to open the automation instance list page.
 *
 * @param  navigation_node $navigation
 * @param  stdClass $course
 * @param  context_course $context
 * @return void
 */
function format_kickstart_extend_navigation_course(navigation_node $navigation, stdClass $course, $context) {
    global $PAGE;
    $addnode = $context->contextlevel === CONTEXT_COURSE;
    $addnode = $addnode && has_capability('format/kickstart:import_from_template', $context);
    if ($addnode &&  $PAGE->course->format !== 'kickstart') {
        $id = $context->instanceid;
        $url = new moodle_url('/course/format/kickstart/list.php', [
            'id' => $id,
        ]);
        $node = $navigation->create(get_string('strkickstart', 'format_kickstart'), $url, navigation_node::TYPE_SETTING, null, null);
        $node->add_class('kickstart-nav');
        $node->set_force_into_more_menu(false);
        $node->set_show_in_secondary_navigation(true);
        $node->key = 'kickstart-nav';
        $navigation->add_node($node);
        $PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'instanceMenuLink', []);
    }
}


function format_kickstart_get_breadcump_menus() {
    global $CFG;
    $menus = [
        'coursetemplate' => get_string('coursetemplate', 'format_kickstart'),
        'studentview' => get_string('studentview', 'format_kickstart'),
        'help' => get_string('help', 'format_kickstart')
    ];

    if (format_kickstart_has_pro()) {
        require_once($CFG->dirroot. "/local/kickstart_pro/lib.php");
        $menus += local_kickstart_pro_get_breadcump_menus();
    }
    return $menus;
}


function format_kickstart_get_action_selector_menus($courseid, $pageurl) {
    global $CFG;

    $activeurl = new moodle_url($pageurl);
    $activeurl->remove_params(['nav']);

    $coursetemplateurl = new moodle_url($activeurl, ['nav' => 'coursetemplate']);
    $studentviewurl = new moodle_url($activeurl, ['nav' => 'studentview']);
    $helpurl = new moodle_url($activeurl, ['nav' => 'help']);

    $menus[$coursetemplateurl->out(false)] = get_string('coursetemplate', 'format_kickstart');
    $menus[$studentviewurl->out(false)] = get_string('studentview', 'format_kickstart');
    $menus[$helpurl->out(false)] = get_string('help' , 'format_kickstart');

    if (format_kickstart_has_pro()) {
        require_once($CFG->dirroot. "/local/kickstart_pro/lib.php");
        $menus += local_kickstart_pro_get_action_selector_menus($courseid, $activeurl);
    }
    return $menus;
}

function format_kickstart_output_fragment_get_kickstart_templatelist($args) {
    global $PAGE, $DB, $USER;
    $course = get_course($args['courseid']);
    $action = $args['action'];

    $PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'init',
    ['contextid' => $args['contextid'], 'courseid' => $course->id, 'nav' => $args['menuid'], 'filteroptions' => false]);

    $params = ['action' => $action, 'value' => $args['value']];

    // Modify the actions related to the kickstart page.
    if ($action == 'changetemplate') {
        if (!empty($args['search'])) {
            $params['action'] = "searchtemplate";
            $params['value'] = $args['search'];
        }

        if ($DB->record_exists('course_format_options', ['courseid' => $course->id, 'name' => 'templatesview'])) {
            $DB->set_field('course_format_options', 'value', $args['value'], ['courseid' => $course->id, 'name' => 'templatesview']);
        } else {
            $record = new stdClass();
            $record->courseid = $course->id;
            $record->format = 'kickstart';
            $record->name = 'templatesview';
            $record->sectionid = 0;
            $record->value = $args['value'];
            $DB->insert_record('course_format_options', $record);
        }
    }
    $renderer = $PAGE->get_renderer('format_kickstart');

    return $renderer->render(new course_template_list($course, $USER->id, $params));
}


function format_kickstart_output_fragment_get_library_courselist($args) {
    global $PAGE;

    $_GET['search'] = $args['searchcourse'];
    $sorttype = is_null($args['sort']) ? 'relevance' : $args['sort'];

    $customvalues = json_decode($args['customvalues']);
    $course = get_course($args['courseid']);
    $context = \context::instance_by_id($args['contextid']);
    $nav = $args['menuid'];

    $PAGE->requires->js_call_amd('format_kickstart/formatkickstart', 'init',
    ['contextid' => $context->id, 'courseid' => $course->id, 'nav' => $nav, 'filteroptions' => false]);


    $renderer = $PAGE->get_renderer('format_kickstart');

    return $renderer->render(new \format_kickstart\output\import_course_list((array) $customvalues, $sorttype));
}


function format_kickstart_output_fragment_get_import_module_box($args) {
    global $PAGE, $OUTPUT;
    $template = [];
    $template['information'] = get_string('importmoduleinformation', 'format_kickstart');
    $modinfo = get_fast_modinfo($args['maincourse']);
    $course = course_get_format($args['maincourse'])->get_course();
    $modinfosections = $modinfo->get_sections();
    $sections = $modinfo->get_section_info_all();
    $sectionsdata = [];
    foreach ($sections as $section) {
        $list['id'] = $section->id;
        $list['name'] = get_section_name($course, $section->section);
        $list['number'] = $section->section;
        $sectionsdata[] = $list;
    }
    $template['sections'] = $sectionsdata;
    return $OUTPUT->render_from_template('format_kickstart/import_module_list', $template);
}


function format_kickstart_output_fragment_import_activity_courselib($args) {
    global $USER, $CFG, $DB, $PAGE;
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/course/format/classes/base.php');
    // Security checks.
    $context = \context_course::instance($args['maincourse']);
    require_capability('moodle/course:manageactivities', $context);

    // Use Moodle's backup/restore functionality
    $bc = new backup_controller(backup::TYPE_1ACTIVITY, $args['cmid'], backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
    $bc->execute_plan();
    $backupid = $bc->get_backupid();
    $bc->destroy();

    $rc = new restore_controller($backupid, $args['maincourse'],
        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, backup::TARGET_EXISTING_ADDING);
    // Set target section using settings
    $plan = $rc->get_plan();
    $rc->execute_precheck();
    $rc->execute_plan();

    // Get mapping data from restore
    $rc->destroy();

    $record = $DB->get_record_sql("SELECT * FROM {course_modules} WHERE course = ? ORDER BY id DESC", [$args['maincourse']], IGNORE_MULTIPLE);

    $courseformat = course_get_format($args['maincourse']);
    $maincourserecord = $courseformat->get_course();
    $modinfo = get_fast_modinfo($maincourserecord);
    $cm = $modinfo->get_cm($record->id);
    $targetsection = $modinfo->get_section_info_by_id($args['sectionid'], MUST_EXIST);

    moveto_module($cm, $targetsection);
    // Any state action mark the state cache as dirty.
    core_courseformat\base::session_cache_reset($maincourserecord);

    return $record->id;
}