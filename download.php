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
 * Download handler for course completion report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Carlo Anardu
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/excellib.class.php');

$courseid = required_param('courseid', PARAM_INT);
$format = optional_param('format', 'excel', PARAM_ALPHA);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);
$onlyactive = optional_param('onlyactive', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:download', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);

$report = local_course_completion_report_build_report($courseid, $cohortid, $lastnamesearch, $onlyactive);

if (empty($report->users) || empty($report->activities)) {
    redirect(
        new moodle_url('/local/course_completion_report/index.php', ['courseid' => $courseid]),
        get_string('nodata', 'local_course_completion_report'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Headers.
$headers = [
    get_string('cohort', 'local_course_completion_report'),
    get_string('firstname'),
    get_string('lastname'),
];
foreach ($report->activities as $activity) {
    $activityname = format_string($activity->name, true, ['escape' => false]);
    $headers[] = $activityname . ' - ' . get_string('col_status', 'local_course_completion_report');
    $headers[] = $activityname . ' - ' . get_string('col_start', 'local_course_completion_report');
    $headers[] = $activityname . ' - ' . get_string('col_end', 'local_course_completion_report');
}
if ($report->hascustomcert) {
    $headers[] = get_string('certificate', 'local_course_completion_report') . ' - ' .
        get_string('col_issued', 'local_course_completion_report');
    $headers[] = get_string('certificate', 'local_course_completion_report') . ' - ' .
        get_string('col_date', 'local_course_completion_report');
}

// Rows.
$rows = [];
foreach ($report->users as $user) {
    $row = [
        implode(', ', $report->cohorts[$user->id] ?? []),
        $user->firstname,
        $user->lastname,
    ];

    foreach ($report->activities as $activity) {
        $cell = local_course_completion_report_get_cell($report, $user->id, $activity->id);
        $row[] = local_course_completion_report_status_label($cell->status);
        $row[] = local_course_completion_report_format_date($cell->start);
        $row[] = local_course_completion_report_format_date($cell->end);
    }

    if ($report->hascustomcert) {
        $certtime = $report->certificates[$user->id] ?? null;
        $row[] = $certtime ? get_string('yes') : get_string('no');
        $row[] = local_course_completion_report_format_date($certtime);
    }

    $rows[] = $row;
}

$filename = 'completion_report_' . clean_filename($course->shortname) . '_' . date('Y-m-d_H-i-s');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility.
    fwrite($output, "\xEF\xBB\xBF");

    // Every cell is neutralised against formula ("CSV") injection.
    fputcsv($output, array_map('local_course_completion_report_csv_safe', $headers), ';', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, array_map('local_course_completion_report_csv_safe', $row), ';', '"', '\\');
    }

    fclose($output);
    exit;
}

// Excel export. write_string() stores every value as plain text, so formulas are never evaluated.
$workbook = new MoodleExcelWorkbook($filename);
$worksheet = $workbook->add_worksheet(get_string('sheetname', 'local_course_completion_report'));

foreach (array_values($headers) as $col => $header) {
    $worksheet->write_string(0, $col, $header);
}

$rownum = 1;
foreach ($rows as $row) {
    foreach (array_values($row) as $col => $cell) {
        $worksheet->write_string($rownum, $col, (string)$cell);
    }
    $rownum++;
}

$workbook->close();
exit;
