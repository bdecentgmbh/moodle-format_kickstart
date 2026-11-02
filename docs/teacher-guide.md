---
audience: teacher, manager
pagetypes:
  - course-view-kickstart
  - course-format-kickstart-*
---

# Kickstart format — Teacher guide

Kickstart gives a new course a starting point. Instead of an empty course, you see a list of course
templates that your administrators have prepared. You choose one, and the course is set up for you.
Until then, your students see a short message instead of an empty course.

## Choosing a template

1. Open your course. In a course in the Kickstart format, the templates are on the course page. In a
   course in another format, click *Kickstart* in the course navigation.
2. Browse the templates, or search for a name, tag or description. The icons next to the search box
   switch between tiles and a list.
3. If a template has a *Preview* button, it opens an example in a new tab.
4. Click *Use template* and confirm with *Import*.

The page reloads when the template is in place.

## What using a template does

There are two kinds of templates.

**A course backup** brings its content into your course. By default, it **replaces everything that is
in the course**, so choose a template before you add your own content. Your administrator can set
templates to be merged into the course instead.

The course keeps its full name, short name, summary, start date, end date and visibility. Other course
settings, including the course format, come from the template.

**A course format**, such as Topics or Weeks, only switches your course to that format with the
settings your administrator has chosen. It does not change your content.

> **Replacing can also remove participants.** With the default settings, Moodle removes the role
> assignments in the course and the enrolment methods that you are allowed to manage, as it does in any
> restore that deletes a course's content. Choose a template before people are enrolled, or ask your
> administrator what is set up on your site.

## The Kickstart menu

The menu above the templates leads to the other Kickstart pages. Your administrator decides which of
them are shown and in which order.

- **Course template**: the list of templates.
- **Student view**: what students see until a template has been chosen.
- **Help**: a short overview of these pages.

## Instructions for you and your students

In the course settings, under *Course format*, you can change:

- **Templates view**: tiles or a list.
- **Student instructions**: shown to students until a template has been chosen. Use them to say what
  students should do when the course is still empty.
- **Teacher instructions**: shown above the templates.

Both instructions start with the text your administrator has set for the site.

## What students see

Students cannot choose templates. In a course in the Kickstart format, they see a welcome message with
the name of the course and the student instructions. Once you have used a template, they see the
course as usual.

*Student view* only works in a course in the Kickstart format. In other formats, it says that it is not
available.

## Who can use templates

You need the capability `format/kickstart:import_from_template`, which editing teachers, course creators
and managers have by default. Non-editing teachers and students see the student view.

If you can also manage templates, a *Manage templates* button leads to the list of all templates. See
the [administrator guide](admin-guide.md).

## When something goes wrong

- **"No templates are available."**: your administrator has not enabled any template for you.
- **"This template is not available for import."**: the template was disabled or restricted after the
  page was loaded. Reload the page and choose another one.
- **"Template has no course backup set."**: the template's backup file is missing. Please tell your
  administrator.
- **"Restore failed with status …"**: the backup could not be restored into your course. The message ends with
  the reason. Please tell your administrator which template you used, and what the message says.
