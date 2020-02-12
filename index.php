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

admin_externalpage_setup('report_coursesize');

/// DEBUGGG_--------------------------------------------------------------------------------
// echo '<pre>';
// $task = new \report_coursesize\task\build_data_task();
// $task->set_custom_data(
//     array(
//         'iteration_limit'       => 100,
//         'file_records_done' => false,
//         'processed_records' => array(),
//         'files'             => array(),
//         // 'contexts'          => array(),
//         // 'systemsize'        => 0,
//         // 'systembackupsize'  => $data->systembackupsize,
//     )
// );
// \core\task\manager::queue_adhoc_task(\report_coursesize\task\build_data_task::make(), true);
// $task->execute();

// $results  = \cache::make('report_coursesize', 'results');
$cache    = \cache::make('report_coursesize', 'in_progress');

// $cache->delete('course_sizes');
// $cache->delete('category_sizes');
// $cache->delete('user_sizes');
var_dump($cache->get('course_sizes'));
// var_dump($results->get('updated'));
// var_dump($results->get('sizes'));
// var_dump($cache->get('files'));
// var_dump($cache->get('contexts'));

// $category = \core_course_category::get(3);
// var_dump($category->get_all_children_ids());
// $tasks    = \core\task\manager::get_adhoc_tasks('\report_coursesize\task\build_data_task');
// $build    = $tasks[array_keys($tasks)[0]];
// $data     = $build->get_custom_data();
// $progress = $data->progress;
// echo 'Stage ' . $progress->stage . ' of ' . $progress->stagetot;
// echo "\nStep " . $progress->step . ' of ' . $progress->steptot;
die();
// -----------------------------------------------------------------------------------------

$params                   = new \stdClass();
$params->categoryid       = optional_param('categoryid', 0, PARAM_INT);
$params->showempty        = optional_param('showempty', false, PARAM_BOOL);
$params->coursedownload   = optional_param('coursedownload', '', PARAM_ALPHA);
$params->categorydownload = optional_param('categorydownload', '', PARAM_ALPHA);
$params->userdownload     = optional_param('userdownload', '', PARAM_ALPHA);

// Load results.
$resultsmanager = new \report_coursesize\results_manager;
$sizes          = $resultsmanager->get_sizes($params->categoryid);

if (!isset($sizes->contexts) || empty($sizes->contexts)) {
    print $OUTPUT->header();
    print get_string('no_results', 'report_coursesize');
    if ($progress = \report_coursesize\task\build_data_task::get_build_progress()) {
        print '<h5>' . get_string('build_status', 'report_coursesize') . '</h5>';
        print get_string('build_status_active', 'report_coursesize', $progress);
    }
    print $OUTPUT->footer();
    die();
}

$systemsizereadable   = get_string('sizeinmb', 'report_coursesize', report_coursesize_bytes_to_megabytes($sizes->systemsize));
$systembackupreadable = get_string('sizeinmb', 'report_coursesize', report_coursesize_bytes_to_megabytes($sizes->systembackupsize));

// Setup the course table.
$coursetable = new \report_coursesize\table\course_table($params->categoryid);
$coursetable->setup();
$coursetable->is_downloading($params->coursedownload, 'coursesizes', 'courses');

// Setup category table.
$categorytable = new \report_coursesize\table\category_table($params->categoryid);
$categorytable->setup();
$categorytable->is_downloading($params->categorydownload, 'categorysizes', 'categories');

// Setup the user table.
$usertable = new \report_coursesize\table\user_table();
$usertable->setup();
// $usertable->is_downloading($userdownload, 'usersizes', 'users');

if (!$coursetable->is_downloading() && !$categorytable->is_downloading() && !$usertable->is_downloading()) {
    print $OUTPUT->header();

    print '<h5>' . get_string('build_status', 'report_coursesize') . '</h5>';
    if ($progress = \report_coursesize\task\build_data_task::get_build_progress()) {
        print get_string('build_status_active', 'report_coursesize', $progress);
    } else {
        print get_string('build_status_none', 'report_coursesize');
    }

    if (empty($params->categoryid)) {
        print $OUTPUT->heading(get_string("sitefilesusage", 'report_coursesize'));
        print '<strong>' . get_string("totalsitedata", 'report_coursesize', number_format($sizes->total_site_usage) . " MB") . '</strong> ';
        print get_string("sizerecorded", "report_coursesize", date("Y-m-d H:i", $resultsmanager->updated)) . "<br/><br/>\n";
        print get_string('catsystemuse', 'report_coursesize', $systemsizereadable) . "<br/>";
        print get_string('catsystembackupuse', 'report_coursesize', $systembackupreadable) . "<br/>";
        if (!empty($CFG->filessizelimit)) {
            print get_string("sizepermitted", 'report_coursesize', number_format($CFG->filessizelimit)) . "<br/>\n";
        }
    }

    $heading = get_string('coursesize', 'report_coursesize');
    if (!empty($params->categoryid)) {
        // $heading .= " - ".$coursecat->name;
    }
    print $OUTPUT->heading($heading);
    $desc = get_string('coursesize_desc', 'report_coursesize');

    if (!$params->showempty) {
        $desc .= ' '. get_string('emptycourseshidden', 'report_coursesize');
    }
    print $OUTPUT->box($desc);
}

// Display the course size table.
if (!$categorytable->is_downloading()) {
    $coursetable->start_output();
    $coursetable->build_table($sizes->contexts->courses);
    $coursetable->finish_output();
}

// Display the category size table.
if (!$coursetable->is_downloading()) {
    $categorytable->start_output();
    $categorytable->build_table($sizes->contexts->categories);
    $categorytable->finish_output();
}

// Display the user size table.
// if (!$coursetable->is_downloading() && $coursecategory == 0) {
    // if (!$usertable->is_downloading()) {
    //     print $OUTPUT->heading(get_string('userstopnum', 'report_coursesize', REPORT_COURSESIZE_NUMBEROFUSERS));
    // }

    // // Build and display table.
    // $usertable->start_output();
    // $usertable->build_table($sizes->users);
    // $usertable->finish_output();
// }

if (!$coursetable->is_downloading() && !$categorytable->is_downloading() && !$usertable->is_downloading()) {
    print $OUTPUT->footer();
}
