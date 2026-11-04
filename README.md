# Kickstart format

[![Moodle Plugin CI](https://github.com/bdecentgmbh/moodle-format_kickstart/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/bdecentgmbh/moodle-format_kickstart/actions/workflows/moodle-ci.yml)

Kickstart is a Moodle course format that helps teachers and organisations create better courses, faster
and more consistently. Instead of an empty course, teachers get a list of course templates to choose
from when they open the course, and students see a helpful message until the course is ready.

Other plugins let the person creating a course choose a template, which is useful in many cases. In many
organisations, however, that person does not decide on the course's content and structure. With Kickstart,
the template is chosen when the course is opened with editing rights.

## Features

- **Course backups as templates**: upload a Moodle course backup (`.mbz`) and teachers can bring it into
  their course with one click. The course keeps its name, dates and visibility.
- **Course formats as templates**: every installed course format can be chosen, with preset options.
- **Search and views**: teachers search templates by name, tag or description, as tiles or a list.
- **Instructions**: a site-wide text for teachers and one for students, adjustable per course, that say what
  to do with a new course.
- **Configurable menu**: choose the order of the Kickstart pages in a course, and hide the ones you do not
  need.

### Kickstart Pro

[Kickstart Pro](https://bdecent.de/products/moodle-plugins/kickstart-course-wizard-pro/) adds:

- Unlimited course templates, in the order you choose, with background images (Kickstart format alone has
  up to four course backup templates).
- Templates only for certain cohorts, categories, roles or users.
- A course library to find other courses and import them or single activities.
- Creating a template from the current course.
- Creating courses from a short form, and applying a template automatically when only one is available.
- A student page with the course image, summary and the staff of the course.

## Requirements

Moodle 5.0 to 5.2. For Moodle 4.5, use Kickstart format 1.5.

If you use Kickstart Pro, always install the same release of both plugins.

## Installation

1. Download the plugin or clone it into `course/format/kickstart` (`public/course/format/kickstart` from
   Moodle 5.1).
2. Go to *Site administration > Notifications* to complete the installation.

Templates are managed in *Site administration > Courses > Course templates*, the settings are in
*Site administration > Plugins > Course formats > Kickstart format*. To use the format, choose *Kickstart format*
in the settings of a course, or as the default in *Site administration > Courses > Default settings > Course
default settings*.

## Documentation

- [Teacher guide](docs/teacher-guide.md): choosing a template, what it does to a course, what students see
- [Administrator guide](docs/admin-guide.md): installation, upgrading, templates, settings, permissions, privacy
- [Developer guide](docs/developer-guide.md): how the format works, tests and releases

With the Documentation Center (`tool_mdocs`) installed, the guides are also offered on the Kickstart pages.

## Support

Issues and pull requests: https://github.com/bdecentgmbh/moodle-format_kickstart

We would love to know whether you like the plugin, and we are always open to suggestions.

## License

Copyright (C) 2021 onwards bdecent gmbh <https://bdecent.de>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General
Public License as published by the Free Software Foundation, either version 3 of the License, or (at your
option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License along with this program. If not, see
<https://www.gnu.org/licenses/>.
