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
trait database_maintenance {

	public static $database_maintenance_application = self::class;

	//class must implement this method
	abstract public static function database_maintenance_sql(string $domain_uuid, string $retention_days): string;

	/**
	 * The class may override this method by returning a different value to use for the default settings category
	 * @return string Value to use in the default settings for a category name
	 */
	public static function database_retention_category(): string{ return self::class; }

	/**
	 * The class may override this method by returning a different value to use for the default settings subcategory
	 * @return string value to use in the default settings for a subcategory (name of the setting)
	 */
	public static function database_retention_subcategory(): string { return "database_retention_days"; }

	/**
	 * Class can override this method to return the default value
	 * @return string
	 */
	public static function database_retention_default_value(): string {
		return '30';
	}

	/**
	 * Class can override this method to do their own maintenance or just return the sql for this method
	 * @return string
	 */
	public static function database_maintenance(database $database, settings $settings): void {
		//get retention days
		$days = $settings->get(self::database_retention_category(), self::database_retention_subcategory(), '');
		//look for old entries
		if (!empty($days) && is_numeric($days)) {
			$domains = maintenance_service::get_domains($database);
			foreach ($domains as $domain_uuid => $domain_name) {
				$database->execute(self::database_maintenance_sql($domain_uuid, $days));
				if ($database->message['code'] === '200') {
					maintenance_service::log_write(self::$database_maintenance_application, "Removed maintenance log entries older than $days days", $domain_uuid);
				} else {
					maintenance_service::log_write(self::$database_maintenance_application, "Failed to clear entries", $domain_uuid, maintenance_service::LOG_ERROR);
				}
			}
		} else {
			//database retention not set or not a valid number
			maintenance_service::log_write(self::$database_maintenance_application, 'Retention days not set', '', maintenance_service::LOG_ERROR);
		}
	}

	/**
	 * Creates a list of categories that have the database_retention_days even when the setting is disabled.
	 * @param database $database Database object
	 * @param bool $enabled Used internally
	 * @return array Two-dimensional array for returning the category and settings value. The key is the UUID of the domain or 'global'.
	 */
	public static function database_maintenance_settings(database $database, bool $enabled = true): array {
		//get the false values first and then overwrite them with true
		if ($enabled) {
			$settings = self::database_maintenance_settings($database, false);
		} else {
			$settings = [];
		}
		$status_string = $enabled ? 'true' : 'false';
		$category = self::database_retention_category();
		$subcategory = self::database_retention_subcategory();
		//get the global settings
		$sql = "select default_setting_value, default_setting_uuid from v_default_settings";
		$sql .= " where default_setting_category = '$category'";
		$sql .= " and default_setting_subcategory = '$subcategory'";
		$sql .= " and default_setting_enabled = '$status_string'";
		$sql .= " limit 1";
		$global_results = $database->select($sql);
		if (!empty($global_results)) {
			foreach ($global_results as $row) {
				$settings['global']['default_setting_uuid'] = $row['default_setting_uuid'];
				$settings['global']['default_setting_value'] = $row['default_setting_value'];
				$settings['global']['default_setting_enabled'] = $enabled;
			}
		}

		//get the per domain settings
		$sql = "select domain_uuid, domain_setting_uuid, domain_setting_enabled, domain_setting_value from v_domain_settings";
		$sql .= " where domain_setting_category = '$category'";
		$sql .= " and domain_setting_subcategory = '$subcategory'";
		$sql .= " and domain_setting_enabled = '$status_string'";
		$domain_results = $database->select($sql);
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
