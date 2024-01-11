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
 * User token admin page.
 *
 * @package     tool_opensesame
 * @copyright   2023 Moodle US
 * @author      Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_opensesame\api\opensesame;
use tool_opensesame\local\data\opensesame_course;
use tool_opensesame\local\opensesame_handler;
use tool_opensesame\task\process_course_task;

// Requirements.
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
// External page setup.
admin_externalpage_setup('courses_status');

$PAGE->set_heading(get_string('opsecoursestatuspage', 'tool_opensesame'));
$baseurl = '/admin/tool/opensesame/opsesame_courses_status.php';

// Page variables.
$output = ''; // Final output to render.

// Process actions.
$page        = optional_param('page', 1, PARAM_INT);
$pagesize    = optional_param('pagesize', 50, PARAM_INT);
$resettasks    = optional_param('reset', 0, PARAM_BOOL);
$updatenames    = optional_param('updatenames', 0, PARAM_BOOL);


if ($page >= 1) {
    $page = $page - 1;
} else {
    $page = 0;
}

$countallopcourses = opensesame_course::count_op_courses();
$templatedata = opensesame_course::export_for_mustache($page, $pagesize);
$pagecount = ceil($countallopcourses / $pagesize);
$pages = [1];
if ($pagecount >= 1) {
    $pages = range(1, $pagecount);
}

$paginationurl = new \moodle_url($baseurl);
$paginationurl->params([
    'pagesize' => $pagesize,
]);
$currentpage = $page + 1;
$queueblocked = process_course_task::queue_is_blocked();
$activities = opensesame_course::op_activities();

$templatecontext = [
    'data' => $templatedata,
    'pages' => $pages,
    'changenames' => true,
    'currentpage' => $currentpage,
    'prevpage' => $currentpage - 1 ? $currentpage - 1 : false,
    'nextpage' => $currentpage < $pagecount ? $currentpage + 1 : false,
    'paginationurl' => $paginationurl->out(false),
    'adhocblocked' => $queueblocked
];
if (!empty($resettasks) && $queueblocked) {
    process_course_task::reset_fail_sync_count();
    $opsecourses = $DB->get_recordset('tool_opensesame_course', ['status' => 'queued']);
    foreach ($opsecourses as $opsecourse) {
        process_course_task::queue_task($opsecourse->id);
    }
    $opsecourses->close();
    redirect(new moodle_url($baseurl), get_string('resumeadhoc', 'tool_opensesame'), null);
}

if ($updatenames) {
    foreach ($activities as $activity) {
        $scorm = $DB->get_record('scorm', ['id' => $activity->id]);
        $opcourse = $DB->get_record('tool_opensesame_course', ['idopensesame' => $activity->idopensesame]);
        $scorm->name = opensesame_handler::generate_activity_name($opcourse);
        $DB->update_record('scorm', $scorm);
    }

    redirect(new moodle_url($baseurl), get_string('namesupdated', 'tool_opensesame'), null);
}

$output .= $OUTPUT->render_from_template('tool_opensesame/opensesame_courses_table', $templatecontext);

// Render output.
echo $OUTPUT->header();
echo $output;
echo $OUTPUT->footer();
