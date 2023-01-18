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
 * Upgrade script for tool_opensesame.
 *
 * @package    tool_opensesame
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool always true
 */
function xmldb_tool_opensesame_upgrade(int $oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023011115) {

        // Define table tool_opensesame to be created.
        $table = new xmldb_table('tool_opensesame');

        // Adding fields to table tool_opensesame.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('provider', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'OpenSesame');
        $table->add_field('idopensesame', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'Default Title');
        $table->add_field('descriptiontext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('thumbnailurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '000000');
        $table->add_field('languages', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('oscategories', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('publishername', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('packagedownloadurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('aicclaunchurl', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table tool_opensesame.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_opensesame.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023011115, 'tool', 'opensesame');
    }

    return true;
}
