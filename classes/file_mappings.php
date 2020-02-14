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

require_once($CFG->dirroot . '/report/coursesize/locallib.php');

class file_mappings {

    public $file_records;
    // public $file_mappings;

    public $current_file_record;
    public $current_file_mapping;

    public $course_lookup;

    public $iteration_limit;
    public $iteration_count;
    public $processed_record_ids;

    protected function get_file_records() {
        global $DB;

        $sql = "SELECT f.id,
                    f.filesize,
                    f.contenthash,
                    f.component,
                    f.contextid,
                    cx.contextlevel,
                    cx.instanceid,
                    cx.path
                FROM {files} f
                JOIN {context} cx
                    ON f.contextid=cx.id
                WHERE f.id > :lastid
                ORDER BY f.id";

        return $DB->get_records_sql(
            $sql,
            array('lastid' => empty($this->processed_record_ids) ? 0 : max($this->processed_record_ids)),
            0,
            $this->iteration_limit
        );
    }

    // public function get_file_mappings() {
    //     $cache = \cache::make('report_coursesize', 'in_progress');
    //     if ($r = $cache->get('file_mappings')) {
    //         return $r;
    //     } else {
    //         return array();
    //     }
    // }

    // public function set_file_mappings($filemappings = null) {
    //     $filemappings = $filemappings ?? $this->file_mappings;
    //     $cache = \cache::make('report_coursesize', 'in_progress');
    //     return $cache->set('file_mappings', $filemappings);
    // }

    public function get_processed_record_ids() {
        $cache = \cache::make('report_coursesize', 'file_mappings');
        return \report_coursesize\util::cache_get($cache, 'processed_record_ids', array());
    }

    public function set_processed_record_ids($pris = null) {
        $pris = $pris ?? $this->processed_record_ids;
        $cache = \cache::make('report_coursesize', 'file_mappings');
        return $cache->set('processed_record_ids', $pris);
    }

    public function process($iterationlimit) {
        $this->processed_record_ids = $this->get_processed_record_ids();
        $this->iteration_limit      = $iterationlimit;
        $this->file_records         = $this->get_file_records();


        $this->courselookup         = $this->get_course_lookup_table();
        // $this->file_mappings        = $this->get_file_mappings();

        $this->file_mappings_cache = \cache::make('report_coursesize', 'file_mappings');

        foreach ($this->file_records as $id => $filerecord) {
            if ($this->iteration_count >= $this->iteration_limit) {
                break;
            }

            $this->current_file_record = $filerecord;
            $this->process_current_file_record();
            $this->iteration_count++;
        }

        $this->set_processed_record_ids();

        return $this->processed_record_ids;
    }

    protected function process_current_file_record() {

        // if (!array_key_exists($filerecord->contenthash, $this->file_mappings)) {
        //     $this->file_mappings[$filerecord->contenthash]
        // }

        $this->current_file_mapping = $this->file_mappings_cache->get($this->current_file_record->contenthash);
        if (!$this->current_file_mapping) {
            $this->current_file_mapping = (object) array(
                'courses'    => array(),
                'categories' => array(),
                'users'      => array(),
                'other'      => array(),
                'size'       => $this->current_file_record->filesize,
            );
        }
        switch($this->current_file_record->contextlevel) {
            case CONTEXT_USER:
                $this->process_file_record_user();
                break;
            case CONTEXT_COURSE:
                $this->process_file_record_course();
                break;
            case CONTEXT_COURSECAT:
                $this->process_file_record_category();
                break;
            case CONTEXT_SYSTEM:
                $this->process_file_record_other();
                break;
            default:
                // Not a course, user, system, category, see it it's something that should be listed under a course
                // Modules & Blocks mostly.
                $course = self::find_course_from_context($this->current_file_record->path);
                if (!$course) {
                    $this->process_file_record_other();
                } else {
                    $this->process_file_record_course($course);
                }
                break;
        }
        $this->processed_record_ids[] = $this->current_file_record->id;
        $this->file_mappings_cache->set($this->current_file_record->contenthash, $this->current_file_mapping);
    }

    protected function process_file_record_user() {
        \report_coursesize\util::array_push_unique($this->current_file_mapping->users, $this->current_file_record->instanceid);
    }

    protected function process_file_record_course($course = null) {
        $course = $course ?? $this->courselookup[$this->current_file_record->contextid]; // Load full course from courselookup so we have the categoryid.
        \report_coursesize\util::array_push_unique($this->current_file_mapping->courses, $course->courseid);
        $this->process_file_record_category($course->categoryid);
    }

    protected function process_file_record_category($categoryid = null) {
        $categoryid = $categoryid ?? $this->current_file_record->instanceid;
        \report_coursesize\util::array_push_unique($this->current_file_mapping->categories, $categoryid);
    }

    protected function process_file_record_other() {
        array_push($this->current_file_mapping->other, $this->current_file_record->instanceid);
    }

    /**
     * Find a course by traversing the file's context path.
     * @param string $path The context path.
     * @param array $courselookup Course lookup table.
     *
     * @return int, or false if not found.
     */
    protected function find_course_from_context($path) {
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
     *
     * @return array
     */
    protected function get_course_lookup_table() {
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