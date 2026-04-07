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
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get cohort names for a user as a comma-separated string
 *
 * @param int $userid User ID
 * @return string Comma-separated cohort names, or empty string
 */
function local_course_completion_report_get_user_cohorts($userid) {
    global $DB;

    $cohorts = $DB->get_records_sql(
        "SELECT c.name
         FROM {cohort} c
         JOIN {cohort_members} cm ON cm.cohortid = c.id
         WHERE cm.userid = ?
         ORDER BY c.name",
        [$userid]
    );

    if (empty($cohorts)) {
        return '';
    }

    return implode(', ', array_column($cohorts, 'name'));
}

/**
 * Get all activities in a course that have completion tracking enabled
 *
 * @param int $courseid Course ID
 * @return array Array of activities with completion info
 */
function local_course_completion_report_get_course_activities($courseid) {
    global $DB;
    
    $activities = $DB->get_records_sql("
        SELECT cm.id, cm.instance, cm.module, cm.section, m.name as modname, 
               cm.completion, cs.name as sectionname, cs.section as sectionnumber
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {course_sections} cs ON cs.id = cm.section
        WHERE cm.course = ?
        AND cm.visible = 1
        AND cm.deletioninprogress = 0
        ORDER BY cs.section, cm.id
    ", [$courseid]);
    
    // Get activity names
    foreach ($activities as $activity) {
        $activityrecord = $DB->get_record($activity->modname, ['id' => $activity->instance], 'id, name');
        if ($activityrecord) {
            $activity->name = $activityrecord->name;
        } else {
            $activity->name = 'Unknown';
        }
    }
    
    return $activities;
}

/**
 * Check if a user has completed an activity
 *
 * @param int $userid User ID
 * @param int $cmid Course module ID
 * @return bool True if completed, false otherwise
 */
function local_course_completion_report_is_activity_completed($userid, $cmid) {
    global $DB;
    
    $completion = $DB->get_record('course_modules_completion', [
        'coursemoduleid' => $cmid,
        'userid' => $userid
    ]);
    
    if ($completion && $completion->completionstate > 0) {
        return true;
    }
    
    return false;
}

/**
 * Get completion date for an activity
 *
 * @param int $userid User ID
 * @param int $cmid Course module ID
 * @return int|null Timestamp of completion or null
 */
function local_course_completion_report_get_completion_date($userid, $cmid) {
    global $DB;
    
    $completion = $DB->get_record('course_modules_completion', [
        'coursemoduleid' => $cmid,
        'userid' => $userid
    ]);
    
    if ($completion && $completion->completionstate > 0 && $completion->timemodified) {
        return $completion->timemodified;
    }
    
    return null;
}

/**
 * Get first access date for an activity (when user started the activity)
 *
 * @param int $userid User ID
 * @param int $cmid Course module ID
 * @return int|null Timestamp of first access or null
 */
function local_course_completion_report_get_first_access_date($userid, $cmid) {
    global $DB;
    
    // Try to get from log table
    $firstaccess = $DB->get_field_sql("
        SELECT MIN(timecreated)
        FROM {logstore_standard_log}
        WHERE userid = ? AND contextlevel = ? AND contextinstanceid = ?
        AND action = 'viewed'
    ", [$userid, CONTEXT_MODULE, $cmid]);
    
    if ($firstaccess) {
        return $firstaccess;
    }
    
    return null;
}

/**
 * Get enrolled users in a course
 *
 * @param int $courseid Course ID
 * @param int $cohortid Optional cohort ID to filter by
 * @param string $lastname Optional lastname search string
 * @return array Array of user objects
 */
function local_course_completion_report_get_enrolled_users($courseid, $cohortid = 0, $lastname = '') {
    global $DB;
    
    $context = context_course::instance($courseid);
    
    // Get enrolled users
    $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname, u.firstname');
    
    // Filter by cohort if specified
    if ($cohortid > 0) {
        $cohortmembers = $DB->get_records('cohort_members', ['cohortid' => $cohortid], '', 'userid');
        $cohortuserids = array_keys($cohortmembers);
        $users = array_filter($users, function($user) use ($cohortuserids) {
            return in_array($user->id, $cohortuserids);
        });
    }
    
    // Filter by lastname if specified
    if (!empty($lastname)) {
        $users = array_filter($users, function($user) use ($lastname) {
            return stripos($user->lastname, $lastname) !== false;
        });
    }
    
    return $users;
}

/**
 * Check if user has been issued a certificate in a course (customcert plugin)
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return object|null Certificate issue record or null
 */
function local_course_completion_report_get_certificate_issue($userid, $courseid) {
    global $DB;
    
    // Check if customcert table exists
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('customcert_issues')) {
        return null;
    }
    
    // Get certificate issue for this user in this course
    // customcert table has direct course field
    $sql = "SELECT ci.id, ci.timecreated, ci.code, c.name as certname
            FROM {customcert_issues} ci
            JOIN {customcert} c ON c.id = ci.customcertid
            WHERE ci.userid = ? AND c.course = ?
            ORDER BY ci.timecreated DESC";
    
    // get_record_sql automatically limits to 1 record
    return $DB->get_record_sql($sql, [$userid, $courseid]);
}

/**
 * Check if customcert plugin is installed
 *
 * @return bool True if customcert is available
 */
function local_course_completion_report_is_customcert_available() {
    global $DB;
    $dbman = $DB->get_manager();
    return $dbman->table_exists('customcert_issues');
}

/**
 * Extend the global navigation tree
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to extend for (optional)
 * @param context $context The context of the course (optional)
 */
function local_course_completion_report_extend_navigation(global_navigation $navigation, stdClass $course = null, context $context = null) {
    // Check if we have the context parameter (Moodle 4.4+) or need to get it (Moodle 4.3)
    if (!$context) {
        $context = context_system::instance();
    }
    
    if (has_capability('local/course_completion_report:view', $context)) {
        $node = $navigation->add(
            'Report Completamento Corsi',
            new moodle_url('/local/course_completion_report/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'course_completion_report'
        );
    }
}
