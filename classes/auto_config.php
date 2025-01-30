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
 * AICC auto configuration tool.
 * @package     tool_opensesame
 * @copyright   2023 Moodle
 * @author      Felicia Wilkes <felicia.wilkes@moodle.com>
 * @author      David Castro <david.castro@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_opensesame;

/**
 * auto_config class
 */
class auto_config {
    /**
     * AICC configuration.
     */
    public function configure() {
        $this->enable_aicc();
    }

    /**
     * Enable AICC.
     */
    private function enable_aicc(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        $allowedtypes = get_config('tool_opensesame', 'allowedtypes');
        if ($allowedtypes == SCORM_TYPE_AICCURL) {
            set_config('aicchacpkeepsessiondata', 1, 'scorm');
            set_config('aicchacptimeout', 30, 'scorm');
            set_config('aiccuserid', 1, 'scorm');
            set_config('allowaicchacp', 1, 'scorm');
            set_config('allowtypeexternalaicc', 1, 'scorm');
        }
    }
}
