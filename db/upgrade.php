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
 * Calculated question type with number formatting upgrade code.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Upgrade code for the calculated question type with number formatting.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qtype_calculatedformat_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this.

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this.

    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this.

    // Moodle v2.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2014102100) {
        // Define field correctanswershowbase to be added to qtype_calculatedfmt_opts.
        $table = new xmldb_table('qtype_calculatedfmt_opts');
        $field = new xmldb_field('correctanswershowbase', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '2', 'correctanswergroupdigits');

        // Conditionally launch add field correctanswershowbase.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Calculatedformat savepoint reached.
        upgrade_plugin_savepoint(true, 2014102100, 'qtype', 'calculatedformat');
    }

    return true;
}


