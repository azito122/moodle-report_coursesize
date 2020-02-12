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

class context_sizes_builder {
    protected $customfilerecords;
    protected $processedids;

    protected $iterationlimit;
    protected $iterationcount;


    public $systemsize = 0;
    public $systembackupsize = 0;

    public $courses    = array();
    public $categories = array();
    public $users      = array();

    public function __construct($contexts, $limit) {
        global $DB;

        $this->courses    = $contexts->courses;
        $this->categories = $contexts->categories;
        $this->users      = $contexts->users;

        $this->customfilerecords = $this->get_custom_file_records();
        $this->iterationlimit    = $limit;
    }

    protected function get_custom_file_records() {
        global $DB;

        $sql = "SELECT rcf.id,
                    rcf.contenthash,
                    rcf.courses,
                    rcf.categories,
                    rcf.users,
                    f.size,
                    f.component
                FROM {report_coursesize_files} rcf
                LEFT JOIN {files} f
                    ON rcf.contenthash=f.contenthash";

        return $DB->get_records_sql('report_coursesize_files');
    }

    public function process_files() {
        foreach ($this->customfilerecords as $customfilerecord) {
            if ($this->iterationcount >= $this->iterationlimit) {
                break;
            }
            $this->process_file($customfilerecord);
        }
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

        $isbackup = ($file->component == 'backup');

        $this->systemsize       += $file->size;
        $this->systembackupsize += $isbackup ? $file->size : 0;

        foreach ($file->users as $userid) {
            self::maybe_add_blank_context($userid, $this->users);

            $this->users[$userid]->total += $file->size;
            if (
                count($file->users)      == 1 &&
                count($file->courses)    == 0 &&
                count($file->categories) == 0 &&
                count($file->other)      == 0
            ) {
                $this->users[$userid]->unique += $file->size;
            }

            if ($isbackup) {
                $this->users[$userid]->backup += $file->size;
            }

            $this->iterationcount += 1;
        }

        foreach ($file->courses as $courseid) {
            self::maybe_add_blank_context($courseid, $this->courses);

            // Metadata.
            $course = $DB->get_record('course', array('id' => $courseid));

            $this->courses[$courseid]->id        = $courseid;
            $this->courses[$courseid]->shortname = $course->shortname;
            $this->courses[$courseid]->category  = $course->category;

            $this->courses[$courseid]->total += $file->size;
            if (
                count($file->courses) == 1 &&
                // count($file->courses) <= 1 &&
                count($file->users)   == 0 &&
                count($file->other)   == 0
            ) {
                $this->courses[$courseid]->unique += $file->size;
            }

            if ($isbackup) {
                $this->courses[$courseid]->backup += $file->size;
            }

            $this->iterationcount += 1;
        }

        foreach ($file->categories as $categoryid) {
            self::maybe_add_blank_context($categoryid, $this->categories);

            $category = $DB->get_record('course_categories', array('id' => $categoryid));

            $this->categories[$categoryid]->id   = $categoryid;
            $this->categories[$categoryid]->name = $category->name;

            $this->categories[$categoryid]->total += $file->size;
            if (
                count($file->categories) == 1 &&
                count($file->users)      == 0 &&
                count($file->other)      == 0
            ) {
                $this->categories[$categoryid]->unique += $file->size;
            }

            if ($isbackup) {
                $this->categories[$categoryid]->backup += $file->size;
            }

            $this->iterationcount += 1;
        }

        $DB->delete_record('report_coursesize_files', array('id' => $file->id));
        $this->iterationcount += 1;
    }
}
