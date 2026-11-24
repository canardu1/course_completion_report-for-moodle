# Course Completion Report for Moodle

🇬🇧 **English** · 🇮🇹 [Italiano](README.it.md)

[![Made by AtlasTech](https://img.shields.io/badge/Made%20by-AtlasTech-blue)](https://atlastech.it)
[![Latest release](https://img.shields.io/github/v/release/canardu1/course_completion_report-for-moodle)](https://github.com/canardu1/course_completion_report-for-moodle/releases/latest)
[![Moodle 4.3+](https://img.shields.io/badge/Moodle-4.3%2B-F98012?logo=moodle&logoColor=white)](https://moodle.org)

A Moodle plugin (`local_course_completion_report`) that builds activity completion reports for a course — with cohorts, start and end dates and certificates — and exports them to Excel or CSV.

## Features

- **Course list** filterable by category and name.
- **Per-course report** showing, for every learner and every activity with completion tracking:
  - **Status**: *Completed*, *Failed* (completed with a failing grade) or *Not completed*;
  - **Start date**: first access to the activity (from the Moodle log);
  - **End date**: completion date.
- Learner **cohorts** as the first column.
- **Certificates** issued with the [Custom certificate](https://moodle.org/plugins/mod_customcert) plugin (if installed): issued yes/no and date.
- **User filters**: cohort, last name, only active enrolments.
- On-screen **preview** with a summary and completion rate.
- **Excel and CSV export** (CSV uses `;` as separator and a UTF-8 BOM for Excel).
- Interface in **English and Italian**.

## Requirements

- Moodle 4.3 or later (Moodle 5.x not yet verified)
- Optional: `mod_customcert` for the certificate columns

## Installation

### From the release ZIP (recommended)

1. Download `local_course_completion_report-<version>.zip` from the [latest release](https://github.com/canardu1/course_completion_report-for-moodle/releases/latest).
2. Go to **Site administration → Plugins → Install plugins**, upload the ZIP and follow the steps.

### Manually

1. Copy the plugin files into `<moodle>/local/course_completion_report/` (the folder **must** be named `course_completion_report`).
2. Go to **Site administration → Notifications** to complete the installation.

## Permissions

| Capability | Default role |
|---|---|
| `local/course_completion_report:view` | Manager |
| `local/course_completion_report:download` | Manager |

The report is **site-wide**: anyone with access can see every course and all of its enrolled users. That is why the capabilities are checked in the system context and granted to managers only by default (site administrators always have access).

## Usage

1. Open **Site administration → Reports → Course completion report**, or `/local/course_completion_report/index.php`.
2. Filter and select a course.
3. Apply the user filters, then use **Preview** or **Download Excel / CSV**.

## Notes

- The **start date** is read from Moodle's standard log: if the site deletes old logs (*Log store → Keep logs for*), the date is no longer available for those activities.
- Dates are shown in the **Europe/Rome** timezone.
- The CSV is protected against *CSV injection*: values starting with `=`, `+`, `-` or `@` are prefixed with an apostrophe so they don't turn into formulas when the file is opened.

## Changelog

### 1.11.0 — 2026-11-24
- **Fix:** activities completed with a failing grade now show as *Failed* instead of *Completed*, and have no end date.
- **Security:** CSV export protected against CSV injection.
- **Performance:** the report is built with a handful of queries instead of three per learner and activity; the course list also uses a single query.
- New **Only active enrolments** filter.
- All text moved to language files, with English and Italian translations.
- Added the Privacy API; default capabilities limited to managers; minimum requirement corrected to Moodle 4.3.
- Fixed shortening of activity names with accented letters in the preview.

### 1.10.0 and earlier
- Cohorts instead of profile fields, cohort and last name filters, customcert certificates, start and end dates.

## License

GNU GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
