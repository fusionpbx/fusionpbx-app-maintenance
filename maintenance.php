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
 * denisent dev team
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

//set the domain and user
$domain_uuid = $_SESSION['domain_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

//internationalization
$language = new text;
$text = $language->get();

//create a database object
$database = database::new();

/**
 * Get the edit URL for a maintenance setting based on its table and UUID.
 *
 * @param string|null $table The table name ('default', 'domain', or 'user').
 * @param string|null $uuid  The UUID of the setting.
 *
 * @return string The edit URL for the maintenance setting, or an empty string if invalid.
 */
function maintenance_setting_edit_url(?string $table, ?string $uuid): string {
	if (empty($table) || empty($uuid)) {
		return '';
	}
	if ($table === 'default') {
		return '/core/default_settings/default_setting_edit.php?id=' . urlencode($uuid);
	}
	if ($table === 'domain') {
		return '/core/domain_settings/domain_setting_edit.php?id=' . urlencode($uuid);
	}
	if ($table === 'user') {
		return '/core/user_settings/user_setting_edit.php?id=' . urlencode($uuid);
	}
	return '';
}

/**
 * Find a maintenance setting in the database based on category, subcategory, and optional domain UUID.
 *
 * @param database $database    The database object.
 * @param string   $category    The category of the maintenance setting.
 * @param string   $subcategory The subcategory of the maintenance setting.
 * @param string   $domain_uuid Optional domain UUID to filter by.
 *
 * @return array The matching maintenance setting, or an empty array if not found.
 */
function maintenance_find_setting(database $database, string $category, string $subcategory, string $domain_uuid = ''): array {
	$matches = maintenance::find_all_uuids($database, $category, $subcategory);

	foreach ($matches as $match) {
		if (($match['table'] ?? '') === 'domain' && !empty($domain_uuid) && ($match['domain_uuid'] ?? '') === $domain_uuid) {
			return $match;
		}
	}
	foreach ($matches as $match) {
		if (($match['table'] ?? '') === 'default') {
			return $match;
		}
	}
	return [];
}

/**
 * Generate an HTML link for a maintenance setting value that allows inline editing.
 *
 * @param string      $value The current value of the setting.
 * @param string|null $table The table name ('default', 'domain', or 'user').
 * @param string|null $uuid  The UUID of the setting.
 *
 * @return string The HTML link for the maintenance setting value, or the escaped value if invalid.
 */
function maintenance_setting_value_link(string $value, ?string $table, ?string $uuid): string {
	if (empty($table) || empty($uuid)) {
			return escape($value);
	}

	$id = 'maintenance_setting_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $uuid);

	$html = "<span id='".$id."_display'>";
	$html .= "<a href='#' onclick=\"document.getElementById('".$id."_display').style.display='none'; document.getElementById('".$id."_edit').style.display='inline-block'; document.getElementById('".$id."_input').focus(); return false;\">";
	$html .= escape($value);
	$html .= "</a>";
	$html .= "</span>";

	$html .= "<span id='".$id."_edit' style='display:none; white-space: nowrap;'>";
	$html .= "<input type='number' id='".$id."_input' class='formfld' style='width: 80px;' value='".escape($value)."' min='0'>";
	$html .= button::create([
		'type'=>'button',
		'label'=>'Save',
		'class'=>'btn',
		'style'=>'margin-left: 4px;',
		'onclick'=>"document.getElementById('setting_table').value='".escape($table)."'; document.getElementById('setting_uuid').value='".escape($uuid)."'; document.getElementById('setting_value').value=document.getElementById('".$id."_input').value; list_action_set('update_setting_value'); list_form_submit('form_list');"
	]);
	$html .= button::create([
		'type'=>'button',
		'label'=>'Cancel',
		'class'=>'btn',
		'style'=>'margin-left: 4px;',
		'onclick'=>"document.getElementById('".$id."_edit').style.display='none'; document.getElementById('".$id."_display').style.display='inline';"
	]);
	$html .= "</span>";

	return $html;
}

/**
 * Get the prefix for a maintenance setting based on its table.
 *
 * @param string|null $table The table name ('default', 'domain', or 'user').
 *
 * @return string The prefix for the maintenance setting, or an empty string if invalid.
 */
function maintenance_setting_prefix(?string $table): string {
	switch ($table) {
		case 'default': return 'default_setting';
		case 'domain': return 'domain_setting';
		case 'user': return 'user_setting';
		default: return '';
	}
}

/**
 * Get the table name for a maintenance setting based on its table.
 *
 * @param string|null $table The table name ('default', 'domain', or 'user').
 *
 * @return string The table name for the maintenance setting, or an empty string if invalid.
 */
function maintenance_setting_table_name(?string $table): string {
	switch ($table) {
		case 'default': return 'default_settings';
		case 'domain': return 'domain_settings';
		case 'user': return 'user_settings';
		default: return '';
	}
}

/**
 * Get the registered maintenance application class name based on the provided application name.
 *
 * @param database $database    The database object.
 * @param string   $application The application name to look for.
 *
 * @return string The registered maintenance application class name, or the original application name if not found.
 */
function maintenance_log_application(database $database, string $application): string {
	$settings = new settings(['database' => $database]);
	$apps = $settings->get('maintenance', 'application', []);

	foreach ($apps as $class) {
		if (!class_exists($class)) {
				continue;
		}

		if ($class === $application) {
				return $class;
		}

		if (method_exists($class, 'database_maintenance_category') && $class::database_maintenance_category() === $application) {
				return $class;
		}

		if (method_exists($class, 'filesystem_maintenance_category') && $class::filesystem_maintenance_category() === $application) {
				return $class;
		}
	}

	return $application;
}

/**
 * Get the last run date and time for a maintenance application, optionally filtered by domain UUID.
 *
 * @param database $database    The database object.
 * @param string   $application The application name to look for.
 * @param string   $domain_uuid Optional domain UUID to filter by.
 *
 * @return string The last run date and time in 'Y-m-d H:i' format, or an empty string if not found.
 */
function maintenance_last_run(database $database, string $application, string $domain_uuid = '') {
	$application = maintenance_log_application($database, $application);

	$sql = "select max(insert_date) from v_maintenance_logs ";
	$sql .= "where maintenance_log_application = :application ";
	$parameters['application'] = $application;

	if (!empty($domain_uuid) && is_uuid($domain_uuid)) {
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}

	$last_run = $database->select($sql, $parameters, 'column');

	// Some maintenance tasks log globally with domain_uuid NULL.
	// If no domain-specific run exists, fall back to the global application run.
	if (empty($last_run) && !empty($domain_uuid) && is_uuid($domain_uuid)) {
			$sql = "select max(insert_date) from v_maintenance_logs ";
			$sql .= "where maintenance_log_application = :application ";
			$sql .= "and domain_uuid is null ";
			$parameters = ['application' => $application];
			$last_run = $database->select($sql, $parameters, 'column');
	}

	if (empty($last_run)) {
			return '';
	}

	$date = new DateTime($last_run);
	$date->setTimezone(new DateTimeZone(date_default_timezone_get()));
	return $date->format('Y-m-d H:i');
}

/**
 * Calculate the next scheduled run time for maintenance based on settings.
 *
 * @param settings $settings The settings object containing maintenance configuration.
 *
 * @return string The next scheduled run time in 'Y-m-d H:i' format, or an empty string if maintenance is disabled or time of day is not set.
 */
function maintenance_next_run(settings $settings) {
	$enabled = $settings->get('maintenance', 'enabled', false);
	$time_of_day = $settings->get('maintenance', 'time_of_day', '');

	if (!$enabled || empty($time_of_day)) {
			return '';
	}

	$now = new DateTime();
	$next = new DateTime(date('Y-m-d').' '.$time_of_day);

	if ($next <= $now) {
			$next->modify('+1 day');
	}

	return $next->format('Y-m-d H:i');
}

/**
 * Generate an HTML link for a maintenance setting that allows inline editing of the enabled state.
 *
 * @param bool        $enabled The current enabled state of the setting.
 * @param string|null $table   The table name ('default', 'domain', or 'user').
 * @param string|null $uuid    The UUID of the setting.
 *
 * @return string The HTML link for the maintenance setting, or the escaped label if invalid.
 */
function maintenance_setting_enabled_inline($enabled, ?string $table = null, ?string $uuid = null) {
	$label = $enabled ? 'true' : 'false';

	if (empty($table) || empty($uuid)) {
			return escape($label);
	}

	return button::create([
		'type'=>'submit',
		'class'=>'link',
		'label'=>escape($label),
		'title'=>'Toggle enabled',
		'onclick'=>"document.getElementById('setting_table').value='".escape($table)."'; document.getElementById('setting_uuid').value='".escape($uuid)."'; list_action_set('toggle_setting_enabled'); list_form_submit('form_list')"
	]);
}


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

	//run a maintenance application now
	if ($action === 'run_now' && permission_exists('maintenance_edit')) {
			$run_class = $_REQUEST['maintenance_class'] ?? '';
			$default_settings_run = new settings(['database' => $database]);
			$registered_classes = $default_settings_run->get('maintenance', 'application', []);

			//run the maintenance application if it is registered and exists
			if (!empty($run_class) && in_array($run_class, $registered_classes) && class_exists($run_class)) {
					if (maintenance_service::run_application($database, $default_settings_run, $run_class)) {
							message::add('Maintenance task ran: '.$run_class);
					}
					else {
							message::add('Maintenance task failed: '.$run_class, 'negative');
					}
			}
			else {
					message::add('Invalid maintenance task', 'negative');
			}

			// Redirect to the maintenance page and exit
			header('Location: maintenance.php');
			exit;
	}

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

	if ($action === 'update_setting_value' && permission_exists('maintenance_edit')) {
		$setting_table = $_REQUEST['setting_table'] ?? '';
		$setting_uuid = $_REQUEST['setting_uuid'] ?? '';
		$setting_value = $_REQUEST['setting_value'] ?? '';
		$prefix = maintenance_setting_prefix($setting_table);
		$table_name = maintenance_setting_table_name($setting_table);

		if (!empty($prefix) && !empty($table_name) && is_uuid($setting_uuid) && is_numeric($setting_value)) {
			$array[$table_name][0][$prefix.'_uuid'] = $setting_uuid;
			$array[$table_name][0][$prefix.'_value'] = $setting_value;
			$database->save($array);
			message::add('Maintenance setting updated');
		}
		else {
			message::add('Invalid maintenance setting value', 'negative');
		}
		header('Location: maintenance.php');
		exit;
	}

	if ($action === 'toggle_setting_enabled' && permission_exists('maintenance_edit')) {
		$setting_table = $_REQUEST['setting_table'] ?? '';
		$setting_uuid = $_REQUEST['setting_uuid'] ?? '';
		$prefix = maintenance_setting_prefix($setting_table);
		$table_name = maintenance_setting_table_name($setting_table);

		if (!empty($prefix) && !empty($table_name) && is_uuid($setting_uuid)) {
			$sql = "select ".$prefix."_enabled from v_".$table_name." where ".$prefix."_uuid = :setting_uuid";
			$parameters['setting_uuid'] = $setting_uuid;
			$current_enabled = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			$new_enabled = ($current_enabled == 'true' || $current_enabled === true || $current_enabled == '1') ? 'false' : 'true';
			$array[$table_name][0][$prefix.'_uuid'] = $setting_uuid;
			$array[$table_name][0][$prefix.'_enabled'] = $new_enabled;
			$database->save($array);
			message::add('Maintenance setting toggled');
		}
		else {
			message::add('Invalid maintenance setting', 'negative');
		}
		header('Location: maintenance.php');
		exit;
	}

	$toggle_maintenance_apps = $_REQUEST['toggle'];
	unset($token);
}

