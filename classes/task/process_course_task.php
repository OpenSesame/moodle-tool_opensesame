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
        if (!self::queue_is_blocked()) {
            $oscourseid = $this->get_custom_data();
            !PHPUNIT_TEST ? mtrace('[INFO] Process course task started') : false;
            $handler = null;
            $handler = new opensesame_handler();
            $success = $handler->process_single_os_course($oscourseid);
            !PHPUNIT_TEST ? mtrace('[INFO] Process course task finished') : false;
            if ($success) {
                // Restart count.
                self::reset_fail_sync_count();
            } else {
                $failcount = self::update_fail_sync_count();
                if (self::queue_is_blocked($failcount)) {
                    !PHPUNIT_TEST ? mtrace('[ERROR] Purging Opensesame sync tasks due to communication errors.') : false;
                    // Let's clean adhoc task table.
                    $adhoctasks = $DB->get_recordset('task_adhoc', ['component' => 'tool_opensesame']);
                    foreach ($adhoctasks as $adhoctask) {
                        $DB->delete_records('task_adhoc', ['id' => $adhoctask->id]);
                    }
                    $adhoctasks->close();
                } else {
                    self::queue_task($oscourseid);
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
        $futuretime = time() + (5 + MINSECS);
        $task->set_next_run_time($futuretime);
        \core\task\manager::queue_adhoc_task($task);
    }

     /**
     * Retrieve count of failed adhoc tasks on syncing opensesame courses.
     * @return int
     */
    public static function get_fail_sync_count() {
        $timeout = 5;
        $locktype = 'tool_opensesame_fail_sync_count';
        $resourse =  'sync_count';
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);
        // Some task could be trying to update the count so we better try to get the lock and wait.
        if ($lock = $lockfactory->get_lock($resourse, $timeout)) {
            $failcount = get_config('tool_opensesame', 'process_course_task_fails_count');
            $lock->release();

        } else {
            throw new \moodle_exception('locktimeout');
        }
        return $failcount;
    }

    /**
     * Updates count of failed adhoc tasks on syncing opensesame courses.
     * @param  bool $reset
     * @return void
     */
    public static function update_fail_sync_count($reset = false) {
        $timeout = 5;
        $locktype = 'tool_opensesame_fail_sync_count';
        $resourse =  'sync_count';
        $lockfactory = \core\lock\lock_config::get_lock_factory($locktype);
        // There could be several tasks trying to update or get the count value.
        if ($lock = $lockfactory->get_lock($resourse, $timeout)) {
            if ($reset) {
                $failcount = 0;
            } else {
                $failcount = get_config('tool_opensesame', 'process_course_task_fails_count');
                $failcount = !empty($failcount) ? $failcount + 1 : 1;
            }
            set_config('process_course_task_fails_count', $failcount, 'tool_opensesame');
            $lock->release();

        } else {
            throw new \moodle_exception('locktimeout');
        }
        return $failcount;
    }

    /**
     * Reset count of failed adhoc tasks on syncing opensesame courses.
     * @return void
     */
    public static function reset_fail_sync_count() {
        self::update_fail_sync_count(true);
    }

    /**
     * Check out if the queue to sync opensesame courses is blocked.
     * @param  int $failcount
     * @return bool
     */
    public static function queue_is_blocked($failcount = null) {
        $failcount = !is_null($failcount) ? $failcount : self::get_fail_sync_count();
        $maxfails = get_config('tool_opensesame', 'coursesyncfailmax');
        $maxfails = !empty($maxfails) ? $maxfails : 5;
        return $failcount >= $maxfails;
    }
}
