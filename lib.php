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
 * Library functions for local_course_completion_report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Carlo Anardu
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/** Activity completed (includes "completed with pass grade"). */
const LOCAL_CCR_STATUS_COMPLETE = 'complete';
/** Activity completed but with a failing grade. */
const LOCAL_CCR_STATUS_FAILED = 'failed';
/** Activity not completed. */
const LOCAL_CCR_STATUS_INCOMPLETE = 'incomplete';

/** Date format used in the report. */
const LOCAL_CCR_DATEFORMAT = '%d/%m/%Y';
/** Timezone used in the report. */
const LOCAL_CCR_TIMEZONE = 'Europe/Rome';

/**
 * Get all visible activities in a course, in course order.
 *
 * @param int $courseid Course ID
 * @return array Array of activity objects (id, modname, name, completion, sectionname, sectionnumber)
 */
function local_course_completion_report_get_course_activities($courseid) {
    $modinfo = get_fast_modinfo($courseid);
    $activities = [];

    foreach ($modinfo->get_sections() as $sectionnum => $cmids) {
        $section = $modinfo->get_section_info($sectionnum);
        foreach ($cmids as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->visible || !empty($cm->deletioninprogress)) {
                continue;
            }
            $activities[$cm->id] = (object)[
                'id'            => $cm->id,
                'modname'       => $cm->modname,
                'name'          => $cm->name,
                'completion'    => (int)$cm->completion,
                'sectionname'   => $section->name,
                'sectionnumber' => $sectionnum,
            ];
        }
    }

    return $activities;
}

/**
 * Get enrolled users in a course.
 *
 * @param int $courseid Course ID
 * @param int $cohortid Optional cohort ID to filter by
 * @param string $lastname Optional lastname search string
 * @param bool $onlyactive Only include users with an active enrolment
 * @return array Array of user objects keyed by user id
 */
function local_course_completion_report_get_enrolled_users($courseid, $cohortid = 0, $lastname = '', $onlyactive = false) {
    global $DB;

    $context = context_course::instance($courseid);
    $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email',
        'u.lastname, u.firstname', 0, 0, $onlyactive);

    if ($cohortid > 0) {
        $cohortuserids = array_flip($DB->get_fieldset_select('cohort_members', 'userid',
            'cohortid = ?', [$cohortid]));
        $users = array_filter($users, function($user) use ($cohortuserids) {
            return isset($cohortuserids[$user->id]);
        });
    }

    if ($lastname !== '') {
        $users = array_filter($users, function($user) use ($lastname) {
            return core_text::strpos(core_text::strtolower($user->lastname), core_text::strtolower($lastname)) !== false;
        });
    }

    return $users;
}

/**
 * Check if the customcert plugin is installed.
 *
 * @return bool
 */
function local_course_completion_report_is_customcert_available() {
    global $DB;
    return $DB->get_manager()->table_exists('customcert_issues');
}

/**
 * Build all the data for the report with a fixed number of queries
 * (instead of several queries per user and per activity).
 *
 * @param int $courseid
 * @param int $cohortid
 * @param string $lastname
 * @param bool $onlyactive
 * @return stdClass {activities, users, hascustomcert, cohorts[userid], completion[userid][cmid], firstaccess[userid][cmid], certificates[userid]}
 */
