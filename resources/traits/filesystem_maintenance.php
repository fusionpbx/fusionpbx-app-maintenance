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

	//the class name is used to track loading the object that uses the filesystem_maintenance
	//this value will appear in the default settings under maintenace->application
	public static $filesystem_maintenance_application = self::class;

	//class must implement this method
	abstract public static function filesystem_maintenance_files(settings $settings, string $domain_uuid, string $domain_name, string $retention_days): array;

	/**
	 * The class may override this method by returning a different value to use for the default settings category
	 * @return string Value to use in the default settings for a category name
	 */
	public static function filesystem_retention_category(): string{ return self::class; }

	/**
	 * The class may override this method by returning a different value to use for the default settings subcategory
	 * @return string value to use in the default settings for a subcategory (name of the setting)
	 */
	public static function filesystem_retention_subcategory(): string { return "filesystem_retention_days"; }

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
		$days = $settings->get(self::filesystem_retention_category(), self::filesystem_retention_subcategory(), '');
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

	/**
	 * Creates a list of categories that have the filesystem_retention_days even when the setting is disabled.
	 * @param database $database Database object
	 * @param bool $enabled Used internally
	 * @return array Two-dimensional array for returning the category and settings value. The key is the UUID of the domain or 'global'.
	 */
	public static function filesystem_maintenance_settings(database $database, bool $enabled = true): array {
		//get the false values first and then overwrite them with true
		if ($enabled) {
			$settings = self::filesystem_maintenance_settings($database, false);
		} else {
			$settings = [];
		}
		$parameters = [];
		$status_string = $enabled ? 'true' : 'false';
		$category = self::filesystem_retention_category();
		$subcategory = self::filesystem_retention_subcategory();
		$parameters['category'] = $category;
		//get the global settings
		$sql = "select default_setting_value, default_setting_uuid from v_default_settings";
		$sql .= " where default_setting_category = :category";
		$sql .= " and default_setting_subcategory = '$subcategory'";
		$sql .= " and default_setting_enabled = '$status_string'";
		$sql .= " limit 1";
		$global_results = $database->select($sql, $parameters);
		if (!empty($global_results)) {
			foreach ($global_results as $row) {
				$settings['global']['default_setting_uuid'] = $row['default_setting_uuid'];
				$settings['global']['default_setting_value'] = $row['default_setting_value'];
				$settings['global']['default_setting_enabled'] = $enabled;
			}
		}

		//get the per domain settings
		$sql = "select domain_uuid, domain_setting_uuid, domain_setting_enabled, domain_setting_value from v_domain_settings";
		$sql .= " where default_setting_category = :category";
		$sql .= " and domain_setting_subcategory = '$subcategory'";
		$sql .= " and domain_setting_enabled = '$status_string'";
		$domain_results = $database->select($sql, $parameters);
		if (!empty($domain_results)) {
			foreach ($domain_results as $row) {
				$settings[$row['domain_uuid']]['domain_setting_uuid'] = $row['domain_setting_uuid'];
				$settings[$row['domain_uuid']]['domain_setting_enabled'] = $row['domain_setting_enabled'];
				$settings[$row['domain_uuid']]['domain_setting_value'] = $row['domain_setting_value'];
			}
		}
		return $settings;
	}

}
