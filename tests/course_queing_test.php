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
use stdClass;
use tool_opensesame\local\opensesame_handler;
use tool_opensesame\api\opensesame;

/**
 * Test class for opensesame retrieve and create record and queue adhoc tasks.
 *
 * @package     tool_opensesame
 * @copyright   2023 Moodle
 * @author      2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_queing_test extends advanced_testcase {

    /** @var tool_opensesame_generator */
    protected $opsmgenerator;

    /**
     * Test setup
     *
     * @return void
     */
    protected function setUp(): void {
        $this->opsmgenerator = self::getDataGenerator()->get_plugin_generator('tool_opensesame');
        $this->resetAfterTest();
    }

    /**
     * Test retrieve_and_process_queue_courses.
     *
     * @return void
     */
    public function test_retrieve_and_process_queue_courses() {

        global $DB;

        // We create a mock of opensesame class.
        $opensesamemock = $this->createMock(opensesame::class);

        $responsemock = new stdClass();
        $coursesnumber = 5;

        // Create some dummy data as the ws response.
        $courselist = $this->opsmgenerator->generate_courselist_opensesame_ws_response($coursesnumber);
        $responsemock->data = array_values($courselist);

        // Configure the mock to return the dummy API response data.
        $opensesamemock->method('get_course_list')
            ->willReturn($responsemock);

        $handler = new opensesame_handler(
            'authurl',
            'clientid',
            'clientsecret',
            'customerintegrationid',
            'http://example.com/'
        );

        // We use the mock class.
        $handler->run($opensesamemock);

        $opsesamecourses = $DB->get_records('tool_opensesame_course');
        $opsesameadhoctasks = $DB->get_records('task_adhoc', ['component' => 'tool_opensesame'], '', 'customdata');
        $opcoursecategories = $DB->get_records('course_categories');

        // The amount of records on opensesame and adhoc task should
        // be the same as the number  opensesamecourse on ws response.
        $this->assertCount($coursesnumber, $opsesamecourses);
        $this->assertCount($coursesnumber, $opsesameadhoctasks);
        // Each course must create 4 categories + Default category.
        $this->assertCount($coursesnumber * 4 + 1, $opcoursecategories);

        foreach ($opsesamecourses as $courserecords) {
            $mockcourse = $courselist[$courserecords->idopensesame];
            $this->assertTrue(isset($opsesameadhoctasks['"'.$courserecords->id.'"']));
            $this->assertEquals($mockcourse->id, $courserecords->idopensesame);
            $this->assertEquals($mockcourse->title, $courserecords->title);
            $this->assertEquals($mockcourse->descriptionText, $courserecords->descriptiontext);
            $this->assertEquals($mockcourse->descriptionHtml, $courserecords->descriptionhtml);
            $this->assertEquals($mockcourse->thumbnailUrl, $courserecords->thumbnailurl);
            $this->assertEquals($mockcourse->duration, $courserecords->duration);
            $this->assertContains($courserecords->languages, $mockcourse->languages);
            foreach ($mockcourse->categories as $category) {
                $this->assertStringContainsString($category, $courserecords->oscategories);
            }
            $this->assertEquals($mockcourse->publisherName, $courserecords->publishername);
            $this->assertEquals($mockcourse->packageDownloadUrl, $courserecords->packagedownloadurl);
            $this->assertEquals($mockcourse->aiccLaunchUrl, $courserecords->aicclaunchurl);
            $this->assertEquals($mockcourse->active, $courserecords->active);
            $this->assertEquals('queued', $courserecords->status);
            $this->assertNull($courserecords->courseid);
        }
    }
}
