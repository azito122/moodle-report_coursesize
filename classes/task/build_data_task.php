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

namespace report_coursesize\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/coursesize/locallib.php');

class build_data_task extends \core\task\adhoc_task {
    /**
     * Return the name of the component.
     *
     * @return string The name of the component.
     */
    public function get_component() {
        return 'report_coursesize';
    }

    public static function make($data = null) {
        $data = $data ?? new \stdClass();

        $task = new \report_coursesize\task\build_data_task();
        $task->set_next_run_time(time() + 1);
        $task->set_custom_data((object)
            array(
                'iteration_limit'       => get_config('report_coursesize', 'iteration_limit') ?? 100,
                'processed_records' => $data->processed_records ?? array(),
                'contexts'          => $data->contexts ?? new \stdClass(),
                'systemsize'        => $data->systemsize ?? 0,
                'systembackupsize'  => $data->systembackupsize ?? 0,
                'progress'          => $data->progress ?? (object) array(
                    'stage'    => 1,
                    'stagetot' => 2,
                    'step'     => 0,
                    'steptot'  => 0,
                ),
            )
        );
        return $task;
    }

    /**
     * Execute the task
     */
    public function execute() {
        $data = $this->get_custom_data();

        switch ($data->progress->stage) {
            case 1:
                $data = $this->process_core_file_records($data);
                break;
            case 2:
                $data = $this->execute_stage_two($data);
                break;
            case 3:
                $this->execute_final($data);
                return;
        }

        $next = self::make($data);
        \core\task\manager::queue_adhoc_task($next);
    }

    protected function process_core_file_records($data) {
        $builder = new \report_coursesize\files_table_builder($data->core_file_records_processed, $data->iteration_limit);
        $isdone  = $builder->process();

        $data->core_file_records_processed = $builder->processed_records;
        $data->progress->step              = count($data->core_file_records_processed);

        if ($isdone) {
            $data->progress->stage = 2;
            $data->progress->step  = 0;
        }

        return $data;
    }

    protected function execute_stage_two($data) {
        global $DB;

        // Update contexts/sizes.
        $cache = \cache::make('report_coursesize', 'in_progress');
        $contexts = (object) array(
            'courses'    => $cache->get('course_sizes'),
            'categories' => $cache->get('category_sizes'),
            'users'      => $cache->get('user_sizes'),
        );

        $contextsizer = new \report_coursesize\context_sizes_builder($contexts, $data->iteration_limit);

        $cache->set('course_sizes', $contextsizer->courses);
        $cache->set('category_sizes', $contextsizer->categories);
        $cache->set('user_sizes', $contextsizer->users);

        $data->systemsize       = $contextsizer->systemsize;
        $data->systembackupsize = $contextsizer->systembackupsize;

        if ($DB->count_records('report_coursesizes_files') == 0) {
            $data->progress->stage = 3;
        }

        return $data;
    }

    protected function execute_final($data) {
        $cache = \cache::make('report_coursesize', 'in_progress');
        $contexts = (object) array(
            'courses'    => $cache->get('course_sizes'),
            'categories' => $cache->get('category_sizes'),
            'users'      => $cache->get('user_sizes'),
        );

        $cache->delete('course_sizes');
        $cache->delete('category_sizes');
        $cache->delete('user_sizes');

        \report_coursesize\results_manager::update((object) array(
            'contexts'         => $contexts,
            'systemsize'       => $data->systemsize,
            'systembackupsize' => $data->systembackupsize
        ));
    }

    public static function get_build_progress() {
        $tasks = \core\task\manager::get_adhoc_tasks('\report_coursesize\task\build_data_task');
        if (count($tasks) > 0) {
            $build = $tasks[array_keys($tasks)[0]];
            $data  = $build->get_custom_data();
            return $data->progress;
        }

        return false;
    }
}