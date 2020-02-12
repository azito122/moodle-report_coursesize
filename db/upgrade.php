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
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2017090600) {
        // Settings deprecated in favor of the Cache API.
        unset_config('filessize', 'report_coursesize');
        unset_config('filessizeupdated', 'report_coursesize');
        upgrade_plugin_savepoint(true, 2017090600, 'report', 'coursesize');
    }

    if ($oldversion < 2019052800) {

        // Define table report_coursesize_files to be created.
        $table = new xmldb_table('report_coursesize_files');

        // Adding fields to table report_coursesize_files.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courses', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('categories', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('users', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table report_coursesize_files.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for report_coursesize_files.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursesize savepoint reached.
        upgrade_plugin_savepoint(true, 2019052800, 'report', 'coursesize');
    }

    return true;
}
