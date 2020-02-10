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

defined('MOODLE_INTERNAL') || die();

class context_sizes {
    protected $courselookup;
    protected $iterlimit = 0;
    protected $itercurrent = 0;
    protected $added_to_files_array = array();

    public $done = false;
    public $date = 0;
    public $progress = array(
        'current_step'  => 0,
        'total_steps'   => 0,
        'current_stage' => 1,
        'total_stages'  => 2,
    );

    public $systemsize = 0;
    public $systembackupsize = 0;

    public $files      = array();
    public $users      = array();
    public $courses    = array();
    public $categories = array();

    public function __construct($limit = 0) {
        global $DB;

        $this->iterlimit  = $limit;

        $this->progress['total_steps'] = $DB->count_records('files');
    }

    private function hit_limit() {
        return ($this->iterlimit != 0 && $this->itercurrent >= $this->iterlimit);
    }

    private static function get_file_data() {
        global $DB;

        $filessql = "SELECT
                cx.id as contextid,
                cx.contextlevel,
                cx.instanceid,
                cx.path,
                cx.depth,
                f.id,
                f.filesize,
                f.contenthash,
                f.component
            FROM {files} f
            INNER JOIN {context} cx
                ON cx.id=f.contextid
            ORDER by cx.depth ASC, cx.path ASC";
        return $DB->get_recordset_sql($filessql);
    }

    public function continue($limit = null) {
        if (is_numeric($limit)) {
            $this->iterlimit = $limit;
        }

        // If we're already at the limit, bail out.
        if ($this->hit_limit()) {return;}

        // Build contextid => coursedata lookup table.
        $this->courselookup = self::get_course_lookup_table($this->categoryid);

        // Continue processing file records to build the files array.
        $this->process_file_records($this->get_file_data($this->categoryid));

        // If we hit the limit in the last step, return now.
        if ($this->hit_limit()) {
            $this->progress['current_step'] = count($this->added_to_files_array);
            $this->date = time();
            return;
        }

        // Proceed to process the files array into context size data.
        $this->process_files_array();
        $this->progress['total_steps'] = count($this->files);
        $this->progress['current_stage'] = 2;

        // If we haven't hit the limit, it means we're done.
        if (!$this->hit_limit()) {
            $this->done = true;
            $this->date = time();
        }
    }

    public function process_files_array() {
        // Process files array into context size data.
        foreach ($this->files as $file) {
            if ($this->hit_limit()) {
                break;
            }

            if ($file['processed']) {
                continue;
            }

            $this->process_file($file);
            $file['processed'] = true;
            $this->progress['current_step']++;
        }
    }

    private static function maybe_add_blank_context($key, &$array) {
        if (!array_key_exists($key, $array)) {
            $array[$key] = array(
                'total' => 0,
                'unique' => 0,
                'backup' => 0,
            );
        }
    }

    private function process_file($file) {
        $isbackup = $file['component'] == 'backup';

        $this->systemsize       += $file['size'];
        $this->systembackupsize += $isbackup ? $file['size'] : 0;

        foreach ($file['users'] as $userid) {
            self::maybe_add_blank_context($userid, $this->users);

            $this->users[$userid]['total'] += $file['size'];
            if (
                count($file['users'])      == 1 &&
                count($file['courses'])    == 0 &&
                count($file['categories']) == 0 &&
                count($file['other'])      == 0
            ) {
                $this->users[$userid]['unique'] += $file['size'];
            }

            if ($isbackup) {
                $this->users[$userid]['backup'] += $file['size'];
            }
        }

        foreach ($file['courses'] as $courseid) {
            self::maybe_add_blank_context($courseid, $this->courses);

            $this->courses[$courseid]['total'] += $file['size'];
            if (
                count($file['courses']) == 1 &&
                count($file['courses']) <= 1 &&
                count($file['users'])   == 0 &&
                count($file['other'])   == 0
            ) {
                $this->courses[$courseid]['unique'] += $file['size'];
            }

            if ($isbackup) {
                $this->courses[$courseid]['backup'] += $file['size'];
            }
        }

        foreach ($file['categories'] as $categoryid) {
            self::maybe_add_blank_context($categoryid, $this->categories);

            $this->categories[$categoryid]['total'] += $file['size'];
            if (
                count($file['categories']) == 1 &&
                count($file['users'])      == 0 &&
                count($file['other'])      == 0
            ) {
                $this->categories[$categoryid]['unique'] += $file['size'];
            }

            if ($isbackup) {
                $this->categories[$userid]['backup'] += $file['size'];
            }
        }
    }
}
