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

namespace report_coursesize\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/tablelib.php");

/**
 * Overrides base flexible_table class to enable downloading with multiple tables per page.
 *
 * @package    report_coursesize
 * @copyright  2017 Lafayette College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class flexible_table extends \flexible_table {
    /**
     * @var string The query param into which the download type will be placed.
     */
    protected $downloadparam = 'download';

    /**
     * Get the html for the download buttons.
     *
     * Overriden within report_coursesize so that we can easily set the query
     * param in subclasses.
     */
    public function download_buttons() {
        global $OUTPUT;

        if ($this->is_downloadable() && !$this->is_downloading()) {
            return $OUTPUT->download_dataformat_selector(
                        get_string('downloadas', 'table'),
                        $this->baseurl->out_omit_querystring(),
                        $this->downloadparam, // Overridden: In core this is just 'download'.
                        $this->baseurl->params());
        } else {
            return '';
        }
    }
}