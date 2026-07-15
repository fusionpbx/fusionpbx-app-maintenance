<?php
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

if (!permission_exists('maintenance_view')) {
	die('Unauthorized');
}

$language = new text;
$text = $language->get();
$database = database::new();

function maintenance_storage_scan($path, $exclude_archive = false) {
	$result = ['bytes' => 0, 'files' => 0];

	if (!is_dir($path)) {
		return $result;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $file) {
		$pathname = $file->getPathname();

		if ($exclude_archive && strpos($pathname, DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR) !== false) {
			continue;
		}

		if ($file->isFile()) {
			$result['bytes'] += $file->getSize();
			$result['files']++;
		}
	}

	return $result;
}

function maintenance_storage_format($bytes) {
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$i = 0;

	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}

	return ($i === 0 ? number_format($bytes, 0) : number_format($bytes, 2)) . ' ' . $units[$i];
}

function maintenance_db_count($database, $table, $where = '', $parameters = []) {
	$sql = "select count(*) from ".$table;
	if (!empty($where)) {
		$sql .= " where ".$where;
	}

	$result = $database->select($sql, $parameters, 'column');
	return is_numeric($result) ? (int)$result : 0;
}

function maintenance_db_size($database, $table) {
	$sql = "select pg_total_relation_size('public.".$table."')";
	$result = $database->select($sql, null, 'column');
	return is_numeric($result) ? (int)$result : 0;
}

function maintenance_setting($database, $category, $subcategory, $default = '') {
	$settings = new settings(['database' => $database]);
	return $settings->get($category, $subcategory, $default);
}


function maintenance_archive_mappings($destinations) {
        $mappings = [];

        foreach (preg_split('/\r\n|\r|\n/', $destinations) as $line) {
                $line = trim($line);
                if ($line == '' || substr($line, 0, 1) == '#') {
                        continue;
                }

                $parts = explode('=>', $line, 2);
                if (count($parts) != 2) {
                        continue;
                }

                $source = rtrim(trim($parts[0]), '/');
                $destination = rtrim(trim($parts[1]), '/');

                if ($source != '' && $destination != '') {
                        $mappings[] = [
                                'source' => $source,
                                'destination' => $destination,
                        ];
                }
        }

        return $mappings;
}

function maintenance_archive_destination($record_path, $mappings) {
        foreach ($mappings as $mapping) {
                $source = $mapping['source'];
                $destination = $mapping['destination'];

                if ($record_path == $source || strpos($record_path, $source.'/') === 0) {
                        $relative_path = ltrim(substr($record_path, strlen($source)), '/');
                        return $destination . ($relative_path != '' ? '/' . $relative_path : '');
                }
        }

        return '';
}

//domain list
$sql = "select domain_uuid, domain_name from v_domains order by domain_name asc";
$domains = $database->select($sql, null, 'all') ?: [];

//domain filesystem usage
$domain_rows = [];
$domain_totals = [
	'recordings' => 0,
	'call_recordings' => 0,
	'voicemail' => 0,
	'fax' => 0,
	'xml_cdr_files' => 0,
	'files' => 0,
	'total' => 0,
];

