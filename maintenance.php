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
	$search = urldecode($_REQUEST['search']);
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

if (!empty($_REQUEST['show'])) {
	$show_all = ($_REQUEST['show'] == 'all') ? true : false;
} else {
	$show_all = false;
}

//order by
if (!empty($_REQUEST['order_by'])) {
	$order_by = $_REQUEST['order_by'];
} else {
	$order_by = '';
}

//paging
$rows_per_page = $_SESSION['domain']['paging']['numeric'] ?? 50;
if (!empty($_REQUEST['page'])) {
	$page = $_REQUEST['page'];
	$offset = $rows_per_page * $page;
} else {
	$page = '';
}

//load the settings
$default_settings = new settings(['database' => $database]);

//get the list in the default settings
$classes = $default_settings->get('maintenance', 'application', []);

//get the display array
$maintenance_apps = [];

if (permission_exists('maintenance_show_all') && $show_all) {
	//get the global settings
	$sql = "select default_setting_subcategory, default_setting_value, default_setting_enabled from v_default_settings";
	$sql .= " where default_setting_category = 'maintenance'";
	$sql .= " and (default_setting_subcategory like '%_database_retention_days' or default_setting_subcategory like '%_filesystem_retention_days')";
	$parameters = null;

	//filter based on search
	if (!empty($search)) {
		$search_param = "%$search%";
		$sql .= " and default_setting_subcategory like :search";
		$parameters['search'] = $search_param;
	}
	if (!empty($page)) {
		$sql .= limit_offset($rows_per_page, $offset);
	}

	$result = $database->execute($sql, $parameters, 'all');

	if (!empty($result)) {
		foreach ($result as $row) {
			if (str_ends_with($row['default_setting_subcategory'], '_database_retention_days')) {
				$class_name = substr($row['default_setting_subcategory'], 0, -1 * strlen('_database_rentention_days') + 1);
				$maintenance_apps[$class_name]['database_maintenance']['global'] = $row;
			}
			if (str_ends_with($row['default_setting_subcategory'], '_filesystem_retention_days')) {
				$class_name = substr($row['default_setting_subcategory'], 0, -1 * strlen('_filesystem_rentention_days') + 1);
				$maintenance_apps[$class_name]['filesystem_maintenance']['global'] = $row;
			}
		}
	}
	//get the domain settings
	$sql = "select domain_uuid, domain_setting_subcategory, domain_setting_value, domain_setting_enabled from v_domain_settings";
	$sql .= " where domain_setting_category = 'maintenance'";
	$sql .= " and (domain_setting_subcategory like '%_database_retention_days' or domain_setting_subcategory like '%_filesystem_retention_days')";

	//filter based on search
	if (!empty($search)) {
		$search_param = "%$search%";
		$sql .= " and domain_setting_subcategory like :search";
		$parameters['search'] = $search_param;
	}
	if (!empty($page)) {
		$sql .= limit_offset($rows_per_page, $offset);
	}

	$result = $database->execute($sql, $parameters, 'all');

	if (!empty($result)) {
		foreach ($result as $row) {
			if (str_ends_with($row['domain_setting_subcategory'], '_database_retention_days')) {
				$class_name = substr($row['domain_setting_subcategory'], 0, -1 * strlen('_database_rentention_days') + 1);
				$maintenance_apps[$class_name]['database_maintenance'][$row['domain_uuid']] = $row;
			}
			if (str_ends_with($row['domain_setting_subcategory'], '_filesystem_retention_days')) {
				$class_name = substr($row['domain_setting_subcategory'], 0, -1 * strlen('_filesystem_rentention_days') + 1);
				$maintenance_apps[$class_name]['filesystem_maintenance'][$row['domain_uuid']] = $row;
			}
		}
	}
} else {
	$domain_uuid = $_SESSION['domain_uuid'];
	//show only the current domain settings
	$sql = "select domain_uuid, domain_setting_subcategory, domain_setting_value, domain_setting_enabled from v_domain_settings";
	$sql .= " where domain_setting_category = 'maintenance'";
	$sql .= " and (domain_setting_subcategory like '%_database_retention_days' or domain_setting_subcategory like '%_filesystem_retention_days')";
	$sql .= " and domain_uuid = '$domain_uuid'";
	//filter based on search
	if (!empty($search)) {
		$search_param = "%$search%";
		$sql .= " and domain_setting_subcategory like :search";
		$parameters['search'] = $search_param;
	}
	if (!empty($page)) {
		$sql .= limit_offset($rows_per_page, $offset);
	}

	$result = $database->execute($sql, $parameters, 'all');

	if (!empty($result)) {
		foreach ($result as $row) {
			if (str_ends_with($row['domain_setting_subcategory'], '_database_retention_days')) {
				$class_name = substr($row['domain_setting_subcategory'], 0, -1 * strlen('_database_rentention_days') + 1);
				$maintenance_apps[$class_name]['database_maintenance'][$row['domain_uuid']] = $row;
			}
			if (str_ends_with($row['domain_setting_subcategory'], '_filesystem_retention_days')) {
				$class_name = substr($row['domain_setting_subcategory'], 0, -1 * strlen('_filesystem_rentention_days') + 1);
				$maintenance_apps[$class_name]['filesystem_maintenance'][$row['domain_uuid']] = $row;
			}
		}
	}

}

//set URL parameters
$url_params = '';

if ($show_all) {
	$url_params = (empty($url_params) ? '?' : '&') . 'show=all';
}
if (!empty($page)) {
	$url_params .= (empty($url_params) ? '?' : '&') . 'page=' . $page;
}
if (!empty($search)) {
	$url_params .= (empty($url_params) ? '?' : '&') . 'search=' . urlencode($search);
}

//get the list of domains
$domain_names = maintenance::get_domains($database);

//create the token
$object = new token;
$token = $object->create($_SERVER['PHP_SELF']);

//show the content
require_once dirname(__DIR__, 2) . '/resources/header.php';

$document['title'] = $text['title-maintenance'];

	echo "<div class='action_bar' id='action_bar'>";
	echo "<div class='heading'><b>Maintenance (" . count($classes) . ")</b></div>";
	echo "<div class='actions'>";
		echo button::create(['type'=>'button','label'=>$text['button-logs'],'icon'=>'fas fa-scroll fa-fw','id'=>'btn_logs', 'link'=>'maintenance_logs.php']);
		//show all
		if (!$show_all) {
			echo button::create(['type'=>'button','alt'=>$text['button-show_all']??'Show All','label'=>$text['button-show_all']??'Show All','class'=>'btn btn-default','icon'=>$_SESSION['theme']['button_icon_all']??'globe','link'=>(empty($url_params) ? '?show=all' : $url_params . '&show=all')]);
		}
		//search form
		echo "<form id='form_search' class='inline' method='get'>";
			if (!empty($page)) {
				echo "<input name='page' type=hidden value='$page'>";
			}
			if ($show_all) {
				echo "<input name='show' type=hidden value='all'>";
			}
			echo "<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
			echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);
		echo "</form>";
	echo "</div>";

	//javascript modal boxes
	echo modal::create(['id'=>'modal-copy','type'=>'copy','actions'=> button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_copy','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"])]);
	echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=> button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
	echo modal::create(['id'=>'modal-toggle','type'=>'toggle','actions'=> button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_toggle','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"])]);

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

//include the footer
require_once dirname(__DIR__, 2) . '/resources/footer.php';

?>
