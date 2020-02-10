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

    /**
     * Execute the task
     */
    public function execute() {
        // $cache = \cache::make('report_coursesize', 'in_progress');
        // $files = $cache->get('files');
        $data        = $this->get_custom_data();
        $data->files = json_decode(json_encode($data->files), true);
        echo ">>>> At beginning, \$files is " . gettype($data->files) . "\n";

        if (!$data->file_records_done) {
            echo "====== BLOCK 1 ===== \n";
            echo '$files is ' . gettype($data->files) . "\n";
            $filerecords = $this->get_file_records();
            if (count($filerecords) > 0) {
                $filesizer               = new \report_coursesize\file_sizer($filerecords, $data->files);
                $data->files             = $filesizer->files;
                $data->processed_records = array_merge((array) $data->processed_records, $filesizer->processed_records);
            } else {
                $data->file_records_done = true;
            }
        } else if (count((array) $data->files) !== 0) {
            echo "====== BLOCK 2 ===== \n";
            echo '$files is ' . gettype($data->files) . "\n";
            $processfiles = array_slice($data->files, 0, $data->batch_limit);
            $keepfiles    = array_slice($data->files, $data->batch_limit);
            $contextsizer = new \report_coursesize\context_sizer($processfiles);

            $data->contexts         = $contextsizer->get_contexts();
            $data->files            = $keepfiles;
            $data->systemsize       = $contextsizer->systemsize;
            $data->systembackupsize = $contextsizer->systembackupsize;
        } else {
            echo "====== BLOCK 3 ===== \n";
            \report_coursesize\results_manager::update((object) array(
                'contexts'         => $data->contexts,
                'systemsize'       => $data->systemsize,
                'systembackupsize' => $data->systembackupsize
            ));
            return;
        }

        $task = new \report_coursesize\task\build_data_task();
        $task->set_next_run_time(time() + 1);
        echo '>>>> Right before setting for next time, $files is ' . gettype($data->files) . "\n";
        $task->set_custom_data((object)
            array(
                'batch_limit'       => get_config('report_coursesize', 'batch_limit') ?? 100,
                'file_records_done' => $data->file_records_done ?? false,
                'processed_records' => $data->processed_records ?? array(),
                'files'             => $data->files ?? array(),
                'contexts'          => $data->contexts ?? new \stdClass(),
                'systemsize'        => $data->systemsize ?? 0,
                'systembackupsize'  => $data->systembackupsize ?? 0,
            )
        );
        \core\task\manager::queue_adhoc_task($task);
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