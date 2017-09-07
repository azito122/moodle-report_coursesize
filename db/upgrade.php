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
 * Upgrade functions.
 *
 * @package    report_coursesize
 * @copyright  2017 Lafayette College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_report_coursesize_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2017090600) {
        // Settings deprecated in favor of the Cache API.
        unset_config('filessize', 'report_coursesize');
        unset_config('filessizeupdated', 'report_coursesize');
        upgrade_plugin_savepoint(true, 2017090600, 'report', 'coursesize');
    }

    return true;
}