//create a boolean value to represent if show_all is enabled
	$show_all = !empty($_REQUEST['show']) && $_REQUEST['show'] === 'all' && permission_exists('maintenance_show_all');

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
	$page = 0; // default to the first page if not specified
	$offset = 0;
}

//load the global settings only
$default_settings = new settings(['database' => $database]);

//get the list in the default settings
$classes = $default_settings->get('maintenance', 'application', []);

//get the display array
$maintenance_apps = [];

if ($show_all) {
	//get a list of domain names
	$domains = maintenance::get_domains($database);

	//get maintainers
	foreach ($classes as $maintainer) {
		$tasks = ['database', 'filesystem'];
		foreach ($tasks as $task) {
			$has_task = "has_{$task}_maintenance";
			if (maintenance::$has_task($maintainer)) {
				$category_method = "get_{$task}_category";
				$subcategory_method = "get_{$task}_subcategory";
				$category = maintenance::$category_method($maintainer);
				$subcategory = maintenance::$subcategory_method($maintainer);

				//get all UUIDs in the database associated with this setting
				$uuids = maintenance::find_all_uuids($database, $category, $subcategory);
				foreach ($uuids as $match) {
					$uuid = $match['uuid'];
					$status = $match['status'];
					$table = $match['table'];
					$domain_uuid = $match['domain_uuid'] ?? 'global';
					$value = $match['value'];
					$maintenance_apps[$category]["{$task}_maintenance"][$domain_uuid]["{$table}_setting_enabled"] = $match['status'];
					$maintenance_apps[$category]["{$task}_maintenance"][$domain_uuid]["{$table}_setting_value"] = $value;
					$maintenance_apps[$category]["{$task}_maintenance"][$domain_uuid]["{$table}_setting_uuid"] = $uuid;
				}
			}
		}
	}
}
else {
	//use the settings object to get the maintenance apps and their values
	foreach ($classes as $maintainer) {
		$domain_settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? $domain_uuid]);

		//database retention days
		if (maintenance::has_database_maintenance($maintainer)) {
			$category = maintenance::get_database_category($maintainer);
			$subcategory = maintenance::get_database_subcategory($maintainer);
			$match = maintenance_find_setting($database, $category, $subcategory, $domain_uuid);

			if (!empty($match)) {
				$table = $match['table'] ?? 'default';
				$maintenance_apps[$category]['database_maintenance'][$domain_uuid][$table.'_setting_value'] = $match['value'] ?? '';
				$maintenance_apps[$category]['database_maintenance'][$domain_uuid][$table.'_setting_enabled'] = $match['status'] ?? true;
				$maintenance_apps[$category]['database_maintenance'][$domain_uuid][$table.'_setting_uuid'] = $match['uuid'] ?? '';
			}
		}

		//filesystem retention days
		if (maintenance::has_filesystem_maintenance($maintainer)) {
			$category = maintenance::get_filesystem_category($maintainer);
			$subcategory = maintenance::get_filesystem_subcategory($maintainer);
			$match = maintenance_find_setting($database, $category, $subcategory, $domain_uuid);

			if (!empty($match)) {
				$table = $match['table'] ?? 'default';
				$maintenance_apps[$category]['filesystem_maintenance'][$domain_uuid][$table.'_setting_value'] = $match['value'] ?? '';
				$maintenance_apps[$category]['filesystem_maintenance'][$domain_uuid][$table.'_setting_enabled'] = $match['status'] ?? true;
				$maintenance_apps[$category]['filesystem_maintenance'][$domain_uuid][$table.'_setting_uuid'] = $match['uuid'] ?? '';
			}
		}
	}

}

