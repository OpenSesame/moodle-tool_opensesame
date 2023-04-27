# OpenSesame admin tool

The OpenSesame admin tool (tool_opensesame) provides communication between 
Moodle and OpenSesame. It also contains all the main settings required for
OpenSesame to function. OpenSesame is course integration tool providing the
ability to automatically create and configure Opensesame Courses within Moodle. 

## Installation

You can download the admin tool plugin from:

https://github.com/(PLACEHOLDER)/moodle-tool_opensesame

This plugin should be located and named as:
 [yourmoodledir]/admin/tool/opensesame

## Configuring the OpenSesame admin tool

Open the settings for the OpenSesame admin tool:

Site Administration > Plugins > Admin Tools > OpenSesame > 
Integration Settings/AICC Configurations


The Customer Integration Id and Allowed Types (scorm type), Client Id,
Client Secret, Authorization URL, and Base URL are all fields that are located
under integration settings. These fields should be populated with values
provided to you by an OpenSesame representative.

If the site Allowed types is set to AICC URL only. The site AICC Configurations link will allow for autoconfiguration of the AICC settings.  The Aicc
Configurations link is located in Site Administration > Courses > OpenSesame >
AICC configurations and Confirm the automated message to automatically set the
AICC configurations to allow the OpenSesame courses to properly be configured
upon creation. A confirmation message will read 'Success - the Opensesame API
required AICC configurations has been automatically configured.'.

## Additional plugins

The OpenSesame admin tool isn't useful on its own. The main functionality of
OpenSesame is provided by two other plugins which you should download and
install.

### OpenSesame Scheduled Tasks
Navigation: Site Administration > Server > Tasks > Scheduled tasks

Title: OpenSesame Course Sync 

Documentation: https://docs.moodle.org/402/en/Scheduled_tasks

## Uninstall
1. Remove the `tool_opensesame` plugin from the Moodle folder:
   * [yourmoodledir]/admin/tool/opensesame
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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
