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
	public static $database_retention_category = self::class;
	public static $database_retention_subcategory = 'database_retention_days';

	//class must implement this method
	abstract public static function database_maintenance_sql(string $domain_uuid, string $retention_days): string;

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
		$days = $settings->get(self::$database_retention_category, self::$database_retention_subcategory, '');
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
}