function local_course_completion_report_build_report($courseid, $cohortid = 0, $lastname = '', $onlyactive = false) {
    global $DB;

    $report = (object)[
        'activities'    => [],
        'users'         => local_course_completion_report_get_enrolled_users($courseid, $cohortid, $lastname, $onlyactive),
        'hascustomcert' => local_course_completion_report_is_customcert_available(),
        'cohorts'       => [],
        'completion'    => [],
        'firstaccess'   => [],
        'certificates'  => [],
    ];

    // Only activities with completion tracking are part of the report.
    $report->activities = array_filter(local_course_completion_report_get_course_activities($courseid),
        function($a) {
            return $a->completion > 0;
        });

    if (empty($report->users)) {
        return $report;
    }
    $userids = array_keys($report->users);
    [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');

    // Cohorts of every user (one query).
    $rs = $DB->get_recordset_sql(
        "SELECT cm.id, cm.userid, c.name
           FROM {cohort_members} cm
           JOIN {cohort} c ON c.id = cm.cohortid
          WHERE cm.userid $usql
       ORDER BY c.name", $uparams);
    foreach ($rs as $row) {
        $report->cohorts[$row->userid][] = format_string($row->name);
    }
    $rs->close();

    // Certificates (one query).
    if ($report->hascustomcert) {
        $rs = $DB->get_recordset_sql(
            "SELECT ci.userid, MAX(ci.timecreated) AS timecreated
               FROM {customcert_issues} ci
               JOIN {customcert} c ON c.id = ci.customcertid
              WHERE c.course = :courseid AND ci.userid $usql
           GROUP BY ci.userid", ['courseid' => $courseid] + $uparams);
        foreach ($rs as $row) {
            $report->certificates[$row->userid] = (int)$row->timecreated;
        }
        $rs->close();
    }

    if (empty($report->activities)) {
        return $report;
    }
    [$cmsql, $cmparams] = $DB->get_in_or_equal(array_keys($report->activities), SQL_PARAMS_NAMED, 'cm');

    // Completion state of every user for every activity (one query).
    $rs = $DB->get_recordset_sql(
        "SELECT id, coursemoduleid, userid, completionstate, timemodified
           FROM {course_modules_completion}
          WHERE coursemoduleid $cmsql AND userid $usql", $cmparams + $uparams);
    foreach ($rs as $row) {
        $report->completion[$row->userid][$row->coursemoduleid] = $row;
    }
    $rs->close();

    // First "viewed" event of every user for every activity (one query).
    // Note: this depends on the standard log; if old logs are purged the date is lost.
    if ($DB->get_manager()->table_exists('logstore_standard_log')) {
        $rs = $DB->get_recordset_sql(
            "SELECT userid, contextinstanceid, MIN(timecreated) AS firstaccess
               FROM {logstore_standard_log}
              WHERE courseid = :courseid
                AND contextlevel = :contextlevel
                AND action = 'viewed'
                AND contextinstanceid $cmsql
                AND userid $usql
           GROUP BY userid, contextinstanceid",
            ['courseid' => $courseid, 'contextlevel' => CONTEXT_MODULE] + $cmparams + $uparams);
        foreach ($rs as $row) {
            $report->firstaccess[$row->userid][$row->contextinstanceid] = (int)$row->firstaccess;
        }
        $rs->close();
    }

    return $report;
}

/**
 * Get the status and dates of one activity for one user from a built report.
 *
 * @param stdClass $report Result of local_course_completion_report_build_report()
 * @param int $userid
 * @param int $cmid
 * @return stdClass {status, start, end}
 */
function local_course_completion_report_get_cell($report, $userid, $cmid) {
    $completion = $report->completion[$userid][$cmid] ?? null;
    $state = $completion ? (int)$completion->completionstate : COMPLETION_INCOMPLETE;

    if ($state === COMPLETION_COMPLETE || $state === COMPLETION_COMPLETE_PASS) {
        $status = LOCAL_CCR_STATUS_COMPLETE;
    } else if ($state === COMPLETION_COMPLETE_FAIL) {
        $status = LOCAL_CCR_STATUS_FAILED;
    } else {
        $status = LOCAL_CCR_STATUS_INCOMPLETE;
    }

    return (object)[
        'status' => $status,
        'start'  => $report->firstaccess[$userid][$cmid] ?? null,
        // End date only makes sense for a real completion.
        'end'    => ($status === LOCAL_CCR_STATUS_COMPLETE && $completion && $completion->timemodified)
            ? (int)$completion->timemodified : null,
    ];
}

/**
 * Human readable label for a status.
 *
 * @param string $status
 * @return string
 */
function local_course_completion_report_status_label($status) {
    return get_string('status_' . $status, 'local_course_completion_report');
}

/**
 * Format a timestamp for the report.
 *
 * @param int|null $timestamp
 * @return string Empty string when no timestamp
 */
function local_course_completion_report_format_date($timestamp) {
    return $timestamp ? userdate($timestamp, LOCAL_CCR_DATEFORMAT, LOCAL_CCR_TIMEZONE) : '';
}

/**
 * Make a value safe to put in a CSV cell.
 *
 * Spreadsheet programs treat cells starting with = + - @ (or tab / carriage return)
 * as formulas, so user-controlled values like names could run formulas when the CSV
 * is opened ("CSV injection"). Prefixing them with an apostrophe neutralises that.
 *
 * @param string $value
 * @return string
 */
function local_course_completion_report_csv_safe($value) {
    $value = (string)$value;
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Render the user filter form (cohort, lastname, only active enrolments).
 *
 * @param moodle_url $baseurl Page the form submits to
 * @param int $courseid
 * @param int $cohortid
 * @param string $lastname
 * @param bool $onlyactive
 * @return string HTML
 */
function local_course_completion_report_render_user_filters(moodle_url $baseurl, $courseid, $cohortid, $lastname, $onlyactive) {
    global $DB;

    $component = 'local_course_completion_report';
    $cohorts = [0 => get_string('allcohorts', $component)];
    foreach ($DB->get_records('cohort', null, 'name', 'id, name') as $cohort) {
        $cohorts[$cohort->id] = format_string($cohort->name);
    }

    $html = html_writer::start_div('card mb-4');
    $html .= html_writer::div(html_writer::tag('h5', get_string('userfilters', $component)), 'card-header');
    $html .= html_writer::start_div('card-body');
    $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(),
        'class' => 'row g-3 align-items-end']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

    $html .= html_writer::start_div('col-12 col-md-6 col-lg-3');
    $html .= html_writer::label(get_string('cohort', $component), 'cohortid', false, ['class' => 'form-label']);
    $html .= html_writer::select($cohorts, 'cohortid', $cohortid, false, ['id' => 'cohortid', 'class' => 'form-select w-100']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-12 col-md-6 col-lg-3');
    $html .= html_writer::label(get_string('lastnamefilter', $component), 'lastnamesearch', false, ['class' => 'form-label']);
    $html .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'lastnamesearch', 'id' => 'lastnamesearch',
        'class' => 'form-control w-100', 'value' => $lastname,
        'placeholder' => get_string('lastnameplaceholder', $component)]);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-12 col-md-6 col-lg-2');
    $html .= html_writer::start_div('form-check');
    $html .= html_writer::checkbox('onlyactive', 1, (bool)$onlyactive, get_string('onlyactive', $component),
        ['id' => 'onlyactive', 'class' => 'form-check-input'], ['class' => 'form-check-label']);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-6 col-lg-2');
    $html .= html_writer::tag('button', get_string('apply', $component), ['type' => 'submit', 'class' => 'btn btn-primary w-100']);
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('col-6 col-lg-2');
    $html .= html_writer::link(new moodle_url($baseurl->out_omit_querystring(), ['courseid' => $courseid]),
        get_string('reset'), ['class' => 'btn btn-secondary w-100']);
    $html .= html_writer::end_div();

    $html .= html_writer::end_tag('form');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    return $html;
}

/**
 * Render the Excel / CSV download buttons.
 *
 * @param array $params Report parameters (courseid, cohortid, lastnamesearch, onlyactive)
 * @return string HTML
 */
function local_course_completion_report_render_download_buttons(array $params) {
    global $OUTPUT;

    $component = 'local_course_completion_report';
    $html = html_writer::link(
        new moodle_url('/local/course_completion_report/download.php', $params + ['format' => 'excel']),
        $OUTPUT->pix_icon('f/spreadsheet', '') . get_string('downloadexcel', $component),
        ['class' => 'btn btn-success me-2']);
    $html .= html_writer::link(
        new moodle_url('/local/course_completion_report/download.php', $params + ['format' => 'csv']),
        $OUTPUT->pix_icon('f/text', '') . get_string('downloadcsv', $component),
        ['class' => 'btn btn-secondary']);
    return $html;
}

/**
 * Extend the global navigation tree.
 *
 * @param global_navigation $navigation The navigation node to extend
 */
function local_course_completion_report_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (has_capability('local/course_completion_report:view', context_system::instance())) {
        $navigation->add(
            get_string('pluginname', 'local_course_completion_report'),
            new moodle_url('/local/course_completion_report/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'course_completion_report'
        );
    }
}
