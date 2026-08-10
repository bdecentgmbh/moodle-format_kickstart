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

require_once($CFG->dirroot . '/course/format/lib.php');


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
            require_once($CFG->dirroot . "/local/kickstart_pro/lib.php");
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
            $data = $this->validate_format_options((array) $data, $sectionid);
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
        $records = $DB->get_records(
            'course_format_options',
            [
                'courseid' => $this->courseid,
                'format' => $this->format,
                'sectionid' => $sectionid,
            ],
            '',
            'name,id,value'
        );
        $changed = $needrebuild = false;
        foreach ($defaultoptions as $key => $value) {
            if (isset($records[$key])) {
                if (array_key_exists($key, $data) && $records[$key]->value !== $data[$key]) {
                    $DB->set_field(
                        'course_format_options',
                        'value',
                        $data[$key],
                        ['id' => $records[$key]->id]
                    );
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
        $data = (array) $data;

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
 * Get the Kickstart template ids stored in the config.
 *
 * @return int[] List of configured template ids.
 */
function format_kickstart_get_templates() {
    $templatesconfig = get_config(null, 'kickstart_templates');

    if (empty($templatesconfig)) {
        return [];
    }

    $templateids = explode(',', $templatesconfig);
    $templateids = array_filter(array_unique($templateids), 'strlen');
    return array_map('intval', $templateids);
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

    global $DB, $CFG;
    $fs = get_file_storage();
    $template->sort = $sort;
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
            $fs->create_file_from_pathname($filerecord, $imagepath);
        }
    }

    // Register the new template in the configured templates list.
    $templates = format_kickstart_get_templates();
    if (!in_array($id, $templates)) {
        $templates[] = $id;
        set_config('kickstart_templates', implode(',', $templates));
    }

    return $id;
}

/**
 * Does this file exist
 * @param object $filerecord
 * @return bool
 */
function check_record_exsist($filerecord) {

    $fs = get_file_storage();
    $exist = $fs->file_exists(
        $filerecord->contextid,
        $filerecord->component,
        $filerecord->filearea,
        $filerecord->itemid,
        $filerecord->filepath,
        $filerecord->filename
    );
    return $exist;
}

/**
 * Import the course format template
 * @return void
 */
function format_kickstart_import_courseformat_template() {
    global $DB, $CFG;
    $formats = core_plugin_manager::instance()->get_plugins_of_type('format');

    // Delete the kickstart format.
    if ($DB->record_exists('format_kickstart_template', ['format' => 'kickstart', 'courseformat' => 1])) {
        $DB->delete_records('format_kickstart_template', ['format' => 'kickstart', 'courseformat' => 1]);
    }
    $counttemplate = $DB->count_records("format_kickstart_template");
    foreach ($formats as $format) {
        if ($format->name == 'kickstart') {
            continue;
        }
        $counttemplate++;
        if ($format->name == 'designer') {
            require_once($CFG->dirroot . "/course/format/designer/lib.php");
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
    global $DB;
    $templates = format_kickstart_get_templates();
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
            $templates[] = $templateid;
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
    global $DB, $SITE;
    $isdesignerformat = ($template->format == 'designer') ? true : false;
    $records = $DB->get_records(
        'course_format_options',
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
            if (
                !$existrecord = $DB->get_record('format_kickstart_options', [
                    'format' => $courseformat,
                    'templateid' => $template->id,
                    'name' => $record->name,
                ])
            ) {
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
    $records = $DB->get_records_menu(
        'format_kickstart_options',
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
    global $DB;
    $templates = format_kickstart_get_templates();
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
            format_kickstart_import_courseformat_template();
        }
        $cache->set('templateformat', true);
    }
}

/**
 * Remove the kickstart template settings.
 * @param int $templateid
 */
function format_kickstart_remove_kickstart_templates($templateid) {
    global $SITE, $DB;
    $fs = get_file_storage();
    $context = context_system::instance();
    $templates = format_kickstart_get_templates();
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
    $key = array_search($templateid, $templates);
    if ($key !== false) {
        unset($templates[$key]);
    }
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
    if ($filearea === 'course_backups') {
        require_capability('format/kickstart:manage_templates', $context);
    } else if ($filearea !== 'description') {
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
        $node = $navigation->create(
            get_string('strkickstart', 'format_kickstart'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null
        );
        $node->add_class('kickstart-nav');
        $node->set_force_into_more_menu(false);
        $node->set_show_in_secondary_navigation(true);
        $node->key = 'kickstart-nav';
        $navigation->add_node($node);
    }
}


/**
 * Returns every Kickstart navigation page in the canonical (default) order.
 *
 * This is the single source of truth for the available sub-pages: the three
 * base pages (course template, student view, help) plus the Kickstart Pro pages
 * (course library, create template) when the Pro plugin is installed. The order
 * and visibility configured by the administrator is applied separately by
 * {@see format_kickstart_get_ordered_pages()}.
 *
 * @return array An associative array of page key => localized display name
 */
function format_kickstart_get_all_pages() {
    global $CFG;
    $pages = [
        'coursetemplate' => get_string('coursetemplate', 'format_kickstart'),
        'studentview' => get_string('studentview', 'format_kickstart'),
        'help' => get_string('help', 'format_kickstart'),
    ];

    if (format_kickstart_has_pro()) {
        require_once($CFG->dirroot . "/local/kickstart_pro/lib.php");
        $pages += local_kickstart_pro_get_breadcump_menus();
    }
    return $pages;
}

/**
 * Applies the admin-configured order and visibility to the Kickstart pages.
 *
 * For each page the administrator configures a numeric "order" value
 * (setting key {@code pageorder_<pagekey>}). Pages with an order of 0 (or an
 * empty value) are hidden; the remaining pages are sorted ascending by their
 * order, falling back to the canonical order for ties so the result is stable.
 *
 * @param array|null $pages Page key => label map. Defaults to {@see format_kickstart_get_all_pages()}.
 * @return array The visible pages as page key => label, in display order
 */
function format_kickstart_get_ordered_pages($pages = null) {
    if ($pages === null) {
        $pages = format_kickstart_get_all_pages();
    }

    $ordered = [];
    $canonicalindex = 0;
    foreach ($pages as $key => $label) {
        $raw = get_config('format_kickstart', 'pageorder_' . $key);
        if ($raw === false || $raw === '') {
            // Not configured yet: keep the canonical position and stay visible so a
            // fresh install (before defaults are applied) never hides every page.
            $order = $canonicalindex + 1;
        } else {
            $order = (int) $raw;
        }
        if ($order <= 0) {
            // An explicit value of 0 hides the page from the dropdown.
            $canonicalindex++;
            continue;
        }
        $ordered[] = [
            'order' => $order,
            'canonical' => $canonicalindex++,
            'key' => $key,
            'label' => $label,
        ];
    }

    usort($ordered, function ($a, $b) {
        return [$a['order'], $a['canonical']] <=> [$b['order'], $b['canonical']];
    });

    $result = [];
    foreach ($ordered as $page) {
        $result[$page['key']] = $page['label'];
    }
    return $result;
}

/**
 * Returns the default navigation page key for the Kickstart format.
 *
 * The default is the first visible page according to the admin-configured order.
 * If every page has been hidden, it falls back to the course template page so the
 * format always has something to render.
 *
 * @return string The default navigation (nav) key
 */
function format_kickstart_get_default_nav() {
    $ordered = format_kickstart_get_ordered_pages();
    if (empty($ordered)) {
        return 'coursetemplate';
    }
    return array_key_first($ordered);
}

/**
 * Retrieves the available breadcrumb menu items for the Kickstart format.
 *
 * Returns the visible pages (course template, student view, help and, when the
 * Kickstart Pro plugin is available, course library and create template) in the
 * order configured by the administrator.
 *
 * @return array An associative array of breadcrumb menu items
 */
function format_kickstart_get_breadcump_menus() {
    return format_kickstart_get_ordered_pages();
}


/**
 * Generates a list of action selector menu items for the Kickstart format.
 *
 * Builds the navigation dropdown URLs and labels for every visible page, honoring
 * the admin-configured order and visibility. The Pro "create template" page stays
 * gated behind the {@code local/kickstart_pro:create_template_course} capability.
 *
 * @param int $courseid The ID of the current course
 * @param moodle_url $pageurl The base URL for the current page
 * @return array An associative array of menu URLs and their corresponding labels
 */
function format_kickstart_get_action_selector_menus($courseid, $pageurl) {
    $activeurl = new moodle_url($pageurl);
    $activeurl->remove_params(['nav']);

    // The Pro create-template page stays gated behind its own capability.
    $cancreatetemplate = !format_kickstart_has_pro()
        || has_capability('local/kickstart_pro:create_template_course', \context_course::instance($courseid));

    $menus = [];
    foreach (format_kickstart_get_ordered_pages() as $key => $label) {
        if ($key === 'createtemplaefromcourse' && !$cancreatetemplate) {
            continue;
        }
        $url = new moodle_url($activeurl, ['nav' => $key]);
        $menus[$url->out(false)] = $label;
    }
    return $menus;
}

/**
 * Retrieves and renders a list of course templates for the Kickstart format.
 *
 * Handles template-related actions such as changing or searching templates,
 * and updates course format options accordingly. Initializes JavaScript
 * and renders the template list using the Kickstart format renderer.
 *
 * @param array $args Arguments containing course, action, and template details
 * @return string Rendered HTML for the course template list
 */
function format_kickstart_output_fragment_get_kickstart_templatelist($args) {
    global $PAGE, $DB, $USER;
    $course = get_course($args['courseid']);
    $action = $args['action'];
    // The search template uses a free-text string.
    if ($action === 'searchtemplate') {
        $value = $args['value'];
    } else {
        $value = ($args['value'] === 'list') ? 'list' : 'tile';
    }

    $context = \context_course::instance($course->id);
    require_capability('format/kickstart:import_from_template', $context);

    $PAGE->requires->js_call_amd(
        'format_kickstart/formatkickstart',
        'init',
        ['contextid' => $args['contextid'], 'courseid' => $course->id, 'nav' => $args['menuid'], 'filteroptions' => false]
    );

    $params = ['action' => $action, 'value' => $value];

    // Modify the actions related to the kickstart page.
    if ($action == 'changetemplate') {
        if (!empty($args['search'])) {
            $params['action'] = "searchtemplate";
            $params['value'] = $args['search'];
        }

        if ($DB->record_exists('course_format_options', ['courseid' => $course->id, 'name' => 'templatesview'])) {
            $DB->set_field('course_format_options', 'value', $value, [
                'courseid' => $course->id,
                'name' => 'templatesview',
            ]);
        } else {
            $record = new stdClass();
            $record->courseid = $course->id;
            $record->format = 'kickstart';
            $record->name = 'templatesview';
            $record->sectionid = 0;
            $record->value = $value;
            $DB->insert_record('course_format_options', $record);
        }
    }
    $renderer = $PAGE->get_renderer('format_kickstart');

    return $renderer->render(new course_template_list($course, $USER->id, $params));
}


/**
 * Retrieves and renders a list of courses for the Kickstart format library.
 *
 * Handles course search, sorting, and pagination for the course library import feature.
 * Initializes JavaScript and renders the course list using the Kickstart format renderer.
 *
 * @param array $args Arguments containing search parameters, course context, and pagination details
 * @return string Rendered HTML for the course library list
 */
function format_kickstart_output_fragment_get_library_courselist($args) {
    global $PAGE;

    $search = clean_param($args['searchcourse'], PARAM_NOTAGS);
    $sorttype = is_null($args['sort']) ? 'relevance' : $args['sort'];

    $customvalues = json_decode($args['customvalues']);
    $course = get_course($args['courseid']);
    $context = \context::instance_by_id($args['contextid']);
    $nav = $args['menuid'];
    $page = $args['page'];

    $PAGE->requires->js_call_amd(
        'format_kickstart/formatkickstart',
        'init',
        ['contextid' => $context->id, 'courseid' => $course->id, 'nav' => $nav, 'filteroptions' => false]
    );

    $renderer = $PAGE->get_renderer('format_kickstart');
    return $renderer->render(new \format_kickstart\output\import_course_list((array) $customvalues, $sorttype, $page, $search));
}


/**
 * Renders the sections/activities accordion for a single course in the library.
 *
 * Called on first expand of the "Show contents" button so the costly
 * get_fast_modinfo + filter passes happen only for courses the user actually
 * looks at, not for every course on the landing page.
 *
 * @param array $args Must contain courseid; optionally maincourse for the import action data.
 * @return string Rendered HTML for the accordion (sections + activities).
 */
function format_kickstart_output_fragment_get_library_coursecontents($args) {
    global $OUTPUT, $CFG;

    $courseid   = (int) ($args['courseid'] ?? 0);
    $maincourse = (int) ($args['maincourse'] ?? 0);

    if (!$courseid) {
        return '';
    }

    // The required capability is the same one the library itself enforces.
    require_capability('moodle/backup:backuptargetimport', \context_course::instance($courseid));

    $list = new \format_kickstart\output\import_course_list();
    $contents = $list->get_course_contents($courseid);

    // The partial needs the maincourse id on each module for the "Import activity" action.
    foreach ($contents as &$section) {
        foreach ($section['modules'] as &$module) {
            $module['maincourse'] = $maincourse;
        }
    }
    unset($section, $module);

    $data = [
        'contents'    => array_values($contents),
        'accordionid' => 'accordion-import-courses-' . $courseid,
        'datatoggle'  => ($CFG->branch >= 500) ? 'data-bs-toggle' : 'data-toggle',
        'datatarget'  => ($CFG->branch >= 500) ? 'data-bs-target' : 'data-target',
    ];

    return $OUTPUT->render_from_template('local_kickstart_pro/import_course_contents', $data);
}


/**
 * Generates a template for importing modules with section information.
 *
 * Prepares a template containing module import information and a list of course sections
 * to be rendered in the module import interface.
 *
 * @param array $args Arguments containing the main course ID
 * @return string Rendered HTML template for module import
 */
function format_kickstart_output_fragment_get_import_module_box($args) {
    global $OUTPUT;

    // Security checks.
    $context = \context_course::instance($args['maincourse']);
    require_capability('moodle/course:manageactivities', $context);

    $modinfo = get_fast_modinfo($args['maincourse']);
    $course = course_get_format($args['maincourse'])->get_course();
    $sections = $modinfo->get_section_info_all();

    $sectionsdata = [];
    foreach ($sections as $section) {
        $list['id'] = $section->id;
        $list['name'] = get_section_name($course, $section->section);
        $list['number'] = $section->section;
        $sectionsdata[] = $list;
    }

    $template = [
        'information' => get_string('importmoduleinformation', 'format_kickstart'),
        'sections' => $sectionsdata,
    ];
    return $OUTPUT->render_from_template('format_kickstart/import_module_list', $template);
}


/**
 * Imports an activity module from one course to another using Moodle's backup and restore functionality.
 *
 * This function handles the process of importing a single course module to a specified section,
 * performing necessary security checks and using Moodle's backup and restore controllers.
 *
 * @param array $args Arguments containing course and module information
 *                    - maincourse: The destination course ID
 *                    - cmid: The course module ID to be imported
 *                    - sectionid: The target section ID for the imported module
 * @return url The ID of the newly imported course module
 */
function format_kickstart_output_fragment_import_activity_courselib($args) {
    global $USER, $CFG, $DB;
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/course/format/classes/base.php');
    // Security checks.
    $context = \context_course::instance($args['maincourse']);
    require_capability('moodle/course:manageactivities', $context);

    // Use Moodle's backup/restore functionality.
    $bc = new backup_controller(
        backup::TYPE_1ACTIVITY,
        $args['cmid'],
        backup::FORMAT_MOODLE,
        backup::INTERACTIVE_NO,
        backup::MODE_IMPORT,
        $USER->id
    );
    $bc->execute_plan();
    $backupid = $bc->get_backupid();
    $backupbasepath = $bc->get_plan()->get_basepath();
    $bc->destroy();

    $rc = new restore_controller(
        $backupid,
        $args['maincourse'],
        backup::INTERACTIVE_NO,
        backup::MODE_IMPORT,
        $USER->id,
        backup::TARGET_EXISTING_ADDING
    );
    // Set target section using settings.
    $plan = $rc->get_plan();
    $cmcontext = \context_module::instance($args['cmid']);

    if (!$rc->execute_precheck()) {
        $precheckresults = $rc->get_precheck_results();
        if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
            if (empty($CFG->keeptempdirectoriesonbackup)) {
                fulldelete($backupbasepath);
            }
        }
    }

    $rc->execute_plan();

    $newcmid = null;
    $tasks = $rc->get_plan()->get_tasks();
    foreach ($tasks as $task) {
        if (is_subclass_of($task, 'restore_activity_task')) {
            if ($task->get_old_contextid() == $cmcontext->id) {
                $newcmid = $task->get_moduleid();
                break;
            }
        }
    }
    // Get mapping data from restore.
    $rc->destroy();

    $courseformat = course_get_format($args['maincourse']);
    $maincourserecord = $courseformat->get_course();
    $modinfo = get_fast_modinfo($maincourserecord);
    $cm = $modinfo->get_cm($newcmid);
    $targetsection = $modinfo->get_section_info_by_id($args['sectionid'], MUST_EXIST);

    moveto_module($cm, $targetsection);
    // Any state action mark the state cache as dirty.
    core_courseformat\base::session_cache_reset($maincourserecord);
    $viewurl = new \moodle_url("/mod/{$cm->modname}/view.php", ['id' => $newcmid]);
    if (
        $subsection = $DB->get_record('modules', ['name' => 'subsection']) && !empty($subsection) &&
        $subsection->id == $cm->module
    ) {
        $subsectionid = $DB->get_field('course_modules', 'id', ['itemid' => $newcmid, 'course' => $cm->course]);
        $viewurl = new \moodle_url('/course/section.php', ['id' => $subsectionid]);
    }

    return $viewurl->out(false);
}