foreach ($domains as $domain) {
	$domain_name = $domain['domain_name'];

	$recordings_path = "/var/lib/freeswitch/recordings/$domain_name";
	$call_recordings_path = "/var/lib/freeswitch/recordings/$domain_name/archive";
	$voicemail_path = "/var/lib/freeswitch/storage/voicemail/default/$domain_name";
	$fax_path = "/var/lib/freeswitch/storage/fax/$domain_name";
	$xml_cdr_files_path = "/var/log/freeswitch/xml_cdr/$domain_name";

	$recordings_scan = maintenance_storage_scan($recordings_path, true);
	$call_recordings_scan = maintenance_storage_scan($call_recordings_path);
	$voicemail_scan = maintenance_storage_scan($voicemail_path);
	$fax_scan = maintenance_storage_scan($fax_path);
	$xml_cdr_files_scan = maintenance_storage_scan($xml_cdr_files_path);

	$recordings = $recordings_scan['bytes'];
	$call_recordings = $call_recordings_scan['bytes'];
	$voicemail = $voicemail_scan['bytes'];
	$fax = $fax_scan['bytes'];
	$xml_cdr_files = $xml_cdr_files_scan['bytes'];

	$files =
		$recordings_scan['files'] +
		$call_recordings_scan['files'] +
		$voicemail_scan['files'] +
		$fax_scan['files'] +
		$xml_cdr_files_scan['files'];

	$total = $recordings + $call_recordings + $voicemail + $fax + $xml_cdr_files;

	$domain_rows[] = [
		'domain_name' => $domain_name,
		'recordings' => $recordings,
		'call_recordings' => $call_recordings,
		'voicemail' => $voicemail,
		'fax' => $fax,
		'xml_cdr_files' => $xml_cdr_files,
		'files' => $files,
		'total' => $total,
	];

	$domain_totals['recordings'] += $recordings;
	$domain_totals['call_recordings'] += $call_recordings;
	$domain_totals['voicemail'] += $voicemail;
	$domain_totals['fax'] += $fax;
	$domain_totals['xml_cdr_files'] += $xml_cdr_files;
	$domain_totals['files'] += $files;
	$domain_totals['total'] += $total;
}

usort($domain_rows, function($a, $b) {
	return $b['total'] <=> $a['total'];
});

//filesystem summary
$recordings_summary = ['bytes' => 0, 'files' => 0];
$call_recordings_summary = ['bytes' => 0, 'files' => 0];
$voicemail_summary = ['bytes' => 0, 'files' => 0];
$fax_summary = ['bytes' => 0, 'files' => 0];
$xml_cdr_files_summary = ['bytes' => 0, 'files' => 0];

foreach ($domain_rows as $row) {
	$recordings_summary['bytes'] += $row['recordings'];
	$call_recordings_summary['bytes'] += $row['call_recordings'];
	$voicemail_summary['bytes'] += $row['voicemail'];
	$fax_summary['bytes'] += $row['fax'];
	$xml_cdr_files_summary['bytes'] += $row['xml_cdr_files'];
}

//file counts already in domain rows are combined; category-specific counts need direct scans
foreach ($domains as $domain) {
	$domain_name = $domain['domain_name'];
	$recordings_scan = maintenance_storage_scan("/var/lib/freeswitch/recordings/$domain_name", true);
	$call_recordings_scan = maintenance_storage_scan("/var/lib/freeswitch/recordings/$domain_name/archive");
	$voicemail_scan = maintenance_storage_scan("/var/lib/freeswitch/storage/voicemail/default/$domain_name");
	$fax_scan = maintenance_storage_scan("/var/lib/freeswitch/storage/fax/$domain_name");
	$xml_cdr_files_scan = maintenance_storage_scan("/var/log/freeswitch/xml_cdr/$domain_name");

	$recordings_summary['files'] += $recordings_scan['files'];
	$call_recordings_summary['files'] += $call_recordings_scan['files'];
	$voicemail_summary['files'] += $voicemail_scan['files'];
	$fax_summary['files'] += $fax_scan['files'];
	$xml_cdr_files_summary['files'] += $xml_cdr_files_scan['files'];
}

$switch_logs_summary = maintenance_storage_scan('/var/log/freeswitch');

$archive_enabled = maintenance_setting($database, 'call_recordings', 'archive_enabled', false);
$archive_destinations = maintenance_setting($database, 'call_recordings', 'archive_destinations', '');
$archive_age_days = maintenance_setting($database, 'call_recordings', 'archive_age_days', 1);
$archive_verify_size = maintenance_setting($database, 'call_recordings', 'archive_verify_size', true);
$archive_log_level = maintenance_setting($database, 'call_recordings', 'archive_log_level', 'summary');
$archive_mappings = maintenance_archive_mappings($archive_destinations);

