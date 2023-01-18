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

require_once($CFG->dirroot . '/lib/filelib.php');

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
        mtrace('check if bearertokenexpiretime setting exist');
        $setting1 = $DB->record_exists('config_plugins', ['plugin' => 'tool_opensesame', 'name' => 'bearertokenexpiretime']);
        mtrace($setting1 . ' setting1');
        if ($setting1 === 0) {
            mtrace('bearertokenexpiretime setting does not exist');
            set_config('bearertokenexpiretime', '', 'tool_opensesame');
            mtrace('bearertokenexpiretime setting is created');
        } else {
            mtrace('bearertokenexpiretime setting does exist');
        }

        $setting2 = $DB->record_exists('config_plugins', ['plugin' => 'tool_opensesame', 'name' => 'bearertokencreatetime']);
        mtrace('checking  if bearertokencreatetime setting exist' . $setting2);
        if ($setting2 === 0) {
            mtrace('bearertokencreatetime setting does not exist');
            set_config('bearertokencreatetime', '', 'tool_opensesame');
            mtrace('bearertokencreatetime setting is created');
        } else {
            mtrace('bearertokencreatetime setting does exist');
        }
        /*
         * When the task runs
         * If the token does not exist, it is created
         * If the token exists and has not expired, no auth process takes place
         * If the token exists and it has expired, it is created
         *
         * */

        $bearertoken = get_config('tool_opensesame', 'bearertoken');
        //mtrace('Checking for the Bearer Token: ' . $bearertoken);
        if ($bearertoken === '') {
            mtrace('You need to create the Bearer Token.' . $bearertoken);

            //Get required credentials
            $authurl = get_config('tool_opensesame', 'authurl');
            //mtrace('?????????' . $authurl . 'authurl');
            $clientid = get_config('tool_opensesame', 'clientid');
            //mtrace('???????' . $clientid . '=clientid');
            $clientsecret = get_config('tool_opensesame', 'clientsecret');
            //mtrace($clientsecret . '=clientsecret');

            //  Request an access token
            $curl = new \curl();
            $curl->setHeader([
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret)))
            ]);

            $response = $curl->post($authurl, 'grant_type=client_credentials&scope=content'
            );
            $statuscode = $curl->info['http_code'];
            $decoded = json_decode($response);
            mtrace('response authtoke' . $response);
            //Access token is returned
            $access_token = $decoded->access_token;
            set_config('bearertoken', $access_token, 'tool_opensesame');
            //set hidden bearertoken create time stamp
            set_config('bearertokencreatetime', time(), 'tool_opensesame');
            $createtime = get_config('tool_opensesame', 'bearertokencreatetime');
            mtrace('createtime: ' . ($createtime));
            //set hidden bearertoken expire time stamp
            set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
            $expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
            mtrace('!!!!Expiretime' . $expiretime);

            $now = time();
            mtrace($now - $expiretime);
            if ($now >= $expiretime) {
                mtrace($now - $expiretime);
                set_config('bearertoken', '', 'tool_opensesame');
            }
            //Integrator issues request with access token
        } else if ($bearertoken !== '') {
            $expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
            mtrace('!!!!Expiretime' . $expiretime);
            mtrace('time: ' . time() . '> $expiretime: ' . $expiretime);
            $now = time();
            mtrace($now - $expiretime);
            if ($now >= $expiretime) {
                mtrace($now - $expiretime);
                set_config('bearertoken', '', 'tool_opensesame');
            } else {
                mtrace('bearer token is not expired');
                //Integrator issues request with access token
                $c = new \curl();
                $bearertoken = get_config('tool_opensesame', 'bearertoken');
                mtrace($bearertoken);
                $c->setHeader(sprintf('Authorization: Bearer %s', $bearertoken));
                $ci = get_config('tool_opensesame', 'customerintegrationid');
                mtrace('ci = ' . $ci);
                $url = get_config('tool_opensesame', 'baseurl') . '/v1/content?customerIntegrationId=' .
                        get_config('tool_opensesame', 'customerintegrationid');
                mtrace('get url' . $url);
                $response = $c->get($url);

                $statuscode = $c->info['http_code'];

                mtrace('Statuscode' . $statuscode);
                //mtrace('decoded response' . $response);
                $dcoded = json_decode($response);
                $data = $dcoded->data;//an array of courses
                foreach ($data as $idkey => $course) {
                    mtrace($course->idkey);
                    mtrace($course->active);

                    $DB->insert_record_raw('tool_opensesame', [
                            'idOpenSesame' => $idkey,
                            'provider' => 'OpenSesame',
                            'active' => $course->active,
                            'title' => $course->title,
                            'descriptionText' => $course->descriptionHTML =
                                    true ? $course->descriptionText : $course->descriptionHTML,
                            'thumbnailURL' => $course->thumbnailURL,
                            'duration' => $course->duration,
                            'languages' => $course->languages,
                            'oscategories' => $course->categories,
                            'publisherName' => $course->publisherName,
                            'packageDownloadUrl' => $course->packageDownloadUrl,
                            'aiccLaunchUrl' => $course->aiccLaunchUrl,
                        //'dateUpdated' => $course->dateUpdated,
                        //'xApiActivityId' => $course->xApiActivityId
                    ]);

                }
                mtrace(json_encode($data));
            }
            //mtrace('bearertoken is set to: ' . $bearertoken);
            $access_token = get_config('tool_opensesame', 'bearertoken');
            //mtrace('Accesstoken: ' . $bearertoken);
            set_config('bearertokencreatetime', time(), 'tool_opensesame');
            $createtime = get_config('tool_opensesame', 'bearertokencreatetime');
            mtrace('createtime: ' . $createtime);
        }

        mtrace('opensesame just finished.');
        return true;
    }
}

