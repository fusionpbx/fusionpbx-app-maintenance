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
 * denisent dev team
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

//add multilingual support
$language = new text;
$text = $language->get();

//set the session variables as local variables
$domain_uuid = $_SESSION['domain_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

//create the database object
$database = database::new();

//create the settings object
if (!$settings) {
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);
}

//set request variables
$search = $_REQUEST["search"] ?? '';
$show = $_REQUEST["show"] ?? '';
$action = $_REQUEST['action'] ?? '';
$maintenance_logs_js = $_POST['maintenance_logs'] ?? [];

//get order and order by
$order_by = $_GET["order_by"] ?? '';
$order = $_GET["order"] ?? '';

//set from session variables
$list_row_edit_button = $settings->get('theme', 'list_row_edit_button', false);

//process the http post data by action
if (!empty($action) && count($maintenance_logs_js) > 0) {
	switch ($action) {
		case 'copy':
			if (permission_exists('maintenance_log_add')) {
				$obj = new maintenance_logs($database, $settings);
				$obj->copy($maintenance_logs_js);
			}
			break;
		case 'toggle':
			if (permission_exists('maintenance_log_edit')) {
				$obj = new maintenance_logs($database, $settings);
				$obj->toggle($maintenance_logs_js);
			}
			break;
		case 'delete':
			if (permission_exists('maintenance_log_delete')) {
				$obj = new maintenance_logs($database, $settings);
				$obj->delete($maintenance_logs_js);
			}
			break;
	}

	header('Location: maintenance_logs.php'.($search != '' ? '?search='.urlencode($search) : null));
	exit;
}

//set the time zone
$time_zone = $settings->get('domain', 'time_zone', date_default_timezone_get());

//add the search string
if (isset($_GET["search"]) && $_GET["search"] != '') {
	$search =  strtolower($_GET["search"]);
}

//get the count
$parameters = [];
$sql = "SELECT"
	. " count(m.maintenance_log_uuid)"
	. " FROM"
	. "  v_maintenance_logs m"
	. " LEFT JOIN v_domains d ON d.domain_uuid = m.domain_uuid";
if ($show == "all" && permission_exists('maintenance_log_all')) {
	$sql .= " WHERE true";
}
else {
	$sql .= " WHERE (m.domain_uuid = :domain_uuid OR m.domain_uuid IS NULL) ";
	$parameters['domain_uuid'] = $domain_uuid;
}

if (isset($search)) {
	$sql .= " and (";
	$sql .= " lower(m.maintenance_log_application) like :search";
	$sql .= " or lower(m.maintenance_log_message) like :search";
	$sql .= " or lower(m.maintenance_log_status) like :search";
	$sql .= " or lower(d.domain_name) like :search";
	$sql .= ")";
	$parameters['search'] = '%'.$search.'%';
}

//$parameters['time_zone'] = $time_zone;
$sql .= " GROUP BY m.maintenance_log_epoch";
$sql .= order_by($order_by, $order, 'maintenance_log_epoch', 'desc');

// Rows per page and offset are not yet defined
//$sql .= limit_offset($rows_per_page, $offset);


if (count($parameters) > 0) {
	$num_rows = $database->select($sql, $parameters, 'column');
} else {
	$num_rows = $database->select($sql, null, 'column');
}

//prepare to page the results
$rows_per_page = $settings->get('domain', 'paging', 50);
$param = $search ? "&search=".$search : null;
$page = $_GET['page'] ?? 0;
list($paging_controls, $rows_per_page) = paging($num_rows, $param, $rows_per_page);
list($paging_controls_mini, $rows_per_page) = paging($num_rows, $param, $rows_per_page, true);
$offset = $rows_per_page * $page;

//get the list
$sql = "SELECT"
	. " m.maintenance_log_uuid"
	. ", m.domain_uuid"
	. ", d.domain_name"
	. ", m.maintenance_log_application"
	. ", to_timestamp(m.maintenance_log_epoch)::timestamptz AS maintenance_log_epoch"
	. ", m.maintenance_log_message"
	. ", m.maintenance_log_status"
	. " FROM"
	. "  v_maintenance_logs m"
	. " LEFT JOIN v_domains d ON d.domain_uuid = m.domain_uuid";
