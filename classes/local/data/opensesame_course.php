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
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame\local\data;

use core\persistent;
use core_user;
use lang_string;

defined('MOODLE_INTERNAL') || die();

/**
 * Opensesame Persistent Entity Class.
 *
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    tool_opensesame
 * @property   string $idopensesame
 * @property   string $title
 * @property   string $descriptiontext
 * @property   string $descriptionhtml
 * @property   string $thumbnailurl
 * @property   string $duration
 * @property   string $languages
 * @property   string $oscategories
 * @property   string $publishername
 * @property   string $packagedownloadurl
 * @property   string $aicclaunchurl
 * @property   bool $active
 * @property   int $courseid
 * @property   string $status
 */
class opensesame_course extends base {
    /** Table name for the persistent. */
    const TABLE = 'tool_opensesame_course';

    /** @var string */
    const STATUS_RETRIEVED = 'retrieved';

    /** @var string */
    const STATUS_QUEUED = 'queued';

    /** @var string */
    const STATUS_CREATED = 'created';

    /** @var string */
    const STATUS_IMAGE_IMPORTED = 'imageimported';

    /** @var string */
    const STATUS_SCORM_IMPORTED = 'scormimported';

    protected static $steps = [
        self::STATUS_RETRIEVED,
        self::STATUS_QUEUED,
        self::STATUS_CREATED,
        self::STATUS_IMAGE_IMPORTED,
        self::STATUS_SCORM_IMPORTED,
    ];

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return array(
            'idopensesame' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'title' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'descriptiontext' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'descriptionhtml' => [
                'type' => PARAM_CLEANHTML,
                'null' => NULL_NOT_ALLOWED,
            ],
            'thumbnailurl' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'duration' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'languages' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'oscategories' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'publishername' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'packagedownloadurl' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'aicclaunchurl' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'active' => [
                'type' => PARAM_RAW,
                'null' => NULL_NOT_ALLOWED,
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
            'status' => [
                'type' => PARAM_ALPHANUMEXT,
                'null' => NULL_NOT_ALLOWED,
                'default' => self::STATUS_RETRIEVED,
                'choices' => static::$steps,
            ],
        );
    }
}
