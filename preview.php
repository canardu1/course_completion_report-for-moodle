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
 * Preview page for course completion report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Get course ID and filters
$courseid = required_param('courseid', PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);

// Check login and capabilities
require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:view', $context);

// Validate course
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/course_completion_report/preview.php', ['courseid' => $courseid]));
$PAGE->set_title('Anteprima Report - ' . format_string($course->fullname));
$PAGE->set_heading('Anteprima Report Completamento');
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

// Back button
echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/course_completion_report/index.php'),
        '<i class="fa fa-arrow-left me-2"></i>Torna alla lista corsi',
        ['class' => 'btn btn-secondary mb-3']
    )
);

echo $OUTPUT->heading('Report Completamento: ' . format_string($course->fullname));

// Filters
echo '<div class="card mb-4">';
echo '<div class="card-header"><h5>Filtri Utenti</h5></div>';
echo '<div class="card-body">';
echo '<form method="get" action="" class="row g-3">';
echo '<input type="hidden" name="courseid" value="' . $courseid . '">';

// Cohort filter
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<label for="cohortid" class="form-label">Coorte</label>';
echo '<select name="cohortid" id="cohortid" class="form-select w-100">';
echo '<option value="0">Tutte le coorti</option>';

$cohorts = $DB->get_records('cohort', null, 'name', 'id, name');
foreach ($cohorts as $cohort) {
    $selected = ($cohortid == $cohort->id) ? 'selected' : '';
    echo '<option value="' . $cohort->id . '" ' . $selected . '>' . format_string($cohort->name) . '</option>';
}
echo '</select>';
echo '</div>';

// Lastname search
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<label for="lastnamesearch" class="form-label">Cognome discente</label>';
echo '<input type="text" name="lastnamesearch" id="lastnamesearch" class="form-control w-100" value="' . s($lastnamesearch) . '" placeholder="Cerca per cognome...">';
echo '</div>';

// Submit button
echo '<div class="col-12 col-sm-6 col-lg-2">';
echo '<label class="form-label d-none d-lg-block">&nbsp;</label>';
echo '<button type="submit" class="btn btn-primary w-100">Applica</button>';
echo '</div>';

// Reset button
echo '<div class="col-12 col-sm-6 col-lg-2">';
echo '<label class="form-label d-none d-lg-block">&nbsp;</label>';
$reseturl = new moodle_url('/local/course_completion_report/preview.php', ['courseid' => $courseid]);
echo html_writer::link($reseturl, 'Reset', ['class' => 'btn btn-secondary w-100']);
echo '</div>';

echo '</form>';
echo '</div>';
echo '</div>';

// Download buttons
echo '<div class="mb-4">';
$excelurl = new moodle_url('/local/course_completion_report/download.php', [
    'courseid' => $courseid,
    'format' => 'excel',
    'cohortid' => $cohortid,
    'lastnamesearch' => $lastnamesearch
]);
echo html_writer::link($excelurl, '<i class="fa fa-file-excel-o"></i> Scarica Excel', ['class' => 'btn btn-success me-2']);

$csvurl = new moodle_url('/local/course_completion_report/download.php', [
    'courseid' => $courseid,
    'format' => 'csv',
    'cohortid' => $cohortid,
    'lastnamesearch' => $lastnamesearch
]);
echo html_writer::link($csvurl, '<i class="fa fa-file-text-o"></i> Scarica CSV', ['class' => 'btn btn-secondary']);
echo '</div>';

// Get data
$activities = local_course_completion_report_get_course_activities($courseid);
$users = local_course_completion_report_get_enrolled_users($courseid, $cohortid, $lastnamesearch);

// Filter activities with completion tracking
$activities_with_completion = array_filter($activities, function($a) { return $a->completion > 0; });

