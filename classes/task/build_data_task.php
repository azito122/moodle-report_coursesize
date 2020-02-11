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
                'batch_limit'       => get_config('report_coursesize', 'batch_limit') ?? 100,
                'processed_records' => $data->processed_records ?? array(),
                'files'             => $data->files ?? array(),
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
        $data        = $this->get_custom_data();
        $data->files = json_decode(json_encode($data->files), true); // Turn it back into an array.

        $done = false;
        switch ($data->progress->stage) {
            case 1:
                echo "\n====== STAGE ONE ======= \n";
                $data = $this->execute_stage_one($data);
                break;
            case 2:
                echo "\n====== STAGE TWO ======= \n";
                $data = $this->execute_stage_two($data);
                break;
            case 3:
                echo "\n====== STAGE THREE ======= \n";
                $this->finish_execution($data);
                $done = true;
                break;
        }

        if (!$done) {
            $next = self::make($data);
            \core\task\manager::queue_adhoc_task($next);
        }
    }

    protected function execute_stage_one($data) {
        $filerecords = $this->get_file_records();

        if (count($filerecords) > 0) {
            $filesizer               = new \report_coursesize\file_sizer($filerecords, $data->files);
            $data->files             = $filesizer->files;
            $data->processed_records = array_merge((array) $data->processed_records, $filesizer->processed_records);

            $data->progress->step = count($data->processed_records);
        }

        if (count($filerecords) < $data->batch_limit) {
            $data->progress->stage = 2;
            $data->progress->step  = 0;
        }

        return $data;
    }

    protected function execute_stage_two($data) {
        $data->progress->steptot = empty($data->progress->steptot) ? count($data->files) : $data->progress->steptot;

        if ($data->batch_limit >= count($data->files)) {
            $processfiles = $data->files;
            $keepfiles    = array();
        } else {
            $processfiles = array_slice($data->files, 0, $data->batch_limit);
            $keepfiles    = array_slice($data->files, $data->batch_limit);
        }

        $data->progress->step = $data->progress->steptot - count($keepfiles);

        if (count($keepfiles) == 0) {
            $data->progress->stage = 3;
        }

        // Update contexts/sizes.
        $contextsizer = new \report_coursesize\context_sizer($processfiles);
        $data->contexts         = $contextsizer->get_contexts();
        $data->files            = $keepfiles;
        $data->systemsize       = $contextsizer->systemsize;
        $data->systembackupsize = $contextsizer->systembackupsize;

        return $data;
    }

    protected function finish_execution($data) {
        \report_coursesize\results_manager::update((object) array(
            'contexts'         => $data->contexts,
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

    public function get_file_records() {
        global $DB;

        $data = $this->get_custom_data();

        $skipids = (string) implode(',', $data->processed_records);
        $wheresql = !empty($skipids) ? "WHERE f.id NOT IN ($skipids)" : '';

        $filessql = "SELECT
                f.id,
                f.filesize,
                f.contenthash,
                f.component,
                cx.id as contextid,
                cx.contextlevel,
                cx.instanceid,
                cx.path,
                cx.depth
            FROM {files} f
            INNER JOIN {context} cx
                ON cx.id=f.contextid
            $wheresql
            ORDER by cx.depth ASC, cx.path ASC";

        return $DB->get_records_sql($filessql, [], 0, (int) $data->batch_limit);
    }
}