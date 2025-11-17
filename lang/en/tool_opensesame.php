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
 * Strings for component 'tool_opensesame', language 'en'
 *
 * @package    tool_opensesame
 * @copyright  2023 Moodle
 * @author     Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author     David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_name'] = 'Activity name';
$string['activity_name_desc'] = 'Select the schema to be used in the Activity name.';
$string['activity_prefix'] = 'Activity prefix';
$string['activity_prefix_desc'] = 'Enter the prefix to be used in the Activity name.';
$string['adhocblocked'] = 'Adhoc task queue for OpenSesame course creation has been blocked due to consecutive task failures.';
$string['aicc'] = 'AICC configurations';
$string['aiccnotactive'] = 'AICC is not active.';
$string['allowedtypes'] = 'Allowed types';
$string['allowedtypes_desc'] = 'Which scorm type should appear in OpenSesame Course API Integration.';
$string['apiauthtoken'] = 'API Authorization token';
$string['apiauthtokendesc'] = 'API Authorization Token for OpenSesame Integration';
$string['apicall_pagesize'] = 'API page size';
$string['apicall_pagesize_desc'] = 'Page size for the api call when retrieving OpenSesame courses';
$string['apitokenempty'] = 'TOKEN of OpenSesame API is not configured correctly.';
$string['apiurlempty'] = 'URL of OpenSesame API is not configured correctly.';
$string['archived'] = 'Course archived';
$string['archived_info'] = 'The OpenSesame course has been archived on OpenSesame and deleted from Moodle.';
$string['authurl'] = 'Authorization API URL';
$string['authurl_missing'] = 'authurl missing';
$string['authurldesc'] = 'Authorization API URL for OpenSesame Integration';
$string['autoconfigsuccess'] = 'Success - the OpenSesame API required AICC configurations has been automatically configured.';
$string['autoconfigure'] = 'Auto configure AICC Configuration';
$string['autoconfigureconfirmation'] = 'Automatically enable aicc configurations that are required by the OpenSesame API. The following actions will be carried out: <ul><li>Enable AICC protocol support</li></ul>';
$string['autoconfiguredesc'] = 'Automatically  AICC Configuration required by the OpenSesame API.';
$string['baseurl'] = 'Base API URL';
$string['baseurl_missing'] = 'baseurl missing';
$string['baseurldesc'] = 'Base API URL for OpenSesame Integration';
$string['bearertoken'] = 'Bearer Token';
$string['bearertokendesc'] = 'API Authorization Token generated for OpenSesame Integration';
$string['cachedef_bearertokencreatetime'] = 'The time the bearer token was first created.';
$string['clientid'] = 'Client Id';
$string['clientid_missing'] = 'clientid missing';
$string['clientiddesc'] = 'Client Id for OpenSesame Integration';
$string['clientsecret'] = 'Client Secret';
$string['clientsecret_missing'] = 'clientsecret missing';
$string['clientsecretdesc'] = 'Client Secret for OpenSesame Integration';
$string['courseid'] = 'Course ID';
$string['coursename'] = 'Course name';
$string['coursesyncfailmax'] = 'Maximum consecutive task failures.';
$string['coursesyncfailmax_desc'] = 'If adhoc tasks fails consecutively adhoc task creation will blocked';
$string['created'] = 'Course created';
$string['created_info'] = 'The OpenSesame course has been created in Moodle.';
$string['customerintegrationid'] = 'Customer Integration Id';
$string['customerintegrationid_missing'] = 'customerintegrationid missing';
$string['customerintegrationiddesc'] = 'Customer Integration Id for OpenSesame Integration';
$string['guid'] = 'GUID';
$string['imageimported'] = 'Course image imported';
$string['imageimported_info'] = 'The OpenSesame course image has been added to the Moodle course.';
$string['localandaiccurl'] = 'Local and AICC URL';
$string['namesupdated'] = 'Success - the schema names have been updated.';
$string['onlyaiccurl'] = 'AICC URL only';
$string['onlylocal'] = 'Local only';
$string['opcategory'] = 'OpenSesame category';
$string['opcategory_desc'] = 'Select parent for OpenSesame categories';
$string['opensesamecat'] = 'OpenSesame';
$string['opensesameintegration'] = 'Integration Settings';
$string['opensesamesync'] = 'OpenSesame Course Sync';
$string['opsecoursestatuspage'] = 'OpenSesame courses status';
$string['optableth:actions'] = 'Actions';
$string['optableth:category'] = 'OpenSesame categories';
$string['optableth:course'] = 'Course';
$string['optableth:id'] = 'GUID';
$string['optableth:status'] = 'Status';
$string['optableth:title'] = 'Title';
$string['pluginname'] = 'OpenSesame Integration';
$string['prefix'] = 'Use prefix as name';
$string['queued'] = 'Queued';
$string['queued_info'] = 'The OpenSesame course has been queued for creation and sync.';
$string['resetfailtasks'] = 'Resume adhoc queue';
$string['resumeadhoc'] = 'The adhoc task queue for OpenSesame has been resumed.';
$string['resyncallqueued'] = 'All OpenSesame courses have been queued for resync.';
$string['resynccourses'] = 'Resync all courses';
$string['resyncqueued'] = 'The courseid {$a} has been queued for resync.';
$string['retrieved'] = 'Retrieved';
$string['retrieved_info'] = 'The course information has been retrieved from OpenSesame.';
$string['scormimported'] = 'Course activity synced';
$string['scormimported_info'] = 'The OpenSesame activity has been added to the Moodle course, sync is complete.';
$string['statuscode400'] =
        '400 Bad Request response status code indicates that the server cannot or will not process the request due to something that is perceived to be a client error';
$string['statusinfo'] = 'Status info';
$string['statusinfo_body'] = '
<p>Retrieved: The course information has been retrieved from OpenSesame.</p>
<p>Queued: The OpenSesame course has been queued for creation and sync.</p>
<p>Course created: The OpenSesame course has been created in Moodle.</p>
<p>Course image imported: The OpenSesame course image has been added to the Moodle course.</p>
<p>Course activity synced: The OpenSesame activity has been added to the Moodle course, sync is complete.</p>
<p>Course archived: The OpenSesame course has been archived on OpenSesame and deleted from Moodle.</p>
';
$string['updateactivityname'] = 'To update the activity name with the latest schema names, click the button below.';
$string['updatenames'] = 'Update';
