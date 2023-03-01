<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * API for OpenSesame
 *
 * @package     tool_opensesame
 * @copyright   2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame;

use context_course;
use core_course_category;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/lib/filelib.php');
/**
 * The api class.
 *
 * Prepares a scheduled task to run every 24/h importing Open-Sesame Courses.
 *
 * @copyright 2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api extends \curl {

    /** @var string the api token */
    private $token;
    /** @var string the api baseurl */
    private $baseurl;

    /**
     * Constructor .
     *
     * @param array $settings additional curl settings.
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($settings = array()) {
        parent::__construct($settings);

        $this->bearertoken = get_config('tool_opensesame', 'bearertoken');

        $this->baseurl = get_config('tool_opensesame', 'baseurl');

        // If the admin omitted the protocol part, add the HTTPS protocol on-the-fly.
        if (!preg_match('/^https?:\/\//', $this->baseurl)) {
            $this->baseurl = 'https://' . $this->baseurl;
        }

        if (empty($this->baseurl)) {
            throw new \moodle_exception('apiurlempty', 'tool_opensesame');
        }

    }

    /**
     * Get http status code
     *
     * @return int|boolean status code or false if not available.
     */
    public function get_http_code() {
        mtrace('Calling get_http_code.');
        $info = $this->get_info();
        if (!isset($info['http_code'])) {
            return false;
        }
        mtrace('returning status code ' . $info['http_code']);
        return $info['http_code'];
    }
    /**
     * Get authenticate  API Credentialing.
     *
     * @return token if authenticated.
     */
    public function authenticate(): token {
        mtrace('Authenticating.');
        $authurl = get_config('tool_opensesame', 'authurl');
        $clientid = get_config('tool_opensesame', 'clientid');
        $clientsecret = get_config('tool_opensesame', 'clientsecret');

        $this->setHeader([
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret)))
        ]);

        $response = $this->post($authurl, 'grant_type=client_credentials&scope=content'
        );

        $decoded = json_decode($response);
        $token = $decoded->access_token;
        set_config('bearertoken', $token, 'tool_opensesame');
        set_config('bearertokencreatetime', time(), 'tool_opensesame');
        $createtime = get_config('tool_opensesame', 'bearertokencreatetime');
        set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
        return $this->get_auth_token();
    }

    /**
     * get_auth_token validates token not expired, if expired, creates a new one.
     *
     * @return false|mixed|object|string|null $token
     * @throws \dml_exception
     */
    public function get_auth_token() {
        mtrace('get_auth_token called');
        $token = get_config('tool_opensesame', 'bearertoken');
        $expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
        $now = time();

        if ($token === '' || $now >= $expiretime) {
            mtrace('Token either does not exist or is expired. Token is being created');
            $token = $this->authenticate();
            return $token;

        } else if ($token !== '' && $now <= $expiretime) {
            mtrace('Token is valid.');
            // Define url for the next function.
            $url = get_config('tool_opensesame', 'baseurl') . '/v1/content?customerIntegrationId=' .
                    get_config('tool_opensesame', 'customerintegrationid') . '&limit=10';
            $this->get_open_sesame_course_list($token, $url);

            return $token;
        }
    }


    /**
     * add_open_sesame_course Adds an open sesame course to moodle.
     *
     * @param object $osdataobject
     * @param string $token
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function add_open_sesame_course(object $osdataobject, string $token): void {
        mtrace('calling add_open_sesame_course');
        global $DB;

        mtrace('Creating Course: ' . $osdataobject->title);
        $data = new \stdClass();

        $data->fullname = $osdataobject->title;
        $data->shortname = $osdataobject->title;
        $data->idnumber = $osdataobject->idopensesame;
        $data->summary = $osdataobject->descriptiontext;
        $data->timecreated = time();

        $stringcategories = $osdataobject->oscategories;

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
                $maxcount = $items;
                $targetcategory = $r[$items - 1];
            }
        }

        $data->category = $DB->get_field('course_categories', 'id', ['name' => $targetcategory]);
        $data->summary .= ' Publisher Name: ' . $osdataobject->publishername . ' Duration: ' . $osdataobject->duration;
        $data->tags = ['open-sesame'];
        $data->enablecompletion = 1;
        $data->completionnotify = 1;
        $coursexist = $DB->record_exists('course', ['idnumber' => $osdataobject->idopensesame]);
        if ($coursexist !== true) {
            $course = create_course($data);
        }
        if ($coursexist == true) {
            mtrace('Course already exist in Moodle Updating Course: ' . $osdataobject->title);
            $course = $DB->get_record('course', array('idnumber' => $osdataobject->idopensesame), '*', MUST_EXIST);
            $data->id = $course->id;
            update_course($data);
        }
        $courseid = $course->id;
        mtrace('courseid ' . $courseid);
        $this->update_osdataobject($courseid, $osdataobject->idopensesame);
        $aicclaunchurl = $this->get_aicc_url($courseid);
        $thumbnailurl = $osdataobject->thumbnailurl;
        $this->create_course_image($courseid, $thumbnailurl);
        $scormpackagedownloadurl = $osdataobject->packagedownloadurl;
        $allowedtype = get_config('tool_opensesame', 'allowedtypes');
        if ($allowedtype == SCORM_TYPE_LOCAL) {
            $this->get_open_sesame_scorm_package($token, $scormpackagedownloadurl, $courseid);
        }
        if ($allowedtype == SCORM_TYPE_AICCURL) {
            $this->get_open_sesame_scorm_package($token, $aicclaunchurl, $courseid);
        }

        $active = $this->os_is_active($osdataobject->idopensesame, $courseid);
        $this->set_self_enrollment($courseid, $active);

    }

    /**
     * Defines the next page url in api.
     *
     * @param $paging
     * @return false|void
     */
    public function determineurl(&$paging){
        foreach ($paging as $key => $url) {
            if ($key == 'next' && !empty($url)) {
                mtrace($key . ' page url' . $url);
                return $url;
            } else {
                return false;
            }
            return false;
        }
    }

    /**
     * get_open_sesame_course_list
     *
     * @param $token
     * @param $url
     * Does not validate the token, the token should be valid
     * Gets a list of courses and processes them using
     * add_open_sesame_course
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function get_open_sesame_course_list($token, $url) {
        global $DB;
        // Integrator issues request with access token.
        $this->setHeader(['content_type: application/json', sprintf('Authorization: Bearer %s', $token)]);
        $response = $this->get($url);
        $statuscode = $this->get_http_code();
        $dcoded = json_decode($response);

        if ($statuscode === 400) {
            mtrace('OpenSesame Course list Statuscode: ' . $statuscode);
            throw new \moodle_exception('statuscode400', 'tool_opensesame');
        }
        if ($statuscode === 200) {
            mtrace('OpenSesame Course list Statuscode: ' . $statuscode);
            $paging = $dcoded->paging;
            $data = $dcoded->data;

            foreach ($data as $key => $osrecord) {

                $this->create_oscategories($osrecord);
                $keyexist =
                        $DB->record_exists('tool_opensesame', ['idopensesame' => $osrecord->id]);
                $osdataobject = new \stdClass();
                $osdataobject->idopensesame = $osrecord->id;
                $osdataobject->provider = 'OpenSesame';
                $osdataobject->active = $osrecord->active;
                $osdataobject->title = $osrecord->title;
                $osdataobject->descriptiontext =
                        $osrecord->descriptionHtml ? $osrecord->descriptionText : $osrecord->descriptionHtml;
                $osdataobject->thumbnailurl = $osrecord->thumbnailUrl;
                $osdataobject->duration = $osrecord->duration;
                $osdataobject->languages = $osrecord->languages[0];
                $osdataobject->oscategories = implode(', ', $osrecord->categories);
                $osdataobject->publishername = $osrecord->publisherName;
                $osdataobject->packagedownloadurl = $osrecord->packageDownloadUrl;
                $osdataobject->aicclaunchurl = $osrecord->aiccLaunchUrl;

                if ($keyexist !== true) {
                    $returnid = $DB->insert_record('tool_opensesame', $osdataobject);
                    mtrace('inserting Open Sesame course ' . $osrecord->title . ' metadata. tool_opensesame id: ' . $returnid);
                }
                $this->add_open_sesame_course($osdataobject, $token);
            }
            $nexturl = $this->determineurl($paging);
            mtrace('nexturl: ' . $nexturl);
            if ($nexturl) {
                $this->get_open_sesame_course_list($token, $nexturl);
            }

        }
    }

    /**
     *  Creates a course image based on the thumbnail url.
     * @param $courseid
     * @param $thumbnailurl
     * @return void
     * @throws \file_exception
     */
    public function create_course_image($courseid, $thumbnailurl): void {
        mtrace('Calling create_course_image');
        $context = context_course::instance($courseid);
        $fileinfo = [
                'contextid' => $context->id,   // ID of the context.
                'component' => 'course', // Your component name.
                'filearea' => 'overviewfiles',       // Usually = table name.
                'itemid' => 0,              // Usually = ID of row in table.
                'filepath' => '/',            // Any path beginning and ending in /.
                'filename' => 'courseimage' . $courseid . '.jpg',   // Any filename.
        ];
        // Create course image.
        $fs = get_file_storage();
        // Make sure there is not an image file to prevent an image file conflict.
        $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
        // Create a new file containing the text 'hello world'.
        $fs->create_file_from_url($fileinfo, $thumbnailurl);
    }

    /**
     * Creates package in Moodle file system to support scorm creation
     * @param $token
     * @param $scormpackagedownloadurl
     * @param $courseid
     * @return void
     * @throws \dml_exception
     * @throws \file_exception
     * @throws \moodle_exception
     * @throws \stored_file_creation_exception
     */
    public function get_open_sesame_scorm_package($token, $scormpackagedownloadurl, $courseid = null) {
        mtrace('calling get_open_sesame_scorm_package');
        global $CFG, $USER;
        require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');
        // Integrator issues request with access token.
        $this->setHeader([sprintf('Authorization: Bearer %s', $token)]);

        $url = $scormpackagedownloadurl . '?standard=scorm';

        $headers = $this->header;

        $filename = 'scorm_' . $courseid . '.zip';
        $path = $CFG->tempdir . '/filestorage/' . $filename;
        // Download to temp directory.
        download_file_content($url, $headers, null, true, 300, 20, false, $path, false);
        // Create a file from temporary folder in the user file draft area.
        $context = context_course::instance($courseid);

        $fs = get_file_storage();
        $fileinfo = [
                'contextid' => $context->id,   // ID of the context.
                'component' => 'mod_scorm', // Your component name.
                'filearea' => 'package',       // Usually = table name.
                'itemid' => 0,              // Usually = ID of row in table.
                'filepath' => '/',            // Any path beginning and ending in /.
                'filename' => $filename,   // Any filename.
        ];
        // Clear file area.
        $fs->delete_area_files($context->id, 'mod_scorm', 'package', 0);
        // Create a new file scorm.zip package inside of course.
        $fs->create_file_from_pathname($fileinfo, $path);

        // Create a new user draft file from mod_scorm package.
        // Get an unused draft itemid which will be used.
        $draftitemid = file_get_submitted_draft_itemid('packagefile');
        // Copy the existing files which were previously uploaded into the draft area.
        file_prepare_draft_area(
                $draftitemid, $context->id, 'mod_scorm', 'package', 0);
        $modinfo = get_fast_modinfo($courseid);

        $this->create_course_scorm_mod($courseid, $draftitemid);

    }

    /**
     * Creates the moduleinfo to create scorm module.
     *
     * @param $courseid
     * @param $draftitemid
     * @throws \moodle_exception
     * @throws \dml_exception
     */
    public function create_course_scorm_mod($courseid, $draftitemid): void {
        mtrace('calling create_course_scorm_mod');
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/mod_form.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        // Get course.
        $course = $DB->get_record('course', ['id' => $courseid]);
        // Check course for modules.

        $modscorm = 19;

        $cmid = $DB->get_field(
                $table = 'course_modules',
                'id',
                ['course' => $courseid, 'module' => $modscorm],
                $strictness = IGNORE_MISSING
        );
        // Found a course module scorm for this course update the activity.
        if ($cmid && $cmid !== null) {
            $update = $cmid;
            // Check the course module exists.
            $cm = get_coursemodule_from_id('', $update, 0, false, MUST_EXIST);
            // Check the course exists.
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

            $return = 0;
            $sectionreturn = 0;

            list($cm, $context, $module, $data, $cw) = get_moduleinfo_data($cm, $course);

            $data->return = $return;
            $data->sr = $sectionreturn;
            $data->update = $update;

            $moduleinfo = $this->get_default_modinfo($courseid, $draftitemid, $module, '0', $sectionreturn, $update,
                    $cm->instance, $cm->id);

            mtrace('preparing course scorm mod');
            // Below returns an array of $cm , $moduleinfo.
            update_moduleinfo($cm, $moduleinfo, $course);

        }
        // Only add a course module if none exist.
        if (!$cmid) {
            // Create top course section.
            $section = 0;
            $sectionreturn = 0;
            $add = 'scorm';
            $courseformat = course_get_format($course);

            $maxsections = $courseformat->get_max_sections();
            if ($section > $maxsections) {
                throw new \moodle_exception('maxsectionslimit', 'moodle', '', $maxsections);
            }
            list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $add, $section);
            $data->return = 0;
            $data->sr = $sectionreturn;
            $data->add = $add;
            $moduleinfo = $this->get_default_modinfo($courseid, $draftitemid, $module, $add, $section);
            add_moduleinfo($moduleinfo, $course);
            mtrace('added course module ');
        }

    }

    /**
     * Stores the default moduleinfo.
     * @param $courseid
     * @param $draftitemid
     * @param $module
     * @param string $add
     * updating this value should be = '0' when creating new mod this value should be = 'scorm'
     * @param int $section
     * @param null $update
     * @param null $instance
     * @param null $coursemodule
     * updating this value should be = $cmid when creating a new mod this value should be = NULL
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_default_modinfo($courseid, $draftitemid, $module, $add = '0', int $section = 0, $update = null, $instance
    = null, $coursemodule = null): \stdClass {
        global $CFG;
        $moduleinfo = new \stdClass();

        $moduleinfo->name = 'scorm_' . $courseid;
        $moduleinfo->introeditor = ['text' => '',
                'format' => '1', 'itemid' => ''];
        $moduleinfo->showdescription = 0;
        $moduleinfo->mform_isexpanded_id_packagehdr = 1;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        // Change scorm type depending on setting default is SCORM_TYPE_LOCAL alternative option is SCORM_TYPE_AICCURL.

        $moduleinfo->scormtype = get_config('tool_opensesame', 'allowedtypes');

        if ($moduleinfo->scormtype === SCORM_TYPE_AICCURL ) {
            $moduleinfo->packageurl = $this->get_aicc_url($courseid);

        }
        $moduleinfo->packagefile = $draftitemid;
        // Update frequency is daily.
        $moduleinfo->updatefreq = 2;
        $moduleinfo->popup = 0;
        $moduleinfo->width = 100;
        $moduleinfo->height = 500;
        $moduleinfo->course = $courseid;
        $moduleinfo->module = $module->id;
        $moduleinfo->modulename = $module->name;
        $moduleinfo->visible = $module->visible;
        $moduleinfo->add = $add;
        $moduleinfo->coursemodule = $coursemodule;
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
     * Establishes a relationship tool_opensesame with moodle table course.
     *
     * @param $courseid
     * @param $osdataobjectid
     * @return void
     * @throws \dml_exception
     */
    public function update_osdataobject($courseid, $osdataobjectid): void {
        mtrace('calling update_osdataobject');
        global $DB;
        $DB->set_field('tool_opensesame', 'courseid', $courseid, ['idopensesame' => $osdataobjectid]);
    }

    /**
     * Determines if the Open-Sesame Course is Active based on API flag.
     * @param $osdataobjectid
     * @param $courseid
     * @return false|mixed
     * @throws \dml_exception
     */
    public function os_is_active($osdataobjectid, $courseid) {
        mtrace('calling os_is_active');
        global $DB;
        $active = $DB->get_field('tool_opensesame', 'active', ['id' => $osdataobjectid, 'courseid' => $courseid]);
        return $active;
    }

    /**
     * AICC launch Url for Scorm Activity: TODO: modify with proper credentialing
     * @param $courseid
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_aicc_url($courseid) {
        mtrace('calling get_aicc_url');
        global $DB;
        $url = $DB->get_field('tool_opensesame', 'aicclaunchurl', ['courseid' => $courseid], MUST_EXIST);
        mtrace('$courseid: ' . $courseid . ' $url: ' . $url);
        return $url;
    }

    /**
     * Sets the enrollment methods for each Open-Sesame course
     * @param $courseid
     * @param $active
     * @return void
     * @throws \dml_exception
     */
    public function set_self_enrollment($courseid, $active): void {
        mtrace('calling set_self_enrollment');
        global $DB;
        // Get enrollment plugin.
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'self']);
        $enrolplugin = enrol_get_plugin($instance->enrol);

        if ($active) {
            $newstatus = 0;

        }
        if (!$active) {
            $newstatus = 1;
        }
        $enrolplugin->update_status($instance, $newstatus);
    }

    /**
     * Creates categories based on Open-Sesame API
     *
     * @param $osrecord
     * @return void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_oscategories($osrecord) {
        global $DB;
        $categories = $osrecord->categories;
        foreach ($categories as $key => $value) {
            $values = explode('|', $value);
            $values = array_values(array_filter($values));

            foreach ($values as $vkey => $vvalue) {

                $catexist =
                        $DB->record_exists('course_categories', ['name' => $vvalue]);

                if ($vkey === 0 && $catexist !== true) {
                    $data = new \stdClass();
                    $data->name = $vvalue;
                    $category = core_course_category::create($data);
                }

                if ($vkey !== 0 && $catexist !== true) {
                    $data = new \stdClass();
                    $data->name = $vvalue;
                    $name = $values[$vkey - 1];
                    $parentid = $DB->get_field('course_categories', 'id', ['name' => $name]);
                    $data->parent = $parentid;
                    $category = core_course_category::create($data);
                }
            }
        }
        \context_helper::build_all_paths();

    }
}
