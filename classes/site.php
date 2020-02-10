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

class site {
    /**
     * Attempt to load the cache. If it's older than 24 hours, invalidate it if it exists.
     *
     * @param int $coursecategory The coursecategory, or 0 for the site.
     *
     * @return array
     */
    public function get_results($coursecategory = 0) {
        $cache = \cache::make('report_coursesize', 'results');
        $date = $cache->get('date');
        $contexts = $cache->get('contexts');
        // $results  = $cache->get('results');
        $lifetime = get_config('report_coursesize', 'cache_lifetime');

        $results = new \stdClass();
        $results->contexts = $contexts;
        if ($contexts === false || $date < (time() - $lifetime)) {
            // $results                   = new \stdClass();
            // $results->date             = time();
            // $results->total_site_usage = self::get_total_site_usage();
            // $results->contexts         = $this->get_context_sizes();
            // $results->summary          = self::get_course_summaries($results->contexts, $coursecategory);
            // // if ($coursecategory === 0) {
            //     $results->users = self::get_users($results->contexts);
            // // }
            // // $cache->set($coursecategory, $results);
            // $cache->set('results', $results);
        }
        return $results;
    }

    private static function get_context_sizes() {
        $cache    = \cache::make('report_coursesize', 'context_sizes');
        $done     = $cache->get('done');
        $lifetime = get_config('report_coursesize', 'cache_lifetime');

        if ($done === false || $done->date < (time() - $lifetime)) {
            // If there is no completed contextsizes obj, or if it is out of date,
            // trigger a new adhoc task to build/rebuild the data.
            $task = new \report_coursesize\task\build_data_task();
            \core\task\manager::queue_adhoc_task($task);
        }

        return $done;
    }

    /**
     * Produce a sorted array of objects suitable for user_table().
     */
    private static function get_users($contexts) {
        global $DB;

        arsort($contexts->usersizes);
        $users = array();
        $usercount = 0;
        foreach ($contexts->usersizes as $userid => $size) {
            $usercount++;
            $user = $DB->get_record('user', array('id' => $userid));
            $item = new \stdClass();
            $item->id = $userid;
            $item->fullname = fullname($user);
            $item->bytesused = $size;
            $users[$userid] = $item;
            if ($usercount >= REPORT_COURSESIZE_NUMBEROFUSERS) {
                break;
            }
        }
        return $users;
    }

    /**
     * Produce a sorted array of objects suitable for site_table().
     * @param array $contexts
     * @return array
     */
    private static function get_course_summaries($contexts, $coursecategory) {
        // Sort the course sizes now.
        arsort($contexts->courses);

        // Get the course metadata.
        $courses = self::get_courses($coursecategory);

        $summary = array(
            'nonzero' => array(),
            'zero' => array()
        );
        foreach ($contexts->coursesizes as $courseid => $size) {
            // Filter out on category lookups.
            if (!array_key_exists($courseid, $courses)) {
                continue;
            }

            $item = new \stdClass();
            $item->id = $courseid;
            $item->shortname = $courses[$courseid]->shortname;
            $item->bytesused = $size;
            $item->backupbytesused = $contexts->coursebackupsizes[$courseid];
            $summary['nonzero'][$courseid] = $item;
            unset($courses[$courseid]);
        }
        $summary['zero'] = $courses;
        return $summary;
    }

    private static function get_course_category_sql($coursecategory) {
        global $DB;

        $context = \context_coursecat::instance($coursecategory);
        $coursecat = core_course_category::get($coursecategory);
        $courses = $coursecat->get_courses(array('recursive' => true, 'idonly' => true));

        if (!empty($courses)) {
            list($insql, $courseparams) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
            $extracoursesql = ' WHERE c.id ' . $insql;
        } else {
            // Don't show any courses if category is selected but category has no courses.
            // This stuff really needs a rewrite!
            $extracoursesql = ' WHERE c.id is null';
        }

        return array($courseparams, $extracoursesql);
    }

    /**
     * Get course front matter and add the associated size information.
     * @param int $coursecategory
     * @param array $contexts
     * @return array
     */
    private static function get_courses($coursecategory = 0) {
        global $DB;
        $coursesql = "SELECT id, shortname FROM {course} c";
        $params = array();

        if ($coursecategory !== 0) {
            list($courseparams, $extracoursesql) = self::get_course_category_sql($coursecategory);
            $coursesql .= $extracoursesql;
            $params = array_merge($params, $courseparams);
        }

        // Get courses.
        return $DB->get_records_sql($coursesql, $params);
    }

    /**
     * Wraps get_directory_size().
     *
     * @return float dataroot size in megabytes.
     */
    private static function get_total_site_usage() {
        global $CFG;
        // Check if the path ends with a "/" otherwise an exception will be thrown
        $sitedatadir = $CFG->dataroot;
        if (is_dir($sitedatadir)) {
            // Only append a "/" if it doesn't already end with one
            if (substr($sitedatadir, -1) !== '/') {
                $sitedatadir .= '/';
            }
        }

        $bytes = get_directory_size($sitedatadir);
        return ceil($bytes / 1048576);
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
}
