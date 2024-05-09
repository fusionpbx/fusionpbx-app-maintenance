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

/**
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
trait filesystem_maintenance {

	public static $filesystem_maintenance_application = self::class;
	public static $filesystem_retention_category = self::class;
	public static $filesystem_retention_subcategory = 'filesystem_retention_days';

	//class must implement this method
	abstract public static function filesystem_maintenance_files(settings $settings, string $domain_uuid, string $domain_name, string $retention_days): array;

	/**
	 * Default setting for global default values when it is created
	 * @return string
	 */
	public static function filesystem_retention_default_value(): string {
		return '30';
	}

	/**
	 * Removes old files on a per-domain basis
	 * @param database $database
	 * @param settings $settings
	 * @return void
	 */
	public static function filesystem_maintenance(database $database, settings $settings): void {
		//get retention days
		$days = $settings->get(self::$filesystem_retention_category, self::$filesystem_retention_subcategory, '');
		//look for old entries
		if (!empty($days) && is_numeric($days)) {
			$domains = maintenance_service::get_domains($database);
			//loop over domains
			foreach ($domains as $domain_uuid => $domain_name) {
				//assign the settings object
				$domain_settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid]);
				$files = self::filesystem_maintenance_files($domain_settings, $domain_uuid, $domain_name, $days);
				//loop over each file in the domain
				foreach ($files as $file) {
					//check if it is old enough
					if (maintenance_service::days_since_created($file) > $days) {
						if (unlink($file)) {
							//successfully deleted file
							maintenance_service::log_write(self::$filesystem_maintenance_application, "Removed $file", $domain_uuid);
						} else {
							//failed
							maintenance_service::log_write(self::$filesystem_maintenance_application, "Unabled to remove $file", $domain_uuid, maintenance_service::LOG_ERROR);
						}
					}
				}
			}
		} else {
			//filesystem retention not set or not a valid number
			maintenance_service::log_write(self::$filesystem_maintenance_application, 'Retention days not set', '', maintenance_service::LOG_ERROR);
		}
	}
}
