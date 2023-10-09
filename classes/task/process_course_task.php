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
        $oscourseid = $this->get_custom_data();
        !PHPUNIT_TEST ?? mtrace('[INFO] Process course task started');
        $handler = null;
        $handler = new opensesame_handler();
        $success = $handler->process_single_os_course($oscourseid);
        !PHPUNIT_TEST ?? mtrace('[INFO] Process course task finished');
        if (!$success) {
            self::queue_task($oscourseid);
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
