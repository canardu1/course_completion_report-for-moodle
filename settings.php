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
 * Settings for local_course_completion_report
 *
 * @package     local_course_completion_report
 * @copyright   2025 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Add the reports page to the Reports section
// Use capability check instead of $hassiteconfig so managers can see it
$reports = new admin_externalpage(
    'local_course_completion_report',
    get_string('pluginname', 'local_course_completion_report'),
    new moodle_url('/local/course_completion_report/index.php'),
    'local/course_completion_report:view'
);
$ADMIN->add('reports', $reports);
