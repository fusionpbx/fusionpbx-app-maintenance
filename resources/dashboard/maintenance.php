<?php

//includes files
	require_once dirname(__DIR__, 4) . "/resources/require.php";

//check for extra functions
	if (!function_exists('has_trait')) {
		if (file_exists(dirname(__DIR__) . '/functions.php')) {
			require_once dirname(__DIR__) . '/functions.php';
		}
	}

//connect to the database
	if (!isset($database)) {
		$database = new database;
	}

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
			$category = $row['category'];
			$subcategory = $row['subcategory'];
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
			$filesystem_category = $row['category'] ?? '';
			$filesystem_subcategory = $row['subcategory'] ?? '';
			$days = $row['days'] ?? '';
			if (!empty($filesystem_category) && !empty($filesystem_subcategory) && !empty($days)) {
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

//count the number of apps enabled
	foreach ($maintainers as $maintenance_app) {
		if (class_exists($maintenance_app)) {
			$app = new $maintenance_app($database, $setting);
			//check for database status
			if (has_trait($app, 'database_maintenance')) {
				$total_maintenance_apps++;
				$category = $app::$database_retention_category;
				$subcategory = $app::$database_retention_subcategory;
				if (!empty($setting->get($category, $subcategory, ''))) {
					$total_running_maintenance_apps++;
				}
			}
			//check for filesystem status
			if (has_trait($app, 'filesystem_maintenance')) {
				$total_maintenance_apps++;
				$category = $app::$filesystem_retention_category;
				$subcategory = $app::$filesystem_retention_subcategory;
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
	if (true) {
		//show the box
		echo "<div class='hud_box'>\n";
		echo "  <div style='display: flex; flex-wrap: wrap; justify-content: center; padding-bottom: 20px;' onclick=\"$('#hud_maintenance_details').slideToggle('fast');\">\n";
		echo "		<span class='hud_title' style='background-color: ".$dashboard_heading_background_color."; color: ".$dashboard_heading_text_color.";'>Maintenance</span>\n";
		echo "		<script src='/app/maintenance/resources/javascript/maintenance_functions.js'></script>";
		if ($dashboard_chart_type === 'doughnut') {
			//add an event listener for showing and hiding the days input box
			echo "	  <div style='width: 150px; height: 150px; padding-top: 7px;'><canvas id='maintenance_chart'></canvas></div>\n";
			echo "    <script src='/app/maintenance/resources/javascript/maintenance_chart.js'></script>\n";
//			echo "	  <span class='hud_stat' style='color: $dashboard_number_text_color; padding-bottom: 27px;'>$total_running_maintenance_apps / $total_maintenance_apps</span>\n";
		}
		if ($dashboard_chart_type === 'none') {
			echo "    <script src='/app/maintenance/resources/javascript/maintenance_chart.js'></script>\n";
			echo "	  <span class='hud_stat' style='color: $dashboard_number_text_color; padding-bottom: 27px;'>$total_running_maintenance_apps / $total_maintenance_apps</span>\n";
		}
		echo "  </div>\n";
		echo "\n";
		//form for maintenance changes
		echo "<form id='form_list_maintainers' name='form_list_maintainers' method='POST'>\n";
		echo " <div class='hud_details hud_box' id='hud_maintenance_details' style='text-align: right'>";
		//save button for changes
		echo "  <button type='button' alt='Save' title='Save' onclick=\"list_form_submit('form_list_maintainers');\" class='btn btn-default ' style='position: absolute; margin-top: -35px; margin-left: -72px;'><span class='fas fa-bolt fa-fw'></span><span class='button-label  pad'>Save</span></button>\n";
		echo "  <table class='tr_hover' width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
		echo "  <tr style='position: -webkit-sticky; position: sticky; z-index: 5; top: 0; left: 2px;'>\n";
		echo "    <th class='hud_heading' style='width: 40%;'>".($text['label-maintenance_application'] ?? 'Maintenance Application')."</th>\n";
		echo "    <th class='hud_heading' style='width: 15%;'>".'Database'."</th>\n";
		echo "    <th class='hud_heading' style='width: 15%;'>".'Days'."</th>\n";
		echo "    <th class='hud_heading' style='width: 15%;'>".'Filesystem'."</th>\n";
		echo "    <th class='hud_heading' style='width: 15%;'>".'Days'."</th>\n";
		echo "  </tr>\n";

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
			$param = [];
			if (class_exists($maintenance_app)) {
				//check for database status
				if (has_trait($maintenance_app, 'database_maintenance')) {
					$database_category = $maintenance_app::$database_retention_category;
					$database_subcategory = $maintenance_app::$database_retention_subcategory;
					$database_default_value = $maintenance_app::database_retention_default_value();
					$database_days = $setting->get($database_category, $database_subcategory, '');
					//uuid of setting
					$database_setting_uuids = maintenance_service::find_uuid($database, $database_category, $database_category);
					$database_setting_uuid = $database_setting_uuids['uuid'];
					$database_setting_table = $database_setting_uuids['table'];
					if (empty($database_days)) {
						$database_checkbox_state = CHECKBOX_UNCHECKED;
					} else {
						$database_checkbox_state = CHECKBOX_CHECKED;
					}
				}

				//check for filesystem status
				if (has_trait($maintenance_app, 'filesystem_maintenance')) {
					$filesystem_category = $maintenance_app::$filesystem_retention_category;
					$filesystem_subcategory = $maintenance_app::$filesystem_retention_subcategory;
					$filesystem_default_value = $maintenance_app::filesystem_retention_default_value();
					$filesystem_days = $setting->get($filesystem_category, $filesystem_subcategory, '');
					//uuid of setting
					$filesystem_setting_uuids = maintenance_service::find_uuid($database, $filesystem_category, $filesystem_subcategory);
					$filesystem_setting_uuid = $filesystem_setting_uuids['uuid'];
					$filesystem_setting_table = $filesystem_setting_uuids['table'];
					if (empty($filesystem_days)) {
						$filesystem_checkbox_state = CHECKBOX_UNCHECKED;
					} else {
						$filesystem_checkbox_state = CHECKBOX_CHECKED;
					}
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
			echo "<tr>\n";
			echo "    <td valign='top' class='".$row_style[$c]." hud_text'>$maintenance_app</td>\n";
			//
			// Database apps
			//
			//hide or show database maintenance ability
			if ($database_checkbox_state !== CHECKBOX_HIDDEN) {
				//enable or disable checkbox
				if (substr($setting->get('theme','input_toggle_style', ''), 0, 6) == 'switch') {
					echo "<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void' style='width: 1%; text-align: center;'>\n";
					echo "	<label class='switch'>\n";
					echo "		<input type='checkbox' name='database_retention_days[$x][status]' value='true' $database_checked onclick=\"this.checked ? show_input_box('database_days_$x') : hide_input_box('database_days_$x');\">\n";
					echo "		<span class='slider'></span>\n";
					echo "	</label>\n";
					echo "</td>\n";
				} else {
					echo "    <td valign='top' class='".$row_style[$c]." hud_text'>$database_status</td>\n";
				}
			} else {
				//not a database maintenance application
				echo "<td valign='top' class='".$row_style[$c]." hud_text'>&nbsp;</td>";
			}
			//database days input box
			echo "    <td valign='top' class='".$row_style[$c]." hud_text input tr_link_void'>\n";
			//hide the input box if we are hiding the checkbox
			if ($database_checkbox_state !== CHECKBOX_CHECKED) {
				$database_input_days_style = " display: none;";
			} else {
				$database_input_days_style = "";
			}

			echo "		<input class='formfld' style='width: 20%; min-width: 40px;$database_input_days_style' type='text' name='database_retention_days[$x][days]' id='database_days_$x' placeholder='days' maxlength='255' value='$database_days'>";
			echo "      <input type='hidden' id='database_uuid_$x' name='database_retention_days[$x][uuid]' value='$database_setting_uuid'>\n";
			echo "      <input type='hidden' id='database_category_$x' name='database_retention_days[$x][category]' value='$database_category'>\n";
			echo "      <input type='hidden' id='database_subcategory_$x' name='database_retention_days[$x][subcategory]' value='$database_subcategory'>\n";
			echo "      <input type='hidden' id='database_type_$x' name='database_retention_days[$x][type]' value='$database_setting_table'>\n";
			echo "    </td>\n";
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
			echo "<td valign='top' class='".$row_style[$c]." hud_text input tr_link_void' style='width: 1%; text-align: center;'>\n";
			if ($filesystem_checkbox_state !== CHECKBOX_HIDDEN) {
				if (substr($setting->get('theme','input_toggle_style', ''), 0, 6) == 'switch') {
						echo "	<label class='switch'>\n";
						echo "		<input type='checkbox' locked id='filesystem_enabled_$x' name='filesystem_retention_days[$x][status]' value='true' $filesystem_checked onclick=\"this.checked ? show_input_box('filesystem_days_$x') : hide_input_box('filesystem_days_$x');\">\n";
						echo "		<span class='slider'></span>\n";
						echo "	</label>\n";
						echo "</td>\n";
				}
				else {
					echo "    <td valign='top' class='".$row_style[$c]." hud_text'>\n";
					if ($show_filesystem_days) {
						echo $filesystem_enabled;
					} else {
						echo "&nbsp;";
					}
					echo "    </td>\n";
				}
			} else {
				echo "&nbsp;";
			}
			echo "</td>\n";
			//filesystem days input box
			echo "    <td valign='top' class='".$row_style[$c]." hud_text input tr_link_void'>\n";
			//hide the input box if we are hiding the checkbox
			if ($filesystem_checkbox_state !== CHECKBOX_CHECKED) {
				$filesystem_input_days_style = " display: none;";
			} else {
				$filesystem_input_days_style = "";
			}
			echo "		<input class='formfld' style='width: 20%; min-width: 40px;$filesystem_input_days_style' type='text' name='filesystem_retention_days[$x][days]' id='filesystem_days_$x' placeholder='days' maxlength='255' value='$filesystem_days'>";
			echo "      <input type='hidden' id='filesystem_uuid_$x' name='filesystem_retention_days[$x][uuid]' value='$filesystem_setting_uuid'>\n";
			echo "      <input type='hidden' id='filesystem_category_$x' name='filesystem_retention_days[$x][category]' value='$filesystem_category'>\n";
			echo "      <input type='hidden' id='filesystem_subcategory_$x' name='filesystem_retention_days[$x][subcategory]' value='$filesystem_subcategory'>\n";
			echo "      <input type='hidden' id='filesystem_type_$x' name='filesystem_retention_days[$x][type]' value='$filesystem_setting_table'>\n";
			echo "    </td>\n";
			echo "</tr>\n";
			$c = !$c;
		}

		//echo "    </div>";
		echo "  </table>\n";
		echo "</div>";
		//$n++;

		//form save submit
		if (permission_exists('ring_group_forward')) {
			echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		}
		echo "</form>\n";
		echo "<span class='hud_expander' onclick=\"$('#hud_maintenance_details').slideToggle('fast');\"><span class='fas fa-ellipsis-h'></span></span>\n";
		echo "</div>\n";
	}
?>