$archive_candidate_count = 0;
$archive_archived_count = 0;
$archive_source_bytes = 0;
$archive_destination_bytes = 0;

$sql = "select record_path, record_name ";
$sql .= "from v_xml_cdr ";
$sql .= "where record_path is not null ";
$sql .= "and record_name is not null ";
$sql .= "and start_stamp < now() - (:archive_age_days || ' days')::interval ";
$parameters = ['archive_age_days' => is_numeric($archive_age_days) ? intval($archive_age_days) : 1];
$archive_rows = $database->select($sql, $parameters, 'all');
unset($sql, $parameters);

if (is_array($archive_rows)) {
        foreach ($archive_rows as $row) {
                $record_path = rtrim($row['record_path'], '/');
                $record_name = $row['record_name'];
                $file = $record_path.'/'.$record_name;

                if (maintenance_archive_destination($record_path, $archive_mappings) != '' && file_exists($file)) {
                        $archive_candidate_count++;
                        $archive_source_bytes += filesize($file);
                }

                foreach ($archive_mappings as $mapping) {
                        $destination = rtrim($mapping['destination'], '/');
                        if ($record_path == $destination || strpos($record_path, $destination.'/') === 0) {
                                $archive_archived_count++;
                                if (file_exists($file)) {
                                        $archive_destination_bytes += filesize($file);
                                }
                                break;
                        }
                }
        }
}

$sql = "select maintenance_log_message, maintenance_log_status, insert_date ";
$sql .= "from v_maintenance_logs ";
$sql .= "where maintenance_log_application = 'call_recordings' ";
$sql .= "and maintenance_log_message like 'Call recordings archive complete%' ";
$sql .= "order by insert_date desc ";
$sql .= "limit 1";
$archive_last_summary = $database->select($sql, null, 'row');
unset($sql);

//database summary rows
$summary_rows = [];

//DB-backed categories
$summary_rows[] = [
	'category' => 'CDR Database',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'cdr', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_xml_cdr'),
	'storage' => maintenance_db_size($database, 'v_xml_cdr') + maintenance_db_size($database, 'v_xml_cdr_flow') + maintenance_db_size($database, 'v_xml_cdr_json') + maintenance_db_size($database, 'v_xml_cdr_logs'),
];

$summary_rows[] = [
	'category' => 'Device Logs',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'device_logs', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_device_logs'),
	'storage' => maintenance_db_size($database, 'v_device_logs'),
];

$summary_rows[] = [
	'category' => 'Event Guard Logs',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'event_guard', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_event_guard_logs'),
	'storage' => maintenance_db_size($database, 'v_event_guard_logs'),
];

$summary_rows[] = [
	'category' => 'Email Queue',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'email_queue', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_email_queue', "email_status = 'sent'"),
	'storage' => maintenance_db_size($database, 'v_email_queue'),
];

$summary_rows[] = [
	'category' => 'Fax Queue',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'fax_queue', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_fax_queue', "fax_status = 'sent'"),
	'storage' => maintenance_db_size($database, 'v_fax_queue'),
];

$summary_rows[] = [
	'category' => 'User Logs',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'users', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_user_logs'),
	'storage' => maintenance_db_size($database, 'v_user_logs'),
];

$summary_rows[] = [
	'category' => 'Database Transactions',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'database_transactions', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_database_transactions'),
	'storage' => maintenance_db_size($database, 'v_database_transactions'),
];

$summary_rows[] = [
	'category' => 'Maintenance Logs',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'maintenance_logs', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_maintenance_logs'),
	'storage' => maintenance_db_size($database, 'v_maintenance_logs'),
];

$summary_rows[] = [
	'category' => 'Fax Database',
	'type' => 'Database',
	'retention' => maintenance_setting($database, 'fax', 'database_retention_days', ''),
	'count' => maintenance_db_count($database, 'v_fax_files') + maintenance_db_count($database, 'v_fax_logs'),
	'storage' => maintenance_db_size($database, 'v_fax_files') + maintenance_db_size($database, 'v_fax_logs'),
];

