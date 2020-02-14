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

namespace report_coursesize\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/coursesize/locallib.php');

class category_table extends flexible_table {
    protected $downloadparam = 'categorydownload';

    public function __construct($categoryid) {
        parent::__construct("report-coursesize-categories");
        $this->init($categoryid);
        $this->sortable(true, 'total');
    }

    protected function init($categoryid) {
        $this->define_columns(array('name', 'total', 'unique', 'backup'));
        $this->define_headers(array(
            get_string('category'),
            get_string('table:column_header:total', 'report_coursesize'),
            get_string('table:column_header:unique', 'report_coursesize'),
            get_string('table:column_header:backup', 'report_coursesize')
        ));
        $this->define_baseurl(new \moodle_url('/report/coursesize/index.php', array('category' => $categoryid)));
    }

    public function build_table($categories) {
        // Size counters.
        $sumtotal = $sumunique = $sumbackup = 0;

        if ($this->is_downloading()) {
            $this->column_suppress('links');
        }

        // Output courses.
        $categories = $this->sort_columns($categories);
        foreach ($categories as $category) {
            $sumtotal  += $category->total;
            $sumunique += $category->unique;
            $sumbackup += $category->backup;
            if ($category->total !== 0 || REPORT_COURSESIZE_SHOW_EMPTY_COURSES) {
                $this->add_data_keyed(
                    $this->format_row($category)
                );
            }
        }

        // Add totals.
        if (!$this->is_downloading()) {
            $this->add_data(array(
                get_string('total'),
                get_string('sizeinmb', 'report_coursesize', number_format(ceil($sumtotal / 1048576))),
                get_string('sizeinmb', 'report_coursesize', number_format(ceil($sumunique / 1048576))),
                get_string('sizeinmb', 'report_coursesize', number_format(ceil($sumbackup / 1048576))),
            ));
        }
    }

    public function col_total($row) {
        if (!isset($row->total)) {
            $row->total = 0;
        }

        $formattedbytes = \report_coursesize\util::bytes_to_megabytes($row->total);

        if ($this->is_downloading()) {
            return $formattedbytes;
        }

        $titlestring = new \stdClass();
        $titlestring->shortname = $row->name;
        $titlestring->bytes = $row->total;
        $url = \html_writer::link(
            new \moodle_url('/report/coursesize/course.php', array('id' => $row->id)),
            get_string('sizeinmb', 'report_coursesize', $formattedbytes),
            array('title' => get_string('coursebytes', 'report_coursesize', $titlestring))
        );
        return $url;
    }

    public function col_unique($row) {
        if (!isset($row->unique)) {
            $row->unique = 0;
        }

        $formattedbytes = \report_coursesize\util::bytes_to_megabytes($row->unique);

        if ($this->is_downloading()) {
            return $formattedbytes;
        }

        $titlestring = new \stdClass();
        $titlestring->shortname = $row->name;
        $titlestring->bytes = $row->unique;
        $url = \html_writer::link(
            new \moodle_url('/report/coursesize/course.php', array('id' => $row->id)),
            get_string('sizeinmb', 'report_coursesize', $formattedbytes),
            array('title' => get_string('coursebytes', 'report_coursesize', $titlestring))
        );
        return $url;
    }

    public function col_backup($row) {
        if (!isset($row->backup)) {
            $row->backup = 0;
        }

        $formattedbytes = \report_coursesize\util::bytes_to_megabytes($row->backup);

        if ($this->is_downloading()) {
            return $formattedbytes;
        }

        $titlestring = new \stdClass();
        $titlestring->shortname = $row->name;
        $titlestring->bytes = $row->backup;
        $url = \html_writer::link(
            new \moodle_url('/report/coursesize/course.php', array('id' => $row->id)),
            get_string('sizeinmb', 'report_coursesize', $formattedbytes),
            array('title' => get_string('coursebytes', 'report_coursesize', $titlestring))
        );
        return $url;
    }

    public function col_name($row) {
        if ($this->is_downloading()) {
            return $row->name;
        }
        return \html_writer::link(new \moodle_url('/course/view.php', array('id' => $row->id)), $row->name);
    }
}
