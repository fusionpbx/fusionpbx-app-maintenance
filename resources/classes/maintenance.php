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
 * Description of maintenance_app
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class maintenance {

	const APP_NAME = 'Maintenance';
	const APP_UUID = '8a209bac-3bba-46eb-828e-bd4087fb9cee';
	const PERMISSION_PREFIX = 'maintenance_';
	const LIST_PAGE = 'maintenance.php';
	const TABLE = 'maintenance';
	const UUID_PREFIX = 'maintenance_';
	const TOGGLE_FIELD = 'maintenance_enabled';
	const TOGGLE_VALUES = ['true', 'false'];

	/**
	 * Returns an array of domain names with their domain UUID as the array key
	 * @param database $database
	 * @param bool $ignore_domain_enabled Omit the SQL where clause for domain_enabled
	 * @param bool $domain_status When the <code>$ignore_domain_enabled</code> is false, set the status to true or false
	 * @return array
	 */
	public static function get_domains(database $database, bool $ignore_domain_enabled = false, bool $domain_status = true): array {
		$domains = [];
		$status_string = $domain_status ? 'true' : 'false';
		$sql = "select domain_uuid, domain_name from v_domains";
		if (!$ignore_domain_enabled) {
			$sql .= " where domain_enabled='$status_string'";
		}
		$result = $database->select($sql);
		if (!empty($result)) {
			foreach ($result as $row) {
				$domains[$row['domain_uuid']] = $row['domain_name'];
			}
		}
		return $domains;
	}

	public static function app_defaults(database $database) {
		//get the maintenance apps
		$database_maintenance_apps = self::find_classes_by_method('database_maintenance');
		$filesystem_maintenance_apps = self::find_classes_by_method('filesystem_maintenance');
		$maintenance_apps = $database_maintenance_apps + $filesystem_maintenance_apps;

		self::register_applications($database, $maintenance_apps);
	}

	/**
	 * Registers the list of applications given in the $maintenance_apps array to the global default settings
	 * @param database $database
	 * @param array $maintenance_apps
	 * @return bool
	 * @access public
	 */
	public static function register_applications(database $database, array $maintenance_apps): bool {

		//make sure there is something to do
		if (count($maintenance_apps) === 0) {
			return false;
		}

		//query the database for the already registered applications
		$registered_apps = self::get_registered_applications($database);

		//load the text for the description
		$text = (new text())->get(null, 'app/' . __CLASS__);

		//register each app
		$new_maintenance_apps = [];
		$index = 0;
		foreach ($maintenance_apps as $application => $file) {
			//format the array for what the database object needs for saving data in the global default settings
			self::add_maintenance_app_to_array($registered_apps, $application, $text['description-default_settings_app'], $new_maintenance_apps, $index);

			//get the application settings from the class for database maintenance
			self::add_database_maintenance_to_array($database, $application, $text['description-retention_days'], $new_maintenance_apps, $index);

			//get the application settings from the class for filesystem maintenance
			self::add_filesystem_maintenance_to_array($database, $application, $text['description-retention_days'], $new_maintenance_apps, $index);
		}
		if (count($new_maintenance_apps) > 0) {
			$database->app_name = self::APP_NAME;
			$database->app_uuid = self::APP_UUID;
			$database->save($new_maintenance_apps);
			return true;
		}
		return false;
	}

	/**
	 * Returns a list of maintenance applications already in the default settings table ignoring default_setting_enabled
	 * @param database $database
	 * @return array
	 * @access public
	 */
	public static function get_registered_applications(database $database): array {
		//get the already registered applications from the global default settings table
		$sql = "select default_setting_value"
			. " from v_default_settings"
			. " where default_setting_category = 'maintenance'"
			. " and default_setting_subcategory = 'application'";

		$result = $database->select($sql);
		if (!empty($result)) {
			$registered_applications = array_map(function ($row) { return $row['default_setting_value']; }, $result);
		}
		else {
			$registered_applications = [];
		}
		return $registered_applications;
	}

	/**
	 * updates the array with a maintenance app using a format the database object save method can use to save in the default settings
	 * default settings category: maintenance, subcategory: application, value: name of new application
	 * @param array $registered_applications List of already registered applications
	 * @param string $application Application class name
	 * @param array $array Array in a format ready to use for the database save method
	 * @param int $index Index pointing to the location to save within $array
	 * @access private
	 */
	private static function add_maintenance_app_to_array(&$registered_applications, $application, $description, &$array, &$index) {
		//verify that the application we need to add is not already listed in the registered applications array
		if (!in_array($application, $registered_applications)) {
			$array['default_settings'][$index]['default_setting_uuid'] = uuid();
			$array['default_settings'][$index]['default_setting_category'] = 'maintenance';
			$array['default_settings'][$index]['default_setting_subcategory'] = 'application';
			$array['default_settings'][$index]['default_setting_name'] = 'array';
			$array['default_settings'][$index]['default_setting_value'] = $application;
			$array['default_settings'][$index]['default_setting_enabled'] = 'true';
			$array['default_settings'][$index]['default_setting_description'] = $description;
			$index++;
		}
	}

	/**
	 * Updates the array with a database maintenance app using a format the database object save method can use in default settings table
	 * <p><b>default setting category</b>: class name that has the <code>use database_maintenance;</code> statement<br>
	 * <b>default setting subcategory</b>: "database_retention_days" (The class can override this setting to a custom value)<br>
	 * <b>default setting value</b>: "30" (The class can override this setting to a custom value)<br>
	 * <b>description</b>: "Number of days the maintenance application will keep the information."<br>
	 * </p>
	 * @param database $database Database object
	 * @param string $application Application class name
	 * @param string $description Description to be added in to the default settings table
	 * @param array $array Array formatted for use in the database save method
	 * @param int $index Index pointer to the save location in $array
	 * @access private
	 */
	private static function add_database_maintenance_to_array($database, $application, $description, &$array, &$index) {
		//get the application settings from the object for database maintenance
		if (method_exists($application, 'database_maintenance')) {
			$category = 'maintenance';
			$subcategory = $application . '_database_retention_days';
			//check if the default setting already exists in global default settings table
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				//does not exist so create it
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = '30';
				$array['default_settings'][$index]['default_setting_enabled'] = 'false';
				$array['default_settings'][$index]['default_setting_description'] = $description;
				$index++;
			} else {
				//already exists
			}
		}
	}

	/**
	 * Query the database for an existing UUID of a maintenance application
	 * @param database $database Database object
	 * @param string $category Category to look for in the database
	 * @param string $subcategory Subcategory or name of the setting in the default settings table
	 * @return string Empty string if not found or a UUID
	 */
	public static function default_setting_uuid(database $database, string $category, string $subcategory): string {
		$sql = 'select default_setting_uuid'
			. ' from v_default_settings'
			. ' where default_setting_category = :category'
			. ' and default_setting_subcategory = :subcategory'
		;
		$params = [];
		$params['category'] = $category;
		$params['subcategory'] = $subcategory;
		return $database->select($sql, $params, 'column');
	}

	/**
	 * Updates the array with a file system maintenance app using a format the database object save method can use in default settings table
	 * <p><b>default setting category:</b> class name that has the <code>use filesystem_maintenance;</code> statement<br>
	 * <b>default setting subcategory:</b> "filesystem_retention_days" (The class can override this setting to a custom value)<br>
	 * <b>default setting value:</b> "30" (The class can override this setting to a custom value)<br>
	 * <b>description:</b> "Number of days the maintenance application will keep the information."<br>
	 * </p>
	 * @param database $database Database object
	 * @param string $application Application class name
	 * @param string $description Description to be added in to the default settings table
	 * @param array $array Array formatted for use in the database save method
	 * @param int $index Index pointer to the save location in $array
	 * @access private
	 */
	private static function add_filesystem_maintenance_to_array($database, $application, $description, &$array, &$index) {
		if (method_exists($application, 'filesystem_maintenance')) {
			//the trait has this value defined
			$category = 'maintenance';
			//the trait has this value defined
			$subcategory = $application . '_filesystem_retention_days';
			//check if the default setting already exists in global settings
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = '30';
				$array['default_settings'][$index]['default_setting_enabled'] = 'false';
				$array['default_settings'][$index]['default_setting_description'] = $description;
				$index++;
			}
		}
	}

	public static function find_classes_by_method(string $method_name): array {
		//set defaults
		$found_classes = [];
		$project_root = dirname(__DIR__, 4);

		//get the autoloader
		if (!class_exists('auto_loader')) {
			require_once $project_root . '/resources/classes/auto_loader.php';
			new auto_loader();
		}

		//get all php files
		$files = glob($project_root . '/*/*/resources/classes/*.php');

		//iterate over the files
		foreach ($files as $file) {
			include_once $file;
			$class = basename($file, '.php');

			//check for the existence of the method
			if (method_exists($class, $method_name)) {
				$found_classes[$class] = $file;
			}
		}
		return $found_classes;
	}
}
