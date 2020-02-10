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

namespace report_coursesize;

class file_sizer {

    private $filerecords;
    private $courselookup;

    public $files             = array();
    public $processed_records = array();

    public function __construct($filerecords, $files = array()) {
        $this->files        = (array) $files;
        $this->filerecords  = $filerecords;
        $this->courselookup = $this->get_course_lookup_table();

        $this->process_file_records();
    }

    public function process_file_records() {
        foreach ($this->filerecords as $filerecord) {
            $this->process_file_record($filerecord);
        }
    }

    private function process_file_record($filerecord) {
        array_push($this->processed_records, $filerecord->id);

        if (!array_key_exists($filerecord->contenthash, $this->files)) {
            $this->files[$filerecord->contenthash] = array(
                'size'       => $filerecord->filesize,
                'component'  => $filerecord->component,
                'processed'  => false,
                'users'      => array(),
                'courses'    => array(),
                'categories' => array(),
                'other'      => array(),
            );
        }

        switch($filerecord->contextlevel) {
            case CONTEXT_USER:
                $this->process_file_record_user($filerecord, $filerecord->instanceid);
                break;
            case CONTEXT_COURSE:
                $this->process_file_record_course($filerecord);
                break;
            case CONTEXT_COURSECAT:
                $this->process_file_record_category($filerecord, $filerecord->instanceid);
                break;
            case CONTEXT_SYSTEM:
                // No extra handling needed; size is already in files array.
                break;
            default:
                // Not a course, user, system, category, see it it's something that should be listed under a course
                // Modules & Blocks mostly.
                $course = self::find_course_from_context($filerecord->path);
                if (!$course) {
                    $this->process_file_record_other($filerecord);
                } else {
                    $this->process_file_record_course($filerecord, $course);
                }
                break;
        }
    }

    private function process_file_record_user($file, $userid) {
        array_push($this->files[$file->contenthash]['users'], $userid);
    }

    private function process_file_record_course($file, $course = null) {
        $course = $course ?? $this->courselookup[$file->contextid];
        array_push($this->files[$file->contenthash]['courses'], $course->courseid);
        $this->process_file_record_category($file, $course->categoryid);
    }

    private function process_file_record_category($file, $categoryid) {
        array_push($this->files[$file->contenthash]['categories'], $categoryid);
    }

    private function process_file_record_other($file) {
        array_push($this->files[$file->contenthash]['other'], $file->instanceid);
    }

    /**
     * Find a course by traversing the file's context path.
     * @param string $path The context path.
     * @param array $courselookup Course lookup table.
     *
     * @return int, or false if not found.
     */
    private function find_course_from_context($path) {
        $contextids = explode('/', $path);
        array_shift($contextids); // Get rid of the leading (empty) array item.
        array_pop($contextids); // Trim the contextid of the current context itself.
        while (count($contextids)) {
            $contextid = array_pop($contextids);
            if (isset($this->courselookup[$contextid])) {
                return $this->courselookup[$contextid];
            }
        }

        // Not found; we'll assume it belongs to the system context.
        return false;
    }

    /**
     * Course lookup table for get_context_sizes().
     * @param int $coursecategory Restrict query based on category.
     *
     * @return array
     */
    private function get_course_lookup_table($categoryid = 0) {
        global $DB;

        $coursesql = "SELECT
                    cx.id,
                    c.id as courseid,
                    c.shortname,
                    ca.id as categoryid
                FROM {course} c
                INNER JOIN {context} cx
                    ON cx.instanceid=c.id AND cx.contextlevel = :contextlevel
                INNER JOIN {course_categories} ca
                    ON ca.id=c.category";
        $params = array('contextlevel' => CONTEXT_COURSE);

        // Build the course lookup table.
        $courselookup = $DB->get_records_sql($coursesql, $params);
        return $courselookup;
    }
}