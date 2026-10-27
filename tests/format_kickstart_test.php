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
 * Kickstart course format testcases.
 *
 * @package    format_kickstart
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace format_kickstart;

use backup;
use backup_controller;
use core\context\course as context_course;
use core\context\system as context_system;
use core\url as moodle_url;

/**
 * Test kickstart course format.
 *
 * @group      format_kickstart
 * @group      format_kickstart_test
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class format_kickstart_test extends \advanced_testcase {
    /**
     * Set the admin user as User.
     *
     * @return void
     */
    public function setup(): void {
        global $CFG;
        require_once($CFG->dirroot . "/course/format/kickstart/lib.php");
        $this->setAdminUser();
        $this->resetAfterTest(true);
    }

    /**
     * Testing the import template in the course.
     * @covers ::import_from_template
     * @return mixed
     */
    public function test_importing(): void {
        global $DB, $CFG;
        $course = $this->getDataGenerator()->create_course([
            'startdate' => 1000,
            'enddate' => 1000,
            'sortorder' => 10001,
            'enablecompletion' => 1,
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
        ]);
        $template = new \stdClass();
        $template->title = '';
        $template->description = '';
        $template->descriptionformat = '';
        $template->id = $DB->insert_record('format_kickstart_template', $template);

        $fs = get_file_storage();

        $fileinfo = [
            'contextid' => context_system::instance()->id,
            'component' => 'format_kickstart',
            'filearea' => 'course_backups',
            'itemid' => $template->id,
            'filepath' => '/',
            'filename' => 'course-10-online.mbz',
        ];

        $fs->create_file_from_pathname($fileinfo, $CFG->dirroot . '/course/format/kickstart/assets/templates/course-10-online.mbz');

        $this->import_template($template, $course);

        $updatecourse = $DB->get_record('course', ['id' => $course->id]);

        $excludefield = [
            'format',
            'sortorder',
            'newsitems',
            'timemodified',
            'enabledcompletion',
            'cacherev',
            'hiddensections',
            'coursedisplay',
            'showactivitydates',
        ];

        foreach ($course as $field => $value) {
            if (in_array($field, $excludefield)) {
                continue;
            }
            $this->assertEquals(
                $course->$field,
                $updatecourse->$field,
                'Ensure course setting was not changed after import: ' . $field
            );
        }
    }

    /**
     * Testing the import template while the site restore defaults are locked.
     * @covers \format_kickstart\course_importer::import
     * @return void
     */
    public function test_importing_with_locked_restore_defaults(): void {
        global $DB, $CFG;

        // Lock the restore default settings the importer overrides
        // (Site administration > Courses > Backups > General restore defaults).
        $lockedconfigs = [
            'restore_general_users_locked',
            'restore_replace_overwrite_conf_locked',
            'restore_replace_course_fullname_locked',
            'restore_replace_course_shortname_locked',
            'restore_replace_course_startdate_locked',
            'restore_replace_keep_roles_and_enrolments_locked',
            'restore_replace_keep_groups_and_groupings_locked',
        ];
        foreach ($lockedconfigs as $config) {
            set_config($config, 1, 'restore');
        }

        $course = $this->getDataGenerator()->create_course();
        $template = $this->create_template_with_backup('locked restore defaults');

        $this->import_template($template, $course);

        // The import went through and the course identity was preserved.
        $updatecourse = $DB->get_record('course', ['id' => $course->id]);
        $this->assertEquals($course->fullname, $updatecourse->fullname);
        $this->assertEquals($course->shortname, $updatecourse->shortname);
    }

    /**
     * Testing the import template with the merge import target.
     *
     * Core locks keep_roles_and_enrolments and keep_groups_and_groupings by
     * config for every restore target that is not "deleting", so importing
     * with the "Merge Kickstart templates into courses" import target must
     * work even though no restore default is locked in the site
     * administration. The template content is merged into the course while
     * the existing content is kept.
     *
     * @covers \format_kickstart\course_importer::import
     * @return void
     */
    public function test_importing_with_merge_import_target(): void {
        global $DB;

        $template = $this->create_template_from_course($this->create_source_course());
        $target = $this->create_target_course(true);

        set_config('importtarget', backup::TARGET_EXISTING_ADDING, 'format_kickstart');
        $this->import_template($template, $target);

        // The existing forum was kept and the template forum was added.
        $this->assertTrue($DB->record_exists('forum', ['course' => $target->id, 'name' => 'Target forum']));
        $this->assertTrue($DB->record_exists('forum', ['course' => $target->id, 'name' => 'Source forum']));
        $this->assert_template_content_restored($target, 2);
        $this->assert_course_identity_preserved($target);
    }

    /**
     * Testing the import template with the replace import target.
     *
     * @covers \format_kickstart\course_importer::import
     * @return void
     */
    public function test_importing_with_replace_import_target(): void {
        global $DB;

        $template = $this->create_template_from_course($this->create_source_course());
        $target = $this->create_target_course(true);

        set_config('importtarget', backup::TARGET_EXISTING_DELETING, 'format_kickstart');
        $this->import_template($template, $target);

        // The existing forum was replaced by the template forum.
        $this->assertFalse($DB->record_exists('forum', ['course' => $target->id, 'name' => 'Target forum']));
        $this->assertTrue($DB->record_exists('forum', ['course' => $target->id, 'name' => 'Source forum']));
        $this->assert_template_content_restored($target, 1);
        $this->assert_course_identity_preserved($target);
    }

    /**
     * Testing the import template with the replace target on a course that
     * previously received template content through a merge import.
     *
     * @covers \format_kickstart\course_importer::import
     * @return void
     */
    public function test_importing_with_replace_after_merge(): void {
        global $DB;

        $template = $this->create_template_from_course($this->create_source_course());
        $target = $this->create_target_course();

        // First merge the template into the course.
        set_config('importtarget', backup::TARGET_EXISTING_ADDING, 'format_kickstart');
        $this->import_template($template, $target);
        $this->assertSame(1, $DB->count_records('subsection', ['course' => $target->id]));

        // Then replace the course content with the same template.
        set_config('importtarget', backup::TARGET_EXISTING_DELETING, 'format_kickstart');
        $this->import_template($template, $target);

        $this->assert_template_content_restored($target, 1);
        $this->assert_course_identity_preserved($target);
    }

    /**
     * Section summaries are trimmed by word count, ignoring whitespace and
     * non-breaking space variations.
     *
     * @covers \format_kickstart\output\import_course_list::sectionsummary_trim_char
     * @return void
     */
    public function test_sectionsummary_trim_char(): void {
        $courselist = new \format_kickstart\output\import_course_list();

        // Summaries within the limit are not trimmed, so no button is shown.
        $this->assertSame('', $courselist->sectionsummary_trim_char($this->get_summary_words(22), 23));
        $this->assertSame('', $courselist->sectionsummary_trim_char($this->get_summary_words(23), 23));

        // Longer summaries are cut after the configured number of words.
        $this->assertSame(
            $this->get_summary_words(23) . '...',
            $courselist->sectionsummary_trim_char($this->get_summary_words(24), 23)
        );
        $this->assertSame(
            $this->get_summary_words(23) . '...',
            $courselist->sectionsummary_trim_char($this->get_summary_words(60), 23)
        );

        // Non-breaking spaces and repeated whitespace do not change the word count.
        $this->assertSame(
            $this->get_summary_words(23) . '...',
            $courselist->sectionsummary_trim_char(str_replace(' ', '&nbsp;', $this->get_summary_words(24)), 23)
        );
        $this->assertSame(
            '',
            $courselist->sectionsummary_trim_char(str_replace(' ', '  ', $this->get_summary_words(23)), 23)
        );

        // The method returns plain text; the template is responsible for escaping it.
        $this->assertSame(
            'Question & Answers...',
            $courselist->sectionsummary_trim_char(format_string('Question & Answers session'), 3)
        );
    }

    /**
     * Build a formatted sample summary of the given number of words.
     *
     * @param int $count Number of words.
     * @return string
     */
    protected function get_summary_words(int $count): string {
        $words = [];
        for ($i = 1; $i <= $count; $i++) {
            $words[] = 'word' . $i;
        }

        return format_string('<p>' . implode(' ', $words) . '</p>');
    }

    /**
     * Create a course template using the bundled backup file.
     *
     * @param string $title Template title.
     * @return \stdClass Template record.
     */
    protected function create_template_with_backup(string $title): \stdClass {
        global $CFG, $DB;
        $template = new \stdClass();
        $template->title = $title;
        $template->description = '';
        $template->descriptionformat = 1;
        $template->id = $DB->insert_record('format_kickstart_template', $template);

        get_file_storage()->create_file_from_pathname([
            'contextid' => context_system::instance()->id,
            'component' => 'format_kickstart',
            'filearea' => 'course_backups',
            'itemid' => $template->id,
            'filepath' => '/',
            'filename' => 'course-10-online.mbz',
        ], $CFG->dirroot . '/course/format/kickstart/assets/templates/course-10-online.mbz');

        return $template;
    }

    /**
     * Create the target course used by the import tests.
     *
     * @param bool $withforum Whether to add a pre-existing forum.
     * @return \stdClass Target course.
     */
    protected function create_target_course(bool $withforum = false): \stdClass {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Target course',
            'shortname' => 'target',
            'format' => 'kickstart',
        ]);
        if ($withforum) {
            $this->getDataGenerator()->create_module('forum', [
                'course' => $course->id,
                'name' => 'Target forum',
            ]);
        }

        return $course;
    }

    /**
     * Assert the template content was restored into the given course.
     *
     * @param \stdClass $course Target course.
     * @param int $forums Expected number of forums in the course.
     */
    protected function assert_template_content_restored(\stdClass $course, int $forums): void {
        global $DB;

        $this->assertSame($forums, $DB->count_records('forum', ['course' => $course->id]));
        $this->assertSame(1, $DB->count_records('label', ['course' => $course->id]));
        $this->assertSame(1, $DB->count_records('subsection', ['course' => $course->id]));
        $this->assertSame(1, $DB->count_records('page', ['course' => $course->id]));

        // The page must be inside the section delegated to the subsection.
        $subsection = $DB->get_record('subsection', ['course' => $course->id], '*', MUST_EXIST);
        $delegated = $DB->get_record('course_sections', [
            'course' => $course->id,
            'component' => 'mod_subsection',
            'itemid' => $subsection->id,
        ]);
        $this->assertNotEmpty($delegated, 'The delegated section was not restored.');

        $pagemoduleid = $DB->get_field('modules', 'id', ['name' => 'page'], MUST_EXIST);
        $pagecm = $DB->get_record('course_modules', [
            'course' => $course->id,
            'module' => $pagemoduleid,
        ], '*', MUST_EXIST);
        $this->assertEquals($delegated->id, $pagecm->section, 'The page is not inside the delegated section.');
    }

    /**
     * Import the given template into the course.
     *
     * @param \stdClass $template Template record.
     * @param \stdClass $course Target course.
     */
    protected function import_template(\stdClass $template, \stdClass $course): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        course_importer::import_from_template($template->id, $course->id);
    }

    /**
     * Assert the course identity fields were preserved by the import.
     *
     * @param \stdClass $course Target course.
     */
    protected function assert_course_identity_preserved(\stdClass $course): void {
        global $DB;

        $updated = $DB->get_record('course', ['id' => $course->id]);
        $this->assertSame($course->fullname, $updated->fullname);
        $this->assertSame($course->shortname, $updated->shortname);
    }

    /**
     * Create a source course with a forum, a label, a subsection module
     * containing a page in its delegated section, a quiz with a question and
     * a block.
     *
     * @return \stdClass Source course.
     */
    protected function create_source_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Source course',
            'shortname' => 'source',
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'name' => 'Source forum',
        ]);
        $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $subsection = $this->getDataGenerator()->create_module('subsection', [
            'course' => $course->id,
            'name' => 'Part one',
        ]);

        $modinfo = get_fast_modinfo($course);
        $delegated = $modinfo->get_section_info_by_component('mod_subsection', $subsection->id);
        $this->assertNotEmpty($delegated, 'The subsection did not create a delegated section.');

        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => $delegated->section,
        ]);

        // A quiz with a question in the course question bank.
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Source quiz',
        ]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category([
            'contextid' => context_course::instance($course->id)->id,
        ]);
        $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);

        // A block in the course context.
        $this->getDataGenerator()->create_block('online_users', [
            'parentcontextid' => context_course::instance($course->id)->id,
        ]);

        return $course;
    }

    /**
     * Back the given course up and register the backup as a template.
     *
     * @param \stdClass $course Course to back up.
     * @return \stdClass Template record.
     */
    protected function create_template_from_course(\stdClass $course): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            get_admin()->id
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $tempfile = make_request_directory() . '/template.mbz';
        $file->copy_content_to($tempfile);
        $bc->destroy();

        $template = new \stdClass();
        $template->title = 'Sample template';
        $template->description = '';
        $template->descriptionformat = 1;
        $template->id = $DB->insert_record('format_kickstart_template', $template);

        get_file_storage()->create_file_from_pathname([
            'contextid' => context_system::instance()->id,
            'component' => 'format_kickstart',
            'filearea' => 'course_backups',
            'itemid' => $template->id,
            'filepath' => '/',
            'filename' => 'template.mbz',
        ], $tempfile);

        return $template;
    }

    /**
     * Case to test the external method to create template.
     * @covers ::format_kickstart_create_template
     * @return void
     */
    public function test_create_template(): void {
        global $DB;
        $prevcount = $DB->count_records('format_kickstart_template');
        $template = $this->format_format_kickstart_template_info();
        $context = context_system::instance();
        format_kickstart_create_template($template, 1, $context, 'format_kickstart');
        $count = $DB->count_records('format_kickstart_template');
        $this->assertEquals($prevcount + 1, $count);
    }

    /**
     * Case to check the availablity of kickstart pro.
     * @covers ::format_kickstart_has_pro
     */
    public function test_check_kickstart_has_pro(): void {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_kickstart_pro');
        $pluginstatus = false;
        if (!empty($plugininfo)) {
            $pluginstatus = true;
        }
        $this->assertEquals(format_kickstart_has_pro(), $pluginstatus);
    }

    /**
     * Case to check that admins and template managers bypass template access
     * restrictions while applyrestrictionstomanagers is disabled (default).
     * @covers ::format_kickstart_can_use_template
     */
    public function test_can_use_template_privileged_bypass_by_default(): void {
        global $DB, $USER;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $cohort = $generator->create_cohort();
        $template = $this->create_restricted_template([
            'restrictcohort' => 1,
            'cohortids' => [$cohort->id],
        ]);

        // Site admin is not in the cohort but bypasses the restriction.
        $this->assertTrue(format_kickstart_can_use_template($template, $course->id, $USER->id));

        // Users with the manage_templates capability bypass the restriction too.
        $manager = $generator->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $generator->role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        $this->assertTrue(format_kickstart_can_use_template($template, $course->id, $manager->id));

        // Regular users do not.
        $user = $generator->create_user();
        $this->assertFalse(format_kickstart_can_use_template($template, $course->id, $user->id));
    }

    /**
     * Case to check that restrictions apply to admins and template managers
     * when applyrestrictionstomanagers is enabled.
     * @covers ::format_kickstart_can_use_template
     */
    public function test_can_use_template_restrictions_apply_to_privileged(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/cohort/lib.php');
        set_config('applyrestrictionstomanagers', 1, 'format_kickstart');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $cohort = $generator->create_cohort();
        $template = $this->create_restricted_template([
            'restrictcohort' => 1,
            'cohortids' => [$cohort->id],
        ]);

        // Admin is not in the cohort, so the template is not available.
        $this->assertFalse(format_kickstart_can_use_template($template, $course->id, $USER->id));
        cohort_add_member($cohort->id, $USER->id);
        $this->assertTrue(format_kickstart_can_use_template($template, $course->id, $USER->id));

        // A manager only satisfies a role restriction listing their role.
        $manager = $generator->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $generator->role_assign($managerrole->id, $manager->id, context_system::instance()->id);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $teachertemplate = $this->create_restricted_template([
            'restrictrole' => 1,
            'roleids' => [$teacherrole->id],
        ]);
        $this->assertFalse(format_kickstart_can_use_template($teachertemplate, $course->id, $manager->id));
        $managertemplate = $this->create_restricted_template([
            'restrictrole' => 1,
            'roleids' => [$managerrole->id],
        ]);
        $this->assertTrue(format_kickstart_can_use_template($managertemplate, $course->id, $manager->id));

        // Templates without restrictions stay available to everyone.
        $plain = $this->create_restricted_template([]);
        $this->assertTrue(format_kickstart_can_use_template($plain, $course->id, $USER->id));
    }

    /**
     * Case to check the template list flags restricted templates for users
     * seeing them through the manage_templates bypass.
     * @covers \format_kickstart\output\course_template_list::get_templates
     */
    public function test_course_template_list_restricted_flag(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'kickstart']);
        $cohort = $generator->create_cohort();
        // Hide the templates created during install to stay within the limit.
        $DB->set_field('format_kickstart_template', 'visible', 0);
        $this->create_restricted_template([
            'title' => 'restricted template',
            'restrictcohort' => 1,
            'cohortids' => [$cohort->id],
        ]);
        $this->create_restricted_template(['title' => 'plain template']);

        // Admin sees both templates, only the restricted one is flagged.
        $list = new \format_kickstart\output\course_template_list($course, $USER->id);
        $templates = [];
        foreach ($list->get_templates() as $template) {
            $templates[$template->title] = $template;
        }
        $this->assertArrayHasKey('restricted template', $templates);
        $this->assertNotEmpty($templates['restricted template']->restricted);
        $this->assertEmpty($templates['plain template']->restricted);

        // A regular user satisfying the restriction gets no flag.
        $user = $generator->create_user();
        cohort_add_member($cohort->id, $user->id);
        $list = new \format_kickstart\output\course_template_list($course, $user->id);
        $templates = [];
        foreach ($list->get_templates() as $template) {
            $templates[$template->title] = $template;
        }
        $this->assertArrayHasKey('restricted template', $templates);
        $this->assertEmpty($templates['restricted template']->restricted);

        // With the setting enabled the admin no longer sees the restricted template.
        set_config('applyrestrictionstomanagers', 1, 'format_kickstart');
        $list = new \format_kickstart\output\course_template_list($course, $USER->id);
        $titles = array_column($list->get_templates(), 'title');
        $this->assertNotContains('restricted template', $titles);
        $this->assertContains('plain template', $titles);
    }

    /**
     * Create a template with the given access restrictions.
     *
     * @param array $restrictions Template fields to override.
     * @return \stdClass The created template record.
     */
    protected function create_restricted_template(array $restrictions): \stdClass {
        global $DB;
        $template = $this->format_format_kickstart_template_info();
        foreach ($restrictions as $field => $value) {
            $template->$field = $value;
        }
        $id = format_kickstart_create_template(
            $template,
            format_kickstart_get_next_template_sort(),
            context_system::instance(),
            'format_kickstart'
        );
        return $DB->get_record('format_kickstart_template', ['id' => $id]);
    }

    /**
     * Get the template info.
     */
    public function format_format_kickstart_template_info(): object {
        $template = [
            'id' => 0,
            'title' => 'demo test 1',
            'description' => '<p dir="ltr" style="text-align: left;">test content of the block content</p>',
            'tags' => [],
            'backupfile' => 'course-10-online.mbz',
            'preview_url' => '',
            'restrictcohort' => 0,
            'cohortids' => [],
            'restrictcategory' => 0,
            'categoryids' => [],
            'includesubcategories' => 0,
            'restrictrole' => 0,
            'roleids' => [],
            'descriptionformat' => 1,
        ];
        return (object) $template;
    }

    /**
     * Case to check the add new course format template.
     * @covers ::format_kickstart_add_couseformat_template
     */
    public function test_format_kickstart_add_couseformat_template(): void {
        global $DB;
        $templatename = "Proline Format";
        $format = "proline";
        $counttemplate = $DB->count_records("format_kickstart_template");
        format_kickstart_add_couseformat_template($templatename, $format, $counttemplate, true);
        $this->assertTrue($DB->record_exists('format_kickstart_template', ['format' => $format, 'courseformat' => 1]));
    }

    /**
     * Case to check the update course format template.
     * @covers ::format_kickstart_update_template_format_options
     */
    public function test_format_kickstart_update_template_format_options(): void {
        global $DB;
        $this->create_kickstart_template_options();
        $format = 'topics';
        $template = $DB->get_record('format_kickstart_template', ['format' => $format, 'courseformat' => 1]);
        $this->assertTrue($DB->record_exists('format_kickstart_options', [
            'format' => $format,
            'templateid' => $template->id, 'name' => 'hiddensections',
        ]));
        $this->assertTrue($DB->record_exists('format_kickstart_options', [
            'format' => $format,
            'templateid' => $template->id, 'name' => 'coursedisplay',
        ]));
    }

    /**
     * Create course template options.
     * @return void
     */
    public function create_kickstart_template_options(): void {
        global $DB;
        $format = 'topics';
        $params['format'] = $format;
        $params['id'] = '1';
        $data['hiddensections'] = 1;
        $data['coursedisplay'] = 0;
        $data['courseformatoptions'] = 1;
        $template = $DB->get_record('format_kickstart_template', ['format' => $format, 'courseformat' => 1]);
        $courseformat = course_get_format((object) $params);
        $courseformat->update_course_format_options($data);
        format_kickstart_update_template_format_options($template);
    }

    /**
     * Case to check the get template options.
     * @covers ::format_kickstart_update_template_format_options
     * @return void
     */
    public function test_format_kickstart_get_template_format_options(): void {
        global $DB;
        $format = 'topics';
        $template = $DB->get_record('format_kickstart_template', ['format' => $format, 'courseformat' => 1]);
        $this->create_kickstart_template_options();
        $val = format_kickstart_get_template_format_options($template);
        $this->assertEquals($val['coursedisplay'], 0);
        $this->assertEquals($val['hiddensections'], 1);
    }

    /**
     * Case to check the remove kickstart template.
     * @covers ::format_kickstart_remove_kickstart_templates
     * @return void
     */
    public function test_format_kickstart_remove_kickstart_templates(): void {
        global $DB, $SITE;
        $format = 'topics';
        $template = $DB->get_record('format_kickstart_template', ['format' => $format, 'courseformat' => 1]);
        $this->create_kickstart_template_options();
        $this->assertTrue(!empty($DB->get_record('format_kickstart_template', ['id' => $template->id])));
        $this->assertTrue(!empty($DB->get_records('course_format_options', [
            'courseid' => $SITE->id,
            'format' => $template->format,
        ])));
        format_kickstart_remove_kickstart_templates($template->id);
        $this->assertFalse($DB->get_record('format_kickstart_template', ['id' => $template->id]));
        $this->assertFalse(in_array($template->id, format_kickstart_get_templates()));
    }

    /**
     * The base navigation pages are always available, in canonical order.
     * @covers ::format_kickstart_get_all_pages
     * @return void
     */
    public function test_format_kickstart_get_all_pages(): void {
        $pages = format_kickstart_get_all_pages();
        $this->assertArrayHasKey('coursetemplate', $pages);
        $this->assertArrayHasKey('studentview', $pages);
        $this->assertArrayHasKey('help', $pages);
        // Canonical order: course template comes first.
        $this->assertSame('coursetemplate', array_key_first($pages));
    }

    /**
     * Without any configuration every page stays visible in canonical order.
     * @covers ::format_kickstart_get_ordered_pages
     * @return void
     */
    public function test_format_kickstart_get_ordered_pages_default(): void {
        $keys = array_keys(format_kickstart_get_ordered_pages());
        // All base pages are present.
        $this->assertContains('coursetemplate', $keys);
        $this->assertContains('studentview', $keys);
        $this->assertContains('help', $keys);
        // Canonical relative order is preserved.
        $this->assertLessThan(array_search('studentview', $keys), array_search('coursetemplate', $keys));
        $this->assertLessThan(array_search('help', $keys), array_search('studentview', $keys));
    }

    /**
     * A configured order reorders the visible pages.
     * @covers ::format_kickstart_get_ordered_pages
     * @return void
     */
    public function test_format_kickstart_get_ordered_pages_reorder(): void {
        set_config('pageorder_help', 1, 'format_kickstart');
        set_config('pageorder_coursetemplate', 2, 'format_kickstart');
        set_config('pageorder_studentview', 3, 'format_kickstart');

        $keys = array_keys(format_kickstart_get_ordered_pages());
        $this->assertLessThan(array_search('coursetemplate', $keys), array_search('help', $keys));
        $this->assertLessThan(array_search('studentview', $keys), array_search('coursetemplate', $keys));
    }

    /**
     * A page configured with order 0 is hidden from the dropdown.
     * @covers ::format_kickstart_get_ordered_pages
     * @return void
     */
    public function test_format_kickstart_get_ordered_pages_hide(): void {
        set_config('pageorder_studentview', 0, 'format_kickstart');

        $keys = array_keys(format_kickstart_get_ordered_pages());
        $this->assertNotContains('studentview', $keys);
        // Other pages remain visible.
        $this->assertContains('coursetemplate', $keys);
        $this->assertContains('help', $keys);
    }

    /**
     * The default nav is the first visible page, with a safe fallback.
     * @covers ::format_kickstart_get_default_nav
     * @return void
     */
    public function test_format_kickstart_get_default_nav(): void {
        // Lowest order wins as the default.
        set_config('pageorder_coursetemplate', 5, 'format_kickstart');
        set_config('pageorder_studentview', 9, 'format_kickstart');
        set_config('pageorder_help', 1, 'format_kickstart');
        $this->assertSame('help', format_kickstart_get_default_nav());

        // When every available page is hidden it falls back to the course template.
        foreach (array_keys(format_kickstart_get_all_pages()) as $key) {
            set_config('pageorder_' . $key, 0, 'format_kickstart');
        }
        $this->assertSame('coursetemplate', format_kickstart_get_default_nav());
    }

    /**
     * The action bar collapses to a plain title when only one page is visible.
     * @covers \format_kickstart\output\general_action_bar::export_for_template
     * @return void
     */
    public function test_general_action_bar_single_item(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['format' => 'kickstart']);
        $context = context_course::instance($course->id);

        // Hide every page except the course template.
        foreach (array_keys(format_kickstart_get_all_pages()) as $key) {
            set_config('pageorder_' . $key, $key === 'coursetemplate' ? 1 : 0, 'format_kickstart');
        }

        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        $actionbar = new \format_kickstart\output\general_action_bar($context, $url, 'kickstart', 'coursetemplate');
        $data = $actionbar->export_for_template($PAGE->get_renderer('format_kickstart'));

        $this->assertArrayHasKey('singleitem', $data);
        $this->assertArrayNotHasKey('generalnavselector', $data);
        $this->assertEquals(get_string('coursetemplate', 'format_kickstart'), $data['title']);
    }

    /**
     * The action bar renders the dropdown selector when several pages are visible.
     * @covers \format_kickstart\output\general_action_bar::export_for_template
     * @return void
     */
    public function test_general_action_bar_multiple_items(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['format' => 'kickstart']);
        $context = context_course::instance($course->id);

        set_config('pageorder_coursetemplate', 1, 'format_kickstart');
        set_config('pageorder_studentview', 2, 'format_kickstart');
        set_config('pageorder_help', 3, 'format_kickstart');

        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        $actionbar = new \format_kickstart\output\general_action_bar($context, $url, 'kickstart', 'coursetemplate');
        $data = $actionbar->export_for_template($PAGE->get_renderer('format_kickstart'));

        $this->assertArrayHasKey('generalnavselector', $data);
        $this->assertArrayNotHasKey('singleitem', $data);
    }
}
