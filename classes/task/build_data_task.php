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
                $data = $this->build_file_mappings($data);
                break;
            case 2:
                $data = $this->build_context_sizes($data);
                break;
            case 3:
                $this->execute_final($data);
                return;
        }

        $next = self::make($data);
        \core\task\manager::queue_adhoc_task($next);
    }

    protected function get_iteration_limit() {
        return get_config('report_coursesize', 'iteration_limit') ?? 100;
    }

    protected function build_file_mappings($data) {
        $filemappings         = new \report_coursesize\file_mappings();
        $processedrecordids   = $filemappings->process($this->get_iteration_limit());
        $data->progress->step = count($processedrecordids);

        if (count($processedrecordids) == 0) {
            $data->progress->stage = 2;
            $data->progress->step  = 0;
        }

        return $data;
    }

    protected function build_context_sizes($data) {
        $contextsizes   = new \report_coursesize\context_sizes();
        $mappingsleft   = $contextsizes->process_file_mappings($this->get_iteration_limit());
        // $data->progress->step = count($processedrecordids);

        if ($mappingsleft == 0) {
            $data->progress->stage = 3;
            $data->progress->step  = 0;
        }

        return $data;
    }

    protected function execute_final() {
        $contextsizes = new \report_coursesize\context_sizes();

        $sizes = $contextsizes->get_sizes();
        $contextsizes->clear_in_progress();

        \report_coursesize\results_manager::update($sizes);
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