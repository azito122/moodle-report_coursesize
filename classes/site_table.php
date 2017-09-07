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

require_once($CFG->libdir . "/tablelib.php");

class site_table extends \flexible_table {
    public function __construct($coursecategory) {
        parent::__construct("report-coursesize-category-{$coursecategory}");
        $this->init($coursecategory);
    }

    protected function init($coursecategory) {
        $this->define_columns(array('shortname', 'bytesused', 'backupbytesused'));
        $this->define_headers(array(
            get_string('course'),
            get_string('diskusage', 'report_coursesize'),
            get_string('backupsize', 'report_coursesize')
        ));
        $this->define_baseurl(new \moodle_url('/report/coursesize/index.php', array('category' => $coursecategory)));
    }

    public function build_table($summary) {
        // Size counters.
        $totalsize = $totalbackupsize = 0;

        if ($this->is_downloading()) {
            $this->column_suppress('links');
        }

        // Output courses with results.
        foreach ($summary['nonzero'] as $courseid => $course) {
            $totalsize += $course->bytesused;
            $totalbackupsize += $course->backupbytesused;
            $this->add_data_keyed(
                $this->format_row($course)
            );
        }

        // Optionally output courses without results.
        if (REPORT_COURSESIZE_SHOWEMPTYCOURSES) {
            foreach ($summary['zero'] as $courseid => $course) {
                $course->bytesused = 0;
                $course->backupbytesused = 0;
                $this->add_data_keyed(
                    $this->format_row($course)
                );
            }
        }

        // Add totals.
        if (!$this->is_downloading()) {
            $this->add_data(array(
                get_string('total'),
                get_string('sizeinmb', 'report_coursesize', number_format(ceil($totalsize / 1048576))),
                get_string('sizeinmb', 'report_coursesize', number_format(ceil($totalbackupsize / 1048576))),
            ));
        }
    }

    public function col_bytesused($row) {
        if (!isset($row->bytesused)) {
            $row->bytesused = 0;
        }
        $formattedbytes = number_format(ceil($row->bytesused / 1048576));
        if ($this->is_downloading()) {
            return $formattedbytes;
        }
        $titlestring = new \stdClass();
        $titlestring->shortname = $row->shortname;
        $titlestring->bytes = $row->bytesused;
        $url = \html_writer::link(
            new \moodle_url('/report/coursesize/course.php', array('id' => $row->id)),
            get_string('sizeinmb', 'report_coursesize', $formattedbytes),
            array('title' => get_string('coursebytes', 'report_coursesize', $titlestring))
        );
        return $url;
    }

    public function col_backupbytesused($row) {
        if (!isset($row->backupbytesused)) {
            $row->backupbytesused = 0;
        }
        $formattedbytes = number_format(ceil($row->backupbytesused / 1048576));
        if ($this->is_downloading()) {
            return $formattedbytes;
        }
        $titlestring = new \stdClass();
        $titlestring->shortname = $row->shortname;
        $titlestring->backupbytes = $row->backupbytesused;
        $url = \html_writer::tag(
            'span',
            get_string('sizeinmb', 'report_coursesize', $formattedbytes),
            array('title' => get_string('coursebackupbytes', 'report_coursesize', $titlestring))
        );
        return $url;
    }

    public function col_shortname($row) {
        if ($this->is_downloading()) {
            return $row->shortname;
        }
        return \html_writer::link(new \moodle_url('/course/view.php', array('id' => $row->id)), $row->shortname);
    }

    public function build($summary) {

    }
}