//sort the result
ksort($maintenance_apps);

//set URL parameters
$url_params = '';

if ($show_all) {
	$url_params = '?show=all';
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
echo "	<div class='heading'><b>Maintenance</b></div>";
echo "	<div class='actions'>";
echo button::create([
	'type'=>'button',
	'label'=>$text['button-reload'],
	'icon'=>$settings->get('theme', 'button_icon_reload'),
	'style'=>'margin-right: 15px;',
	'link'=>'maintenance_reload.php'
]);
echo button::create([
	'type'=>'button',
	'label'=>$text['button-logs'],
	'icon'=>$settings->get('theme', 'button_icon_log'),
	'id'=>'btn_logs',
	'link'=>'maintenance_logs.php'
]);
echo button::create([
	'type'=>'button',
	'label'=>$text['button-storage_usage'],
	'icon'=>$settings->get('theme', 'button_icon_database'),
	'id'=>'btn_storage_usage',
	'link'=>'maintenance_storage.php'
]);
//show all
if (!$show_all) {
	echo button::create([
		'type'=>'button',
		'alt'=>$text['button-show_all']??'Show All',
		'label'=>$text['button-show_all']??'Show All',
		'class'=>'btn btn-default',
		'icon'=>$_SESSION['theme']['button_icon_all']??'globe',
		'link'=>(empty($url_params) ? '?show=all' : $url_params . '&show=all')
	]);
}
//search form
echo "		<form id='form_search' class='inline' method='get'>";
if (!empty($page)) {
	echo "		<input name='page' type=hidden value='$page'>";
}
if ($show_all) {
	echo "		<input name='show' type=hidden value='all'>";
}
echo "			<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
echo button::create([
	'label'=>$text['button-search'],
	'icon'=>$_SESSION['theme']['button_icon_search'],
	'type'=>'submit',
	'id'=>'btn_search'
]);
echo "		</form>";
echo "	</div>";

//javascript modal boxes
echo modal::create([
	'id'=>'modal-copy',
	'type'=>'copy',
	'actions'=> button::create([
		'type'=>'button',
		'label'=>$text['button-continue'],
		'icon'=>$settings->get('theme', 'button_icon_copy'),
		'id'=>'btn_copy',
		'style'=>'float: right; margin-left: 15px;',
		'collapse'=>'never',
		'onclick'=>"modal_close(); list_action_set('copy'); list_form_submit('form_list');"
	])
]);
echo modal::create([
	'id'=>'modal-delete','type'=>'delete',
	'actions'=> button::create([
		'type'=>'button',
		'label'=>$text['button-continue'],
		'icon'=>'check',
		'icon'=>$settings->get('theme', 'button_icon_delete'),
		'id'=>'btn_delete',
		'style'=>'float: right; margin-left: 15px;',
		'collapse'=>'never',
		'onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"
	])
]);
echo modal::create([
	'id'=>'modal-toggle',
	'type'=>'toggle',
	'actions'=> button::create([
		'type'=>'button',
		'label'=>$text['button-continue'],
		'icon'=>$settings->get('theme', 'button_icon_toggle'),
		'id'=>'btn_toggle',
		'style'=>'float: right; margin-left: 15px;',
		'collapse'=>'never',
		'onclick'=>"modal_close(); list_action_set('toggle'); list_form_submit('form_list');"
	])
]);

echo "	<div style='clear: both;'></div>";
echo "	<br/><br/>";
echo "	<form id='form_list' method='post'>";
echo "		<input type='hidden' id='action' name='action' value=''>";
echo "		<input type='hidden' name='search' value=\"".escape($search)."\">";
echo "		<div class='card'>\n";
echo "			<table class='list'>";
echo "				<tr class='list-header'>";
echo "					<th>Name</th>";
if ($show_all) {
	echo "				<th>Domain</th>";
}
echo "					<th>Database Enabled</th>";
echo "					<th>Retention Days</th>";
echo "					<th>File System Enabled</th>";
echo "					<th>Retention Days</th>";
echo "                  <th>Last Run</th>";
echo "                  <th>Next Run</th>";
echo "                  <th>Action</th>";
echo "				</tr>";

//list all maintenance applications from the defaults settings for global and each domain and show if they are enabled or disabled
foreach ($maintenance_apps as $class => $app_settings) {
	//make the class name more user-friendly
	if ($class === 'cdr') {
	    $display_name = strtoupper(str_replace('_', ' ', $class));
	} else {
	    $display_name = ucwords(str_replace('_', ' ', $class));
	}

	//display global first
	if ((isset($app_settings['database_maintenance']['global']) || isset($app_settings['filesystem_maintenance']['global'])) && $show_all) {
		echo "<tr class='list-row' style=''>";
		echo "	<td>$display_name</td>";
		echo "	<td>".$text['label-global']."</td>";
		if (isset($app_settings['database_maintenance']['global'])) {
			$value = $app_settings['database_maintenance']['global']['default_setting_value'];
			$uuid = $app_settings['database_maintenance']['global']['default_setting_uuid'] ?? '';
			echo "<td>".maintenance_setting_enabled_inline(!empty($app_settings['database_maintenance']['global']['default_setting_enabled']), 'default', $uuid)."</td>";
			echo "<td>".maintenance_setting_value_link($value, 'default', $uuid)."</td>";
		} else {
			echo "<td>&nbsp;</td>";
			echo "<td>&nbsp;</td>";
		}
		if (isset($app_settings['filesystem_maintenance']['global'])) {
			$value = $app_settings['filesystem_maintenance']['global']['default_setting_value'];
			$uuid = $app_settings['filesystem_maintenance']['global']['default_setting_uuid'] ?? '';
			echo "<td>".maintenance_setting_enabled_inline(!empty($app_settings['filesystem_maintenance']['global']['default_setting_enabled']), 'default', $uuid)."</td>";
			echo "<td>".maintenance_setting_value_link($value, 'default', $uuid)."</td>";
		} else {
			echo "<td>&nbsp;</td>";
			echo "<td>&nbsp;</td>";
		}
            $last_run = maintenance_last_run($database, $class);
            $next_run = maintenance_next_run($default_settings);
            echo "<td class='no-wrap'>".escape($last_run)."</td>";
            echo "<td class='no-wrap'>".escape($next_run)."</td>";
            echo "<td class='no-link center'>";
            echo button::create([
            	'type'=>'submit',
            	'class'=>'link',
            	'label'=>$text['label-run_now'],
            	'title'=>$text['label-run'].' '.$display_name.' '.$text['title-maintenance'],
            	'onclick'=>"document.getElementById('maintenance_class').value='".escape($class)."'; list_action_set('run_now'); list_form_submit('form_list')"
        	]);
            echo "</td>";
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
			echo "	<td>$display_name</td>";
			if ($show_all) {
				echo "<td>".$domain_names[$domain_uuid]."</td>";
			}
			if (isset($app_settings['database_maintenance'][$domain_uuid])) {
				$setting = $app_settings['database_maintenance'][$domain_uuid];
				$table = isset($setting['domain_setting_value']) ? 'domain' : (isset($setting['default_setting_value']) ? 'default' : (isset($setting['user_setting_value']) ? 'user' : ''));
				$value = $setting[$table.'_setting_value'] ?? '';
				$uuid = $setting[$table.'_setting_uuid'] ?? '';
				echo "<td>".maintenance_setting_enabled_inline(!empty($setting[$table.'_setting_enabled']), $table, $uuid)."</td>";
				echo "<td>".maintenance_setting_value_link($value, $table, $uuid)."</td>";
			} else {
				echo "<td>&nbsp;</td>";
				echo "<td>&nbsp;</td>";
			}
			if (isset($app_settings['filesystem_maintenance'][$domain_uuid])) {
				$setting = $app_settings['filesystem_maintenance'][$domain_uuid];
				$table = isset($setting['domain_setting_value']) ? 'domain' : (isset($setting['default_setting_value']) ? 'default' : (isset($setting['user_setting_value']) ? 'user' : ''));
				$value = $setting[$table.'_setting_value'] ?? '';
				$uuid = $setting[$table.'_setting_uuid'] ?? '';
				echo "<td>".maintenance_setting_enabled_inline(!empty($setting[$table.'_setting_enabled']), $table, $uuid)."</td>";
				echo "<td>".maintenance_setting_value_link($value, $table, $uuid)."</td>";
			} else {
				echo "<td>&nbsp;</td>";
				echo "<td>&nbsp;</td>";
			}
                $last_run = maintenance_last_run($database, $class, $domain_uuid);
                $next_run = maintenance_next_run($default_settings);
                $run_class = maintenance_log_application($database, $class);
                echo "<td class='no-wrap'>".escape($last_run)."</td>";
                echo "<td class='no-wrap'>".escape($next_run)."</td>";
                echo "<td class='no-link center'>";
                echo button::create([
                	'type'=>'submit',
                	'class'=>'link',
                	'label'=>$text['label-run_now'],
                	'title'=>$text['label-run'].' '.$display_name.' '.$text['title-maintenance'],
                	'onclick'=>"document.getElementById('maintenance_class').value='".escape($run_class)."'; list_action_set('run_now'); list_form_submit('form_list')"
            	]);
                echo "</td>";
			echo "</tr>";
		}
	}
}
echo "			</table>";
echo "		</div>";
echo "          <input type='hidden' id='setting_table' name='setting_table' value=''>";
echo "          <input type='hidden' id='setting_uuid' name='setting_uuid' value=''>";
echo "          <input type='hidden' id='setting_value' name='setting_value' value=''>";
echo "          <input type='hidden' id='maintenance_class' name='maintenance_class' value=''>";
echo "		<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>";
echo "	</form>";
echo "</div>";

//include the footer
require_once dirname(__DIR__, 2) . '/resources/footer.php';