//Filesystem-backed categories
$summary_rows[] = [
	'category' => 'Recordings',
	'type' => 'Filesystem',
	'retention' => '',
	'count' => $recordings_summary['files'],
	'storage' => $recordings_summary['bytes'],
];

$summary_rows[] = [
	'category' => 'Call Recordings',
	'type' => 'Filesystem',
	'retention' => maintenance_setting($database, 'call_recordings', 'filesystem_retention_days', ''),
	'count' => $call_recordings_summary['files'],
	'storage' => $call_recordings_summary['bytes'],
];

$summary_rows[] = [
	'category' => 'Voicemail Files',
	'type' => 'Filesystem',
	'retention' => maintenance_setting($database, 'voicemail', 'filesystem_retention_days', ''),
	'count' => $voicemail_summary['files'],
	'storage' => $voicemail_summary['bytes'],
];

$summary_rows[] = [
	'category' => 'Fax Files',
	'type' => 'Filesystem',
	'retention' => maintenance_setting($database, 'fax', 'filesystem_retention_days', ''),
	'count' => $fax_summary['files'],
	'storage' => $fax_summary['bytes'],
];

$summary_rows[] = [
	'category' => 'XML CDR Files',
	'type' => 'Filesystem',
	'retention' => '',
	'count' => $xml_cdr_files_summary['files'],
	'storage' => $xml_cdr_files_summary['bytes'],
];

$summary_rows[] = [
	'category' => 'Switch Logs',
	'type' => 'Filesystem',
	'retention' => maintenance_setting($database, 'switch', 'filesystem_retention_days', ''),
	'count' => $switch_logs_summary['files'],
	'storage' => $switch_logs_summary['bytes'],
];

usort($summary_rows, function($a, $b) {
	return $b['storage'] <=> $a['storage'];
});

$document['title'] = "Maintenance Storage Usage";
require_once dirname(__DIR__, 2) . "/resources/header.php";

echo "<div class='action_bar' id='action_bar'>\n";
echo "	<div class='heading'><b>Maintenance Storage Usage</b></div>\n";
echo "	<div class='actions'>\n";
echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'maintenance.php']);
echo "	</div>\n";
echo "	<div style='clear: both;'></div>\n";
echo "</div>\n";

echo "Shows estimated storage usage managed by FusionPBX maintenance tasks. Filesystem values are calculated from file sizes, not allocated disk blocks.";
echo "<br /><br />\n";


echo "<div class='heading'><b>Call Recording Archive</b></div>\n";
echo "<div class='card'>\n";
echo "<table class='list'>\n";
echo "<tr class='list-header'><th>Setting</th><th>Value</th></tr>\n";
echo "<tr class='list-row'><td>Enabled</td><td>".escape($archive_enabled ? 'true' : 'false')."</td></tr>\n";
echo "<tr class='list-row'><td>Archive Age</td><td>".escape($archive_age_days)." days</td></tr>\n";
echo "<tr class='list-row'><td>Verify Size</td><td>".escape($archive_verify_size ? 'true' : 'false')."</td></tr>\n";
echo "<tr class='list-row'><td>Log Level</td><td>".escape($archive_log_level)."</td></tr>\n";
echo "<tr class='list-row'><td>Eligible Source Recordings</td><td>".number_format($archive_candidate_count)." files / ".maintenance_storage_format($archive_source_bytes)."</td></tr>\n";
echo "<tr class='list-row'><td>Archived Recordings</td><td>".number_format($archive_archived_count)." files / ".maintenance_storage_format($archive_destination_bytes)."</td></tr>\n";

