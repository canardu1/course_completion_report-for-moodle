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
 * @copyright   2025 Carlo Anardu
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);
$onlyactive = optional_param('onlyactive', 0, PARAM_BOOL);

require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:view', $context);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$component = 'local_course_completion_report';
$params = [
    'courseid' => $courseid,
    'cohortid' => $cohortid,
    'lastnamesearch' => $lastnamesearch,
    'onlyactive' => (int)$onlyactive,
];

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/course_completion_report/preview.php', $params));
$PAGE->set_title(get_string('previewtitle', $component, format_string($course->fullname)));
$PAGE->set_heading(get_string('previewheading', $component));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

echo html_writer::div(html_writer::link(
    new moodle_url('/local/course_completion_report/index.php'),
    get_string('backtocourses', $component),
    ['class' => 'btn btn-secondary mb-3']
));

echo $OUTPUT->heading(get_string('reportfor', $component, format_string($course->fullname)));

echo local_course_completion_report_render_user_filters($PAGE->url, $courseid, $cohortid, $lastnamesearch, $onlyactive);

echo html_writer::div(local_course_completion_report_render_download_buttons($params), 'mb-4');

$report = local_course_completion_report_build_report($courseid, $cohortid, $lastnamesearch, $onlyactive);

if (empty($report->users)) {
    echo $OUTPUT->notification(get_string('nousers', $component), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (empty($report->activities)) {
    echo $OUTPUT->notification(get_string('noactivities', $component), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$badges = [
    LOCAL_CCR_STATUS_COMPLETE   => ['bg-success-subtle', 'bg-success'],
    LOCAL_CCR_STATUS_FAILED     => ['bg-warning-subtle', 'bg-warning text-dark'],
    LOCAL_CCR_STATUS_INCOMPLETE => ['bg-danger-subtle', 'bg-danger'],
];
$empty = html_writer::tag('td', '-', ['class' => 'small text-muted']);

echo html_writer::start_div('table-responsive');
echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered table-hover', 'id' => 'report-table']);

// Header rows.
echo html_writer::start_tag('thead', ['class' => 'table-dark']);
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('cohort', $component));
echo html_writer::tag('th', get_string('firstname'));
echo html_writer::tag('th', get_string('lastname'));
foreach ($report->activities as $activity) {
    $fullname = format_string($activity->name, true, ['escape' => false]);
    echo html_writer::tag('th', s(shorten_text($fullname, 20)),
        ['colspan' => 3, 'class' => 'text-center', 'title' => $fullname]);
}
if ($report->hascustomcert) {
    echo html_writer::tag('th', get_string('certificate', $component), ['colspan' => 2, 'class' => 'text-center']);
}
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr', ['class' => 'table-secondary']);
echo html_writer::tag('th', '') . html_writer::tag('th', '') . html_writer::tag('th', '');
foreach ($report->activities as $activity) {
    echo html_writer::tag('th', get_string('col_status', $component), ['class' => 'small']);
    echo html_writer::tag('th', get_string('col_start', $component), ['class' => 'small']);
    echo html_writer::tag('th', get_string('col_end', $component), ['class' => 'small']);
}
if ($report->hascustomcert) {
    echo html_writer::tag('th', get_string('col_issued', $component), ['class' => 'small']);
    echo html_writer::tag('th', get_string('col_date', $component), ['class' => 'small']);
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Data rows.
$totalcompletions = 0;
echo html_writer::start_tag('tbody');
foreach ($report->users as $user) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', s(implode(', ', $report->cohorts[$user->id] ?? [])));
    echo html_writer::tag('td', s($user->firstname));
    echo html_writer::tag('td', s($user->lastname));

    foreach ($report->activities as $activity) {
        $cell = local_course_completion_report_get_cell($report, $user->id, $activity->id);
        if ($cell->status === LOCAL_CCR_STATUS_COMPLETE) {
            $totalcompletions++;
        }
        [$cellclass, $badgeclass] = $badges[$cell->status];
        echo html_writer::tag('td',
            html_writer::span(local_course_completion_report_status_label($cell->status), 'badge ' . $badgeclass),
            ['class' => 'text-center ' . $cellclass]);
        echo $cell->start ? html_writer::tag('td', local_course_completion_report_format_date($cell->start), ['class' => 'small']) : $empty;
        echo $cell->end ? html_writer::tag('td', local_course_completion_report_format_date($cell->end), ['class' => 'small']) : $empty;
    }

    if ($report->hascustomcert) {
        $certtime = $report->certificates[$user->id] ?? null;
        if ($certtime) {
            echo html_writer::tag('td', html_writer::span(get_string('yes'), 'badge bg-success'),
                ['class' => 'text-center bg-success-subtle']);
            echo html_writer::tag('td', local_course_completion_report_format_date($certtime), ['class' => 'small']);
        } else {
            echo html_writer::tag('td', html_writer::span(get_string('no'), 'badge bg-warning text-dark'),
                ['class' => 'text-center bg-warning-subtle']);
            echo $empty;
        }
    }
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

// Summary.
$totalusers = count($report->users);
$totalactivities = count($report->activities);
$totalpossible = $totalusers * $totalactivities;
$completionrate = $totalpossible > 0 ? round(($totalcompletions / $totalpossible) * 100, 1) : 0;

echo html_writer::start_div('card mt-4');
echo html_writer::div(html_writer::tag('h5', get_string('summary', $component)), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::start_div('row');
echo html_writer::div(html_writer::tag('strong', get_string('totalusers', $component)) . ' ' . $totalusers, 'col-md-3');
echo html_writer::div(html_writer::tag('strong', get_string('trackedactivities', $component)) . ' ' . $totalactivities, 'col-md-3');
echo html_writer::div(html_writer::tag('strong', get_string('totalcompletions', $component)) . ' ' .
    $totalcompletions . '/' . $totalpossible, 'col-md-3');
echo html_writer::div(html_writer::tag('strong', get_string('completionrate', $component)) . ' ' . $completionrate . '%', 'col-md-3');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
