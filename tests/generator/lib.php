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
 * Create data for unit testing
 *
 * @package tool_opensesame
 * @category test
 * @copyright 2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_opensesame_generator extends component_generator_base {

    /**
     * Create dummy data for opensesame to manage
     *
     * @param int $numrecords Quantity of records to create
     * @param string $url Dummy url.
     *
     * @return array
     */
    public function generate_courselist_opensesame_ws_response($numrecords, $url = 'https://example.com') {
        $records = [];
        $testlangs = ['test_lang'];

        for ($i = 0; $i < $numrecords; $i++) {
            $record = [
                "id" => "idopensesame-$i",
                "title" => "Default Title $i",
                "descriptionText" => "descriptiontext-$i",
                "descriptionHtml" => "descriptionhtml-$i",
                'imageUrl' => "$url/imageurl_$i.jpg",
                "thumbnailUrl" => "$url/thumbnail_$i.jpg",
                "duration" => sprintf("%02d:%02d:%02d", rand(0, 23), rand(0, 59), rand(0, 59)),
                "languages" => $testlangs,
                "categories" => ["|1st Parent Category_$i|1st Sub Category_$i", "|2nd Parent Category_$i|2nd Sub Category_$i"],
                "publisherName" => "Publisher XYZ_$i",
                "packageDownloadUrl" => "$url/package_$i.zip",
                "aiccLaunchUrl" => "$url/aicc_$i",
                "active" => rand(0, 1),
                "timecreated" => time(),
                "timemodified" => time(),
                "usermodified" => time(),
            ];

            $records["idopensesame-$i"] = (object)$record;
        }

        return $records;
    }
}
