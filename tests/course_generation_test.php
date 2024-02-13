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
 * File containing tests for Queueing creationg of opensesame courses.
 *
 * @package     tool_opensesame
 * @copyright   2023 Moodle
 * @author      2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_opensesame;

use advanced_testcase;
use file_storage;
use stdClass;
use tool_opensesame\local\opensesame_handler;
use tool_opensesame\api\opensesame;
use tool_opensesame\auto_config;

/**
 * Test class for opensesame retrieve, create record and queue adhoc tasks.
 *
 * @package     tool_opensesame
 * @copyright   2023 Moodle
 * @author      2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_generation_test extends advanced_testcase {

    /** @var tool_opensesame_generator */
    protected $opsmgenerator;

    /**
     * Test setup
     */
    protected function setUp(): void {
        $ac = new auto_config();
        $ac->configure();
        set_config('activity_name', 'courseid', 'tool_opensesame');
        set_config('activity_prefix', 'scorm_', 'tool_opensesame');
        $this->opsmgenerator = self::getDataGenerator()->get_plugin_generator('tool_opensesame');
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Test process_single_os_course
     */
    public function test_process_single_os_course() {

        global $DB, $CFG;

        // We create a mock of opensesame class.
        $opensesamemock = $this->createMock(opensesame::class);
        $opensesamehandlermock = $this->getMockBuilder(opensesame_handler::class)
            ->setConstructorArgs(
                [
                    'authurl',
                    'clientid',
                    'clientsecret',
                    'customerintegrationid',
                    'http://example.com/'
                    ]
            )
            ->onlyMethods(['process_created_to_imageimported'])
            ->getMock();

        $responsemock = new stdClass();
        $coursesnumber = 5;
        // Create some dummy data as the ws response.
        $courselist = $this->opsmgenerator->generate_courselist_opensesame_ws_response($coursesnumber);
        $responsemock->data = array_values($courselist);
        // Configure the mock to return the dummy API response data.
        $scormpath = $CFG->dirroot . '/admin/tool/opensesame/tests/fixtures/package.zip';
        $opensesamemock->method('get_course_list')
            ->willReturn($responsemock);
        $opensesamemock->method('download_scorm_package')
            ->willReturn($scormpath);
        $opensesamehandlermock->method('process_created_to_imageimported')
            ->willReturn('');

        $handler = $opensesamehandlermock;

        // We use the mock class.
        $handler->run($opensesamemock);

        // Info running the task.
        $opsesamecourses = $DB->get_records('tool_opensesame_course');
        $moodlecourses = $DB->get_records('course');
        // There should be only 1 record on course table.
        $this->assertCount($coursesnumber, $opsesamecourses);
        $this->assertCount(1, $moodlecourses);

        foreach ($opsesamecourses as $opcourse) {
            $this->assertEquals('queued', $opcourse->status);
            $handler->process_single_os_course($opcourse->id, $opensesamemock);
        }

        $opsesamecourses = $DB->get_records('tool_opensesame_course');
        $moodlecourses = $DB->get_records('course');

        // All the opensesame courses + course default.
        $this->assertCount($coursesnumber + 1, $moodlecourses);

        foreach ($opsesamecourses as $opcourse) {
            $this->assertNotEmpty($opcourse->courseid);
            $moodlecourse = $moodlecourses[$opcourse->courseid];
            $this->assertEquals($opcourse->idopensesame, $moodlecourse->idnumber);
            $this->assertEquals($opcourse->title, $moodlecourse->fullname);
            $this->assertStringContainsString($opcourse->descriptiontext, $moodlecourse->summary);
            $this->assertStringContainsString($opcourse->duration, $moodlecourse->summary);
            $this->assertStringContainsString($opcourse->publishername, $moodlecourse->summary);
            $this->assertEquals('scormimported', $opcourse->status);
        }
    }
}
