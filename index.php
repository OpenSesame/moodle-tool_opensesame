<?php
//// This file is part of Moodle - http://moodle.org/
////
//// Moodle is free software: you can redistribute it and/or modify
//// it under the terms of the GNU General Public License as published by
//// the Free Software Foundation, either version 3 of the License, or
//// (at your option) any later version.
////
//// Moodle is distributed in the hope that it will be useful,
//// but WITHOUT ANY WARRANTY; without even the implied warranty of
//// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//// GNU General Public License for more details.
////
//// You should have received a copy of the GNU General Public License
//// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
///**
// * Index.php
// *
// * @package    tool
// * @subpackage opensesame
// * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
// * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
// */
//
//require('../../../config.php');
////require_once($CFG->libdir . '/adminlib.php');
////admin_externalpage_setup('toolopensesame');
//
////protect the page
//require_login();
////only Site admins
//if (!is_siteadmin()) {
//    print_error('noprermissions', 'core');
//}
//
////set up the page for display
//$PAGE->set_context(context_system::instance());
//$PAGE->set_pagelayout('admin');
//$PAGE->set_heading($SITE->fullname);
//$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'tool_opensesame'));
//$PAGE->set_url('/tool/opensesame/index.php');
//$PAGE->set_pagetype('admin-' . $PAGE->pagetype);
//
//echo $OUTPUT->header();
//echo $OUTPUT->heading(get_string('pluginname', 'tool_opensesame'));
//
////testing opensesame task in userinterface
//$task = new \tool_opensesame\task\opensesamesync();
//
//echo html_writer::tag('div', html_writer::tag('p', $task->get_name()));
//try {
//    $task->execute(true);
//} catch (Exception $e) {
//    echo $OUTPUT->notification($e->getMessage(), 'error');
//    echo $OUTPUT->footer();
//    die;
//}
//echo $OUTPUT->notification('Successfully executed.', 'success');
//echo $OUTPUT->footer();