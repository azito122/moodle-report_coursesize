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
    protected $file_mappings;
    protected $course_lookup;

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

    public function get_file_mappings() {
        $cache = \cache::make('report_coursesize', 'in_progress');
        if ($r = $cache->get('file_mappings')) {
            return $r;
        } else {
            return array();
        }
    }

    public function set_file_mappings($filemappings = null) {
        $filemappings = $filemappings ?? $this->file_mappings;
        $cache = \cache::make('report_coursesize', 'in_progress');
        return $cache->set('file_mappings', $filemappings);
    }

    public function get_processed_record_ids() {
        $cache = \cache::make('report_coursesize', 'in_progress');
        if ($r = $cache->get('processed_record_ids')) {
            return $r;
        } else {
            return array();
        }
    }

    public function set_processed_record_ids($pris = null) {
        $pris = $pris ?? $this->processed_record_ids;
        $cache = \cache::make('report_coursesize', 'in_progress');
        return $cache->set('processed_record_ids', $pris);
    }

    public function process($iterationlimit) {
        $this->processed_record_ids = $this->get_processed_record_ids();
        $this->iteration_limit      = $iterationlimit;
        $this->file_records         = $this->get_file_records();
        $this->courselookup         = $this->get_course_lookup_table();
        $this->file_mappings        = $this->get_file_mappings();

        foreach ($this->file_records as $id => $filerecord) {
            if ($this->iteration_count >= $this->iteration_limit) {
                break;
            }

            if (in_array($id, $this->processed_record_ids)) {
                continue;
            }

            $this->process_file_record($filerecord);
            $this->iteration_count++;
        }

        if (count($this->processed_record_ids) > 0) {
            $this->set_processed_record_ids();
            $this->set_file_mappings();
        }

        return $this->processed_record_ids;
    }

    protected function process_file_record($filerecord) {
        array_push($this->processed_record_ids, $filerecord->id);

        if (!array_key_exists($filerecord->contenthash, $this->file_mappings)) {
            $this->file_mappings[$filerecord->contenthash] = (object) array(
                'courses'    => array(),
                'categories' => array(),
                'users'      => array(),
                'other'      => array(),
                'size'       => $filerecord->filesize,
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
                $this->process_file_record_other($filerecord, $filerecord->instanceid);
                break;
            default:
                // Not a course, user, system, category, see it it's something that should be listed under a course
                // Modules & Blocks mostly.
                $course = self::find_course_from_context($filerecord->path);
                if (!$course) {
                    $this->process_file_record_other($filerecord, $filerecord->instanceid);
                } else {
                    $this->process_file_record_course($filerecord, $course);
                }
                break;
        }
    }

    protected function process_file_record_user($file, $userid) {
        \report_coursesize\util::array_push_unique($this->file_mappings[$file->contenthash]->users, $userid);
    }

    protected function process_file_record_course($file, $course = null) {
        $course = $course ?? $this->courselookup[$file->contextid]; // Load full course from courselookup so we have the categoryid.
        \report_coursesize\util::array_push_unique($this->file_mappings[$file->contenthash]->courses, $course->courseid);
        $this->process_file_record_category($file, $course->categoryid);
    }

    protected function process_file_record_category($file, $categoryid) {
        \report_coursesize\util::array_push_unique($this->file_mappings[$file->contenthash]->categories, $categoryid);
    }

    protected function process_file_record_other($file, $instanceid) {
        array_push($this->file_mappings[$file->contenthash]->other, $instanceid);
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