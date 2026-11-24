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
 * @copyright   2025 Carlo Anardu
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/course_completion_report:view', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$cohortid = optional_param('cohortid', 0, PARAM_INT);
$lastnamesearch = optional_param('lastnamesearch', '', PARAM_TEXT);
$onlyactive = optional_param('onlyactive', 0, PARAM_BOOL);

$component = 'local_course_completion_report';
$baseurl = new moodle_url('/local/course_completion_report/index.php');

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('pluginname', $component));
$PAGE->set_heading(get_string('pluginname', $component));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', $component));

// Courses (one query, including category name and enrolled users count).
$coursesql = "SELECT c.id, c.fullname, c.shortname, cc.name AS categoryname,
                     (SELECT COUNT(DISTINCT ue.userid)
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                       WHERE e.courseid = c.id) AS enrolledcount
                FROM {course} c
           LEFT JOIN {course_categories} cc ON cc.id = c.category
               WHERE c.id <> :siteid";
$params = ['siteid' => SITEID];
if ($categoryid > 0) {
    $coursesql .= " AND c.category = :categoryid";
    $params['categoryid'] = $categoryid;
}
if ($search !== '') {
    $coursesql .= " AND (" . $DB->sql_like('c.fullname', ':search1', false) .
        " OR " . $DB->sql_like('c.shortname', ':search2', false) . ")";
    $params['search1'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['search2'] = '%' . $DB->sql_like_escape($search) . '%';
}
$coursesql .= " ORDER BY c.fullname";
$courses = $DB->get_records_sql($coursesql, $params);

// Course filter form.
$categories = [0 => get_string('allcategories', $component)];
foreach ($DB->get_records('course_categories', null, 'name', 'id, name') as $cat) {
    $categories[$cat->id] = format_string($cat->name);
}
$courseoptions = [0 => get_string('selectcourse', $component)];
foreach ($courses as $course) {
    $courseoptions[$course->id] = format_string($course->fullname);
}

echo html_writer::start_div('card mb-4');
echo html_writer::div(html_writer::tag('h5', get_string('filters', $component)), 'card-header');
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'row g-3 align-items-end']);

echo html_writer::start_div('col-12 col-md-6 col-lg-3');
echo html_writer::label(get_string('category'), 'categoryid', false, ['class' => 'form-label']);
echo html_writer::select($categories, 'categoryid', $categoryid, false, ['id' => 'categoryid', 'class' => 'form-select w-100']);
echo html_writer::end_div();

echo html_writer::start_div('col-12 col-md-6 col-lg-4');
echo html_writer::label(get_string('course'), 'courseid', false, ['class' => 'form-label']);
echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['id' => 'courseid', 'class' => 'form-select w-100']);
echo html_writer::end_div();

echo html_writer::start_div('col-12 col-md-6 col-lg-3');
echo html_writer::label(get_string('searchcourse', $component), 'search', false, ['class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'id' => 'search', 'class' => 'form-control w-100',
    'value' => $search, 'placeholder' => get_string('searchcourseplaceholder', $component)]);
echo html_writer::end_div();

echo html_writer::start_div('col-12 col-md-6 col-lg-2');
echo html_writer::tag('button', get_string('filter', $component), ['type' => 'submit', 'class' => 'btn btn-primary w-100']);
echo html_writer::end_div();

echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

if ($courseid > 0) {
    // Selected course: user filters, download buttons and activity list.
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $reportparams = [
        'courseid' => $courseid,
        'cohortid' => $cohortid,
        'lastnamesearch' => $lastnamesearch,
        'onlyactive' => (int)$onlyactive,
    ];

    echo local_course_completion_report_render_user_filters($baseurl, $courseid, $cohortid, $lastnamesearch, $onlyactive);

    $activities = local_course_completion_report_get_course_activities($courseid);
    $users = local_course_completion_report_get_enrolled_users($courseid, $cohortid, $lastnamesearch, $onlyactive);
    $withcompletion = count(array_filter($activities, function($a) {
        return $a->completion > 0;
    }));

    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header d-flex justify-content-between align-items-center flex-wrap gap-2');
    echo html_writer::tag('h5', get_string('coursex', $component, format_string($course->fullname)), ['class' => 'mb-0']);
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/course_completion_report/preview.php', $reportparams),
            get_string('preview', $component), ['class' => 'btn btn-primary me-2']) .
        local_course_completion_report_render_download_buttons($reportparams));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    echo html_writer::start_div('row mb-3');
    echo html_writer::div(html_writer::div(html_writer::tag('strong', get_string('enrolledusers', $component)) . ' ' .
        count($users), 'alert alert-info mb-0'), 'col-md-4');
    echo html_writer::div(html_writer::div(html_writer::tag('strong', get_string('totalactivities', $component)) . ' ' .
        count($activities), 'alert alert-info mb-0'), 'col-md-4');
    echo html_writer::div(html_writer::div(html_writer::tag('strong', get_string('activitieswithcompletion', $component)) . ' ' .
        $withcompletion, 'alert alert-info mb-0'), 'col-md-4');
    echo html_writer::end_div();

    echo html_writer::tag('h6', get_string('courseactivities', $component));
    $table = new html_table();
    $table->attributes['class'] = 'table table-sm table-striped';
    $table->head = [
        get_string('section'),
        get_string('type', $component),
        get_string('name'),
        get_string('completion', $component),
    ];
    foreach ($activities as $activity) {
        $sectionname = $activity->sectionname !== null && $activity->sectionname !== ''
            ? format_string($activity->sectionname)
            : get_string('section') . ' ' . $activity->sectionnumber;
        $table->data[] = [
            $sectionname,
            s(get_string('modulename', $activity->modname)),
            format_string($activity->name),
            $activity->completion > 0
                ? html_writer::span(get_string('completionon', $component), 'badge bg-success')
                : html_writer::span(get_string('completionoff', $component), 'badge bg-secondary'),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');

    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    // No course selected: list all matching courses.
    echo html_writer::start_div('card');
    echo html_writer::div(html_writer::tag('h5', get_string('availablecourses', $component)), 'card-header');
    echo html_writer::start_div('card-body');

    if (!empty($courses)) {
        $table = new html_table();
        $table->attributes = ['class' => 'table table-striped table-hover', 'id' => 'courses-table'];
        $table->head = [
            get_string('course'),
            get_string('category'),
            get_string('enrolledusers', $component),
            get_string('actions'),
        ];
        foreach ($courses as $course) {
            $actions = html_writer::link(new moodle_url($baseurl, ['courseid' => $course->id]),
                get_string('select'), ['class' => 'btn btn-primary btn-sm me-1']);
            $actions .= html_writer::link(new moodle_url('/local/course_completion_report/preview.php', ['courseid' => $course->id]),
                get_string('preview', $component), ['class' => 'btn btn-info btn-sm me-1']);
            $actions .= html_writer::link(new moodle_url('/local/course_completion_report/download.php',
                ['courseid' => $course->id, 'format' => 'excel']), 'Excel', ['class' => 'btn btn-success btn-sm']);
            $table->data[] = [
                format_string($course->fullname),
                $course->categoryname !== null ? format_string($course->categoryname) : '-',
                (int)$course->enrolledcount,
                $actions,
            ];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    } else {
        echo html_writer::tag('p', get_string('nocourses', $component), ['class' => 'text-muted']);
    }

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
