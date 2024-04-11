<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

/**
 * File containing tests for testing queries execution.
 *
 * @package     tool_opensesame
 * @copyright   2024 Moodle
 * @author      2024 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_opensesame;

use advanced_testcase;
use tool_opensesame\local\opensesame_handler;
use tool_opensesame\local\data\opensesame_course;

/**
 * Test class for testing queries execution.
 *
 * @package     tool_opensesame
 * @copyright   2024 Moodle
 * @author      2024 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class queries_test extends advanced_testcase {

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test retrieve_and_process_queue_courses.
     */
    public function test_queries_funtions() {

        // This both test are to make sure the queries runs on several db engines.
        $this->assertTrue(opensesame_handler::delete_disabled_courses());
        $response = opensesame_course::op_activities();
        $this->assertEmpty($response);
    }
}
