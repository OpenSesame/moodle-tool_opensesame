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
        $authurl = get_config('tool_opensesame', 'authurl');
        mtrace('?????????' . $authurl . 'authurl');
        $clientid = get_config('tool_opensesame', 'clientid');
        mtrace('???????' . $clientid . '=clientid');
        $clientsecret = get_config('tool_opensesame', 'clientsecret');
        mtrace($clientsecret . '=clientsecret');

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
        $access_token = $decoded->access_token;
        mtrace('response = ' . $response);
        mtrace('statuscode = ' . $statuscode);
        mtrace('response access_token' . gettype($response));
        mtrace('decoded =' . $decoded->access_token);
        mtrace('opensesame just finished.');
        return true;
    }
}

