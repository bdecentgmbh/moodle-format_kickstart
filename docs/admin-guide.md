---
audience: admin, manager
pagetypes:
  - admin-setting-formatsettingkickstart
  - admin-course-format-kickstart-*
  - course-format-kickstart-*
  - course-view-kickstart
---

# Kickstart format — Administrator guide

Kickstart format (`format_kickstart`) is a course format that offers teachers a list of course templates
when they open a new course. The template is chosen when the course is opened, not when it is created,
so the person creating courses does not have to decide on their content and structure. For using it in
a course, see the [teacher guide](teacher-guide.md).

## Requirements

Moodle 5.0 to 5.2. No other plugins are needed. For Moodle 4.5, use Kickstart format 1.5.

[Kickstart Pro](https://bdecent.de/products/moodle-plugins/kickstart-course-wizard-pro/)
(`local_kickstart_pro`) adds more templates, access restrictions for templates, a course library and
more. Its settings appear on the Kickstart settings page, and its guides are part of that plugin.

## Installation

1. Download the plugin or clone it into `course/format/kickstart` (`public/course/format/kickstart` from
   Moodle 5.1).
2. Go to *Site administration > Notifications* to complete the installation.

The installation adds one template for each installed course format and a demo template called
"demo test 1". Disable or delete the demo template before teachers start using Kickstart.

To use Kickstart for all new courses, choose it as the course format in *Site administration > Courses >
Default settings > Course default settings*.

## Upgrading

If you use Kickstart Pro, **always install the same release of both plugins**. Other combinations are not
supported and can fail: Kickstart Pro requires a matching Kickstart format, but Moodle cannot stop you
from updating Kickstart format alone.

Copy both plugins into place before you run the upgrade, for example with git or on the command line,
and then upgrade once. If you install ZIP files in the browser, Moodle needs Kickstart format first:
install Kickstart Pro right after it, because some pages can fail until both are updated.

Moodle does not allow going back to an older version without restoring a backup of your site.

### Upgrading to 1.6

- Moodle 5.0 is now the minimum. Sites on Moodle 4.5 stay on Kickstart format 1.5.
- With Kickstart Pro, there is a new setting, *Apply template access restrictions to managers*. It is off,
  so nothing changes until you enable it.
- If a template cannot be restored, the error message now includes the reason.

## Course templates

Go to *Site administration > Courses > Course templates*, or to *Site administration > Plugins > Course
formats > Kickstart format > Manage templates*.

There are two kinds of templates:

- **Course backups** bring the content of a Moodle course backup (`.mbz`) into the course.
- **Course formats** switch the course to a course format, without touching its content. There is one for
  each installed course format, and one for each course type of Format Designer, if you have it. They
  are added and removed with the course formats.

### Creating a template

1. Set up a course the way a new course should look, and make a backup of it without user data.
2. In the list of templates, click *Create template*.
3. Enter a **title**, and optionally a **description** and **tags**. Teachers can search for all three.
4. Upload the backup as **Course backup file (.mbz)**.
5. Optionally, enter a **Preview URL**. Teachers then see a *Preview* button that opens this address in a
   new tab, for example an example course they can look at.

Without Kickstart Pro, you can create up to four course backup templates, and teachers see at most
four. Course format templates do not count.

### Managing templates

- **Enable or disable** a template with the eye icon. Teachers only see enabled templates.
- **Edit** changes a template. For a course format template, you can change the description, tags and the
  options of the course format that a course gets when this template is used.
- **Delete** is available for course backup templates. Course format templates cannot be deleted; disable
  them instead.

Disabling a course format in *Site administration > Plugins > Course formats > Manage course formats* also
hides its template. Enabling the format brings the template back.

## What using a template does

A course backup template restores the backup into the course. Without Kickstart Pro:

- The content of the course is **replaced**.
- The course keeps its full name, short name, summary, start date, end date and visibility. All other
  course settings, including the course format, come from the backup.
- User data in the backup is not restored.
- **Current roles and enrolments are not kept.** As in any restore that deletes a course's content, Moodle
  removes the role assignments in the course and the enrolment methods that the person using the template
  is allowed to manage. The same applies to current groups and groupings. Moodle then makes sure that this
  person can still access the course.

If teachers choose templates in courses that already have participants, tell them to do so before people
are enrolled. Kickstart Pro has settings to keep roles, enrolments, groups and existing content.

Teachers do not need Moodle's backup and restore capabilities to use a template, and locked restore
defaults of the site do not apply to these settings.

## Settings

Go to *Site administration > Plugins > Course formats > Kickstart format > General settings*.

- **Default templates view**: tiles or a list, for new courses.
- **Default student instructions**: what students see until a template has been chosen. Teachers can change
  it per course.
- **Default teacher instructions**: what teachers see above the templates. Teachers can change it per
  course.
- **Navigation page order**: the order of the pages in the Kickstart menu. The page with the lowest number
  opens first. *Hide* removes a page from the menu.

## Permissions

| Capability | Default roles | Allows |
|---|---|---|
| `format/kickstart:manage_templates` | Manager | Creating, editing, enabling, disabling and deleting templates. Checked in the system context. Holders can use every template, unless Kickstart Pro is set to apply template restrictions to them |
| `format/kickstart:import_from_template` | Manager, Course creator, Editing teacher | Seeing the Kickstart pages of a course and using templates in it. Everybody else sees the student view |

## Privacy

Kickstart format stores one user preference, the number of templates per page on the list of templates.
Using a template is logged with Moodle's event *Course imported*. A course backup can contain user data,
so make the backups for your templates without it.
