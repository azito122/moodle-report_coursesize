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
 * Site size calculations.
 *
 * @package    report_coursesize
 * @copyright  2017 Lafayette College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_coursesize;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class context_sizes {
    protected $file_mappings;
    protected $iteration_limit;
    protected $iteration_count;

    // public $systembackupsize = 0;

    public $system_sizes = array();
    public $courses      = array();
    public $categories   = array();
    public $users        = array();

    public function get_sizes($which = null) {
        $cache = \cache::make('report_coursesize', 'in_progress');

        if (!isset($which)) {
            return (object) array(
                'course_sizes'   => $cache->get('course_sizes'),
                'category_sizes' => $cache->get('category_sizes'),
                'user_sizes'     => $cache->get('user_sizes'),
                'system_sizes'   => $cache->get('system_sizes'),
            );
        }

        return $cache->get($which . '_sizes') ?? array();
    }

    public function update_sizes($which, $value) {
        $cache = \cache::make('report_coursesize', 'in_progress');
        return $cache->set($which . '_sizes', $value);
    }

    public function process_file_mappings($iterationlimit) {
        $this->iteration_limit = $iterationlimit;
        $this->course_sizes    = $this->get_sizes('course');
        $this->category_sizes  = $this->get_sizes('category');
        $this->user_sizes      = $this->get_sizes('user');
        $this->system_sizes    = $this->get_sizes('system');

        $filemappings        = new \report_coursesize\file_mappings();
        $this->file_mappings = $filemappings->get_file_mappings();

        foreach ($this->file_mappings as $id => $filemapping) {
            if ($this->iteration_count >= $this->iteration_limit) {
                break;
            }
            $this->process_file_mapping($filemapping);
            unset($this->file_mappings[$id]);
        }

        $filemappings->update_file_mappings($this->file_mappings);

        $this->update_sizes('course', $this->course_sizes);
        $this->update_sizes('category', $this->category_sizes);
        $this->update_sizes('user', $this->user_sizes);
        $this->update_sizes('system', $this->system_sizes);

        return count($this->file_mappings);
    }

    private function maybe_add_blank_context($key, &$array) {
        if (!array_key_exists($key, $array)) {
            $array[$key] = new stdClass();
            $array[$key]->total = 0;
            $array[$key]->unique = 0;
            $array[$key]->backup = 0;
        }
    }

    private function process_file_mapping($filemapping) {
        global $DB;

        // $isbackup = ($filemapping->component == 'backup');
        $isbackup = false;

        $this->system_sizes->total += $filemapping->size;
        // $this->systembackupsize += $isbackup ? $filemapping->size : 0;

        foreach ($filemapping->users as $userid) {
            self::maybe_add_blank_context($userid, $this->users);

            $this->users[$userid]->total += $filemapping->size;
            if (
                count($filemapping->users)      == 1 &&
                count($filemapping->courses)    == 0 &&
                count($filemapping->categories) == 0 &&
                count($filemapping->other)      == 0
            ) {
                $this->users[$userid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->users[$userid]->backup += $filemapping->size;
            }

            $this->iterationcount += 1;
        }

        foreach ($filemapping->courses as $courseid) {
            self::maybe_add_blank_context($courseid, $this->courses);

            // Metadata.
            $course = $DB->get_record('course', array('id' => $courseid));

            $this->courses[$courseid]->id        = $courseid;
            $this->courses[$courseid]->shortname = $course->shortname;
            $this->courses[$courseid]->category  = $course->category;

            $this->courses[$courseid]->total += $filemapping->size;
            if (
                count($filemapping->courses) == 1 &&
                // count($filemapping->courses) <= 1 &&
                count($filemapping->users)   == 0 &&
                count($filemapping->other)   == 0
            ) {
                $this->courses[$courseid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->courses[$courseid]->backup += $filemapping->size;
            }

            $this->iterationcount += 1;
        }

        foreach ($filemapping->categories as $categoryid) {
            self::maybe_add_blank_context($categoryid, $this->categories);

            $category = $DB->get_record('course_categories', array('id' => $categoryid));

            $this->categories[$categoryid]->id   = $categoryid;
            $this->categories[$categoryid]->name = $category->name;

            $this->categories[$categoryid]->total += $filemapping->size;
            if (
                count($filemapping->categories) == 1 &&
                count($filemapping->users)      == 0 &&
                count($filemapping->other)      == 0
            ) {
                $this->categories[$categoryid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->categories[$categoryid]->backup += $filemapping->size;
            }

            $this->iterationcount += 1;
        }

        $this->iterationcount += 1;
    }
}
