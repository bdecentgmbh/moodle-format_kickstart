---
exclude: true
---

# Kickstart format — Developer guide

How the format is built, for people changing it or testing it.

## How it works

A course in this format has no sections to show. `format.php` draws the Kickstart pages instead. Courses in
other formats reach the same pages through a secondary navigation node
(`format_kickstart_extend_navigation_course()`) and `list.php`.

| Part | Where | What it does |
|---|---|---|
| Course format | `lib.php` (`format_kickstart`) | Course options: templates view, student and teacher instructions as `_editor` options, stored as `userinstructions` and `userinstructionsformat`, and likewise for the teacher instructions |
| Kickstart pages | `format.php`, `list.php`, `classes/output/kickstartHandler.php`, `classes/output/kickstart_page.php` | `get_<nav>_content()` per page. With Kickstart Pro, its handler adds the Pro pages |
| Menu | `format_kickstart_get_all_pages()`, `format_kickstart_get_ordered_pages()`, `classes/output/general_action_bar.php` | The pages and their order from the `pageorder_<page>` settings; 0 hides a page |
| Template list | `classes/output/course_template_list.php`, `templates/course_template_list.mustache`, `amd/src/formatkickstart.js` | Templates the user may use, with search and the tile or list view through fragments |
| Access | `format_kickstart_can_use_template()` | The one check for listing and importing: visible, enabled and, for the Pro restrictions, cohort, category, user and role. Holders of `manage_templates` bypass the restrictions unless `applyrestrictionstomanagers` is on (`format_kickstart_template_restrictions_bypassed()`) |
| Import | `classes/course_importer.php`, `classes/external.php`, `import.php` | `import_from_template()` restores a backup or switches the course format, then triggers `course_imported`. The web service `format_kickstart_import_template` is what the list calls |
| Managing templates | `templates.php`, `template.php`, `classes/template_table.php`, `classes/form/template_form.php` | The list of templates, creating, editing and deleting |
| Course format templates | `format_kickstart_import_courseformat_template()`, `format_kickstart_check_format_template()`, `classes/observer.php` | One template per course format, kept in step at runtime (cache `templates`) and when a format is enabled or disabled |
| Course library | `classes/output/import_course_list.php`, `classes/output/import_courselibrary_search.php`, the fragments in `lib.php` | Lives here, but is only offered with Kickstart Pro, which has the templates and the import page |

Templates are stored in `format_kickstart_template`, the options of course format templates in
`format_kickstart_options`. The order of the templates is in the config value `kickstart_templates`. Files are in
the system context: `course_backups` of this component, and `templatebackimg` of `local_kickstart_pro`.

### Restoring a template

`course_importer::import()` restores with `restore_controller` and the target from the setting `importtarget`, by
default `backup::TARGET_EXISTING_DELETING`. It sets the restore settings itself, and clears any lock on them first,
as core does for duplicating a course, so teachers need no restore capabilities and locked site defaults cannot
break the import.

`keep_roles_and_enrolments` and `keep_groups_and_groupings` only exist for targets that replace content, so they
are not set when merging. Without their settings (without Kickstart Pro) they are false, and
`restore_controller::execute_plan()` then lets core remove the roles and enrolments.

After the restore, `import()` puts back the full name, short name, summary, end date, creation time and visibility
of the course.

### Kickstart Pro

`format_kickstart_has_pro()` checks whether `local_kickstart_pro` is installed. `$CFG->kickstart_pro` overrides it,
for example in tests. Pro-only settings are in this plugin's `settings.php`, and their strings in its language file.
This plugin calls functions and classes of Kickstart Pro directly, so the two must be from the same release.

## Tests

- PHPUnit: `tests/format_kickstart_test.php`, including imports with locked restore defaults, with both import
  targets, and the template access checks.
- Behat: `tests/behat/`.
- CI: `.github/workflows/moodle-ci.yml` runs `moodle-plugin-ci` on Moodle 5.0, 5.1 and 5.2. It runs on pull requests and on pushes to `main`, and can be started by hand.

## Releases

Publish a release on GitHub. `.github/workflows/release.yml` does the rest, after Moodle Plugin CI has passed on the
tagged commit. CI does not run on other commits, so tag the head of a pull request or a commit on `main`. `<release>` is `$plugin->release` from `version.php`.

| | Pre-release ("Set as a pre-release") | Release |
|---|---|---|
| Tag | `v<release>` or `v<release>-<suffix>` (`v1.6-rc1`), any branch | `v<release>` (`v1.6`) or `v<release>-r<n>`, on `main` |
| Needs | | A section `## <release> (<version>)` in `CHANGES.md` |
| ZIP on the GitHub release | Yes | Yes |
| Moodle Marketplace | No | Yes |
| Website | staging.bdecent.de | bdecent.de and staging.bdecent.de |

Repository secrets: `BDECENT_RELEASE_SECRET`, `BDECENT_STAGING_AUTH` and `MOODLE_MARKETPLACE_TOKEN`. To run it again, use
*Actions > Release > Run workflow* with the tag. `.gitattributes` keeps repository files out of the ZIP.

Kickstart format and Kickstart Pro ship together:

1. Merge Kickstart format first. Kickstart Pro's CI on `main` installs Kickstart format's `main`.
2. Merge Kickstart Pro.
3. Publish Kickstart Pro's release first, then this plugin's, so that the Marketplace never offers a version whose
   Kickstart Pro is not in the shop yet.
