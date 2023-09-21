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
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    if ($oldversion < 2023011116) {

        // Define field active to be added to tool_opensesame.
        $table = new xmldb_table('tool_opensesame');
        $field = new xmldb_field('active', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, '0', 'aicclaunchurl');

        // Conditionally launch add field active.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing type of field idopensesame on table tool_opensesame to char.
        $table = new xmldb_table('tool_opensesame');
        $field = new xmldb_field('idopensesame', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '0', 'provider');

        // Launch change of type for field idopensesame.
        $dbman->change_field_type($table, $field);

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023011116, 'tool', 'opensesame');
    }
    if ($oldversion < 2023013100) {

        // Define field courseid to be added to tool_opensesame.
        $table = new xmldb_table('tool_opensesame');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'active');

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023013100, 'tool', 'opensesame');
    }
    if ($oldversion < 2023082900) {

        // Define field status to be added to tool_opensesame.
        $table = new xmldb_table('tool_opensesame');
        $field = new xmldb_field('status', XMLDB_TYPE_TEXT, null, null, null, null, null, 'courseid');
        $field2 = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023082900, 'tool', 'opensesame');
    }

    if ($oldversion < 2023082903) {
        $DB->delete_records('tool_opensesame');

        // Define key courseid (foreign) to be added to tool_opensesame.
        $table = new xmldb_table('tool_opensesame');

        $index = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        // Conditionally launch drop index status.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Launch add key courseid.
        $dbman->add_key($table, $key);

        // Changing nullability and type of field status on table tool_opensesame to not null.
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'retrieved');

        // Launch change of nullability for field status.
        $dbman->change_field_notnull($table, $field);
        // Launch change of type for field status.
        $dbman->change_field_type($table, $field);
        // Launch change of default for field status.
        $dbman->change_field_default($table, $field);

        $index = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        // Conditionally launch add index status.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023082903, 'tool', 'opensesame');
    }

    if ($oldversion < 2023082904) {
        $DB->delete_records('tool_opensesame');

        $table = new xmldb_table('tool_opensesame');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'tool_opensesame_course');
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023082904, 'tool', 'opensesame');
    }

    if ($oldversion < 2023082905) {
        $table = new xmldb_table('tool_opensesame_course');

        $fieldtoadd = new xmldb_field('descriptionhtml', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);

        $fieldtoremove = new xmldb_field('provider', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);

        // Conditionally launch add field descriptionhtml.
        if (!$dbman->field_exists($table, $fieldtoadd)) {
            $dbman->add_field($table, $fieldtoadd);
        }

        // Conditionally launch remove field provider.
        if ($dbman->field_exists($table, $fieldtoremove)) {
            $dbman->drop_field($table, $fieldtoremove);
        }

        // Opensesame savepoint reached.
        upgrade_plugin_savepoint(true, 2023082905, 'tool', 'opensesame');
    }

    if ($oldversion < 2023082906) {
        // Define field courseid to be modified to tool_opensesame_course.
        $table = new xmldb_table('tool_opensesame_course');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null);

        // Launch change of nullability for field status.
        $dbman->change_field_notnull($table, $field);

        upgrade_plugin_savepoint(true, 2023082906, 'tool', 'opensesame');
    }

    if ($oldversion < 2023082907) {
        // Define field courseid to be modified to tool_opensesame_course.
        $table = new xmldb_table('tool_opensesame_course');
        // Changing nullability and type of field status on table tool_opensesame to not null.
        $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'retrieved');

        // Launch change of nullability for field status.
        $dbman->change_field_type($table, $field);
        $dbman->change_field_default($table, $field);

        upgrade_plugin_savepoint(true, 2023082907, 'tool', 'opensesame');
    }

    return true;
}