if ($show == "all" && permission_exists('maintenance_log_all')) {
	$sql .= " WHERE true";
}
else {
	$sql .= " WHERE (m.domain_uuid = :domain_uuid OR m.domain_uuid IS NULL) ";
	$parameters['domain_uuid'] = $domain_uuid;
}

if (isset($search)) {
	$sql .= " and (";
	$sql .= " lower(m.maintenance_log_application) like :search";
	$sql .= " or lower(m.maintenance_log_message) like :search";
	$sql .= " or lower(m.maintenance_log_status) like :search";
	$sql .= " or lower(d.domain_name) like :search";
	$sql .= ")";
	$parameters['search'] = '%'.$search.'%';
}

//$parameters['time_zone'] = $time_zone;
$sql .= order_by($order_by, $order, 'maintenance_log_epoch', 'desc');
$sql .= limit_offset($rows_per_page, $offset);
$maintenance_logs = $database->select($sql, $parameters, 'all');
unset($sql, $parameters);

//no results
if ($maintenance_logs === false) {
	$maintenance_logs = [];
}

//create token
$token = new token;
$token_arr = $token->create($_SERVER['PHP_SELF']);

//include the header
$document['title'] = $text['title-maintenance_logs'];
require_once "resources/header.php";

//show the content
echo "<div class='action_bar' id='action_bar'>\n";
echo "	<div class='heading'>";
echo "		<b>".$text['title-maintenance_logs']."</b>";
echo "	</div>\n";
echo "	<div class='actions'>\n";
//button back
echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'maintenance.php']);
if (permission_exists('maintenance_log_delete') && $maintenance_logs) {
	//button delete
	echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'id'=>'btn_delete','name'=>'btn_delete','style'=>'display: none; margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
}
echo "		<form id='form_search' class='inline' method='get'>\n";
if (permission_exists('maintenance_log_all')) {
	if ($show == 'all') {
		echo "	<input type='hidden' name='show' value='all'>\n";
	}
	else {
		//button show_all
		echo button::create(['type'=>'button','alt'=>$text['button-show_all']??'Show All','label'=>$text['button-show_all']??'Show All','class'=>'btn btn-default','icon'=>$_SESSION['theme']['button_icon_all']??'globe','link'=>(empty($url_params) ? '?show=all' : $url_params . '&show=all')]);
	}
}
echo "			<input type='text' class='txt list-search' name='search' id='search' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown='list_search_reset();'>";
//button search
echo button::create(['label'=>$text['button-search'],'icon'=>$_SESSION['theme']['button_icon_search'],'type'=>'submit','id'=>'btn_search']);
//button reset
//echo button_reset::create(empty($search));
if (!empty($paging_controls_mini)) {
	echo "		<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
}
echo "		</form>\n";
echo "	</div>\n";
echo "	<div style='clear: both;'></div>\n";
echo "</div>\n";

if (permission_exists('maintenance_log_delete') && $maintenance_logs) {
	echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_action_set('delete'); list_form_submit('form_list');"])]);
}

echo $text['description-maintenance_logs']."\n";
echo "<br /><br />\n";

//group maintenance logs by application
$maintenance_log_groups = [];
foreach ($maintenance_logs as $row) {
        $application = $row['maintenance_log_application'] ?? '';
        if ($application == '') {
                $application = 'unknown';
        }
        $maintenance_log_groups[$application][] = $row;
}

ksort($maintenance_log_groups);

function maintenance_log_display_name($application) {
        if ($application == 'xml_cdr') {
                return 'CDR';
        }
        return ucwords(str_replace('_', ' ', $application));
}

