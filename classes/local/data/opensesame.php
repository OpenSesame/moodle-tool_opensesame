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
 * Opensesame Persistent Class.
 *
 * This class defines the persistent entity for the 'tool_opensesame' table in Moodle.
 * It encapsulates the logic for interacting with the table's data.
 *
 * @package    tool_opensesame
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame\local\data;

use core\persistent;
use core_user;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Opensesame Persistent Entity Class.
 */
class opensesame extends base {
    /** Table name for the persistent. */
    const TABLE = 'tool_opensesame';

    /** @var string */
    const STATUS_COURSE_CREATED = 'course created';

    /** @var string */
    const STATUS_COURSE_RETRIEVED = 'course created';

    /** @var string */
    const STATUS_COURSE_PENDING = 'course pending';


    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
                'provider' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => 'OpenSesame'
                ],
                'idopensesame' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => '0'
                ],
                'title' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => 'Default Title' // This is the same in the 'install.xml' file if altered changes need to be updated in both places.
                ],
                'descriptiontext' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => ''
                ],
                'thumbnailurl' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_ALLOWED,
                ],
                'duration' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => '000000'
                ],
                'languages' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => '' // TODO: Check response to see if multiple languages exist for some courses the database has others other than English.
                ],
                'oscategories' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_ALLOWED,
                ],
                'publishername' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_ALLOWED,
                ],
                'packagedownloadurl' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_ALLOWED,
                ],
                'aicclaunchurl' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_ALLOWED
                ],
                'active' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => '0'
                ],
                'courseid' => [
                        'type' => PARAM_INT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => '0'
                ],
                'status' => [
                        'type' => PARAM_TEXT,
                        'null' => NULL_NOT_ALLOWED,
                        'default' => self::STATUS_COURSE_PENDING,
                        'choices' => [
                                self::STATUS_COURSE_PENDING,
                                self::STATUS_COURSE_CREATED,
                                self::STATUS_COURSE_RETRIEVED
                        ]
                ],
        );
    }

    // Custom method to retrieve courses by status
    public static function get_courses_by_status($status) {
        global $DB;
        return $DB->get_records('tool_opensesame', ['status' => $status]);
    }

}
