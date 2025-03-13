<?php

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

//includes files
	require_once dirname(__DIR__, 4) . "/resources/require.php";

//connect to the database
	if (!isset($database)) {
		$database = database::new();
	}

//create a token
	$token = (new token())->create($_SERVER['PHP_SELF']);

//check permisions
	require_once dirname(__DIR__, 4) . "/resources/check_auth.php";
	if (permission_exists('xml_cdr_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//define constants to use for a three position checkbox
	const CHECKBOX_HIDDEN = -1;
	const CHECKBOX_UNCHECKED = 0;
	const CHECKBOX_CHECKED = 1;

//process database post
	if (!empty($_POST['database_retention_days'])) {
		$records = [];
		$update_permissions = new permissions($database);
		$index = 0;
		foreach($_POST['database_retention_days'] as $row) {
			if (empty($row['status']) || empty($row['days'])) {
				$row['status'] = 'false';
			}
			//check enabled/disabled status
			$uuid = (empty($row['uuid']) ? uuid() : $row['uuid']);
			$table = (empty($row['type']) ? 'default' : $row['type']);
			//check table
			if ($table !== 'domain' && $table !== 'default') {
				header('HTTP/1.1 403 Forbidden', true, 403);
				die();
			}
			$category = $row['category'] ?? '';
			$subcategory = $row['subcategory'] ?? '';
			if (!empty($category) && !empty($subcategory)) {
				$days = $row['days'];
				$status = $row['status'];
				$records["{$table}_settings"][$index]["{$table}_setting_uuid"] = $uuid;
				$records["{$table}_settings"][$index]["{$table}_setting_category"] = $row['category'];
				$records["{$table}_settings"][$index]["{$table}_setting_subcategory"] = $row['subcategory'];
				$records["{$table}_settings"][$index]["{$table}_setting_value"] = $days;
				$records["{$table}_settings"][$index]["{$table}_setting_name"] = 'numeric';
				$records["{$table}_settings"][$index]["{$table}_setting_enabled"] = $row['status'];
				if (!$update_permissions->exists("v_{$table}_setting_add")) {
					$update_permissions->add("v_{$table}_setting_add", 'temp');
				}
				//compare the current value with the default setting
				$index++;
			}
		}
		if (count($records) > 0) {
			$database->save($records);
			if ($database->message['code'] === "200") {
				message::add($text['message-update']);
			}
		}
		unset($update_permissions);
	}

//process filesystem post
	if (!empty($_POST['filesystem_retention_days'])) {
		$records = [];
		$index = 0;
		$update_permissions = new permissions($database);
		foreach($_POST['filesystem_retention_days'] as $row) {
			if (empty($row['status']) || empty($row['days'])) {
				$row['status'] = 'false';
			}

			$uuid = (empty($row['uuid']) ? uuid() : $row['uuid']);
			$table = (empty($row['type']) ? 'default' : $row['type']);
			//check table
			if ($table !== 'domain' && $table !== 'default') {
				header('HTTP/1.1 403 Forbidden', true, 403);
				die();
			}
			$filesystem_category = $row['category'] ?? '';
			$filesystem_subcategory = $row['subcategory'] ?? '';
			if (!empty($filesystem_category) && !empty($filesystem_subcategory) && !empty($days)) {
				$days = $row['days'] ?? '';
				$records["{$table}_settings"][$index]["{$table}_setting_uuid"] = $uuid;
				$records["{$table}_settings"][$index]["{$table}_setting_category"] = $filesystem_category;
				$records["{$table}_settings"][$index]["{$table}_setting_subcategory"] = $filesystem_subcategory;
				$records["{$table}_settings"][$index]["{$table}_setting_value"] = $days;
				$records["{$table}_settings"][$index]["{$table}_setting_name"] = 'numeric';
				$records["{$table}_settings"][$index]["{$table}_setting_enabled"] = $row['status'];
				$index++;
				if (!$update_permissions->exists("v_{$table}_setting_add")) {
					$update_permissions->add("v_{$table}_setting_add", 'temp');
				}
			}
		}
		if (count($records) > 0) {
			$database->save($records);
			if ($database->message['code'] === "200") {
				message::add($text['message-update']);
			}
			unset($update_permissions);
		}
	}

//set defaults
	$filesystem_count = 0;
	$total_running_maintenance_apps = 0;
	$total_maintenance_apps = 0;
	$maintenance_apps = [];
	$validated_path = PROJECT_PATH."/core/dashboard/index.php";
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';

//create the settings object for this user and domain
	$setting = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//check the running maintenance apps
	$maintainers = $setting->get('maintenance', 'application', []);

//sort the applications
	array_multisort($maintainers);

//count the number of apps enabled
	foreach ($maintainers as $maintenance_app) {
		if (class_exists($maintenance_app)) {
			//check for database status
			if (method_exists($maintenance_app, 'database_maintenance')) {
				$total_maintenance_apps++;
				$category = maintenance::get_database_category($maintenance_app);
				$subcategory = maintenance::get_database_subcategory($maintenance_app);
				if (!empty($setting->get($category, $subcategory, ''))) {
					$total_running_maintenance_apps++;
				}
			}
			//check for filesystem status
			if (method_exists($maintenance_app, 'filesystem_maintenance')) {
				$total_maintenance_apps++;
				$category = maintenance::get_filesystem_category($maintenance_app);
				$subcategory = maintenance::get_filesystem_subcategory($maintenance_app);
				if(!empty($setting->get($category, $subcategory, ''))) {
					$total_running_maintenance_apps++;
				}
			}
		}
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get($_SESSION['domain']['language']['code'], 'core/user_settings');

//set the rows to alternate shading background
	$c = 0;
	$row_style = [];
	$row_style[$c] = "row_style0";
	$row_style[!$c] = "row_style1";

//show the box content
if (permission_exists('maintenance_view')) {
	//show the box
	echo "<div class='hud_box'>";
	echo "	<div class='hud_content' onclick=\"$('#hud_maintenance_details').slideToggle('fast'); toggle_grid_row_end('Maintenance')\">";
	echo "		<span class='hud_title'><a onclick=\"document.location.href='/app/maintenance/maintenance.php'\">".$text['label-maintenance']."</a></span>";
	echo "		<script src='/app/maintenance/resources/javascript/maintenance_functions.js'></script>";
	if ($dashboard_chart_type === 'doughnut') {
		//add an event listener for showing and hiding the days input box
		echo "	<div class='hud_chart' style='width: 250px;'><canvas id='maintenance_chart'></canvas></div>";
		echo "	<script>\n";
		echo "		const maintenance_chart = new Chart(\n";
		echo "			document.getElementById('maintenance_chart').getContext('2d'),\n";
		echo "			{\n";
		echo "				type: 'doughnut',\n";
		echo "				data: {\n";
		echo "					labels: ['".$text['label-running'].": ".$total_running_maintenance_apps."', '".$text['label-total'].": ".$total_maintenance_apps."'],\n";
		echo "					datasets: [{\n";
		echo "						data: [".$total_running_maintenance_apps.", ".($total_maintenance_apps - $total_running_maintenance_apps)."],\n";
		echo "						backgroundColor: [\n";
		echo "							'".($setting->get('theme', 'dashboard_maintenance_chart_main_color') ?? "#2a9df4")."',\n";
		echo "							'".($setting->get('theme', 'dashboard_maintenance_chart_sub_color') ?? "#d4d4d4")."'\n";
		echo "						],\n";
		echo "						borderColor: '".$setting->get('theme', 'dashboard_chart_border_color')."',\n";
		echo "						borderWidth: '".$setting->get('theme', 'dashboard_chart_border_width')."',\n";
		echo "					}]\n";
		echo "				},\n";
		echo "				options: {\n";
		echo "					plugins: {\n";
		echo "						chart_number: {\n";
		echo "							text: ".$total_running_maintenance_apps."\n";
		echo "						},\n";
		echo "						legend: {\n";
		echo "							display: true,\n";
		echo "							position: 'right',\n";
		echo "							reverse: true,\n";
		echo "							labels: {\n";
		echo "								usePointStyle: true,\n";
		echo "								pointStyle: 'rect',\n";
		echo "								color: '".$dashboard_label_text_color."'\n";
		echo "							}\n";
		echo "						},\n";
		echo "					}\n";
		echo "				},\n";
		echo "				plugins: [{\n";
		echo "					id: 'chart_number',\n";
		echo "					beforeDraw(chart, args, options){\n";
		echo "						const {ctx, chartArea: {top, right, bottom, left, width, height} } = chart;\n";
		echo "						ctx.font = chart_text_size + ' ' + chart_text_font;\n";
		echo "						ctx.textBaseline = 'middle';\n";
		echo "						ctx.textAlign = 'center';\n";
		echo "						ctx.fillStyle = '".$dashboard_number_text_color."';\n";
		echo "						ctx.fillText(options.text, width / 2, top + (height / 2));\n";
		echo "						ctx.save();\n";
		echo "					}\n";
		echo "				}]\n";
		echo "			}\n";
		echo "		);\n";
		echo "	</script>\n";
	}
	if ($dashboard_chart_type === 'number') {
		echo "	<span class='hud_stat'>$total_running_maintenance_apps / $total_maintenance_apps</span>";
	}
	echo "	</div>";
	//form for maintenance changes
	echo "	<form id='form_list_maintainers' name='form_list_maintainers' method='POST'>";
	echo "		<div class='hud_details hud_box' id='hud_maintenance_details' style='text-align: right'>";
	//save button for changes
	if (permission_exists('maintenance_edit')) {
		echo "		<button type='button' alt='Save' title='Save' onclick=\"list_form_submit('form_list_maintainers');\" class='btn btn-default ' style='position: absolute; margin-top: -35px; margin-left: -72px;'><span class='fas fa-bolt fa-fw'></span><span class='button-label  pad'>Save</span></button>";
	}
	echo "			<table class='tr_hover' width='100%' cellpadding='0' cellspacing='0' border='0'>";
	echo "				<tr style='position: -webkit-sticky; position: sticky; z-index: 5; top: 0; left: 2px;'>";
	echo "					<th class='hud_heading' style='width: 40%;'>".($text['label-app'] ?? 'Application')."</th>";
	echo "					<th class='hud_heading' style='width: 15%;'>".'Database'."</th>";
	echo "					<th class='hud_heading' style='width: 15%;'>".'Days'."</th>";
	echo "					<th class='hud_heading' style='width: 15%;'>".'Filesystem'."</th>";
	echo "					<th class='hud_heading' style='width: 15%;'>".'Days'."</th>";
	echo "				</tr>";

	//iterate maintainers
	foreach($maintainers as $x => $maintenance_app) {
		$database_days = "";
		$filesystem_days = "";
		$database_category = '';
		$database_subcategory = '';
		$filesystem_category = '';
		$filesystem_subcategory = '';
		$filesystem_checkbox_state = CHECKBOX_HIDDEN;
		$database_checkbox_state = CHECKBOX_HIDDEN;
		$database_default_value = "";
		$filesystem_default_value = "";
		$param = [];
		if (class_exists($maintenance_app)) {
			$display_name = $maintenance_app;
			//check for database status
			if (method_exists($maintenance_app, 'database_maintenance')) {
				$database_category = maintenance::get_database_category($maintenance_app);
				$database_subcategory = maintenance::get_database_subcategory($maintenance_app);
				$database_default_value = maintenance::get_app_config_value($database_category, $database_subcategory); //app_config.php default value
				$database_days = $setting->get($database_category, $database_subcategory, '');
				$display_name = $database_category;
				//uuid of setting
				$database_setting_uuids = maintenance::find_uuid($database, $database_category, $database_subcategory);
				$database_setting_uuid = $database_setting_uuids['uuid'];
				$database_setting_table = $database_setting_uuids['table'];
				if (empty($database_days)) {
					$database_checkbox_state = CHECKBOX_UNCHECKED;
				} else {
					$database_checkbox_state = CHECKBOX_CHECKED;
				}
			} else {
				$database_setting_uuid = '';
				$database_setting_table = '';
			}

			//check for filesystem status
			if (method_exists($maintenance_app, 'filesystem_maintenance')) {
				$filesystem_category = maintenance::get_filesystem_category($maintenance_app);
				$filesystem_subcategory = maintenance::get_filesystem_subcategory($maintenance_app);
				$filesystem_default_value = maintenance::get_app_config_value($filesystem_category, $filesystem_subcategory);
				$filesystem_days = $setting->get($filesystem_category, $filesystem_subcategory, '');
				if (empty($database_category)) {
					$display_name = $filesystem_category;
				}
				//uuid of setting
				$filesystem_setting_uuids = maintenance::find_uuid($database, $filesystem_category, $filesystem_subcategory);
				$filesystem_setting_uuid = $filesystem_setting_uuids['uuid'];
				$filesystem_setting_table = $filesystem_setting_uuids['table'];
				if (empty($filesystem_days)) {
					$filesystem_checkbox_state = CHECKBOX_UNCHECKED;
				} else {
					$filesystem_checkbox_state = CHECKBOX_CHECKED;
				}
			} else {
				$filesystem_setting_uuid = '';
				$filesystem_setting_table = '';
			}
		}

		//set status and color for database maintenance apps
		if ($database_checkbox_state === CHECKBOX_CHECKED) {
			$database_checked = "checked='checked'";
		}
		else {
			$database_checked = '';
		}
		//display the maintanence application
		echo "<tr>";
		if ($display_name === 'cdr') {
			$display_name = strtoupper(str_replace('_','',$display_name));
		}
		else {
			$display_name = ucwords(str_replace('_', ' ', $display_name));
		}
		echo "	<td valign='top' class='".$row_style[$c]." hud_text'>$display_name</td>";
		//
		// Database apps
		//
		//hide or show database maintenance ability
		echo "	<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void' style='width: 1%; text-align: center;'>";
		if (permission_exists('maintenance_edit')) {
			if ($database_checkbox_state !== CHECKBOX_HIDDEN) {
				//enable or disable checkbox
				if (substr($setting->get('theme','input_toggle_style', ''), 0, 6) == 'switch') {
					echo "<label class='switch'>";
					echo "	<input type='checkbox' name='database_retention_days[$x][status]' value='true' $database_checked onclick=\"this.checked ? show_input_box('database_days_$x') : hide_input_box('database_days_$x');\">";
					echo "	<span class='slider'></span>";
					echo "</label>";
				} else {
					echo "<select class='formfld' name='database_retention_days[$x][status]'>";
					echo "	<option value='true' ".($database_checkbox_state === CHECKBOX_CHECKED ? "selected='selected'" : null)." onclick=\"this.selected ? show_input_box('database_days_$x') : null;\">".$text['label-enabled']."</option>";
					echo "	<option value='false' ".($database_checkbox_state === CHECKBOX_UNCHECKED ? "selected='selected'" : null)." onclick=\"this.selected ? hide_input_box('database_days_$x') : null;\">".$text['label-disabled']."</option>";
					echo "</select>";
				}
			} else {
				//not a database maintenance application
				echo "&nbsp;";
			}
		} else {
			if ($database_checkbox_state !== CHECKBOX_HIDDEN) {
				echo $database_checkbox_state === CHECKBOX_CHECKED ? $text['label-enabled'] : $text['label-disabled'];
			} else {
				//not a database maintenance application
				echo "&nbsp;";
			}
		}
		echo "	</td>";
		//database days input box
		echo "	<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void'>";
		//hide the input box if we are hiding the checkbox
		if ($database_checkbox_state !== CHECKBOX_CHECKED) {
			$database_input_days_style = " display: none;";
		} else {
			$database_input_days_style = "";
		}
		//check for permission
		if (permission_exists('maintenance_edit')) {
			echo "<input class='formfld' style='width: 20%; min-width: 40px;$database_input_days_style' type='text' name='database_retention_days[$x][days]' id='database_days_$x' placeholder='days' maxlength='255' value='" . ($database_checkbox_state === CHECKBOX_CHECKED ? $database_days : $database_default_value) . "'>";
			echo "<input type='hidden' id='database_uuid_$x' name='database_retention_days[$x][uuid]' value='$database_setting_uuid'>";
			echo "<input type='hidden' id='database_category_$x' name='database_retention_days[$x][category]' value='$database_category'>";
			echo "<input type='hidden' id='database_subcategory_$x' name='database_retention_days[$x][subcategory]' value='$database_subcategory'>";
			echo "<input type='hidden' id='database_type_$x' name='database_retention_days[$x][type]' value='$database_setting_table'>";
		} else {
			echo "$database_days";
		}
		echo "	</td>";
		//set the checkboxes to checked
		if ($filesystem_checkbox_state === CHECKBOX_CHECKED) {
			$filesystem_checked = "checked='checked'";
		}
		else {
			$filesystem_checked = '';
		}
		//
		//filesystem apps
		//
		echo "	<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void' style='width: 1%; text-align: center;'>";
		if (permission_exists('maintenance_edit')) {
			if ($filesystem_checkbox_state !== CHECKBOX_HIDDEN) {
				if (substr($setting->get('theme','input_toggle_style', ''), 0, 6) == 'switch') {
					echo "<label class='switch'>";
					echo "	<input type='checkbox' locked id='filesystem_enabled_$x' name='filesystem_retention_days[$x][status]' value='true' $filesystem_checked onclick=\"this.checked ? show_input_box('filesystem_days_$x') : hide_input_box('filesystem_days_$x');\">";
					echo "	<span class='slider'></span>";
					echo "</label>";
				} else {
					echo "<select class='formfld' id='filesystem_enabled_$x' name='filesystem_retention_days[$x][status]'>";
					echo "	<option value='true' ".($filesystem_checkbox_state === CHECKBOX_CHECKED ? "selected='selected'" : null)." onclick=\"this.selected ? show_input_box('filesystem_days_$x') : null;\">".$text['label-enabled']."</option>";
					echo "	<option value='false' ".($filesystem_checkbox_state === CHECKBOX_UNCHECKED ? "selected='selected'" : null)." onclick=\"this.selected ? hide_input_box('filesystem_days_$x') : null;\">".$text['label-disabled']."</option>";
					echo "</select>";
				}
			} else {
				echo "&nbsp;";
			}
		} else {
			if ($filesystem_checkbox_state !== CHECKBOX_HIDDEN) {
				echo $filesystem_checkbox_state === CHECKBOX_CHECKED ? $text['label-enabled'] : $text['label-disabled'];
			} else {
				//not a database maintenance application
				echo "&nbsp;";
			}
		}
		echo "	</td>";
		//filesystem days input box
		echo "	<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void'>";
		//hide the input box if we are hiding the checkbox
		if ($filesystem_checkbox_state !== CHECKBOX_CHECKED) {
			$filesystem_input_days_style = " display: none;";
		} else {
			$filesystem_input_days_style = "";
		}
		if (permission_exists('maintenance_edit')) {
			echo "<input class='formfld' style='width: 20%; min-width: 40px;$filesystem_input_days_style' type='text' name='filesystem_retention_days[$x][days]' id='filesystem_days_$x' placeholder='days' maxlength='255' value='" . ($filesystem_checkbox_state === CHECKBOX_CHECKED ? $filesystem_days : $filesystem_default_value) . "'>";
			echo "<input type='hidden' id='filesystem_uuid_$x' name='filesystem_retention_days[$x][uuid]' value='$filesystem_setting_uuid'>";
			echo "<input type='hidden' id='filesystem_category_$x' name='filesystem_retention_days[$x][category]' value='$filesystem_category'>";
			echo "<input type='hidden' id='filesystem_subcategory_$x' name='filesystem_retention_days[$x][subcategory]' value='$filesystem_subcategory'>";
			echo "<input type='hidden' id='filesystem_type_$x' name='filesystem_retention_days[$x][type]' value='$filesystem_setting_table'>";
		} else {
			echo "$filesystem_days";
		}
		echo "	</td>";
		echo "</tr>";
		$c = !$c;
	}
	echo "			</table>";
	echo "		</div>";

	//form save submit
	if (permission_exists('maintenance_edit')) {
		echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>";
	}
	echo "	</form>";
	echo "	<span class='hud_expander' onclick=\"$('#hud_maintenance_details').slideToggle('fast'); toggle_grid_row_end('Maintenance')\"><span class='fas fa-ellipsis-h'></span></span>";
	echo "</div>";
}
