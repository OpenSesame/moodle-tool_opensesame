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

use context_course;
use tool_opensesame\Opensesameapi;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Scheduled task integrating with OpenSesame API every 24 hours.
 *
 *
 * @since      3.9
 * @package    tool_opensesame
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Opensesamesync extends \core\task\scheduled_task
{

    /**
     * Get a descriptive name for this task.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name()
    {
        return get_string('opensesamesync', 'tool_opensesame');
    }

    /**
     * Scheduled task to initiate Open Sesame API.
     *
     * @param null $testing
     * @return bool
     * @throws \dml_exception
     */
    public function execute($testing = null): bool
    {

        mtrace("Opensesame task just started.");

        $opensesameapi = new Opensesameapi;
        $opensesameapi->get_auth_token();

        mtrace('opensesame just finished.');
        return true;
    }
}

