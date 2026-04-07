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
 * Main page for course completion report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Check login and capabilities
require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:view', $context);

// Get filter parameters
$courseid = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/course_completion_report/index.php'));
$PAGE->set_title('Report Completamento Corsi');
$PAGE->set_heading('Report Completamento Corsi');
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading('Report Completamento Corsi');

// Get categories for filter
$categories = $DB->get_records('course_categories', null, 'name', 'id, name');

// Build filter form
echo '<div class="card mb-4">';
echo '<div class="card-header"><h5>Filtri</h5></div>';
echo '<div class="card-body">';
echo '<form method="get" action="" class="row g-3">';

// Category filter
echo '<div class="col-12 col-md-6 col-lg-3">';
echo '<label for="categoryid" class="form-label">Categoria</label>';
echo '<select name="categoryid" id="categoryid" class="form-select w-100">';
echo '<option value="0">Tutte le categorie</option>';
foreach ($categories as $cat) {
    $selected = ($categoryid == $cat->id) ? 'selected' : '';
    echo '<option value="' . $cat->id . '" ' . $selected . '>' . format_string($cat->name) . '</option>';
}
echo '</select>';
echo '</div>';

// Course filter - populate based on category
echo '<div class="col-12 col-md-6 col-lg-4">';
echo '<label for="courseid" class="form-label">Corso</label>';
echo '<select name="courseid" id="courseid" class="form-select w-100">';
echo '<option value="0">Seleziona un corso</option>';

// Get courses (filtered by category if selected)
$coursesql = "SELECT c.id, c.fullname, c.shortname 
              FROM {course} c 
              WHERE c.id > 1";
$params = [];

if ($categoryid > 0) {
    $coursesql .= " AND c.category = ?";
    $params[] = $categoryid;
}

