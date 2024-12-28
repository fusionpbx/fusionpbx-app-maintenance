# Maintenance Service

## Why Use Maintenance Tasks?
This application fully integrates in to the Dashboard to show enabled / disabled maintenance applications at a glance on the current domain.
- Automatic detection and installation of new maintenance services when **App Defaults** is executed.
- Each application can execute on a per domain basis making it possible for a per tenant limit
- Simple function call for each class enabling a complete customization for the maintenance application. This opens a new host of capabilities like filesystem quotas or archiving old database records.
- Built-in logging is available via the *maintenance_service::log_write()* method and viewable in the new **Maintenance Logs** viewer (see screenshots).
- Default setings are done automatically per maintenance application when registered. Each application can set the number of days to retain data both globally and per domain under the **Maintenance** category.

## New Service Class
Utilizes the new service class to allow for easy installation and a standardize comprehensive command line interpreter. With the ability to reload the maintenance service settings without a restart of the service.
Now simply type ``./maintenance_service -r`` or ``./maintenance_service --reload`` and the settings will be reloaded.

## New Settings Class
Utilizes the new settings class to allow for independent loading of the default settings or getting the current database connection. Simply use ``$database = $settings->database();`` to get the already connected database.

## Dashboard Integration
Shows the maintenance applications currently active and their retention days on the filesystem and database. Easily modify the retention days for each registered application.

## Developer Friendly
Quickly create new maintenance applications by adding a ``public static function database_maintenance(settings $settings): void {}`` to handle any database work and/or a ``public static function filesystem_maintenance(settings $settings): void {}`` to handle any filesystem work in your existing class or create a new class that uses one or both methods. The maintenance application will automatically find it and register the application when **App Defaults** has been executed. Once the application is enabled in default settings, the application will execute on the next cycle.

## Screenshots

### Dashboard

![dashboard](https://github.com/fusionpbx/fusionpbx-app-maintenance/blob/main/resources/images/screenshot_dashboard.png)

### Maintenance Page

![maintenance page](https://github.com/fusionpbx/fusionpbx-app-maintenance/blob/main/resources/images/screenshot_maintenace.png)

### Maintenance Logs

![maintenance logs](https://github.com/fusionpbx/fusionpbx-app-maintenance/blob/main/resources/images/screenshot_maintenance_logs.png)

---

## Installation

### Clone this repo to FusionPBX app folder:

```
sudo git clone https://github.com/fusionpbx/fusionpbx-app-maintenance /var/www/fusionpbx/app/maintenance
sudo chown -R www-data:www-data /var/www/fusionpbx/app/maintenance
```

### Install as a service for Systemd based Linux (Debian, Ubuntu, CentOS, etc)

```
sudo cp /var/www/fusionpbx/app/maintenance/resources/service/debian.service /etc/systemd/system/maintenance.service
sudo systemctl daemon-reload
sudo systemctl enable maintenance
sudo systemctl start maintenance
```

### Other system types

Enter the following commands as root user

```
cd /var/www/fusionpbx/app/maintenance/resources/service
./maintenance_service
```

## Command-line help

Use the command-line help function to view the options available

```
cd /var/www/fusionpbx/app/maintenance/resources/service
./maintenance_service --help
```

Using the above command will output something similar to:

```
Version 1.00
Usage: php maintenance_service [options]
Options:
-v         --version       Show the version information
-h         --help          Show the version and help message
-a         --about         Show the version and copyright information
-r         --reload        Reload settings for an already running service
-d <level> --debug <level> Set the syslog level between 0 (EMERG) and 7 (DEBUG). 5 (INFO) is default
-c <path>  --config <path> Full path and file name of the configuration file to use. /etc/fusionpbx/config.conf or /usr/local/etc/fusionpbx/config.conf on FreeBSD is default
-1         --no-fork       Do not fork the process
-x         --exit          Exit the service gracefully
-i         --immediate     Launch maintenance tasks immediately on startup and on each reload
```
---

### Code Examples

```
/**
 * Example to calculate the file sizes of voicemail to implement a quota for tenants.
 * Called when a file system maintenance is triggered from the maintenance service.
 * @param settings $settings Settings Object
 * @return void
 */
public static function filesystem_maintenance(settings $settings): void {
	$database = $settings->database();
	$voicemail_location = $settings->get('switch', 'voicemail', '/var/lib/freeswitch/storage/voicemail') . '/default';
	$domains = maintenance_service::get_domains($database, true);
	foreach ($domains as $domain_uuid => $domain_name) {
		$domain_settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid]);
		$quota_bytes = $domain_settings(self::class, 'filesystem_quota_bytes');
		$directory = $voicemail_location . "/$domain_name/*";
		$wav_files = glob($directory . '/msg_*.wav');
		$mp3_files = glob($directory . '/msg_*.mp3');
		$voicemail_files = array_merge($wav_files, $mp3_files);
		$files = [];
		foreach ($voicemail_files as $file) {
			$files[] = [
				'path' => $file,
				'size' => filesize ($file),
				'mtime' => filemtime($file)
			];
		}
		usort($files, function ($a, $b) {
			if (!empty($a) && !empty($b)) {
				return $a['mtime'] <=> $b['mtime'];
			} else {
				return 0;
			}
		});
		$total_bytes = array_sum(array_column($files, 'size'));
		foreach ($files as $file) {
			while ($total_bytes > $quota_bytes) {
				//directory is over quota
				if (unlink($file['path'])) {
					maintenance_service::log_write(self::class, "Removed oldest voicemail file:" . $file['path'], $domain_uuid);
					$total_bytes -= $file['size'];
				} else {
					maintenance_service::log_write(self::class, "Failed to delete file: " . $file['path'], $domain_uuid, maintenance_service::LOG_ERROR);
				}
			}
		}
	}
}

/**
 * Example to clear out the Event Guard logs without using a per domain loop
 * @param settins $settings
 * @return void
 */
public static function database_maintenance(settings $settings): void {
	$table = 'event_guard_logs';
	$database = $settings->database();
	$category = self::class;
	$subcategory = 'database_retention_days';
	$retention_days = $settings->get($category, $subcategory, '');
	if (!empty($retention_days)) {
		$sql = "delete from v_{$table} where insert_date < NOW() - INTERVAL '{$retention_days} days'";
		$database->execute($sql);
		if ($database->message['code'] === '200') {
			maintenance_service::log_write(self::class, "Log entries cleared");
		} else {
			maintenance_service::log_write(self::class, "Failed to clear database entries", null, maintenance_service::LOG_ERROR);
		}
	}
}

```
