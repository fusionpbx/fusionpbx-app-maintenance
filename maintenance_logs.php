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
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";
require_once "resources/paging.php";

//check permissions
if (permission_exists('maintenance_log_view')) {
	//access granted
}
else {
	echo "access denied";
	exit;
}

//add multi-lingual support
$language = new text;
$text = $language->get();

//set additional variables
$database = new database;
$setting = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? '', 'user_uuid' => $_SESSION['user_uuid'] ?? '']);
$search = $_GET["search"] ?? '';
$show = $_GET["show"] ?? '';

//set from session variables
$list_row_edit_button = !empty($_SESSION['theme']['list_row_edit_button']['boolean']) ? $_SESSION['theme']['list_row_edit_button']['boolean'] : 'false';

//get the http post data
if (!empty($_POST['maintenance_logs']) && is_array($_POST['maintenance_logs'])) {
	$action = $_POST['action'];
	$search = $_POST['search'];
	$maintenance_logs = $_POST['maintenance_logs'];
}

//process the http post data by action
if (!empty($action) && is_array($maintenance_logs) && @sizeof($maintenance_logs) != 0) {
	switch ($action) {
		case 'copy':
			if (permission_exists('maintenance_log_add')) {
				$obj = new maintenance_logs($database, $setting);
				$obj->copy($maintenance_logs);
			}
			break;
		case 'toggle':
			if (permission_exists('maintenance_log_edit')) {
				$obj = new maintenance_logs($database, $setting);
				$obj->toggle($maintenance_logs);
			}
			break;
		case 'delete':
			if (permission_exists('maintenance_log_delete')) {
				$obj = new maintenance_logs($database, $setting);
				$obj->delete($maintenance_logs);
			}
			break;
	}

	header('Location: maintenance_logs.php'.($search != '' ? '?search='.urlencode($search) : null));
	exit;
}

//get order and order by
$order_by = $_GET["order_by"] ?? '';
$order = $_GET["order"] ?? '';

//set the time zone
if (isset($_SESSION['domain']['time_zone']['name'])) {
	$time_zone = $_SESSION['domain']['time_zone']['name'];
}
else {
	$time_zone = date_default_timezone_get();
}

//add the search string
if (isset($_GET["search"]) && $_GET["search"] != '') {
	$search =  strtolower($_GET["search"]);
}

//get the count
$parameters = [];
$sql = "select count(maintenance_log_uuid) \n";
$sql .= "from v_maintenance_logs \n";
//	if ($show == "all" && permission_exists('maintenance_log_all')) {
//		$sql .= "where true \n";
//	}
//	else {
//		$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
//		$parameters['domain_uuid'] = $domain_uuid;
//	}
if (!empty($search)) {
	$sql .= "and (";
	$sql .= "	lower(maintenance_address) like :search ";
	$sql .= "	or lower(request_scheme) like :search ";
	$sql .= "	or lower(http_host) like :search ";
	$sql .= "	or lower(server_port) like :search ";
	$sql .= "	or lower(server_protocol) like :search ";
	$sql .= "	or lower(query_string) like :search ";
	$sql .= "	or lower(remote_address) like :search ";
	$sql .= "	or lower(http_user_agent) like :search ";
	$sql .= "	or lower(http_status) like :search ";
	$sql .= "	or lower(http_status_code) like :search ";
	$sql .= ") ";
	$parameters['search'] = '%'.$search.'%';
}
$num_rows = $database->select($sql, $parameters, 'column');

//prepare to page the results
//	$rows_per_page = (!empty($_SESSION['domain']['paging']['numeric'])) ? $_SESSION['domain']['paging']['numeric'] : 50;
//	$param = $search ? "&search=".$search : null;
//	$page = isset($_GET['page']) ? $_GET['page'] : 0;
//	list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
//	list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
//	$offset = $rows_per_page * $page;

//get the list
$sql = "select"
	. " maintenance_log_uuid"
	. ", domain_uuid"
	. ", maintenance_log_application"
	. ", to_timestamp(maintenance_log_epoch)::timestamptz as maintenance_log_epoch"
	. ", maintenance_log_message"
	. ", maintenance_log_status"
	. " from"
	. " v_maintenance_logs"
;
//	if (isset($search)) {
//		$sql .= "and ( \n";
//		$sql .= "	lower(maintenance_address) like :search \n";
//		$sql .= "	or lower(request_scheme) like :search \n";
//		$sql .= "	or lower(http_host) like :search \n";
//		$sql .= "	or lower(server_port) like :search \n";
//		$sql .= "	or lower(server_protocol) like :search \n";
//		$sql .= "	or lower(query_string) like :search \n";
//		$sql .= "	or lower(remote_address) like :search \n";
//		$sql .= "	or lower(http_user_agent) like :search \n";
//		$sql .= "	or lower(http_status) like :search \n";
//		$sql .= "	or lower(http_status_code) like :search \n";
//		$sql .= ") ";
//		$parameters['search'] = '%'.$search.'%';
//	}

