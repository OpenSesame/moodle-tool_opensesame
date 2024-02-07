<?php
// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * API for Open Sesame.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     David Castro <david.castro@moodle.com>
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame\api;

/**
 * API for Open Sesame.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     David Castro <david.castro@moodle.com>
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opensesame extends \curl {

    /** @var string */
    private $authurl;

    /** @var string */
    private $clientid;

    /** @var string */
    private $clientsecret;

    /** @var string */
    private $customerintegrationid;

    /** @var string */
    private $baseurl;

    /** @var string */
    private $accesstoken;

    /** @var int */
    private $retries;

    /**
     * Constructor.
     * @param string $authurl
     * @param string $clientid
     * @param string $clientsecret
     * @param string $customerintegrationid
     * @param string $baseurl
     * @param int $retries
     * @param array $settings
     * @throws \moodle_exception
     */
    public function __construct(
            $authurl, $clientid, $clientsecret, $customerintegrationid, $baseurl, $retries = 3, $settings = []) {
        parent::__construct($settings);
        $this->authurl = $authurl;
        $this->clientid = $clientid;
        $this->clientsecret = $clientsecret;
        $this->customerintegrationid = $customerintegrationid;
        $this->baseurl = $baseurl;
        $this->retries = $retries;
    }

    /**
     * Get http status code.
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
     * Do a GET call.
     *
     * @param string $resource path of the resource.
     * @param array $header
     * @param array $params
     * @param string $url to override using the API URL.
     * @return string JSON String of result.
     * @throws \moodle_exception
     */
    private function get_request($resource, $header = [], $params = [], $url = '') {
        if (empty($url)) {
            $url = $this->baseurl . $resource;
        }
        $header = array_merge($header, $this->get_authentication_header());

        $this->resetHeader();
        $this->resetcookie();
        $this->reset_request_state_vars();
        $this->resetopt();
        $this->setHeader($header);

        !PHPUNIT_TEST ? mtrace("Retrieving page: $params[page]") : false;
        $response = $this->get($url, $params);

        $httpcode = $this->get_http_code();
        if ($httpcode !== 200) {
            $debug = "HTTP code: $httpcode" . PHP_EOL;
            if ($httpcode === 401) {
                unset_config('accesstoken', 'tool_opensesame');
                $this->accesstoken = null;
            }
            $debug .= "Response: $response" . PHP_EOL;
            foreach ($params as $key => $value) {
                $debug .= "$key: $value" . PHP_EOL;
            }
            !PHPUNIT_TEST ? mtrace("Debug: " . $debug) : false;
            return false;
        }
        !PHPUNIT_TEST ? mtrace("Success retrieve Page: $params[page]") : false;
        return $response;
    }

    /**
     * Executes a GET request with a specific set of retry attempts.
     * If 200 code is not achieved, it will retry until exhausting the allowed amount.
     * @param string $resource
     * @param array $header
     * @param array $params
     * @param string $url
     * @return string
     */
    private function get_request_retries($resource, $header = [], $params = [], $url = '') {
        $maxattempts = $this->retries;
        $attempts = 0;
        $response = false;
        $exception = null;

        do {
            try {
                $response = $this->get_request($resource, $header, $params, $url);
                if ($response !== false) {
                    break;
                } else {
                    sleep(3);
                }
            } catch (\Exception $ex) {
                $exception = $ex;
                sleep(3);
            } finally {
                $attempts += 1;
            }
        } while ($attempts < $maxattempts && $response === false);

        if (!is_null($exception)) {
            throw $exception;
        }
        return $response;
    }

    /**
     * Authenticates with the Opensesame auth URL to get a token.
     * @return bool true if auth succeeded.
     * @throws \dml_exception
     */
    private function authenticate(): bool {
        !PHPUNIT_TEST ? mtrace("Authenticating with Open Sesame") : false;
        $clientid = $this->clientid;
        $clientsecret = $this->clientsecret;
        $this->resetHeader();
        $this->setHeader([
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            sprintf('Authorization: Basic %s', base64_encode(sprintf('%s:%s', $clientid, $clientsecret))),
        ]);
        $authurl = $this->authurl;
        $response = $this->post($authurl, 'grant_type=client_credentials&scope=content');
        if ($this->get_http_code() !== 200) {
            !PHPUNIT_TEST ? mtrace($response) : false;
            return false;
        }

        $decoded = json_decode($response);
        if (!empty($decoded->access_token)) {
            $this->accesstoken = $decoded->access_token;
            set_config('accesstoken', $this->accesstoken, 'tool_opensesame');
            !PHPUNIT_TEST ? mtrace('Token was retrieved successfully') : false;
            return true;
        }
        !PHPUNIT_TEST ? mtrace('Access token could not be decoded') : false;
        return false;
    }

    /**
     * Get a digest authentication header.
     *
     * @return array of authentification headers
     * @throws \moodle_exception
     */
    public function get_authentication_header(): array {
        $token = $this->get_accesstoken();
        if (empty($token)) {
            return [];
        }

        return [sprintf('Authorization: Bearer %s', $token)];
    }

    /**
     * Gets the access token, tries to authenticate if it is not present.
     * @return string|bool False if token cannot be retrieved.
     */
    private function get_accesstoken() {
        if (empty($this->accesstoken)) {
            $token = get_config('tool_opensesame', 'accesstoken');
            if ($token !== false) {
                return $this->accesstoken = $token;
            }

            $maxattempts = $this->retries;
            $attempts = 0;
            $authenticated = false;
            !PHPUNIT_TEST ? mtrace('Token was not found, authentication is beginning') : false;
            do {
                $authenticated = $this->authenticate();
                if ($authenticated) {
                    break;
                }
                sleep(3);
                $attempts += 1;
            } while ($attempts < $maxattempts && !$authenticated);

            if (!$authenticated) {
                return false;
            }
        }
        return $this->accesstoken;
    }

    /**
     * Gets a list of courses from Open Sesame.
     * @param int $pagesize Default to 50.
     * @param int $page Page for the next call.
     * @return object A Open Sesame response as a PHP Object.
     */
    public function get_course_list(int $pagesize = 50, int $page = 1): object {
        $params = [
            'limit' => $pagesize,
            'customerIntegrationId' => $this->customerintegrationid,
            'page' => $page
        ];

        $header = ['Accept: application/json', 'Content-Type: application/json'];

        $response = $this->get_request_retries(
            "/v1/content" ,
            $header,
            $params,
        );

        return json_decode($response);
    }

    /**
     * Debug the request information.
     * @param int $pagesize
     * @return void
     */
    public function request_debug($pagesize): void {
        $url = $this->baseurl . "/v1/content";
        $customerintegrationid = $this->customerintegrationid;
        !PHPUNIT_TEST ? mtrace("Getting this: $url Customer Integration Id: $customerintegrationid Pagesize: $pagesize") : false;
    }

    /**
     * Downloads a scorm package and saves it in a temporary directory.
     * @param string $downloadurl
     * @param string $filename
     * @return string
     * @throws \Exception
     */
    public function download_scorm_package(string $downloadurl, string $filename): string {
        $tempdir = make_request_directory();
        $path = "{$tempdir}/{$filename}";
        $res = download_file_content(
            $downloadurl, $this->get_authentication_header(), null, false, 320, 20, false, $path);
        if ($res === false) {
            throw new \Exception("Could not download file from URL: $downloadurl");
        }
        return $path;
    }
}
