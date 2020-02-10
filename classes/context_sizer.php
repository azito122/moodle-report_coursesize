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

class context_sizer {
    private $files;

    public $systemsize = 0;
    public $systembackupsize = 0;

    public $users      = array();
    public $courses    = array();
    public $categories = array();

    public function __construct($files) {
        $this->files = $files;
        $this->process_files();
    }

    public function process_files() {
        foreach ($this->files as $file) {
            $this->process_file($file);
        }
    }

    public function get_contexts() {
        $return                   = new \stdClass();
        $return->systemsize       = $this->systemsize;
        $return->systembackupsize = $this->systembackupsize;
        $return->users            = $this->users;
        $return->courses          = $this->courses;
        $return->categories       = $this->categories;

        return $return;
    }

    private function maybe_add_blank_context($key, &$array) {
        if (!array_key_exists($key, $array)) {
            $array[$key] = new stdClass();
            $array[$key]->total = 0;
            $array[$key]->unique = 0;
            $array[$key]->backup = 0;
        }
    }

    private function process_file($file) {
        global $DB;

        $isbackup = ($file['component'] == 'backup');

        $this->systemsize       += $file['size'];
        $this->systembackupsize += $isbackup ? $file['size'] : 0;

        foreach ($file['users'] as $userid) {
            self::maybe_add_blank_context($userid, $this->users);

            $this->users[$userid]->total += $file['size'];
            if (
                count($file['users'])      == 1 &&
                count($file['courses'])    == 0 &&
                count($file['categories']) == 0 &&
                count($file['other'])      == 0
            ) {
                $this->users[$userid]->unique += $file['size'];
            }

            if ($isbackup) {
                $this->users[$userid]->backup += $file['size'];
            }
        }

        foreach ($file['courses'] as $courseid) {
            self::maybe_add_blank_context($courseid, $this->courses);

            // Metadata.
            $course = $DB->get_record('course', array('id' => $courseid));

            $this->courses[$courseid]->id        = $courseid;
            $this->courses[$courseid]->shortname = $course->shortname;
            $this->courses[$courseid]->category  = $course->category;

            $this->courses[$courseid]->total += $file['size'];
            if (
                count($file['courses']) == 1 &&
                // count($file['courses']) <= 1 &&
                count($file['users'])   == 0 &&
                count($file['other'])   == 0
            ) {
                $this->courses[$courseid]->unique += $file['size'];
            }

            if ($isbackup) {
                $this->courses[$courseid]->backup += $file['size'];
            }
        }

        foreach ($file['categories'] as $categoryid) {
            self::maybe_add_blank_context($categoryid, $this->categories);

            $category = $DB->get_record('course_categories', array('id' => $categoryid));

            $this->categories[$categoryid]->id   = $categoryid;
            $this->categories[$categoryid]->name = $category->name;

            $this->categories[$categoryid]->total += $file['size'];
            if (
                count($file['categories']) == 1 &&
                count($file['users'])      == 0 &&
                count($file['other'])      == 0
            ) {
                $this->categories[$categoryid]->unique += $file['size'];
            }

            if ($isbackup) {
                $this->categories[$categoryid]->backup += $file['size'];
            }
        }
    }
}
