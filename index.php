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
 * Version information
 *
 * @package    report_coursesize
 * @copyright  2014 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('locallib.php');

admin_externalpage_setup('reportcoursesize');

// Dirty hack to filter by coursecategory - not very efficient.
$coursecategory = optional_param('category', 0, PARAM_INT);
$download       = optional_param('download', '', PARAM_ALPHA);
$userdownload   = optional_param('userdownload', '', PARAM_ALPHA);

// Load results.
$results = \report_coursesize\site::get_results($coursecategory);

$systemsizereadable = number_format(ceil($results->contexts->systemsize / 1048576)) . "MB";
$systembackupreadable = number_format(ceil($results->contexts->systembackupsize / 1048576)) . "MB";

// Setup the course table.
$table = new \report_coursesize\site_table($coursecategory);
$table->setup();
$table->is_downloading($download, 'coursesizes', 'courses');

// Setup the user table.
$usertable = new \report_coursesize\user_table();
$usertable->setup();
$usertable->is_downloading($userdownload, 'usersizes', 'users');

if (!$table->is_downloading() && !$usertable->is_downloading()) {
    print $OUTPUT->header();
    if (empty($coursecat)) {
        print $OUTPUT->heading(get_string("sitefilesusage", 'report_coursesize'));
        print '<strong>' . get_string("totalsitedata", 'report_coursesize', number_format($results->total_site_usage) . " MB") . '</strong> ';
        print get_string("sizerecorded", "report_coursesize", date("Y-m-d H:i", $results->date)) . "<br/><br/>\n";
        print get_string('catsystemuse', 'report_coursesize', $systemsizereadable) . "<br/>";
        print get_string('catsystembackupuse', 'report_coursesize', $systembackupreadable) . "<br/>";
        if (!empty($CFG->filessizelimit)) {
            print get_string("sizepermitted", 'report_coursesize', number_format($CFG->filessizelimit)) . "<br/>\n";
        }
    }

    $heading = get_string('coursesize', 'report_coursesize');
    if (!empty($coursecat)) {
        $heading .= " - ".$coursecat->name;
    }
    print $OUTPUT->heading($heading);
    $desc = get_string('coursesize_desc', 'report_coursesize');

    if (!REPORT_COURSESIZE_SHOWEMPTYCOURSES) {
        $desc .= ' '. get_string('emptycourseshidden', 'report_coursesize');
    }
    print $OUTPUT->box($desc);
}

// Display the course size table.
if (!$usertable->is_downloading()) {
    $table->start_output();
    $table->build_table($results->summary);
    $table->finish_output();
}

// Display the user size table.
if (!$table->is_downloading() && $coursecategory == 0) {
    if (!$usertable->is_downloading()) {
        print $OUTPUT->heading(get_string('userstopnum', 'report_coursesize', REPORT_COURSESIZE_NUMBEROFUSERS));
    }

    // Build and display table.
    $usertable->start_output();
    $usertable->build_table($results->users);
    $usertable->finish_output();
}

if (!$table->is_downloading() && !$usertable->is_downloading()) {
    print $OUTPUT->footer();
}
