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
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/excellib.class.php');

// Get parameters
$courseid = required_param('courseid', PARAM_INT);
$format = optional_param('format', 'excel', PARAM_ALPHA);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);

// Check login and capabilities
require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:download', $context);

// Validate course
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Get data
$activities = local_course_completion_report_get_course_activities($courseid);
$users = local_course_completion_report_get_enrolled_users($courseid, $cohortid, $lastnamesearch);

// Filter activities with completion tracking
$activities_with_completion = array_filter($activities, function($a) { return $a->completion > 0; });

if (empty($users) || empty($activities_with_completion)) {
    redirect(
        new moodle_url('/local/course_completion_report/index.php', ['courseid' => $courseid]),
        'Nessun dato disponibile per questo corso',
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Build headers
$headers = ['Coorte', 'Nome', 'Cognome'];
foreach ($activities_with_completion as $activity) {
    $activityname = format_string($activity->name);
    $headers[] = $activityname . ' - Stato';
    $headers[] = $activityname . ' - Data Inizio';
    $headers[] = $activityname . ' - Data Fine';
}

// Add certificate columns if customcert is available
$hascustomcert = local_course_completion_report_is_customcert_available();
if ($hascustomcert) {
    $headers[] = 'Attestato - Scaricato';
    $headers[] = 'Attestato - Data';
}

// Build data rows
$rows = [];
foreach ($users as $user) {
    $cohorts = local_course_completion_report_get_user_cohorts($user->id);

    $row = [
        $cohorts,
        $user->firstname,
        $user->lastname
    ];
    
    foreach ($activities_with_completion as $activity) {
        $completed = local_course_completion_report_is_activity_completed($user->id, $activity->id);
        $startdate = local_course_completion_report_get_first_access_date($user->id, $activity->id);
        $enddate = local_course_completion_report_get_completion_date($user->id, $activity->id);
        
        $row[] = $completed ? 'Completato' : 'Non completato';
        $row[] = $startdate ? userdate($startdate, '%d/%m/%Y', 'Europe/Rome') : '';
        $row[] = $enddate ? userdate($enddate, '%d/%m/%Y', 'Europe/Rome') : '';
    }
    
    // Add certificate data
    if ($hascustomcert) {
        $certissue = local_course_completion_report_get_certificate_issue($user->id, $courseid);
        if ($certissue) {
            $row[] = 'Sì';
            $row[] = userdate($certissue->timecreated, '%d/%m/%Y', 'Europe/Rome');
        } else {
            $row[] = 'No';
            $row[] = '';
        }
    }
    
    $rows[] = $row;
}

// Generate filename
$filename = 'completion_report_' . clean_filename($course->shortname) . '_' . date('Y-m-d_H-i-s');

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, $headers, ';');
    
    // Write data
    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
    
} else {
    // Excel Export using MoodleExcelWorkbook (no composer required)
    $workbook = new MoodleExcelWorkbook($filename);
    $worksheet = $workbook->add_worksheet('Report');
    
    // Write headers
    $col = 0;
    foreach ($headers as $header) {
        $worksheet->write_string(0, $col, $header);
        $col++;
    }
    
    // Write data rows
    $rownum = 1;
    foreach ($rows as $row) {
        $col = 0;
        foreach ($row as $cell) {
            $worksheet->write_string($rownum, $col, $cell);
            $col++;
        }
        $rownum++;
    }
    
    $workbook->close();
    exit;
}
