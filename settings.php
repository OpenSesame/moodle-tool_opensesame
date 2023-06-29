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
 * Capability overview settings
 *
 * @package    tool_opensesame
 * @subpackage opensesame
 * @copyright  2023 Felicia Wilkes <felicia.wilkes@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;

if ($hassiteconfig) {

    // Place settings category named opensesameintegration under tab courses.
    $ADMIN->add('tools',
            new admin_category('opensesameintegration', new lang_string('opensesamecat', 'tool_opensesame')),
    );
    // Places the link to the settingspage under the new category.
    $settings = new admin_settingpage('tool_opensesame', get_string('opensesameintegration', 'tool_opensesame'));
    // Creating new settings to add the new settingspage.

    if (!is_array($CFG->forced_plugin_settings['tool_opensesame'])) {
        $settings->add(new admin_setting_configtext('tool_opensesame/clientid', get_string('clientid', 'tool_opensesame'),
                get_string('clientiddesc', 'tool_opensesame'), '', PARAM_RAW));
        $settings->add(new admin_setting_configpasswordunmask('tool_opensesame/clientsecret',
                get_string('clientsecret', 'tool_opensesame'),
                get_string('clientsecretdesc', 'tool_opensesame'), ''));
        $settings->add(new admin_setting_configtext('tool_opensesame/authurl', get_string('authurl', 'tool_opensesame'),
                get_string('authurldesc', 'tool_opensesame'), 'https://auth.coursecloud.net/oauth2/aus1l01v8s55riV0C0h8/v1/token',
                PARAM_URL));
        $settings->add(new admin_setting_configtext('tool_opensesame/baseurl', get_string('baseurl', 'tool_opensesame'),
                get_string('baseurldesc', 'tool_opensesame'), 'https://api.delivery.opensesame.com', PARAM_URL));
    }

    $settings->add(new admin_setting_configpasswordunmask('tool_opensesame/customerintegrationid',
            get_string('customerintegrationid', 'tool_opensesame'),
            get_string('customerintegrationiddesc', 'tool_opensesame'), ''));

    // Add scorm type select.
    // Types allowed.

    require_once($CFG->dirroot . '/mod/scorm/lib.php');
    $options = array(

            SCORM_TYPE_LOCAL => new lang_string('onlylocal', 'tool_opensesame'),
            SCORM_TYPE_AICCURL => new lang_string('onlyaiccurl', 'tool_opensesame')
    );
    $name = get_string('allowedtypes', 'tool_opensesame');
    $desc = get_string('allowedtypes_desc', 'tool_opensesame');
    $default = SCORM_TYPE_LOCAL;
    $settings->add(new admin_setting_configselect('tool_opensesame/allowedtypes', $name, $desc, $default, $options));

    $config     = get_config('tool_opensesame');
    $configured = !empty($config) &&
            !empty($config->clientid) &&
            !empty($config->clientsecret) &&
            !empty($config->authurl) &&
            !empty($config->baseurl) &&
            !empty($config->allowedtypes);

    if ($configured &&
            $config->allowedtypes === 'aiccurl') {
        // Add external page to manage OpenSesame AICC Link Configurations.
        $ADMIN->add('opensesameintegration', new admin_externalpage('aicc_config', new lang_string('aicc', 'tool_opensesame'),
                new moodle_url('/admin/tool/opensesame/autoconfigaicc.php')));
    }

    // Add to the admin settings for opensesameintegration.
    $ADMIN->add('opensesameintegration', $settings);
}