$parameters = [];
//	$parameters['time_zone'] = $time_zone;
//	$sql .= order_by($order_by, $order, 'timestamp', 'desc');
//	$sql .= limit_offset($rows_per_page, $offset);
//	$maintenance_logs = $database->select($sql, $parameters, 'all');
$maintenance_logs = $database->select($sql);

//no results
if ($maintenance_logs === false) {
	$maintenance_logs = [];
}

//reset parameters
unset($sql, $parameters);

//create token
$object = new token;
$token = $object->create($_SERVER['PHP_SELF']);

//include the header
$document['title'] = $text['title-maintenance_logs'];
require_once "resources/header.php";

//show the content
echo "<div class='action_bar' id='action_bar'>\n";
	echo "<div class='heading'>";
		echo "<b>".$text['title-maintenance_logs']." (".$num_rows.")</b>";
	echo "</div>\n";
	echo "<div class='actions'>\n";
		echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'maintenance.php']);
		if (permission_exists('maintenance_log_delete') && $maintenance_logs) {
			echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','onclick'=>"modal_open('modal-delete','btn_delete');"]);
		}
		echo "<form id='form_search' class='inline' method='get'>\n";
			echo "<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown='list_search_reset();'>";
			echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search','style'=>($search != '' ? 'display: none;' : null)]);
			echo button::create(['label'=>$text['button-reset'],'icon'=>$_SESSION['theme']['button_icon_reset'],'type'=>'button','id'=>'btn_reset','link'=>'maintenance_logs.php','style'=>($search == '' ? 'display: none;' : null)]);
			if (!empty($paging_controls_mini)) {
				echo "<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
			}
		echo "</form>\n";
	echo "</div>\n";
	echo "<div style='clear: both;'></div>\n";
echo "</div>\n";

if (permission_exists('maintenance_log_delete') && $maintenance_logs) {
	echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

echo $text['description-maintenance_logs']."\n";
echo "<br /><br />\n";

echo "<form id='form_list' method='post'>\n";
	echo "<input type='hidden' id='action' name='action' value=''>\n";
	echo "<input type='hidden' name='search' value=\"".escape($search)."\">\n";

	echo "<table class='list'>\n";
		//header row
		echo "<tr class='list-header'>\n";
			if (permission_exists('maintenance_log_delete')) {
				echo "<th class='checkbox'>\n";
					echo "<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle();' ".($maintenance_logs ?: "style='visibility: hidden;'").">\n";
				echo "</th>\n";
			}
			if ($show == 'all' && permission_exists('maintenance_log_all')) {
				echo th_order_by('domain_name', $text['label-domain'], $order_by, $order);
			}
			echo "<th class='left'>".$text['label-domain']."</th>\n";
			echo "<th class='left'>".$text['label-application']."</th>\n";
			echo "<th class='left'>".$text['label-server_timestamp']."</th>\n";
			echo "<th class='left'>".$text['label-status']."</th>\n";
			echo "<th class='left hide-md-dn'>".$text['label-message']."</th>\n";
			if (permission_exists('maintenance_log_edit') && $list_row_edit_button == 'true') {
				echo "<td class='action-button'>&nbsp;</td>\n";
			}
		echo "</tr>\n";

		//data rows
		if (is_array($maintenance_logs) && @sizeof($maintenance_logs) != 0) {
			$domains = maintenance_service::get_domains($database);
			$x = 0;
			foreach ($maintenance_logs as $row) {
				$application_name = ucwords(str_replace('_', ' ', $row['maintenance_log_application']));
				$domain_name = $domains[$row['domain_uuid']] ?: 'Global';
				if (permission_exists('maintenance_log_edit')) {
					$list_row_url = "maintenance_log_edit.php?id=".urlencode($row['maintenance_log_uuid']);
				}
				echo "<tr class='list-row' href='".$list_row_url."'>\n";
					if (permission_exists('maintenance_log_delete')) {
						echo "<td class='checkbox'>\n";
							echo "<input type='checkbox' name='maintenance_logs[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"if (!this.checked) { document.getElementById('checkbox_all').checked = false; }\">\n";
							echo "<input type='hidden' name='maintenance_logs[$x][uuid]' value='".escape($row['maintenance_log_uuid'])."' />\n";
						echo "</td>\n";
					}
					echo "<td class='left'>$domain_name</td>\n";
					echo "<td class='left'>".escape($application_name)."</td>\n";
					echo "<td class='left'>".escape($row['maintenance_log_epoch'])."</td>\n";
					echo "<td class='left'>".escape($row['maintenance_log_status'])."</td>\n";
					echo "<td class='left hide-sm-dn'>".escape($row['maintenance_log_message'])."</td>\n";
					if (permission_exists('maintenance_log_edit') && $list_row_edit_button == 'true') {
						echo "<td class='action-button'>\n";
							echo button::create(['type'=>'button','title'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>$list_row_url]);
						echo "</td>\n";
					}
				echo "</tr>\n";
				$x++;
			}
		}

	echo "</table>\n";
	echo "<br />\n";
	echo "<div align='center'>".$paging_controls."</div>\n";
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
echo "</form>\n";

//include the footer
require_once "resources/footer.php";

?>
