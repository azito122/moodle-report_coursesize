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

class files_table_builder {

    protected $core_file_records;
    protected $course_lookup;
    protected $iteration_limit;

    public $processed_records = array();

    public function __construct($processedrecords, $iterationlimit) {
        $this->processed_records = $processedrecords;
        $this->iteration_limit   = $iterationlimit;
        $this->core_file_records = $this->get_core_file_records();
        $this->courselookup      = $this->get_course_lookup_table();
    }

    protected function get_core_file_records() {
        global $DB;

        $skipids  = (string) implode(',', $this->processed_records);
        $wheresql = !empty($skipids) ? "WHERE f.id NOT IN ($skipids)" : '';

        $filessql = "SELECT
                f.id,
                f.filesize,
                f.contenthash,
                f.component,
                cx.id as contextid,
                cx.contextlevel,
                cx.instanceid,
                cx.path,
                cx.depth
            FROM {files} f
            INNER JOIN {context} cx
                ON cx.id=f.contextid
            $wheresql
            ORDER by cx.depth ASC, cx.path ASC";

        return $DB->get_records_sql($filessql, [], 0, (int) $this->iteration_limit);
    }

    public function process() {
        foreach ($this->core_file_records as $corefilerecord) {
            $this->process_core_file_record($corefilerecord);
        }

        if (count($this->core_file_records) < $this->iteration_limit) {
            return true;
        } else {
            return false;
        }
    }

    protected function process_core_file_record($corefilerecord) {
        array_push($this->processed_records, $corefilerecord->id);

        switch($corefilerecord->contextlevel) {
            case CONTEXT_USER:
                $this->process_core_file_record_user($corefilerecord, $corefilerecord->instanceid);
                break;
            case CONTEXT_COURSE:
                $this->process_core_file_record_course($corefilerecord);
                break;
            case CONTEXT_COURSECAT:
                $this->process_core_file_record_category($corefilerecord, $corefilerecord->instanceid);
                break;
            case CONTEXT_SYSTEM:
                // No extra handling needed; size is already in files array.
                break;
            default:
                // Not a course, user, system, category, see it it's something that should be listed under a course
                // Modules & Blocks mostly.
                $course = self::find_course_from_context($corefilerecord->path);
                if (!$course) {
                    $this->process_core_file_record_other($corefilerecord);
                } else {
                    $this->process_core_file_record_course($corefilerecord, $course);
                }
                break;
        }
    }

    private function process_core_file_record_user($file, $userid) {
        $this->update_custom_table($file, 'users', $userid);
        // array_push($this->files[$file->contenthash]['users'], $userid);
    }

    private function process_core_file_record_course($file, $course = null) {
        $course = $course ?? $this->courselookup[$file->contextid];
        $this->update_custom_table($file, 'courses', $course->courseid);
        // array_push($this->files[$file->contenthash]['courses'], $course->courseid);
        $this->process_core_file_record_category($file, $course->categoryid);
    }

    private function process_core_file_record_category($file, $categoryid) {
        $this->update_custom_table($file, 'categories', $categoryid);
        // array_push($this->files[$file->contenthash]['categories'], $categoryid);
    }

    private function process_core_file_record_other($file) {
        $this->update_custom_table($file, 'other', $file->instanceid);
        // array_push($this->files[$file->contenthash]['other'], $file->instanceid);
    }

    protected function update_custom_table($corefilerecord, $key, $value) {
        global $DB;

        $customfilerecord = $DB->get_record(
            'report_coursesize_files',
            array('contenthash' => $corefilerecord->contenthash),
            null,
            IGNORE_MISSING
        );

        if (empty($customfilerecord)) {
            $customfilerecord = new \stdClass();
            $customfilerecord->contenthash = $corefilerecord->contenthash;
            $id = $DB->insert_record($customfilerecord);
            $customfilerecord = $DB->get_record('report_coursesize_files', array('id' => $id));
        }

        $items = explode(',', $customfilerecord->{$key});
        array_push($items, $value);
        $customfilerecord->{$key} = implode(',', $items);

        $DB->update_record('report_coursesize_files', $customfilerecord);
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