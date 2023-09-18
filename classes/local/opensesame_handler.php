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

use core_course_category;
use tool_opensesame\api\opensesame;
use tool_opensesame\local\data\opensesame_course;
use tool_opensesame\local\migration_handler;
use tool_opensesame\task\process_course_task;

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

    /** @var array */
    const OS_COURSE_TO_MOODLE_COURSE_MAPPINGS = [
        'title' => ['shortname', 'fullname'],
        'idopensesame' => 'idnumber',
    ];

    /** @var array */
    const MOODLE_COURSE_DEFAULTS = [
        'tags' => ['open-sesame'],
        'enablecompletion' => 1,
        'completionnotify' => 1,
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
        $this->retrieve_and_process_queue_courses($api);
    }

    private function retrieve_and_process_queue_courses(opensesame $api) {
        $nexturl = '';
        $pagesize = 50;
        do {
            $requestdata = $api->get_course_list($pagesize, $nexturl);
            $this->create_opensesame_entities($requestdata->data);
            // Create categories for later use.
            foreach ($requestdata->data as $datum) {
                $this->create_oscategories($datum->categories);
            }
            // Next page.
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
     * Processes a single Open Sesame course until all its steps are processed.s
     */
    public function process_single_os_course($id): bool {
        $oscourse = opensesame_course::get_record([
            'id' => $id
        ]);
        return $this->process_and_log_entity($oscourse, $this->api);
    }

    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_retrieved_to_queued(opensesame_course &$oscourse, opensesame $api): string {
        process_course_task::queue_task($oscourse->id);
        return '';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_queued_to_created(opensesame_course &$oscourse, opensesame $api): string {
        global $DB;
        $coursedata = (object) self::MOODLE_COURSE_DEFAULTS;
        $this->process_mappings($coursedata, $oscourse, self::OS_COURSE_TO_MOODLE_COURSE_MAPPINGS);
        // Remaining, non-mappable data.
        $coursedata->summary = "<p>{$oscourse->descriptiontext}</p>";
        $coursedata->summaryformat = FORMAT_HTML;
        if (!empty($oscourse->descriptionhtml)) {
            $coursedata->summary = $oscourse->descriptionhtml;
        }
        $coursedata->summary = $oscourse->descriptiontext;
        $coursedata->summary .= "<br>Publisher Name: {$oscourse->publishername}<br>Duration: {$oscourse->duration}";
        $coursedata->category = $this->extract_category_id_from_os_string(
            $oscourse->oscategories
        );
        $courseid = $DB->get_field('course', 'id', ['idnumber' => $coursedata->idnumber]);
        if (empty($courseid)) {
            $course = create_course($coursedata);
            $courseid = $course->id;
        } else {
            $coursedata->id = $courseid;
            update_course($coursedata);
        }
        $oscourse->courseid = $courseid;
        return '';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_created_to_imageretrieved(opensesame_course &$oscourse, opensesame $api): string {
        return 'Not implemented';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_imageretrieved_to_imageimported(opensesame_course &$oscourse, opensesame $api): string {
        return 'Not implemented';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_imageimported_to_scormretrieved(opensesame_course &$oscourse, opensesame $api): string {
        return 'Not implemented';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_scormretrieved_to_scormimported(opensesame_course &$oscourse, opensesame $api): string {
        return 'Not implemented';
    }

    /**
     * Creates Moodle categories based on Open-Sesame course categories.
     *
     * @param object $osrecord
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_oscategories(array $oscategories): void {
        global $DB;

        foreach ($oscategories as $value) {
            $values = explode('|', $value);
            $values = array_values(array_filter($values));

            foreach ($values as $vkey => $vvalue) {
                $catexist =
                        $DB->record_exists('course_categories', ['name' => $vvalue]);

                if ($vkey === 0 && $catexist !== true) {
                    $data = new \stdClass();
                    $data->name = $vvalue;
                    core_course_category::create($data);
                }

                if ($vkey !== 0 && $catexist !== true) {
                    $data = new \stdClass();
                    $data->name = $vvalue;
                    $name = $values[$vkey - 1];
                    $parentid = $DB->get_field('course_categories', 'id', ['name' => $name]);
                    $data->parent = $parentid;
                    core_course_category::create($data);
                }
            }
        }
        \context_helper::build_all_paths();
    }

    private function extract_category_id_from_os_string($stringcategories) {
        global $DB;
        // PHP compare  each array elements choose the element that has the most items in it.
        $result = [];
        $string = $stringcategories;

        $firstdimension = explode(',', $string); // Divide by , symbol.
        foreach ($firstdimension as $temp) {
            // Take each result of division and explode it by , symbol and save to result.
            $pos = strpos($temp, '|');
            if ($pos !== false) {
                $newtemp = substr_replace($temp, '', $pos, strlen('|'));
                $newtemp = trim($newtemp);
            }

            $result[] = explode('|', $newtemp);
        }
        $targetcategory = '';

        foreach ($result as $r) {
            $maxcount = 0;
            $items = count($r);
            if ($items > $maxcount) {
                $targetcategory = $r[$items - 1];
            }
        }

        return $DB->get_field('course_categories', 'id', ['name' => $targetcategory]);
    }
}
