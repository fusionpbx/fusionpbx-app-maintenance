<?php

/*
FusionPBX
Version: MPL 1.1

The contents of this file are subject to the Mozilla Public License Version
1.1 (the "License"); you may not use this file except in compliance with
the License. You may obtain a copy of the License at
http://www.mozilla.org/MPL/

Software distributed under the License is distributed on an "AS IS" basis,
WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
for the specific language governing rights and limitations under the
License.

The Original Code is FusionPBX

The Initial Developer of the Original Code is
Mark J Crane <markjcrane@fusionpbx.com>
Portions created by the Initial Developer are Copyright (C) 2008-2018
the Initial Developer. All Rights Reserved.

Contributor(s):
Mark J Crane <markjcrane@fusionpbx.com>
Tim Fry <tim.fry@hotmail.com>
*/

//check permission
require_once dirname(__DIR__, 2) . '/resources/require.php';
require_once "resources/check_auth.php";
require_once "resources/paging.php";

if (permission_exists('maintenance_view')) {
	// permission granted
} else {
	die('Unauthorized');
}

//token
$object = new token;
$token = $object->create($_SERVER['PHP_SELF']);

//internationalization
$language = new text;
$text = $language->get();

//create a new settings object ignoring the current domain
$settings = new settings();

//get the current list of all database and filesystem maintenance classes
$maintenance_classes = implementing_classes_arr('database_maintenance', 'filesystem_maintenance');

//get the list in the default settings
$default_settings_classes = $settings->get('maintenance', 'application', []);

//compare to the installed list
$difference = array_diff($maintenance_classes, $default_settings_classes);

//show the content
$document['title'] = $text['title-maintenance'];
require_once dirname(__DIR__, 2) . '/resources/header.php';


	echo "<div class='action_bar' id='action_bar'>";
	echo "<div class='heading'><b>Maintenance (<?= count($maintenance_classes) ?>)</b></div>";
	echo "<div class='actions'>";
		echo "<form action='maintenance_logs.php'>";
			echo "<button>Logs</button>";
		echo "</form>";
		echo "<form>";
			echo "<button>Register</button>";
			echo "<span><input id='search' type='text'/></span>";
			echo "<button>Search</button>";
		echo "</form>";
	echo "</div>";
	echo "<br/><br/>";
	echo "<form id='form_list'>";
		echo "<table class='list'>";
			echo "<tr class='list-header'>";
				echo "<th class='checkbox'><input type='checkbox'/></th>";
				echo "<th>Name</th>";
				echo "<th>Registered</th>";
				echo "<th>Database Enabled</th>";
				echo "<th>Retention Days</th>";
				echo "<th>File System Enabled</th>";
				echo "<th>Retention Days</th>";
			echo "</tr>";
			foreach ($maintenance_classes as $x => $class) {
				$obj = new $class;
				$installed = array_search($class, $difference) ? 'No' : 'Yes';
				if ($obj instanceof database_maintenance) {
					$database_maintenance_retention = $settings->get($obj->database_retention_category(), $obj->database_retention_subcategory(), '');
					$database_maintenance_enabled = empty($database_maintenance_retention) ? "No" : "Yes";
				} else {
					$database_maintenance_enabled = "";
					$database_maintenance_retention = "";
				}
				if ($obj instanceof filesystem_maintenance) {
					$filesystem_maintenance_retention = $settings->get($obj->filesystem_retention_category(), $obj->filesystem_retention_subcategory(), '');
					$filesystem_maintenance_enabled = empty($filesystem_maintenance_retention) ? "No" : "Yes";
				} else {
					$filesystem_maintenance_enabled = "";
					$filesystem_maintenance_retention = "";
				}

			echo "<tr class='list-row' style=''>";
				echo "<td class='center'><input type='checkbox' id='$class'/></td>";
				echo "<td>$class</td>";
				echo "<td ". ($installed=='No' ? "style=' background-color: var(--warning);'" : 'style=" background-color: none;"') .">$installed</td>";
				echo "<td>$database_maintenance_enabled</td>";
				echo "<td>$database_maintenance_retention</td>";
				echo "<td>$filesystem_maintenance_enabled</td>";
				echo "<td>$filesystem_maintenance_retention</td>";
			echo "</tr>";
			}
		echo "</table>";
	echo "</form>";
echo "</div>";

<?php
require_once dirname(__DIR__, 2) . '/resources/footer.php';
