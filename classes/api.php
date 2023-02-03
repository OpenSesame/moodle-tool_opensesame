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
 * @category    classes
 * @copyright   2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame;

use context_course;
use core_course_category;

//require_once("$CFG->libdir/filelib.php");

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/lib/filelib.php');

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

        $this->bearertoken = get_config('local_opensesame', 'bearertoken');
        $this->baseurl = get_config('local_opensesame', 'baseurl');

        // If the admin omitted the protocol part, add the HTTPS protocol on-the-fly.
        if (!preg_match('/^https?:\/\//', $this->baseurl)) {
            $this->baseurl = 'https://' . $this->baseurl;
        }

        if (empty($this->baseurl)) {
            throw new \moodle_exception('apiurlempty', 'local_opensesame');
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
        mtrace('returning status code' . $info['http_code']);
        return $info['http_code'];
    }

    public function authenticate() {
        mtrace('Calling Authenticate().');
        $authurl = get_config('tool_opensesame', 'authurl');
        //mtrace('?????????' . $authurl . 'authurl');
        $clientid = get_config('tool_opensesame', 'clientid');
        $clientsecret = get_config('tool_opensesame', 'clientsecret');

        $this->setHeader([
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret)))
        ]);

        $response = $this->post($authurl, 'grant_type=client_credentials&scope=content'
        );
        $statuscode = $this->get_http_code();
        $decoded = json_decode($response);
        $token = $decoded->access_token;
        set_config('bearertoken', $token, 'tool_opensesame');
        //mtrace('set hidden bearertoken create time stamp');
        set_config('bearertokencreatetime', time(), 'tool_opensesame');
        $createtime = get_config('tool_opensesame', 'bearertokencreatetime');
        set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
        return $this->get_auth_token();
    }

    /**
     * get_auth_token: Getting the auth token
     * This method would validate that the token has not expired,
     *  and if it has, then creates a new one
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
            $this->get_open_sesame_course_list($token);
            return $token;
        }
    }

    /**
     * add_open_sesame_course
     * Adds an open sesame course to moodle,
     *
     * @param $osrecord
     * @throws \file_exception
     * @throws \moodle_exception
     */

    public function add_open_sesame_course($osrecord, $token) {
        mtrace('calling add_open_sesame_course');
        global $DB;
        $coursexist =
                $DB->record_exists('course', ['idnumber' => $osrecord->id]);

        if ($coursexist !== true) {
            $data = new \stdClass();
            $data->fullname = $osrecord->title;
            $data->shortname = $osrecord->title;
            $data->idnumber = $osrecord->id;
            $data->summary = $osrecord->descriptionHtml;
            $data->timecreated = time();
            $data->category = $DB->get_field('course_categories', 'id', ['name' => 'Miscellaneous']);
            $data->summary .= ' Publisher Name: ' . $osrecord->publisherName . ' Duration: ' . $osrecord->duration;

            $course = create_course($data);
            //this should now be moodle courseid not osid.
            $courseid = $course->id;
            $thumbnailurl = $osrecord->thumbnailUrl;
            $this->create_course_image($courseid, $thumbnailurl);
            $scormpackagedownloadurl = $osrecord->packageDownloadUrl;
            $this->get_open_sesame_scorm_package($token, $scormpackagedownloadurl, $courseid);
            $this->update_osrecord($courseid, $osrecord->id);
            $active = $this->os_is_active($osrecord->id, $courseid);

            $this->set_self_enrollment($courseid, $active);

        }
        if ($coursexist == true) {
            mtrace('Course: ' . $osrecord->title . ' needs updating');
        }
    }

    /**
     * get_open_sesame_course_list
     *
     * @param $token
     * Does not validate the token, the token should be valid
     * Gets a list of courses and processes them using
     * add_open_sesame_course
     */

    public function get_open_sesame_course_list($token) {
        global $DB;
        //Integrator issues request with access token
        mtrace('Calling get_open_sesame_course list');
        $this->setHeader(sprintf('Authorization: Bearer %s', $token));
        $url = get_config('tool_opensesame', 'baseurl') . '/v1/content?customerIntegrationId=' .
                get_config('tool_opensesame', 'customerintegrationid');
        $response = $this->get($url);
        $statuscode = $this->get_http_code();
        $dcoded = json_decode($response);

        if ($statuscode === 400) {
            mtrace('OpenSesame Course list Statuscode: ' . $statuscode);
            //$this->authenticate();
        }
        if ($statuscode === 200) {
            mtrace('OpenSesame Course list Statuscode: ' . $statuscode);
            $data = $dcoded->data;
            mtrace('Response debug for Moodle category creation' . $response);
            //mtrace('Response' . $response);
            foreach ($data as $osrecord) {
                //categories": [
                //                "|Compliance|HIPAA",
                //                "|Industry Specific|Healthcare"
                //            ],
                //$categories = $osrecord->categories;
                //foreach ($categories as $key => $value) {
                //    mtrace('Categories' . $key . '-' . $value);
                //    //$value = "|Compliance|HIPAA"
                //    $values = explode('|', $value);
                //    //$value
                //    $values = array_values(array_filter($values));
                //    mtrace('Values = ' . json_encode($values));
                //    //returns [Compliance, HIPAA]
                //    foreach ($values as $vkey => $vvalue) {
                //        mtrace('vkey: ' . $vkey . 'vvalue: ' . $vvalue);
                //        $catexist =
                //                $DB->record_exists('course_categories', ['name' => $vvalue]);
                //
                //        if ($vkey === 0 && $catexist !== true) {
                //            $data = new \stdClass();
                //            mtrace('vkey is ' . $vkey);
                //            mtrace('vvalue is ' . $vvalue);
                //            $data->name = $vvalue;
                //            core_course_category::create($data);
                //        }
                //
                //        if ($vkey !== 0 && $catexist !== true) {
                //            $data = new \stdClass();
                //            $data->name = $vvalue;
                //            $name = $values[$vkey - 1];
                //            $parentid = $DB->get_field('course_categories', 'id', ['name' => $name]);
                //            $data->parent = $parentid;
                //            core_course_category::create($data);
                //        }
                //    }
                //}
                //target
                $this->create_oscategories($osrecord);
                $keyexist =
                        $DB->record_exists('tool_opensesame', ['idopensesame' => $osrecord->id]);
                if ($keyexist !== true) {
                    //mtrace('Osrecord being created for ' . $osrecord->id);
                    //mtrace('osrecord' . json_encode($osrecord));
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
                    $osdataobject->packageDownloadurl = $osrecord->packageDownloadUrl;
                    $osdataobject->aicclaunchurl = $osrecord->aiccLaunchUrl;
                    //mtrace('osdataobject:' . json_encode($osdataobject));
                    $returnid = $DB->insert_record('tool_opensesame', $osdataobject);
                    mtrace('inserting record ' . $returnid);

                    $this->add_open_sesame_course($osrecord, $token);

                }

            }

        }

    }

    /**
     * @param $courseid
     * @param $thumbnailurl
     * @return void
     * @throws \file_exception
     */
    public function create_course_image($courseid, $thumbnailurl) {
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
        //create course image
        $fs = get_file_storage();

        // Create a new file containing the text 'hello world'.
        $fs->create_file_from_url($fileinfo, $thumbnailurl);
    }

    public function get_open_sesame_scorm_package($token, $scormpackagedownloadurl, $courseid = null) {
        mtrace('calling get_open_sesame_scorm_package');
        global $CFG, $USER;
        require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');
        //Integrator issues request with access token

        $this->setHeader(['Content-Type: application/json', sprintf('Authorization: Bearer %s', $token)]);

        $url = $scormpackagedownloadurl . '?standard=scorm';

        $headers = $this->header;

        $filename = 'scorm_' . $courseid . '.zip';
        $path = $CFG->tempdir . '/zip/' . $filename;
        //Download to temp directory
        download_file_content($url, $headers, null, true, 300, 20, false, $path, false);
        //create a file from temporary folder in the user file draft area
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

        // Create a new file scorm.zip package inside of course.
        $storedfile = $fs->create_file_from_pathname($fileinfo, $path);
        //create a new user draft file from mod_scorm package
        // Get an unused draft itemid which will be used
        $draftitemid = file_get_submitted_draft_itemid('packagefile');
        // Copy the existing files which were previously uploaded
        // into the draft area
        file_prepare_draft_area(
                $draftitemid, $context->id, 'mod_scorm', 'package', 0);

        $this->create_course_scorm_mod($courseid, $draftitemid);

    }

    public function create_course_scorm_mod($courseid, $draftitemid) {
        mtrace('calling create_course_scorm_mod');
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/format/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/mod_form.php');

        //get course
        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        //create top course section
        $section = 0;
        $sectionreturn = 0;
        $add = 'scorm';
        $courseformat = course_get_format($course);
        $maxsections = $courseformat->get_max_sections();
        if ($section > $maxsections) {
            print_error('maxsectionslimit', 'moodle', '', $maxsections);
        }
        list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $add, $section);
        $data->return = 0;
        $data->sr = $sectionreturn;
        $data->add = $add;

        $moduleinfo = new \stdClass();
        $moduleinfo->name = 'scorm_' . $courseid;
        $moduleinfo->introeditor = ['text' => '',
                'format' => '1', 'itemid' => ''];
        $moduleinfo->showdescription = 0;
        $moduleinfo->mform_isexpanded_id_packagehdr = 1;
        $moduleinfo->scormtype = 'local';
        $moduleinfo->packagefile = $draftitemid;
        $moduleinfo->updatefreq = 0;
        $moduleinfo->popup = 0;
        $moduleinfo->width = 100;
        $moduleinfo->height = 500;
        $moduleinfo->course = $courseid;
        $moduleinfo->module = $module->id;
        $moduleinfo->modulename = $module->name;
        $moduleinfo->visible = $module->visible;
        $moduleinfo->add = $add;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->section = $section;

        //$data stdclass with everything to add_mod_info
        add_moduleinfo($moduleinfo, $course);
    }

    public function update_osrecord($courseid, $osrecordid) {
        mtrace('calling update_osrecord');
        global $DB;
        $DB->set_field('tool_opensesame', 'courseid', $courseid, ['idopensesame' => $osrecordid]);
    }

    public function os_is_active($osrecordid, $courseid) {
        mtrace('calling os_is_active');
        global $DB;
        $active = $DB->get_field('tool_opensesame', 'active', ['idopensesame' => $osrecordid, 'courseid' => $courseid]);
        return $active;
    }

    public function set_self_enrollment($courseid, $active) {
        mtrace('calling set_self_enrollment');
        global $DB;
        // get enrollment plugin
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'self']);
        $enrolplugin = enrol_get_plugin($instance->enrol);

        if ($active) {
            //$DB->set_field('enrol', 'status', 0, ['courseid' => $courseid, 'enrol' => 'self']);
            $newstatus = 0;

        }
        if (!$active) {
            //$DB->set_field('enrol', 'status', 1, ['courseid' => $courseid, 'enrol' => 'self']);
            $newstatus = 1;
        }
        $enrolplugin->update_status($instance, $newstatus);
    }

    public function create_oscategories($osrecord) {
        global $DB;
        $categories = $osrecord->categories;
        foreach ($categories as $key => $value) {
            mtrace('Categories' . $key . '-' . $value);
            //$value = "|Compliance|HIPAA"
            $values = explode('|', $value);
            //$value
            $values = array_values(array_filter($values));
            mtrace('Values = ' . json_encode($values));
            //returns [Compliance, HIPAA]
            foreach ($values as $vkey => $vvalue) {
                mtrace('vkey: ' . $vkey . 'vvalue: ' . $vvalue);
                $catexist =
                        $DB->record_exists('course_categories', ['name' => $vvalue]);

                if ($vkey === 0 && $catexist !== true) {
                    $data = new \stdClass();
                    mtrace('vkey is ' . $vkey);
                    mtrace('vvalue is ' . $vvalue);
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
}