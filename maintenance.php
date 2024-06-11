<?php
declare(strict_types=1);
/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2024
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

//check permission
require_once dirname(__DIR__, 2) . '/resources/require.php';
require_once "resources/check_auth.php";
require_once "resources/paging.php";
require_once __DIR__ . '/resources/functions.php';

if (permission_exists('maintenance_view')) {
	// permission granted
} else {
	die('Unauthorized');
}

if (!empty($_REQUEST['search'])) {
	$search = $_REQUEST['search'];
} else {
	$search = '';
}

//internationalization
$language = new text;
$text = $language->get();

//create a database object
$database = database::new();

//process registering maintenance applications
if (!empty($_REQUEST['action'])) {
	//validate the token
	$token = new token;
	if (!$token->validate($_SERVER['PHP_SELF'])) {
		message::add($text['message-invalid_token'], 'negative');
		header('Location: maintenance.php');
		exit;
	}
	$action = $_REQUEST['action'];
	$checked_apps = $_REQUEST['maintenance_apps'] ?? [];
	switch($action) {
		case 'toggle':
			if (permission_exists('maintenance_edit')) {
				if (maintenance::register_applications($database, $checked_apps)) {
					message::add($text['message-toggle']);
				} else {
					message::add($text['message-register_failed'], 'negative');
				}
			} else {
				message::add($text['message-action_prohibited'], 'negative');
			}
			break;
	}
	$toggle_maintenance_apps = $_REQUEST['toggle'];
	unset($token);
}

//load the settings
$default_settings = new settings(['database' => $database]);

//get the list in the default settings
$classes = $default_settings->get('maintenance', 'application', []);

//get the display array
$maintenance_apps = [];

//get all domains if the user has the permission to see them
if (permission_exists('maintenance_show_all')) {
	foreach ($classes as $class) {
		if (has_trait($class, 'database_maintenance')) {
			$maintenance_apps[$class]['database_maintenance'] = $class::database_maintenance_settings($database);
		}
		if (has_trait($class, 'filesystem_maintenance')) {
			$maintenance_apps[$class]['filesystem_maintenance'] = $class::filesystem_maintenance_settings($database);
		}
	}
}
else {
	$domain_uuid = $_SESSION['domain_uuid'];
	$domain_settings = new settings(['domain_uuid' => $domain_uuid]);
	//get only the local domain values
	foreach ($classes as $class) {
		if (has_trait($class, 'database_maintenance')) {
			$maintenance_apps[$class]['database_maintenance'][$domain_uuid]['domain_setting_value'] = $domain_settings->get($class, $class::database_retention_subcategory());
			$maintenance_apps[$class]['database_maintenance'][$domain_uuid]['domain_setting_enabled'] = true;
		}
		if (has_trait($class, 'filesystem_maintenance')) {
			$maintenance_apps[$class]['filesystem_maintenance'][$domain_uuid]['domain_setting_value'] = $domain_settings->get($class, $class::filesystem_retention_subcategory());
			$maintenance_apps[$class]['filesystem_maintenance'][$domain_uuid]['domain_setting_enabled'] = true;
		}
	}
}

//get the list of domains
$domain_names = maintenance::get_domain_names($database);

//create the token
$object = new token;
$token = $object->create($_SERVER['PHP_SELF']);

require_once dirname(__DIR__, 2) . '/resources/header.php';

