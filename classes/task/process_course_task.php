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
use tool_opensesame\local\opensesame_handler;

/**
 * Ad-hoc task for processing single open sesame courses.
 *
 * @package     tool_opensesame
 * @copyright   2023 Moodle US
 * @author      David Castro <david.castro@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_course_task extends \core\task\adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        $failcount = get_config('tool_opensesame', 'process_course_task_fails_count');
        $maxfails = get_config('tool_opensesame', 'max_consecutive_fails');
        $maxfails = !empty($maxfails) ? $maxfails : 5;
        if ($maxfails < $failcount) {
            $oscourseid = $this->get_custom_data();
            !PHPUNIT_TEST ? mtrace('[INFO] Process course task started') : false;
            $handler = null;
            $handler = new opensesame_handler();
            $success = $handler->process_single_os_course($oscourseid);
            !PHPUNIT_TEST ? mtrace('[INFO] Process course task finished') : false;
            if ($success) {
                // Restart count.
                set_config('process_course_task_fails_count', 0, 'tool_opensesame');
            } else {
                $failcount = !empty($failcount) ? $failcount + 1 : 1;
                set_config('process_course_task_fails_count', $failcount, 'tool_opensesame');
                self::queue_task($oscourseid);
                if ($failcount >= $maxfails) {
                    !PHPUNIT_TEST ? mtrace('PURGING OPENSESAME TASKS DUE CONSECUTIVE FAILS') : false;
                    // Let's clean adhoc task table.
                    $adhoctasks = $DB->get_recordset('task_adhoc', ['component' => 'tool_opensesame']);
                    foreach($adhoctasks as $adhoctask) {
                        $DB->delete_records('task_adhoc', ['id' => $adhoctask->id]);
                    }
                    $adhoctasks->close();

                    !PHPUNIT_TEST ? mtrace('REVERTING OPENSESAME COURSES STATUS') : false;
                    // Return the status to retrieved so we can queue again when the issue is solved.
                    $opsecourses = $DB->get_recordset('tool_opensesame_course', ['status' => 'queued']);
                    foreach($opsecourses as $opsecourse) {
                        $opsecourse->status = 'retrieved';
                        $DB->update_record('tool_opensesame_course', $opsecourse);
                    }
                    $opsecourses->close();
                }
            }
        }

        return true;
    }

    /**
     * Queues this task to run in the next 5 minutes.
     * @param string $oscourseid
     */
    public static function queue_task(string $oscourseid) {
        $task = new self();
        $task->set_custom_data($oscourseid);
        $futuretime = time() + (5 * MINSECS);
        $task->set_next_run_time($futuretime);
        \core\task\manager::queue_adhoc_task($task);
    }
}
