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
 * opensesame process handler.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame;

use completion_info;
use tool_opensesame\local\data\base;
use tool_opensesame\task\opensesamesync;
use tool_opensesame\api\opensesame;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/externallib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

/**
 * Class for Opensesame API.
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle US
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opensesame_handler_old {

    /** @var array */
    const OSCOURSE_TO_MCOURSE_MAPPING = [
            /*
             * $data->fullname = $osdataobject->title;
        $data->shortname = $osdataobject->title;
        $data->idnumber = $osdataobject->idopensesame;
        $data->summary = $osdataobject->descriptiontext;
             */
            'title' => 'fullname',
            'title' => 'shortname',
            'idopensesame' => 'idnumber',
            'descriptiontext' => 'summary',
            'startdate' => 'startdate',
            'enddate' => 'enddate',
    ];

    /** @var array */
    const REGISTRATION_DATA_TO_REGISTRATION_FIELD_MAPPINGS = [
            'Id' => 'altaiid',
            'Event,Id' => 'altaieventid',
            'Attendee,Id' => 'altaiattendeeid',
    ];

    /**
     * API URL.
     *
     * @var string URL for Opensesame API
     */
    private $authurl;

    /**
     * API Clientid.
     *
     * @var string
     */
    private $clientid;

    /**
     * API client secret.
     *
     * @var string
     */
    private $clientsecret;

    /**
     * API baseurl.
     *
     * @var string
     */
    private $baseurl;

    /**
     * API customer integration id.
     *
     * @var string
     */
    private $customerintegrationid;

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
            $authurl = null, $clientid = null, $clientsecret = null, $baseurl = null, $customerintegrationid = null) {
        $this->authurl = $authurl ?? get_config('tool_opensesame', 'authurl');
        $this->clientid = $clientid ?? get_config('tool_opensesame', 'clientid');
        $this->clientsecret = $clientsecret ?? get_config('tool_opensesame', 'clientsecret');
        $this->baseurl = $baseurl ?? get_config('tool_opensesame', 'baseurl');
        $this->customerintegration = $customerintegrationid ?? get_config('tool_opensesame', 'customerintegrationid');

        if (empty($this->authurl) || empty($this->clientid) || empty($this->clientsecret) || empty($this->baseurl)|| empty($this->customerintegration)) {
            throw new \moodle_exception('configerror', 'tool_opensesame');
        }
    }

    /**
     * The retrieve_courses function populates the opensesame table with data from the API.
     *
     * @param opensesame $api Opensesame API object.
     * Runs the import process.
     */
    public function retrieve_courses(opensesame $api = null) {
        if (is_null($api)) {
            $api = new opensesame($this->authurl, $this->clientid, $this->clientsecret, $this->customerintegrationid);
            $course_catalog_data = $api->set_authtoken();
            file_put_contents("debug.txt", "Inside Retrieved courses. ..$course_catalog_data. \n", FILE_APPEND);        }
        $this->process_courses($api);
    }

    /**
     * The create_courses function creates courses in Moodle.
     *
     * @param opensesame $api Opensesame API object.
     * Runs the import process.
     */
    public function create_courses(opensesame $api = null) {
        if (is_null($api)) {
            $api = new opensesame($this->authurl, $this->username, $this->password);
        }
        $this->set_authtoken($api);
        $this->process_events($api);
        $this->process_completions($api);
    }
    /**
     * Executes the data processing functions.
     *
     * @param opensesame $api Opensesame API object.
     * Runs the import process.
     */
    public function run(opensesame $api = null) {
        if (is_null($api)) {
            $api = new opensesame($this->authurl, $this->username, $this->password);
        }
        $this->set_authtoken($api);
        $this->process_completions($api);
    }


    
}
