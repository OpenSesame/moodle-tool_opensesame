# OpenSesame Connector

The OpenSesame Connector (tool_opensesame) configures communication between
Moodle and OpenSesame. It also contains all of the main settings required to integrate with OpenSesame content. OpenSesame is a course integration tool providing the
ability to automatically create and configure Opensesame courses within Moodle.

## Installation

You can download the OpenSesame Connector plugin [here](https://github.com/OpenSesame/moodle-tool_opensesame).

This plugin should be located and named as:

`[yourmoodledir]/admin/tool/opensesame`

## Configuring the OpenSesame Connector

Open the settings for the OpenSesame Connector following this file path:

`Site Administration > Plugins > Admin Tools > OpenSesame >
Integration Settings/AICC Configurations`

The following fields should be populated with values provided to you by an OpenSesame representative and are all located under "Integration Settings".

- Customer Integration Id and Allowed Types (scorm type), Client Id,
  Client Secret, Authorization URL, and Base URL

If the site `Allowed types` is set to `AICC URL only`, the site "AICC Configurations" link will allow for autoconfiguration of the AICC settings.

The "AICC Configurations" link is located at:

`Site Administration > Courses > OpenSesame >
AICC configurations`

Confirm the automated message to automatically set the
"AICC configurations" to allow the OpenSesame courses to properly be configured
upon creation. A confirmation message will read 'Success - the Opensesame API
required AICC configurations has been automatically configured.'.

## Additional configuration

The OpenSesame Connector requires a scheduled task to synchronize courses that you want to provide to your students. We recommend that you configure the scheduled task to run at least once a day during non-peak hours.

The scheduled task is named "OpenSesame Course Sync" and can be found within:

`Site Administration > Server > Tasks > Scheduled Tasks`

## Uninstall

1. Remove the `tool_opensesame` plugin from the Moodle folder:
   - [yourmoodledir]/admin/tool/opensesame
2. Access the plugin uninstall page: Site Administration > Plugins >
   Plugins overview
3. Look for the removed plugins and click on uninstall-link for the plugin.

## License for OpenSesame admin tool

This file is part of Moodle - http://moodle.org/

Moodle is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Moodle is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Moodle. If not, see <http://www.gnu.org/licenses/>.
