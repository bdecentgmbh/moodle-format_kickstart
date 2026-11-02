# Changes

## 1.6 (2026110201)

- Moodle 5.0 to 5.2; sites on 4.5 stay on 1.5.
- New Pro setting *Apply template access restrictions to managers*, plus a *Restricted* badge.
- Failed imports now show the reason.
- Bootstrap 5 throughout; JavaScript no longer uses jQuery.

## 1.5 (2026110200)

- Moodle 4.5 to 5.2.
- Security: template access is also checked on import, not only in the list.
- Importing a template no longer fails when the site's restore defaults are locked.
- "Merge Kickstart templates into courses" no longer fails on settings that only apply when
  replacing.
- The settings for keeping current roles, enrolments and groups now work as described.
- The course library uses Moodle's full capability check and shows at least 10 courses per page.
- Section summaries are trimmed by a correct word count.
- Improved list and grid sizing; template pictures are no longer cropped.
- Accessibility: labelled view and slide buttons, headings for screen readers.
- Student and teacher instructions work with web services; the upgrade converts existing ones.
- Uninstalling removes only the course format options Kickstart created.
- Documentation for the Documentation Center and a new README.