//show the content
$document['title'] = $text['title-maintenance'];

	echo "<div class='action_bar' id='action_bar'>";
	echo "<div class='heading'><b>Maintenance (" . count($classes) . ")</b></div>";
	echo "<div class='actions'>";
		echo button::create(['type'=>'button','label'=>$text['button-logs'],'icon'=>'fas fa-scroll fa-fw','id'=>'btn_logs', 'link'=>'maintenance_logs.php']);
		echo button_toggle::create(['label'=>$text['button-register'],'icon'=>'fas fa-registered fa-fw']);
		echo button_show_all::create();
		echo "<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
		echo button_search::create(empty($search));
		echo button_reset::create(empty($search));
	echo "</div>";

	//javascript modal boxes
	echo modal_copy::create('form_list');
	echo modal_delete::create('form_list');
	echo modal_toggle::create('form_list');

	echo "<div style='clear: both;'></div>";
	echo "<br/><br/>";
	echo "<form id='form_list' method='post'>";
		echo "<input type='hidden' id='action' name='action' value=''>";
		echo "<input type='hidden' name='search' value=\"".escape($search)."\">";
		echo "<table class='list'>";
			echo "<tr class='list-header'>";
				echo "<th>Name</th>";
				if (permission_exists('maintenance_show_all')) {
					echo "<th>Domain</th>";
				}
				echo "<th>Database Enabled</th>";
				echo "<th>Retention Days</th>";
				echo "<th>File System Enabled</th>";
				echo "<th>Retention Days</th>";
			echo "</tr>";
			//list all maintenance applications from the defaults settings for global and each domain and show if they are enabled or disabled
			foreach ($maintenance_apps as $class => $app_settings) {
				//make the class name more user friendly
				$display_name = ucwords(str_replace('_', ' ', $class));
				//display global first
				if ((isset($app_settings['database_maintenance']['global']) || isset($app_settings['filesystem_maintenance']['global'])) && permission_exists('maintenance_show_all')) {
					echo "<tr class='list-row' style=''>";
						echo "<td>$display_name</td>";
							echo "<td>" . $text['label-global'] . "</td>";
						if (isset($app_settings['database_maintenance']['global'])) {
							$enabled = $app_settings['database_maintenance']['global']['default_setting_enabled'] ? $text['label-yes'] : $text['label-no'];
							$value = $app_settings['database_maintenance']['global']['default_setting_value'];
							echo "<td>$enabled</td>";
							echo "<td>$value</td>";
						} else {
							echo "<td>&nbsp;</td>";
							echo "<td>&nbsp;</td>";
						}
						if (isset($app_settings['filesystem_maintenance']['global'])) {
							$enabled = $app_settings['filesystem_maintenance']['global']['default_setting_enabled'] ? $text['label-yes'] : $text['label-no'];
							$value = $app_settings['filesystem_maintenance']['global']['default_setting_value'];
							echo "<td>$enabled</td>";
							echo "<td>$value</td>";
						} else {
							echo "<td>&nbsp;</td>";
							echo "<td>&nbsp;</td>";
						}
					echo "</tr>";
				}
				if (isset($app_settings['database_maintenance']) || isset($app_settings['filesystem_maintenance'])) {
					//get all domains with database traits
					$database_domain_uuids = array_keys($app_settings['database_maintenance'] ?? []);
					//get all domains with filesystem traits
					$filesystem_domain_uuids = array_keys($app_settings['filesystem_maintenance'] ?? []);
					//combine database and filesystem domain_uuids without duplicates
					$domain_uuids = $database_domain_uuids + $filesystem_domain_uuids;
					//loop through domains that have the database and filesystem traits
					foreach ($domain_uuids as $domain_uuid) {
						//skip global it has already been done
						if ($domain_uuid === 'global') {
							continue;
						}
						echo "<tr class='list-row' style=''>";
							echo "<td>$display_name</td>";
							if (permission_exists('maintenance_show_all')) {
								echo "<td>" . $domain_names[$domain_uuid] . "</td>";
							}
							if (isset($app_settings['database_maintenance'][$domain_uuid])) {
								$enabled = $app_settings['database_maintenance'][$domain_uuid]['domain_setting_enabled'] ? $text['label-yes'] : $text['label-no'];
								$value = $app_settings['database_maintenance'][$domain_uuid]['domain_setting_value'];
								echo "<td>$enabled</td>";
								echo "<td>$value</td>";
							} else {
								echo "<td>&nbsp;</td>";
								echo "<td>&nbsp;</td>";
							}
							if (isset($app_settings['filesystem_maintenance'][$domain_uuid])) {
								$enabled = $app_settings['filesystem_maintenance'][$domain_uuid]['domain_setting_enabled'] ? $text['label-yes'] : $text['label-no'];
								$value = $app_settings['filesystem_maintenance'][$domain_uuid]['domain_setting_value'];
								echo "<td>$enabled</td>";
								echo "<td>$value</td>";
							} else {
								echo "<td>&nbsp;</td>";
								echo "<td>&nbsp;</td>";
							}
						echo "</tr>";
					}
				}
			}
		echo "</table>";
		echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>";
	echo "</form>";
echo "</div>";

require_once dirname(__DIR__, 2) . '/resources/footer.php';
