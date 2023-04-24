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
 * AICC auto configuration page
 *
 * @package    tool_opensesame
 * @subpackage opensesame
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_opensesame\auto_config;

require(__DIR__.'/../../../config.php');

$PAGE->set_url(new moodle_url('/admin/tool/opensesame/autoconfigaicc.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('autoconfigure', 'tool_opensesame'));

require_login();
require_capability('moodle/site:config', context_system::instance());

$action = optional_param('action', null, PARAM_ALPHA);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('autoconfigure', 'tool_opensesame'));

if ($action === 'confirm' || $action === 'view') {
    if ($action === 'confirm') {
        $ac = new auto_config();
        $ac->configure();
    }
    $data = [];
    echo $OUTPUT->render_from_template('tool_opensesame/auto_conf_result', $data);
    echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'tool_opensesame']));
} else {
    $continueurl = new moodle_url('/admin/tool/opensesame/autoconfigaicc.php', ['action' => 'confirm']);
    $cancelurl = new moodle_url('/admin/settings.php', ['section' => 'tool_opensesame']);
    echo $OUTPUT->confirm(get_string('autoconfigureconfirmation', 'tool_opensesame'), $continueurl, $cancelurl);
}

echo $OUTPUT->footer();