if (!empty($archive_mappings)) {
        foreach ($archive_mappings as $mapping) {
                echo "<tr class='list-row'><td>Destination Mapping</td><td>".escape($mapping['source'])." => ".escape($mapping['destination'])."</td></tr>\n";
        }
}
else {
        echo "<tr class='list-row'><td>Destination Mapping</td><td>Not configured</td></tr>\n";
}

if (is_array($archive_last_summary) && !empty($archive_last_summary)) {
        echo "<tr class='list-row'><td>Last Archive Summary</td><td>".escape($archive_last_summary['insert_date'])." - ".escape($archive_last_summary['maintenance_log_message'])."</td></tr>\n";
}
else {
        echo "<tr class='list-row'><td>Last Archive Summary</td><td>No archive summary found</td></tr>\n";
}

echo "</table>\n";
echo "</div>\n";
echo "<br />\n";

echo "<div class='heading'><b>Storage Summary</b></div>\n";
echo "<div class='card'>\n";
echo "<table class='list'>\n";
echo "<tr class='list-header'>\n";
echo "	<th>Category</th>\n";
echo "	<th>Type</th>\n";
echo "	<th class='right'>Retention</th>\n";
echo "	<th class='right'>Records / Files</th>\n";
echo "	<th class='right'>Storage</th>\n";
echo "</tr>\n";

foreach ($summary_rows as $row) {
	echo "<tr class='list-row'>\n";
	echo "	<td>".escape($row['category'])."</td>\n";
	echo "	<td>".escape($row['type'])."</td>\n";
	echo "	<td class='right'>".(!empty($row['retention']) ? escape($row['retention'])." days" : "&nbsp;")."</td>\n";
	echo "	<td class='right'>".number_format($row['count'])."</td>\n";
	echo "	<td class='right'><b>".maintenance_storage_format($row['storage'])."</b></td>\n";
	echo "</tr>\n";
}

echo "</table>\n";
echo "</div>\n";

echo "<br />\n";
echo "<div class='heading'><b>Domain Storage Usage</b></div>\n";
echo "<div class='card'>\n";
echo "<table class='list'>\n";
echo "<tr class='list-header'>\n";
echo "	<th>Domain</th>\n";
echo "	<th class='right'>Recordings</th>\n";
echo "	<th class='right'>Call Recordings</th>\n";
echo "	<th class='right'>Voicemail</th>\n";
echo "	<th class='right'>Fax</th>\n";
echo "	<th class='right'>XML CDR Files</th>\n";
echo "	<th class='right'>Files</th>\n";
echo "	<th class='right'>Total</th>\n";
echo "</tr>\n";

foreach ($domain_rows as $row) {
	echo "<tr class='list-row'>\n";
	echo "	<td>".escape($row['domain_name'])."</td>\n";
	echo "	<td class='right'>".maintenance_storage_format($row['recordings'])."</td>\n";
	echo "	<td class='right'>".maintenance_storage_format($row['call_recordings'])."</td>\n";
	echo "	<td class='right'>".maintenance_storage_format($row['voicemail'])."</td>\n";
	echo "	<td class='right'>".maintenance_storage_format($row['fax'])."</td>\n";
	echo "	<td class='right'>".maintenance_storage_format($row['xml_cdr_files'])."</td>\n";
	echo "	<td class='right'>".number_format($row['files'])."</td>\n";
	echo "	<td class='right'><b>".maintenance_storage_format($row['total'])."</b></td>\n";
	echo "</tr>\n";
}

echo "<tr class='list-row'>\n";
echo "	<td><b>Total</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['recordings'])."</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['call_recordings'])."</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['voicemail'])."</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['fax'])."</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['xml_cdr_files'])."</b></td>\n";
echo "	<td class='right'><b>".number_format($domain_totals['files'])."</b></td>\n";
echo "	<td class='right'><b>".maintenance_storage_format($domain_totals['total'])."</b></td>\n";
echo "</tr>\n";

echo "</table>\n";
echo "</div>\n";

require_once dirname(__DIR__, 2) . "/resources/footer.php";
