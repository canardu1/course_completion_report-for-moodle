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
 * Upgrade script for local_course_completion_report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_course_completion_report_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025020201) {
        // Initial version - no database changes needed
        upgrade_plugin_savepoint(true, 2025020201, 'local', 'course_completion_report');
    }
    
    if ($oldversion < 2025020202) {
        // Version 1.1.0 - Added certificate tracking from customcert plugin
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020202, 'local', 'course_completion_report');
    }
    
    if ($oldversion < 2025020203) {
        // Version 1.2.0 - Added time to date columns
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020203, 'local', 'course_completion_report');
    }
    
    if ($oldversion < 2025020204) {
        // Version 1.3.0 - Removed time from date columns
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020204, 'local', 'course_completion_report');
    }
    
    if ($oldversion < 2025020205) {
        // Version 1.4.0 - Allow managers to view report
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020205, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020206) {
        // Version 1.5.0 - Replaced matricola/department custom profile fields with cohort membership
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020206, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020207) {
        // Version 1.6.0 - Added group and lastname filters
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020207, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020208) {
        // Version 1.7.0 - Added filters to preview page
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020208, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020209) {
        // Version 1.8.0 - Replaced group filter with cohort filter
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020209, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020210) {
        // Version 1.9.0 - Improved responsive layout for filters
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020210, 'local', 'course_completion_report');
    }

    if ($oldversion < 2025020211) {
        // Version 1.10.0 - Fixed filter field alignment and width issues
        // No database changes needed
        upgrade_plugin_savepoint(true, 2025020211, 'local', 'course_completion_report');
    }

    return true;
}
