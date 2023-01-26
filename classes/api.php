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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/lib/filelib.php');

class api extends \curl {

    /** @var string the api token */
    private $token;
    /** @var string the api baseurl */
    private $baseurl;
    /**
     * @var array Profile fields.
     */
    public $profile_fields = [];

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
        $info = $this->get_info();
        if (!isset($info['http_code'])) {
            return false;
        }
        return $info['http_code'];
    }

    public function authenticate() {
        mtrace('Authenticating...');
        $authurl = get_config('tool_opensesame', 'authurl');
        //mtrace('?????????' . $authurl . 'authurl');
        $clientid = get_config('tool_opensesame', 'clientid');
        $clientsecret = get_config('tool_opensesame', 'clientsecret');

        mtrace('Requesting an access token');

        $this->setHeader([
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret)))
        ]);

        $response = $this->post($authurl, 'grant_type=client_credentials&scope=content'
        );
        $statuscode = $this->get_http_code();
        $decoded = json_decode($response);
        //prints mtrace('response authtoke' . $response);
        mtrace('Access token is returned');
        $token = $decoded->access_token;
        set_config('bearertoken', $token, 'tool_opensesame');
        mtrace('set hidden bearertoken create time stamp');
        set_config('bearertokencreatetime', time(), 'tool_opensesame');
        $createtime = get_config('tool_opensesame', 'bearertokencreatetime');

        mtrace('set hidden bearertoken expire time stamp');
        set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
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
            $this->authenticate();
        }

        if ($token !== '' && $now <= $expiretime) {
            mtrace('Token is valid.');
        }
        return $token;
    }

    /**
     * add_open_sesame_course
     * Adds an open sesame course to moodle,
     *
     * @param $osrecord
     * @throws \file_exception
     * @throws \moodle_exception
     */

    public function add_open_sesame_course($osrecord) {
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
            $scormpackage = $osrecord->packageDownloadUrl;
            //$this->create_scorm_file($courseid, $scormpackage);
            //Todo add courseid to tool_opensesame table to cross to establish a relationship between opensesame data and moodle
            // course created.
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
        mtrace('get_open_sesame_course list');
        $this->setHeader(sprintf('Authorization: Bearer %s', $token));
        $url = get_config('tool_opensesame', 'baseurl') . '/v1/content?customerIntegrationId=' .
                get_config('tool_opensesame', 'customerintegrationid');
        $response = $this->get($url);
        $statuscode = $this->get_http_code();
        $dcoded = json_decode($response);
        mtrace('Statuscode: ' . $statuscode);
        if ($statuscode === 200) {

            $data = $dcoded->data;
            //mtrace('Response' . $response);
            foreach ($data as $osrecord) {
                $keyexist =
                        $DB->record_exists('tool_opensesame', ['idopensesame' => $osrecord->id]);
                if ($keyexist !== true) {
                    mtrace('Osrecord being created for ' . $osrecord->id);
                    $DB->insert_record_raw('tool_opensesame', [
                            'idopensesame' => $osrecord->id,
                            'provider' => 'OpenSesame',
                            'active' => $osrecord->active,
                            'title' => $osrecord->title,
                            'descriptiontext' => $osrecord->descriptionHtml =
                                    true ? $osrecord->descriptionText : $osrecord->descriptionHtml,
                            'thumbnailurl' => $osrecord->thumbnailUrl,
                            'duration' => $osrecord->duration,
                            'languages' => $osrecord->languages,
                            'oscategories' => $osrecord->categories,
                            'publishername' => $osrecord->publisherName,
                            'packageDownloadurl' => $osrecord->packageDownloadUrl,
                            'aicclaunchurl' => $osrecord->aiccLaunchUrl,
                    ]);
                    $this->add_open_sesame_course($osrecord);

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
        $context = context_course::instance($courseid);

        mtrace('Course Created: ' . $courseid . ' Thumbnail url: ' . $thumbnailurl);
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
        mtrace('Course image placed inside of database');
    }

    public function create_scorm_file($courseid, $scormpackage) {
        $context = context_course::instance($courseid);

        mtrace('Course Created: ' . $courseid . ' ScormPackage Download url: ' . $scormpackage);
        $fileinfo = [
                'contextid' => $context->id,   // ID of the context.
                'component' => 'mod_scorm', // Your component name.
                'filearea' => 'package',       // Usually = table name.
                'itemid' => 0,              // Usually = ID of row in table.
                'filepath' => '/',            // Any path beginning and ending in /.
                'filename' => 'scorm_' . $courseid . '.zip',   // Any filename.
        ];
        //create course image
        $fs = get_file_storage();
        $scormurl = $scormpackage . '?standard=scorm';
        // Create a new file .
        $fs->create_file_from_url($fileinfo, $scormurl);
        mtrace('Course image placed inside of database');
    }

}