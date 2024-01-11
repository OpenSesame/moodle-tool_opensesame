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

use context_course;
use core_course_category;
use tool_opensesame\api\opensesame;
use tool_opensesame\auto_config;
use tool_opensesame\local\data\opensesame_course;
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

    /**
     * Retrieves all courses from Open Sesame and queues them for individual
     * processing by ad-hoc tasks.
     * @param opensesame $api
     */
    private function retrieve_and_process_queue_courses(opensesame $api): void {
        $page = 1;
        $pagesize = get_config('tool_opensesame', 'apicall_pagesize');
        $pagesize = $pagesize ? $pagesize : 50;
        $api->request_debug($pagesize);
        do {
            $requestdata = $api->get_course_list($pagesize, $page);

            $this->create_opensesame_entities($requestdata->data);
            // Create categories for later use.
            foreach ($requestdata->data as $datum) {
                $this->create_oscategories($datum->categories);
            }
            // Next page.
            $page++;
        } while (!empty($requestdata->paging->next));

        // Queue all entities which don't exist and are active.
        $newentities = opensesame_course::get_recordset([
            'status' => opensesame_course::STATUS_RETRIEVED,
            'active' => 1
        ]);

        $this->process_and_log_entities($newentities, $api, [
            opensesame_course::STATUS_QUEUED => true,
        ]);

        // Delete all courses that are disabled.
        $this->delete_disabled_courses();
    }

    /**
     * Creates open sesame courses for an array of open sesame data records
     * retrieved from their API.
     * @param array $records
     */
    private function create_opensesame_entities(array $records): void {
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
        }
    }

    /**
     * Processes a single Open Sesame course until all its steps are processed.
     * @param int $id
     * @param opensesame $api opensesame API object.
     *
     * @return bool true if successful.
     */
    public function process_single_os_course(int $id, opensesame $api = null): bool {
        if (is_null($api)) {
            $api = $this->api;
        }

        $oscourse = opensesame_course::get_record([
            'id' => $id
        ]);
        if (empty($oscourse)) {
            // Open sesame course has been deleted.
            return true;
        }
        return $this->process_and_log_entity($oscourse, $api);
    }

    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @return string Error message or empty
     */
    public function process_retrieved_to_queued(opensesame_course &$oscourse): string {
        process_course_task::queue_task($oscourse->id);
        return '';
    }


    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
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
     * @throws \file_exception
     */
    public function process_created_to_imageimported(opensesame_course &$oscourse, opensesame $api): string {
        $thumbnailurl = $oscourse->thumbnailurl;
        $courseid = $oscourse->courseid;
        $context = context_course::instance($courseid);
        $fileinfo = [
            'contextid' => $context->id,    // ID of the context.
            'component' => 'course',        // Your component name.
            'filearea'  => 'overviewfiles', // Usually = table name.
            'itemid'    => 0,               // Usually = ID of row in table.
            'filepath'  => '/',             // Any path beginning and ending in /.
            'filename'  => 'courseimage_' . $courseid . '.jpg',   // Any filename.
        ];
        // Create course image.
        $fs = get_file_storage();
        // Make sure there is not an image file to prevent an image file conflict.
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
        // Create a new file containing the text 'hello world'.
        $fs->create_file_from_url($fileinfo, $thumbnailurl);
        return '';
    }

    /**
     * Processes open sesame course entity.
     * @param opensesame_course $oscourse
     * @param opensesame $api
     * @return string Error message or empty
     */
    public function process_imageimported_to_scormimported(opensesame_course &$oscourse, opensesame $api): string {
        $courseid = $oscourse->courseid;
        $guid = $oscourse->idopensesame;
        $allowedtype = get_config('tool_opensesame', 'allowedtypes');

        if ($allowedtype == SCORM_TYPE_LOCAL) {
            $message = $this->get_os_scorm_package($oscourse->packagedownloadurl, $courseid, $api, $guid);
        } else { // AICC type.
            $message = $this->get_os_scorm_package($oscourse->aicclaunchurl, $courseid, $api, $guid);
        }

        return $message;
    }

    /**
     * Generates a file name for a downloaded package.
     * @param string $guid
     * @return string
     */
    private function generate_os_package_filename(string $guid): string {
        return $guid . '.zip';
    }

    /**
     * Downloads an Open Sesame Scorm package for a given URL and associates it to a SCORM activity in a course.
     * @param string $downloadurl
     * @param int $courseid
     * @param opensesame $api
     * @param string $guid
     */
    private function get_os_scorm_package(string $downloadurl, int $courseid, opensesame $api, $guid) {
        // Download file.
        $filename = $this->generate_os_package_filename($guid);
        $path = $api->download_scorm_package($downloadurl, $filename);
        // Create a file from temporary folder in the user file draft area.
        $context = context_course::instance($courseid);
        $fs = get_file_storage();
        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'mod_scorm',
            'filearea'  => 'package',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        // Clear file area.
        $fs->delete_area_files($context->id, 'mod_scorm', 'package', 0);
        // Create a new file scorm.zip package inside of course.
        $fs->create_file_from_pathname($fileinfo, $path);
        // Create a new user draft file from mod_scorm package.
        // Get an unused draft itemid which will be used.
        $draftitemid = file_get_submitted_draft_itemid('packagefile');
        // Copy the existing files which were previously uploaded into the draft area.
        file_prepare_draft_area($draftitemid, $context->id, 'mod_scorm', 'package', 0);
        get_fast_modinfo($courseid);

        return $this->create_course_scorm_mod($courseid, $draftitemid, $downloadurl);
    }

    /**
     * Creates the moduleinfo to create scorm module.
     *
     * @param int $courseid
     * @param int $draftitemid
     * @param string $downloadurl
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_course_scorm_mod(int $courseid, int $draftitemid, string $downloadurl): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/mod_form.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $instances = $modinfo->get_instances_of('scorm');
        $cmid = null;
        if (!empty($instances)) {
            if (count($instances) > 1) {
                return "Course with id {$courseid} has multiple scorm activities, please delete them.";
            }
            foreach ($instances as $id => $info) {
                $cmid = $id;
                break;
            }
        }
        // Found a course module scorm for this course update the activity.
        if (!is_null($cmid)) {
            // Check the course module exists.
            $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
            [$cm, $context, $module, $data, $cw] = get_moduleinfo_data($cm, $course);
            $data->return = 0;
            $data->sr = 0;
            $data->update = $cmid;
            $moduleinfo = $this->build_scorm_modinfo(
                $downloadurl, $courseid, $draftitemid, $module, '0', 0, $cmid, $cm->instance, $cm->id);
            update_moduleinfo($cm, $moduleinfo, $course);
        } else {
            // Create top course section.
            $add = 'scorm';
            $section = 0;
            $courseformat = course_get_format($course);
            $maxsections = $courseformat->get_max_sections();
            if ($maxsections === 0) {
                throw new \moodle_exception('maxsectionslimit', 'moodle', '', $maxsections);
            }
            [$module, $context, $cw, $cm, $data] = prepare_new_moduleinfo_data($course, $add, $section);
            $data->return = 0;
            $data->sr = 0;
            $data->add = $add;
            $moduleinfo = $this->build_scorm_modinfo(
                $downloadurl, $courseid, $draftitemid, $module, $add, $section);
            add_moduleinfo($moduleinfo, $course);
        }
        return '';
    }

    /**
     * Builds the scorm module info object.
     *
     * @param string $downloadurl
     * @param int $courseid
     * @param int $draftitemid
     * @param object $mod
     * @param string $add updating this value should be = '0' when creating new mod this value should be = 'scorm'
     * @param int $section
     * @param null|int $updt
     * @param string|null $instance
     * @param null|int $cm = $cmid when creating a new mod this value should be = NULL
     * @return \stdClass
     * @throws \dml_exception
     */
    private function build_scorm_modinfo(string $downloadurl, int $courseid, int $draftitemid, object $mod, string $add = '0',
                                         int $section = 0, int $updt = null, string $instance = null, int $cm = null
    ): \stdClass {
        global $CFG;
        $moduleinfo = new \stdClass();
        $opcourse = opensesame_course::get_record([
            'courseid' => $courseid,
        ]);
        $moduleinfo->name = self::generate_activity_name($opcourse);
        $moduleinfo->introeditor = ['text' => '',
            'format' => '1', 'itemid' => '0'];
        $moduleinfo->showdescription = 0;
        $moduleinfo->mform_isexpanded_id_packagehdr = 1;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        $moduleinfo->scormtype = get_config('tool_opensesame', 'allowedtypes');
        if ($moduleinfo->scormtype == SCORM_TYPE_AICCURL) {
            $moduleinfo->packageurl = $downloadurl;
        }
        $moduleinfo->packagefile = $draftitemid;
        // Update frequency is daily.
        $moduleinfo->updatefreq = 2;
        $moduleinfo->popup = 0;
        $moduleinfo->width = 100;
        $moduleinfo->height = 500;
        $moduleinfo->course = $courseid;
        $moduleinfo->module = $mod->id;
        $moduleinfo->modulename = $mod->name;
        $moduleinfo->visible = $mod->visible;
        $moduleinfo->add = $add;
        $moduleinfo->coursemodule = $cm;
        $moduleinfo->cmidnumber = null;
        $moduleinfo->section = $section;
        $moduleinfo->displayattemptstatus = 1;
        $moduleinfo->completionstatusrequired = COMPLETION_CRITERIA_TYPE_ACTIVITY;
        $moduleinfo->completion = COMPLETION_CRITERIA_TYPE_DATE;
        $moduleinfo->completionview = 1;
        $moduleinfo->instance = $instance;
        return $moduleinfo;
    }

    /**
     * Creates Moodle categories based on Open-Sesame course categories.
     *
     * @param array $oscategories
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
                    $data->parent = get_config('tool_opensesame', 'opsesamecategory');
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

    /**
     * Extracts the category id associated with the category string.
     * @param string $stringcategories
     * @return int The category id.
     */
    private function extract_category_id_from_os_string($stringcategories) {
        global $DB;

        $firstelement = explode(',', $stringcategories)[0]; // Select only first tree.
        $treecategory = explode('|', $firstelement); // Take tree and explode it by | symbol.
        $targetcategory = end($treecategory); // Select last grandchild category.

        return $DB->get_field('course_categories', 'id', ['name' => $targetcategory]);
    }

    /**
     * Generates a name for a course activity.
     * @param object $opcourse
     * @return string
     */
    public static function generate_activity_name($opcourse) {
        $pluginconfig = get_config('tool_opensesame');
        $activityname = $pluginconfig->activity_name;
        $activityprefix = $pluginconfig->activity_prefix;
        switch ($activityname) {
            case 'guid':
                $name = $opcourse->guid;
                break;
            case 'courseid':
                $name = $opcourse->courseid;
                break;
            case 'coursename':
                $name = $opcourse->title;
                break;
            case 'prefix':
                $name = '';
                break;
            default:
                $name = $opcourse->guid;
                break;
        }
        return !empty($activityprefix) ? $activityprefix . $name : $name;
    }

    /**
     * Deletes all disabled course from Moodle.
     * @return bool If successful.
     */
    private function delete_disabled_courses() {
        global $DB;

        $sql = 'SELECT courseid, status 
                  FROM {tool_opensesame_course}
                 WHERE active = 0
                   AND (courseid IS NOT NULL AND courseid <> 0)
                   AND status <> :status';

        $disabledcourses = $DB->get_records_sql($sql, ['status' => opensesame_course::STATUS_DELETED]);

        foreach ($disabledcourses as $disabledcourse) {
            !PHPUNIT_TEST ? mtrace('[INFO] Deleting course: ' . $disabledcourse->courseid) : false;
            // Delete course.
            if (delete_course($disabledcourse->courseid, true)) {
                $disabledcourse->courseid = 0;
                $disabledcourse->status = opensesame_course::STATUS_DELETED;
                $disabledcourse->mtrace_errors_save();
                !PHPUNIT_TEST ? mtrace('[INFO] Success delete, course: ' . $disabledcourse->courseid) : false;
            } else {
                !PHPUNIT_TEST ? mtrace('[ERROR] Error deleting course: ' . $disabledcourse->courseid) : false;
            }
        }
        return true;
    }

}
