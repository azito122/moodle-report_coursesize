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

class results_manager {
    public $updated = 0;

    public function get_sizes($categoryid) {
        $cache          = \cache::make('report_coursesize', 'results');
        $contextsizes     = new \report_coursesize\context_sizes();

        $this->updated  = $cache->get('updated');
        $sizes          = $contextsizes->get_sizes('results');
        $totalsiteusage = $cache->get('total_site_usage');

        $lifetime = (int) get_config('report_coursesize', 'cache_lifetime') * 3600;

        // If we're missing data, or if data is stale, schedule a new build.
        if (
            (empty($sizes->courses) && empty($sizes->categories) && empty($sizes->users))
            || $this->updated < (time() - $lifetime)
        ) {
            if (!\report_coursesize\task\build_data_task::get_progress(true)) {
                \core\task\manager::queue_adhoc_task(\report_coursesize\task\build_data_task::make(), true);
            }
        }

        if (is_numeric($categoryid) && !empty($categoryid)) {
            $filteredcontexts = $cache->get('filteredcontexts') ?? array();

            if ($filteredcontexts !== false && array_key_exists($categoryid, $filteredcontexts)) {
                $filteredbycat = $filteredcontexts[$categoryid];
            } else {
                $allowedcatids = $this->get_child_category_ids($categoryid);

                $filteredbycat                 = new \stdClass();
                $filteredbycat->course_sizes   = array();
                $filteredbycat->category_sizes = array();

                foreach ($sizes->courses as $course) {
                    if (in_array($course->category, $allowedcatids)) {
                        $filteredbycat->courses[$course->id] = $course;
                    }
                }

                foreach ($sizes->categories as $category) {
                    if (in_array($category->id, $allowedcatids)) {
                        $filteredbycat->categories[$category->id] = $category;
                    }
                }

                $filteredcontexts = is_array($filteredcontexts) ? $filteredcontexts : array();
                $filteredcontexts[$categoryid] = $filteredbycat;
                $cache->set('filteredcontexts', $filteredcontexts);
            }

            $sizes->courses    = $filteredbycat->course_sizes;
            $sizes->categories = $filteredbycat->category_sizes;
        }

        $sizes->total_site_usage = $totalsiteusage;
        return $sizes;
    }

    private function get_child_category_ids($categoryid) {
        $category = \core_course_category::get($categoryid);
        return array_merge([$categoryid], $category->get_all_children_ids());
    }

    public static function update($sizes) {
        $cache            = \cache::make('report_coursesize', 'results');
        $contextsizes     = new \report_coursesize\context_sizes();
        $total_site_usage = self::get_total_site_usage();

        $cache->set('updated', time());
        $contextsizes->set_sizes($sizes, 'results');
        $cache->set('total_site_usage', $total_site_usage);
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
}