if (empty($users)) {
    echo $OUTPUT->notification('Nessun utente iscritto a questo corso.', 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (empty($activities_with_completion)) {
    echo $OUTPUT->notification('Nessuna attività con tracciamento completamento in questo corso.', 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Build the report table
echo '<div class="table-responsive">';
echo '<table class="table table-striped table-bordered table-hover" id="report-table">';

// Header row
echo '<thead class="table-dark">';
echo '<tr>';
echo '<th>Coorte</th>';
echo '<th>Nome</th>';
echo '<th>Cognome</th>';

foreach ($activities_with_completion as $activity) {
    $activityname = format_string($activity->name);
    if (strlen($activityname) > 20) {
        $activityname = substr($activityname, 0, 17) . '...';
    }
    echo '<th colspan="3" class="text-center" title="' . format_string($activity->name) . '">' . $activityname . '</th>';
}

// Add certificate column if customcert is available
$hascustomcert = local_course_completion_report_is_customcert_available();
if ($hascustomcert) {
    echo '<th colspan="2" class="text-center">Attestato</th>';
}

echo '</tr>';

// Sub-header for each activity (Stato, Data Inizio, Data Fine)
echo '<tr class="table-secondary">';
echo '<th></th><th></th><th></th>';
foreach ($activities_with_completion as $activity) {
    echo '<th class="small">Stato</th>';
    echo '<th class="small">Data Inizio</th>';
    echo '<th class="small">Data Fine</th>';
}
if ($hascustomcert) {
    echo '<th class="small">Scaricato</th>';
    echo '<th class="small">Data</th>';
}
echo '</tr>';

echo '</thead>';

// Data rows
echo '<tbody>';
foreach ($users as $user) {
    $cohorts = local_course_completion_report_get_user_cohorts($user->id);

    echo '<tr>';
    echo '<td>' . s($cohorts) . '</td>';
    echo '<td>' . format_string($user->firstname) . '</td>';
    echo '<td>' . format_string($user->lastname) . '</td>';
    
    foreach ($activities_with_completion as $activity) {
        $completed = local_course_completion_report_is_activity_completed($user->id, $activity->id);
        $startdate = local_course_completion_report_get_first_access_date($user->id, $activity->id);
        $enddate = local_course_completion_report_get_completion_date($user->id, $activity->id);
        
        // Status
        if ($completed) {
            echo '<td class="text-center bg-success-subtle"><span class="badge bg-success">Completato</span></td>';
        } else {
            echo '<td class="text-center bg-danger-subtle"><span class="badge bg-danger">Non completato</span></td>';
        }
        
        // Start date
        if ($startdate) {
            echo '<td class="small">' . userdate($startdate, '%d/%m/%Y', 'Europe/Rome') . '</td>';
        } else {
            echo '<td class="small text-muted">-</td>';
        }
        
        // End date
        if ($enddate) {
            echo '<td class="small">' . userdate($enddate, '%d/%m/%Y', 'Europe/Rome') . '</td>';
        } else {
            echo '<td class="small text-muted">-</td>';
        }
    }
    
    // Certificate column
    if ($hascustomcert) {
        $certissue = local_course_completion_report_get_certificate_issue($user->id, $courseid);
        if ($certissue) {
            echo '<td class="text-center bg-success-subtle"><span class="badge bg-success">Sì</span></td>';
            echo '<td class="small">' . userdate($certissue->timecreated, '%d/%m/%Y', 'Europe/Rome') . '</td>';
        } else {
            echo '<td class="text-center bg-warning-subtle"><span class="badge bg-warning">No</span></td>';
            echo '<td class="small text-muted">-</td>';
        }
    }
    
    echo '</tr>';
}
echo '</tbody>';

echo '</table>';
echo '</div>';

// Summary statistics
$totalUsers = count($users);
$totalActivities = count($activities_with_completion);

echo '<div class="card mt-4">';
echo '<div class="card-header"><h5>Riepilogo</h5></div>';
echo '<div class="card-body">';
echo '<div class="row">';
echo '<div class="col-md-3"><strong>Utenti totali:</strong> ' . $totalUsers . '</div>';
echo '<div class="col-md-3"><strong>Attività tracciate:</strong> ' . $totalActivities . '</div>';

// Calculate overall completion rate
$totalCompletions = 0;
$totalPossible = $totalUsers * $totalActivities;
foreach ($users as $user) {
    foreach ($activities_with_completion as $activity) {
        if (local_course_completion_report_is_activity_completed($user->id, $activity->id)) {
            $totalCompletions++;
        }
    }
}
$completionRate = $totalPossible > 0 ? round(($totalCompletions / $totalPossible) * 100, 1) : 0;

echo '<div class="col-md-3"><strong>Completamenti totali:</strong> ' . $totalCompletions . '/' . $totalPossible . '</div>';
echo '<div class="col-md-3"><strong>Tasso completamento:</strong> ' . $completionRate . '%</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer();
