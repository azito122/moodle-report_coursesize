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

class user_table extends flexible_table {
    protected $downloadparam = 'userdownload';

    public function __construct() {
        parent::__construct("report-coursesize-users");
        $this->init();
    }

    protected function init() {
        $this->define_columns(array('fullname', 'bytesused'));
        $this->define_headers(
            array(
                get_string('user'),
                get_string('diskusage', 'report_coursesize')
            )
        );
        $this->define_baseurl(new \moodle_url('/report/coursesize/user.php'));
    }

    public function build_table($users) {
        foreach ($users as $userid => $user) {
            $this->add_data_keyed(
                $this->format_row($user)
            );
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
        return get_string('sizeinmb', 'report_coursesize', $formattedbytes);
    }

    public function col_fullname($row) {
        if ($this->is_downloading()) {
            return $row->fullname;
        }
        return \html_writer::link(new \moodle_url('user/view.php', array('id' => $row->id)), $row->fullname);
    }
}
