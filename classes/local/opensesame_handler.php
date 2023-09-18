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
 * Open sesame process handler.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame\local;

use tool_opensesame\api\opensesame;
use tool_opensesame\local\data\opensesame_course;
use tool_opensesame\local\migration_handler;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/externallib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Open sesame process handler.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opensesame_handler extends migration_handler {

    /** @var array */
    const REMOTE_COURSE_TO_OS_COURSE_MAPPINGS = [
        'id' => 'idopensesame',
        'title' => 'title',
        'descriptionText' => 'descriptiontext',
        'descriptionHtml' => 'descriptionhtml',
        'thumbnailUrl' => 'thumbnailurl',
        'duration' => 'duration',
        'languages' => 'languages',
        'categories' => 'oscategories',
        'publisherName' => 'publishername',
        'packageDownloadUrl' => 'packagedownloadurl',
        'aiccLaunchUrl' => 'aicclaunchurl',
        'active' => 'active',
    ];

    /** @var array */
    const REMOTE_COURSE_TRANSFORMS = [
        'categories' => self::TRANSFORM_COMMA_IMPLODE,
        'languages' => self::TRANSFORM_EXTRACT_FIRST,
    ];

    /**
     * Open Sesame API.
     *
     * @var opensesame
     */
    private $api;

    /**
     * Constructor method.
     *
     * @param mixed $authurl
     * @param mixed $clientid
     * @param mixed $clientsecret
     * @param mixed $baseurl
     * @param mixed $customerintegrationid
     */
    public function __construct(
        $authurl = null, $clientid = null, $clientsecret = null, $baseurl = null, $customerintegrationid = null
    ) {
        $authurl = $authurl ?? get_config('tool_opensesame', 'authurl');
        $clientid = $clientid ?? get_config('tool_opensesame', 'clientid');
        $clientsecret = $clientsecret ?? get_config('tool_opensesame', 'clientsecret');
        $baseurl = $baseurl ?? get_config('tool_opensesame', 'baseurl');
        $customerintegrationid = $customerintegrationid ?? get_config('tool_opensesame', 'customerintegrationid');

        if (empty($authurl) || empty($clientid) || empty($clientsecret) || empty($baseurl) || empty($customerintegrationid)) {
            throw new \moodle_exception('configerror', 'tool_opensesame');
        }

        $this->api = new opensesame($authurl, $clientid, $clientsecret, $customerintegrationid, $baseurl);
    }

    /**
     * Executes the data processing functions.
     *
     * @param opensesame $api opensesame API object.
     */
    public function run(opensesame $api = null) {
        if (is_null($api)) {
            $api = $this->api;
        }
        $this->retrieve_and_process_opensesame_courses($api);
    }

    private function retrieve_and_process_opensesame_courses(opensesame $api) {
        $nexturl = '';
        $pagesize = 50;
        do {
            $requestdata = $api->get_course_list($pagesize, $nexturl);
            $this->create_opensesame_entities($requestdata->data);
            $nexturl = $requestdata->paging->next;
        } while (!empty($nexturl));

        // Queue all entities which don't exist.
        $this->process_and_log_entities(opensesame_course::get_recordset([
            'status' => opensesame_course::STATUS_RETRIEVED,
        ]), $api, [
            opensesame_course::STATUS_QUEUED,
        ]);
    }

    private function create_opensesame_entities($records) {
        $entities = [];
        foreach ($records as $record) {
            $existingoscourse = opensesame_course::get_record([
                'idopensesame' => $record->id,
            ]);
            $id = $existingoscourse !== false ? $existingoscourse->id : 0;
            $oscourse = new opensesame_course($id);
            $this->process_mappings(
                $oscourse,
                $record,
                self::REMOTE_COURSE_TO_OS_COURSE_MAPPINGS,
                self::REMOTE_COURSE_TRANSFORMS);
            $oscourse->mtrace_errors_save();
            $entities[] = $oscourse;
        }
        return $entities;
    }

    /**
     * Processes retrieved open sesame courses to queue them in adhoc tasks.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    protected function process_retrieved_to_queued(opensesame_course &$oscourse, opensesame $api): string {
        return 'Not implemented';
    }
}
