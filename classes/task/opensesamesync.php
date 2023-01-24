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
namespace tool_opensesame\task;

use context_course;
use tool_opensesame\api;

require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Simple task class responsible for integrating with OpenSesame every 24 hours. Disclaimer:
 * Task does not sync with Open sesame yet, it serves as a placeholder
 *
 * @since      3.9
 * @package    tool_opensesame
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opensesamesync extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('opensesamesync', 'tool_opensesame');
    }

    /**
     * @param $testing
     * @return bool
     */
    public function execute($testing = null) {
        global $DB;
        mtrace("Opensesame task just started.");

        //make two hidden settings in config_plugin table
        $setting1 = $DB->record_exists('config_plugins', ['plugin' => 'tool_opensesame', 'name' => 'bearertokenexpiretime']);
        if ($setting1 === 0) {
            mtrace('bearertokenexpiretime setting does not exist');
            set_config('bearertokenexpiretime', '', 'tool_opensesame');
            mtrace('bearertokenexpiretime setting is created');
        }

        $setting2 = $DB->record_exists('config_plugins', ['plugin' => 'tool_opensesame', 'name' => 'bearertokencreatetime']);

        if ($setting2 === 0) {
            mtrace('bearertokencreatetime setting does not exist');
            set_config('bearertokencreatetime', '', 'tool_opensesame');
            mtrace('bearertokencreatetime setting is created');
        }
        /*
         * When the task runs
         * If the token does not exist, it is created
         * If the token exists and has not expired, no auth process takes place
         * If the token exists and it has expired, it is created
         *
         * */

        $bearertoken = get_config('tool_opensesame', 'bearertoken');
        $expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
        $now = time();
        //If the token does not exist, it is created
        if ($bearertoken === '') {
            mtrace('You need to create the Bearer Token.' . $bearertoken);
            //todo make into a function 79-110
            //Get required credentials
            //$authurl = get_config('tool_opensesame', 'authurl');
            ////mtrace('?????????' . $authurl . 'authurl');
            //$clientid = get_config('tool_opensesame', 'clientid');
            //$clientsecret = get_config('tool_opensesame', 'clientsecret');
            //
            //mtrace('Requesting an access token');
            //$curl = new \curl();
            //$curl->setHeader([
            //        'Content-Type: application/x-www-form-urlencoded',
            //        'Accept: application/json',
            //        sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret)))
            //]);
            //
            //$response = $curl->post($authurl, 'grant_type=client_credentials&scope=content'
            //);
            //$statuscode = $curl->info['http_code'];
            //$decoded = json_decode($response);
            ////prints mtrace('response authtoke' . $response);
            //mtrace('Access token is returned');
            //$access_token = $decoded->access_token;
            //set_config('bearertoken', $access_token, 'tool_opensesame');
            //mtrace('set hidden bearertoken create time stamp');
            //set_config('bearertokencreatetime', time(), 'tool_opensesame');
            //$createtime = get_config('tool_opensesame', 'bearertokencreatetime');
            //
            //mtrace('set hidden bearertoken expire time stamp');
            //set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
            //$expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
            api::get_authentication();

            //Integrator issues request with access token
        }
        //If the token exists and it has expired, it is created
        if ($bearertoken !== '' && $now >= $expiretime) {
            mtrace('If the token exists and it has expired, it is created');
            mtrace('Bearer Token is Expired. Resetting Bearer token to empty.');
            set_config('bearertoken', '', 'tool_opensesame');

            api::get_authentication();

        }
        //If the token exists and has not expired, no auth process takes place, get content using bearer token
        if ($bearertoken !== '' && $now <= $expiretime) {
            //no auth takes place, get content using bearer token
            mtrace('bearer token is not expired no auth takes place, get content using bearer token');
            ////Integrator issues request with access token

            $data = api::get_oscontent();
            foreach ($data as $oscourse) {

                $keyexist =
                        $DB->record_exists('tool_opensesame', ['idopensesame' => $oscourse->id]);

                if ($keyexist !== true) {
                    $DB->insert_record_raw('tool_opensesame', [
                            'idOpenSesame' => $oscourse->id,
                            'provider' => 'OpenSesame',
                            'active' => $oscourse->active,
                            'title' => $oscourse->title,
                            'descriptionText' => $oscourse->descriptionHtml =
                                    true ? $oscourse->descriptionText : $oscourse->descriptionHtml,
                            'thumbnailURL' => $oscourse->thumbnailUrl,
                            'duration' => $oscourse->duration,
                            'languages' => $oscourse->languages,
                            'oscategories' => $oscourse->categories,
                            'publisherName' => $oscourse->publisherName,
                            'packageDownloadUrl' => $oscourse->packageDownloadUrl,
                            'aiccLaunchUrl' => $oscourse->aiccLaunchUrl,
                    ]);

                }
                $coursexist =
                        $DB->record_exists('course', ['idnumber' => $oscourse->id]);

                if ($coursexist !== true) {
                    $data = new \stdClass();

                    $data->fullname = $oscourse->title;
                    $data->shortname = $oscourse->title;
                    $data->idnumber = $oscourse->id;
                    $data->summary = $oscourse->descriptionHtml;
                    $data->timecreated = time();
                    $data->category = $DB->get_field('course_categories', 'id', ['name' => 'Miscellaneous']);
                    $data->summary .= ' Publisher Name: ' . $oscourse->publisherName . ' Duration: ' . $oscourse->duration;
                    //$data->catogory = $DB->get_record('course_categories', array('name' => 'Miscellaneous'), 'id', MUST_EXIST);
                    $course = create_course($data);
                    //this should now be moodle courseid not osid.
                    $courseid = $course->id;
                    $context = context_course::instance($courseid);

                    mtrace('Course Created: ' . $course->id . ' Thumbnail url: ' . $oscourse->thumbnailUrl);
                    $fileinfo = [
                            'contextid' => $context->id,   // ID of the context.
                            'component' => 'course', // Your component name.
                            'filearea' => 'overviewfiles',       // Usually = table name.
                            'itemid' => 0,              // Usually = ID of row in table.
                            'filepath' => '/',            // Any path beginning and ending in /.
                            'filename' => 'courseimage.jpg',   // Any filename.
                    ];
                    //create course image
                    $fs = get_file_storage();

                    // Create a new file containing the text 'hello world'.
                    $fs->create_file_from_url($fileinfo, $oscourse->thumbnailUrl);
                    mtrace('Course image placed inside of database');
                }
                if ($coursexist == true) {
                    mtrace('Course: ' . $oscourse->title . ' needs updating');
                }
            }

        }
        mtrace('opensesame just finished.');
        return true;
    }
}

