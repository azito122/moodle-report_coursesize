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
    // public $file_mappings;
    public $contenthashes;
    public $sizes;
    public $iteration_limit;
    public $iteration_count;

    public function get_sizes($area = 'in_progress') {
        $cache = \cache::make('report_coursesize', $area);

        if (isset($this->sizes)) {
            return $this->sizes;
        }

        return (object) array(
            'courses'    => \report_coursesize\util::cache_get($cache, 'course_sizes', array()),
            'categories' => \report_coursesize\util::cache_get($cache, 'category_sizes', array()),
            'users'      => \report_coursesize\util::cache_get($cache, 'user_sizes', array()),
            'system'     => \report_coursesize\util::cache_get($cache, 'system_sizes', (object) array(
                'total'  => 0,
                'backup' => 0,
            )),
        );
    }

    public function set_sizes($sizes, $area = 'in_progress') {
        $cache = \cache::make('report_coursesize', $area);

        $cache->set('course_sizes', $sizes->courses);
        $cache->set('category_sizes', $sizes->categories);
        $cache->set('user_sizes', $sizes->users);
        $cache->set('system_sizes', $sizes->system);
    }

    public function clear_in_progress() {
        $cache = \cache::make('report_coursesize', 'in_progress');
        $cache->purge();
    }

    public function process_file_mappings($iterationlimit) {
        $this->iteration_limit = $iterationlimit;
        $this->sizes           = $this->get_sizes();

        $filemappingscache = \cache::make('report_coursesize', 'file_mappings');
        $contenthashes = $filemappingscache->get('content_hashes');
        $this->contenthashes = $contenthashes;

        // $filemappings          = new \report_coursesize\file_mappings();
        // $this->file_mappings   = $filemappings->get_file_mappings();

        $futurecontenthashes = $contenthashes;
        $countprocessed = 0;
        foreach ($contenthashes as $k => $contenthash) {
            if ($this->iteration_count >= $this->iteration_limit) {
                break;
            }
            $filemapping = $filemappingscache->get($contenthash);
            $this->process_file_mapping($filemapping);

            // $filemappingscache->delete($contenthash);
            unset($futurecontenthashes[$k]);

            $countprocessed += 1;
        }

        $filemappingscache->set('content_hashes', $futurecontenthashes);
        // $filemappings->set_file_mappings($this->file_mappings);

        $this->set_sizes($this->sizes);

        return $countprocessed;
    }

    private function process_file_mapping($filemapping) {
        global $DB;

        // $isbackup = ($filemapping->component == 'backup');
        $isbackup = false;

        $this->sizes->system->total += $filemapping->size;
        // $this->systembackupsize += $isbackup ? $filemapping->size : 0;

        foreach ($filemapping->users as $userid) {
            self::maybe_add_blank_context($userid, $this->sizes->users);

            $this->sizes->users[$userid]->total += $filemapping->size;
            if (
                count($filemapping->users)      == 1 &&
                count($filemapping->courses)    == 0 &&
                count($filemapping->categories) == 0 &&
                count($filemapping->other)      == 0
            ) {
                $this->sizes->users[$userid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->sizes->users[$userid]->backup += $filemapping->size;
            }

            $this->iteration_count += 1;
        }

        foreach ($filemapping->courses as $courseid) {
            self::maybe_add_blank_context($courseid, $this->sizes->courses);

            // Metadata.
            if (!property_exists($this->sizes->courses[$courseid], 'shortname')) {
                $course = $DB->get_record('course', array('id' => $courseid));

                $this->sizes->courses[$courseid]->id        = $courseid;
                $this->sizes->courses[$courseid]->idnumber  = $course->idnumber;
                $this->sizes->courses[$courseid]->shortname = $course->shortname;
                $this->sizes->courses[$courseid]->category  = $course->category;
            }

            $this->sizes->courses[$courseid]->total += $filemapping->size;
            if (
                count($filemapping->courses) == 1 &&
                count($filemapping->other)   == 0
            ) {
                $this->sizes->courses[$courseid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->sizes->courses[$courseid]->backup += $filemapping->size;
            }

            $this->iteration_count += 1;
        }

        foreach ($filemapping->categories as $categoryid) {
            self::maybe_add_blank_context($categoryid, $this->sizes->categories);

            if (!property_exists($this->sizes->categories[$categoryid], 'name')) {
                $category = $DB->get_record('course_categories', array('id' => $categoryid));

                $this->sizes->categories[$categoryid]->id   = $categoryid;
                $this->sizes->categories[$categoryid]->name = $category->name;
            }

            $this->sizes->categories[$categoryid]->total += $filemapping->size;
            if (
                count($filemapping->categories) == 1 &&
                count($filemapping->other)      == 0
            ) {
                $this->sizes->categories[$categoryid]->unique += $filemapping->size;
            }

            if ($isbackup) {
                $this->sizes->categories[$categoryid]->backup += $filemapping->size;
            }

            $this->iteration_count += 1;
        }

        $this->iteration_count += 1;
    }

    private function maybe_add_blank_context($key, &$array) {
        if (!array_key_exists($key, $array)) {
            $array[$key] = new stdClass();
            $array[$key]->total = 0;
            $array[$key]->unique = 0;
            $array[$key]->backup = 0;
        }
    }
}