echo "<style>
.maintenance-status-card summary { cursor: pointer; list-style: none; }
.maintenance-status-card summary::-webkit-details-marker { display: none; }
.maintenance-status-header { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
.maintenance-status-title { font-weight: bold; font-size: 115%; }
.maintenance-status-meta { font-weight: normal; opacity: 0.8; }
.maintenance-status-pill { display: inline-block; padding: 3px 8px; border-radius: 12px; font-weight: bold; }
.maintenance-status-ok { background: #e7f6e7; color: #1f6f1f; }
.maintenance-status-error { background: #fde8e8; color: #9b1c1c; }
.maintenance-status-grid { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 12px; margin-top: 12px; }
.maintenance-status-metric { border: 1px solid #eee; padding: 8px 10px; border-radius: 4px; }
.maintenance-status-label { opacity: 0.75; font-size: 90%; }
.maintenance-status-value { font-weight: bold; margin-top: 3px; }
</style>\n";

echo "<div class='heading'><b>Maintenance Status</b></div>\n";

foreach ($maintenance_log_groups as $application => $rows) {
        $errors = 0;
        foreach ($rows as $row) {
                if (($row['maintenance_log_status'] ?? '') == 'error') {
                        $errors++;
                }
        }

        $latest = $rows[0] ?? [];
        $latest_time = $latest['maintenance_log_epoch'] ?? '';
        $latest_message = $latest['maintenance_log_message'] ?? '';
        $latest_status = $latest['maintenance_log_status'] ?? '';

        $status_label = $latest_status == 'error' ? 'Error' : 'Success';
        $status_class = $latest_status == 'error' ? 'maintenance-status-error' : 'maintenance-status-ok';
        $open = $latest_status == 'error' ? ' open' : '';

        echo "<details class='card maintenance-status-card'".$open.">\n";
        echo "  <summary>\n";
        echo "    <div class='maintenance-status-header'>\n";
        echo "      <div><span class='maintenance-status-title'>".escape(maintenance_log_display_name($application))."</span> ";
        echo "      <span class='maintenance-status-meta'>(".number_format(count($rows))." log".(count($rows) == 1 ? "" : "s").($errors > 0 ? ", ".$errors." error".($errors == 1 ? "" : "s") : "").")</span></div>\n";
        echo "      <div><span class='maintenance-status-pill ".$status_class."'>".$status_label."</span></div>\n";
        echo "    </div>\n";

        echo "    <div class='maintenance-status-grid'>\n";
        echo "      <div class='maintenance-status-metric'><div class='maintenance-status-label'>Last Run</div><div class='maintenance-status-value'>".escape($latest_time)."</div></div>\n";
        echo "      <div class='maintenance-status-metric'><div class='maintenance-status-label'>Status</div><div class='maintenance-status-value'>".escape($status_label)."</div></div>\n";
        echo "      <div class='maintenance-status-metric'><div class='maintenance-status-label'>Entries</div><div class='maintenance-status-value'>".number_format(count($rows))."</div></div>\n";
        echo "      <div class='maintenance-status-metric'><div class='maintenance-status-label'>Errors</div><div class='maintenance-status-value'>".number_format($errors)."</div></div>\n";
        echo "    </div>\n";
        echo "  </summary>\n";

        echo "  <br />\n";
        echo "  <table class='list'>\n";
        echo "    <tr class='list-header'>\n";
        if ($show == 'all' && permission_exists('maintenance_log_all')) {
                echo "      <th>Domain</th>\n";
        }
        echo "      <th>Server Timestamp</th>\n";
        echo "      <th>Status</th>\n";
        echo "      <th>Message</th>\n";
        echo "    </tr>\n";

        foreach ($rows as $row) {
                echo "    <tr class='list-row'>\n";
                if ($show == 'all' && permission_exists('maintenance_log_all')) {
                        echo "      <td>".escape($row['domain_name'])."</td>\n";
                }
                echo "      <td class='no-wrap'>".escape($row['maintenance_log_epoch'])."</td>\n";
                echo "      <td>".escape($row['maintenance_log_status'])."</td>\n";
                echo "      <td>".escape($row['maintenance_log_message'])."</td>\n";
                echo "    </tr>\n";
        }

        echo "  </table>\n";
        echo "</details>\n";
        echo "<br />\n";
}

//show the footer and stop before the legacy raw table
require_once "resources/footer.php";
exit;
