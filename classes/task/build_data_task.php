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

    public static function make() {
        $task = new \report_coursesize\task\build_data_task();
        $task->set_next_run_time(time() + 1);
        return $task;
    }

    /**
     * Execute the task
     */
    public function execute() {
        $progress = $this->get_progress();

        switch ($progress->stage) {
            case 1:
                $progress = $this->build_file_mappings($progress);
                break;
            case 2:
                $progress = $this->build_context_sizes($progress);
                break;
            case 3:
                $this->execute_final();
                return;
        }

        $this->set_progress($progress);

        $next = self::make();
        \core\task\manager::queue_adhoc_task($next);
    }

    public static function get_progress($real = false) {
        $cache = \cache::make('report_coursesize', 'in_progress');
        $default = $real ? false : (object) array(
            'step' => 0,
            'stage' => 1
        );
        return \report_coursesize\util::cache_get($cache, 'progress', $default);
    }

    public static function set_progress($progress) {
        $cache = \cache::make('report_coursesize', 'in_progress');
        $cache->set('progress', $progress);
    }

    protected function get_iteration_limit() {
        return get_config('report_coursesize', 'iteration_limit') ?? 100;
    }

    protected function build_file_mappings($progress) {
        $filemappings = new \report_coursesize\file_mappings();
        $filemappings->process($this->get_iteration_limit());

        $progress->step = count($filemappings->processed_record_ids);

        if ($filemappings->iteration_count < $filemappings->iteration_limit) {
            $progress->stage = 2;
            $progress->step  = 0;
        }

        return $progress;
    }

    protected function build_context_sizes($progress) {
        $contextsizes   = new \report_coursesize\context_sizes();
        $mappingsleft   = $contextsizes->process_file_mappings($this->get_iteration_limit());
        // $data->progress->step = count($processedrecordids);

        if ($mappingsleft == 0) {
            $progress->stage = 3;
            $progress->step  = 0;
        }

        return $progress;
    }

    protected function execute_final() {
        $contextsizes = new \report_coursesize\context_sizes();

        $sizes = $contextsizes->get_sizes();
        $contextsizes->clear_in_progress();

        \report_coursesize\results_manager::update($sizes);
    }
}