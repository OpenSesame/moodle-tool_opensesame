# OpenSesame admin tool

The OpenSesame admin tool (tool_openSesame) provides communication between Moodle and OpenSesame. It also
contains all the main settings required for OpenSesame to function. OpenSesame is course integration tool providing the ability to automatically create and configure Opensesame Courses within Moodle. 

## Installation

You can download the admin tool plugin from:

https://github.com/(PLACEHOLDER)/moodle-tool_openSesame

This plugin should be located and named as:
 [yourmoodledir]/admin/tool/openSesame

## Configuring the OpenSesame admin tool

Open the settings for the OpenSesame admin tool:

Site Administration > Courses > OpenSesame

The Customer Integration Id and Allowed Types (scorm type) are located under integration settings. The Client Id, Client Secret, Authorization URL, and Base URL are all fields that should be placed as forced settings in the Moodle site config.php file. However, if these settings are not located in the config.php file they will appear in the Integration Setting location as well. These fields should be populated with values provided to you by an
OpenSesame representative.

If Allowed types is set to AICC URL only for the scorm type being utilized in the Opensesame Courses is selected you will need to go to the Aicc Configurations link located in Site Administration > Courses > OpenSesame > AICC configurations and Confirm the automated message to automatically set the AICC configurations to allow the OpenSesame courses to properly be configured upon creation. A confirmation message will read 'Success - the Opensesame API required AICC configurations has been automatically configured.'.

## Additional plugins

The OpenSesame admin tool isn't useful on its own. The main functionality of OpenSesame is provided by two other plugins which you
should download and install:

### OpenSesame Scheduled Tasks
Navigation: Site Administration > Server > Tasks > Scheduled tasks

Title: OpenSesame Course Sync 

Configuration: click the settings icon next to the component name OpenSesame Integration. This will allow you to schedule your integration. Note this will require your sites crons to be properly configured and running.

Logs: Logs can be viewed and download via the note icon next to the setting icon.


## Uninstall
1. Remove the `tool_openSesame` plugin from the Moodle folder:
   * [yourmoodledir]/admin/tool/opensesame
   
2. Access the plugin uninstall page: Site Administration > Plugins > Plugins overview
3. Look for the removed plugins and click on uninstall for the plugin. 

## License for OpenSesame admin tool

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