if (!empty($search)) {
    $coursesql .= " AND (c.fullname LIKE ? OR c.shortname LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$coursesql .= " ORDER BY c.fullname";
$courses = $DB->get_records_sql($coursesql, $params);

foreach ($courses as $course) {
    $selected = ($courseid == $course->id) ? 'selected' : '';
    echo '<option value="' . $course->id . '" ' . $selected . '>' . format_string($course->fullname) . '</option>';
}
echo '</select>';
echo '</div>';

// Search filter
echo '<div class="col-12 col-md-6 col-lg-3">';
echo '<label for="search" class="form-label">Cerca corso</label>';
echo '<input type="text" name="search" id="search" class="form-control w-100" value="' . s($search) . '" placeholder="Nome corso...">';
echo '</div>';

// Submit button
echo '<div class="col-12 col-md-6 col-lg-2">';
echo '<label class="form-label d-none d-lg-block">&nbsp;</label>';
echo '<button type="submit" class="btn btn-primary w-100">Filtra</button>';
echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

// Additional filters for selected course
if ($courseid > 0) {
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
    $reseturl = new moodle_url('/local/course_completion_report/index.php', ['courseid' => $courseid]);
    echo html_writer::link($reseturl, 'Reset', ['class' => 'btn btn-secondary w-100']);
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

// If course is selected, show the report options
if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $activities = local_course_completion_report_get_course_activities($courseid);
    $users = local_course_completion_report_get_enrolled_users($courseid, $cohortid, $lastnamesearch);
    
    echo '<div class="card mb-4">';
    echo '<div class="card-header d-flex justify-content-between align-items-center">';
    echo '<h5 class="mb-0">Corso: ' . format_string($course->fullname) . '</h5>';
    echo '<div>';
    
    // Preview button
    $previewurl = new moodle_url('/local/course_completion_report/preview.php', [
        'courseid' => $courseid,
        'cohortid' => $cohortid,
        'lastnamesearch' => $lastnamesearch
    ]);
    echo html_writer::link($previewurl, '<i class="fa fa-eye"></i> Anteprima', ['class' => 'btn btn-primary me-2']);
    
    // Excel download button
    $excelurl = new moodle_url('/local/course_completion_report/download.php', [
        'courseid' => $courseid,
        'format' => 'excel',
        'cohortid' => $cohortid,
        'lastnamesearch' => $lastnamesearch
    ]);
    echo html_writer::link($excelurl, '<i class="fa fa-file-excel-o"></i> Excel', ['class' => 'btn btn-success me-2']);
    
    // CSV download button
    $csvurl = new moodle_url('/local/course_completion_report/download.php', [
        'courseid' => $courseid,
        'format' => 'csv',
        'cohortid' => $cohortid,
        'lastnamesearch' => $lastnamesearch
    ]);
    echo html_writer::link($csvurl, '<i class="fa fa-file-text-o"></i> CSV', ['class' => 'btn btn-secondary']);
    
    echo '</div>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Summary info
    echo '<div class="row mb-3">';
    echo '<div class="col-md-4">';
    echo '<div class="alert alert-info mb-0">';
    echo '<strong>Utenti iscritti:</strong> ' . count($users);
    echo '</div>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<div class="alert alert-info mb-0">';
    echo '<strong>Attività totali:</strong> ' . count($activities);
    echo '</div>';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<div class="alert alert-info mb-0">';
    echo '<strong>Attività con completamento:</strong> ' . count(array_filter($activities, function($a) { return $a->completion > 0; }));
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Activities list
    echo '<h6>Attività nel corso:</h6>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped">';
    echo '<thead><tr><th>Sezione</th><th>Tipo</th><th>Nome</th><th>Completamento</th></tr></thead>';
    echo '<tbody>';
    foreach ($activities as $activity) {
        $sectionname = !empty($activity->sectionname) ? $activity->sectionname : 'Sezione ' . $activity->sectionnumber;
        $completiontext = $activity->completion > 0 ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Non attivo</span>';
        echo '<tr>';
        echo '<td>' . format_string($sectionname) . '</td>';
        echo '<td>' . $activity->modname . '</td>';
        echo '<td>' . format_string($activity->name) . '</td>';
        echo '<td>' . $completiontext . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

// Show all courses table if no course selected
if ($courseid == 0) {
    echo '<div class="card">';
    echo '<div class="card-header"><h5>Corsi disponibili</h5></div>';
    echo '<div class="card-body">';
    
    if (!empty($courses)) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-striped table-hover" id="courses-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Corso</th>';
        echo '<th>Categoria</th>';
        echo '<th>Utenti iscritti</th>';
        echo '<th>Azioni</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            $enrolledcount = count_enrolled_users($coursecontext);
            $category = $DB->get_record('course_categories', ['id' => $DB->get_field('course', 'category', ['id' => $course->id])]);
            
            echo '<tr>';
            echo '<td>' . format_string($course->fullname) . '</td>';
            echo '<td>' . ($category ? format_string($category->name) : '-') . '</td>';
            echo '<td>' . $enrolledcount . '</td>';
            echo '<td>';
            
            // Action buttons
            $selecturl = new moodle_url('/local/course_completion_report/index.php', ['courseid' => $course->id]);
            echo html_writer::link($selecturl, 'Seleziona', ['class' => 'btn btn-primary btn-sm me-1']);
            
            $previewurl = new moodle_url('/local/course_completion_report/preview.php', ['courseid' => $course->id]);
            echo html_writer::link($previewurl, 'Anteprima', ['class' => 'btn btn-info btn-sm me-1']);
            
            $excelurl = new moodle_url('/local/course_completion_report/download.php', ['courseid' => $course->id, 'format' => 'excel']);
            echo html_writer::link($excelurl, 'Excel', ['class' => 'btn btn-success btn-sm']);
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p class="text-muted">Nessun corso trovato con i filtri selezionati.</p>';
    }
    
    echo '</div>';
    echo '</div>';
}

echo $OUTPUT->footer();
