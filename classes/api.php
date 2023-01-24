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

    /**
     * Get an digest authentication header.
     *
     * @return array of authentification headers
     * @throws \moodle_exception
     */
    private function get_bearer_token(): array {
        $this->setopt('CURLOPT_HEADER', true);
        $this->setopt('CURLOPT_RETURNTRANSFER', true);

        $header = array();
        $header[] = sprintf('Authorization: Bearer %s', $this->bearertoken);

        return $header;
    }

    /**
     * Do a GET call .
     *
     * @param string $resource path of the resource.
     * @return string JSON String of result.
     * @throws \moodle_exception
     */
    public function get_request($resource) {

        $url = $this->baseurl . $resource;
        $this->resetHeader();
        $header = $this->get_authentication_header();
        $header[] = 'Content-Type: application/json';
        $this->setHeader($header);

        return $this->get($url);
    }

    public function authenticate() {//Get required credentials
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
        $statuscode = $this->info['http_code'];
        $decoded = json_decode($response);
        //prints mtrace('response authtoke' . $response);
        mtrace('Access token is returned');
        $access_token = $decoded->access_token;
        set_config('bearertoken', $access_token, 'tool_opensesame');
        mtrace('set hidden bearertoken create time stamp');
        set_config('bearertokencreatetime', time(), 'tool_opensesame');
        $createtime = get_config('tool_opensesame', 'bearertokencreatetime');

        mtrace('set hidden bearertoken expire time stamp');
        set_config('bearertokenexpiretime', ($createtime + $decoded->expires_in), 'tool_opensesame');
        $expiretime = get_config('tool_opensesame', 'bearertokenexpiretime');
    }

    public function get_oscontent() {
        //Integrator issues request with access token
        $bearertoken = get_config('tool_opensesame', 'bearertoken');
        $this->setHeader(sprintf('Authorization: Bearer %s', $bearertoken));
        //$ci = get_config('tool_opensesame', 'customerintegrationid');
        $url = get_config('tool_opensesame', 'baseurl') . '/v1/content?customerIntegrationId=' .
                get_config('tool_opensesame', 'customerintegrationid');

        $response = $this->get($url);
        $statuscode = $this->info['http_code'];
        $dcoded = json_decode($response);
        $data = $dcoded->data;
        return $data;
    }

}