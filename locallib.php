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

define('REPORT_COURSESIZE_SHOWEMPTYCOURSES', false);
define('REPORT_COURSESIZE_NUMBEROFUSERS', 10);
define('REPORT_COURSESIZE_UPDATETOTAL_FREQUENCY', 1 * DAYSECS);

function report_coursesize_get_context_sizes() {
    global $DB;

    // Generate a full list of context sitedata usage stats.
    $subsql = 'SELECT f.id, f.contenthash, f.contextid, f.filesize as filesize' .
    ' FROM {files} f';
    $wherebackup = ' WHERE component like \'backup\' AND referencefileid IS NULL';
    $groupby = ' GROUP BY f.contextid';

    $sizesql = 'SELECT ' .
    'cx.id as contextid, ' .
    'cx.contextlevel, ' .
    'cx.instanceid, ' .
    'cx.path, ' .
    'cx.depth, ' .
    'files.filesize, ' .
    'files.contenthash, ' .
    'files.id as fileid, ' .
    'backups.filesize as backupsize' .
    ' FROM {context} cx ' .
    ' INNER JOIN ( ' . $subsql . ' ) files on cx.id=files.contextid' .
    ' LEFT JOIN ( ' . $subsql . $wherebackup . ' ) backups on cx.id=backups.contextid' .
    ' ORDER by cx.depth ASC, cx.path ASC';
    return $DB->get_recordset_sql($sizesql);
}

function report_coursesize_get_course_lookup($categoryid = null) {
    global $DB;

    $extracoursesql = '';
    if (!empty($categoryid)) {
        $extracoursesql = ' WHERE ca.id = ' . $categoryid;
    }

    // This seems like an in-efficient method to filter by course categories as we are not excluding them from the main list.
    $coursesql = 'SELECT cx.id, c.shortname, c.category, c.fullname, c.id as courseid ' .
    'FROM {course} c '
    . ' LEFT JOIN {course_categories} ca on c.category = ca.id'
    . ' INNER JOIN {context} cx ON cx.instanceid=c.id AND cx.contextlevel = ' . CONTEXT_COURSE
    . $extracoursesql;

    return $DB->get_records_sql($coursesql);
}