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

	const DATABASE_SUBCATEGORY = 'database_retention_days';
	const FILESYSTEM_SUBCATEGORY = 'filesystem_retention_days';

	private static $app_config_list = null;

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

	/**
	 * Registers new applications by searching for files in the project that have the signature for a method called
	 * <code>public static function database_maintenance</code> or the method <code>public static function
	 * filesystem_maintenance</code>. When found, they are added to the <code>default_settings</code> category of
	 * <b>maintenance</b> and put in to subcategory array <b>application</b> in default settings table.
	 * This function is intended to be called by the upgrade method.
	 * @param database $database
	 */
	public static function app_defaults(database $database) {
		//get the maintenance apps
		$database_maintenance_apps = self::find_classes_by_method('database_maintenance');
		$filesystem_maintenance_apps = self::find_classes_by_method('filesystem_maintenance');
		$maintenance_apps = $database_maintenance_apps + $filesystem_maintenance_apps;
		if (!empty($maintenance_apps)) {
			self::register_applications($database, $maintenance_apps);
		}
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
			//self::add_database_maintenance_to_array($database, $application, $text['description-retention_days'], $new_maintenance_apps, $index);

			//get the application settings from the class for filesystem maintenance
			//self::add_filesystem_maintenance_to_array($database, $application, $text['description-retention_days'], $new_maintenance_apps, $index);
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
	 * Returns the class specified category to be used for the maintenance service. If the class does not have a method that
	 * returns a string with the category to use then the class name will be used as the category.
	 * @param object|string $class_name
	 * @return string
	 */
	public static function get_database_category($class_name): string {
		if (method_exists($class_name, 'database_maintenance_category')) {
			$default_value = $class_name::database_maintenance_category();
		} else {
			$default_value = $class_name;
		}
		return $default_value;
	}

	/**
	 * Returns the class specified subcategory to be used for the maintenance service. If the class does not have a method that
	 * returns a string with the subcategory to use then the class name will be used as the subcategory.
	 * @param object|string $class_name
	 * @return string
	 */
	public static function get_database_subcategory($class_name): string {
		if (method_exists($class_name, 'database_maintenance_subcategory')) {
			$default_value = $class_name::database_maintenance_subcategory();
		} else {
			$default_value = self::DATABASE_SUBCATEGORY;
		}
		return $default_value;
	}

	public static function get_database_retention_days(settings $settings, $class_name, $default_value = ''): string {
		return $settings->get(self::get_database_category($class_name), self::get_database_subcategory($class_name), $default_value);
	}

	/**
	 * Returns the class specified category to be used for the maintenance service. If the class does not have a method that
	 * returns a string with the category to use then the class name will be used as the category.
	 * @param object|string $class_name
	 * @return string
	 */
	public static function get_filesystem_category($class_name): string {
		if (method_exists($class_name, 'filesystem_maintenance_category')) {
			$default_value = $class_name::filesystem_maintenance_category();
		} else {
			$default_value = $class_name;
		}
		return $default_value;
	}

	/**
	 * Returns the class specified subcategory to be used for the maintenance service. If the class does not have a method that
	 * returns a string with the subcategory to use then the class name will be used as the subcategory.
	 * @param object|string $class_name
	 * @return string
	 */
	public static function get_filesystem_subcategory($class_name): string {
		if (method_exists($class_name, 'filesystem_maintenance_subcategory')) {
			$default_value = $class_name::filesystem_maintenance_subcategory();
		} else {
			$default_value = self::FILESYSTEM_SUBCATEGORY;
		}
		return $default_value;
	}

	public static function get_filesystem_retention_days(settings $settings, $class_name, $default_value = ''): string {
		return $settings->get(self::get_filesystem_category($class_name), self::get_filesystem_subcategory($class_name), $default_value);
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
	 * Get a value from the app_config.php file default settings
	 * This function will load all app_config.php files and then load them in a class array only once. The first call will
	 * have a performance impact but subsequent calls will have minimal impact as no files will be loaded.
	 * @param string $category
	 * @param string $subcategory
	 * @return array|string|null If no value is found then null will be returned
	 */
	public static function get_app_config_value(string $category, string $subcategory) {
		$return_value = null;
		//check if this is the first time loading the files
		if (self::$app_config_list === null) {
			//load the app_config files once
			self::load_app_config_list();
		}
		if (!empty(self::$app_config_list[$category][$subcategory])) {
			$return_value = self::$app_config_list[$category][$subcategory];
		}
		return $return_value;
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
	 * <p><b>default setting category</b>: class name that has the <code>implements database_maintenance;</code> statement<br>
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
			$category = $application;
			$subcategory =  self::DATABASE_SUBCATEGORY;
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
			$category = $application;
			//the trait has this value defined
			$subcategory = 'filesystem_retention_days';
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

	private static function load_app_config_list() {

		//app_config files use the array $apps to define the default_settings
		global $apps;

		//initialize the config list
		self::$app_config_list = [];

		//get the list of app_config files
		$project_dir = dirname(__DIR__, 4);
		$app_config_files = glob($project_dir . '/app/*/app_config.php');
		$core_config_files = glob($project_dir . '/core/*/app_config.php');
		$config_files = array_merge($app_config_files, $core_config_files);

		//iterate over list
		foreach ($config_files as $x => $file) {
			//include the app_config file
			include_once $file;
			//create a classname
			//get the array from the included file
			if (!empty($apps[$x]['default_settings'])) {
				foreach ($apps[$x]['default_settings'] as $setting) {
					//get the subcategory
					$category = $setting['default_setting_category'];
					$subcategory = $setting['default_setting_subcategory'];
					$value = $setting['default_setting_value'];
					$type = $setting['default_setting_name'];
					//check for array type
					if ($type !== 'array') {
						//store the values
						self::$app_config_list[$category][$subcategory] = $value;
					} else {
						$order = intval($setting['default_setting_order']);
						self::$app_config_list[$category][$subcategory][$order] = $value;
					}
				}
			}
		}
	}

	/**
	 * Finds the UUID of a maintenance setting searching in the default settings, domain settings, and user settings tables.
	 * This is primarily used for the dashboard display as there was a need to detect a setting even if it was disabled.
	 * <p>NOTE:<br>
	 * This will not deal with an array of returned values (such as maintenance_application) appropriately at this time as it has a limited scope of detecting a
	 * string value that is unique with the category and subcategory combination across the tables.</p>
	 * @param database $database Already connected database object
	 * @param string $category Main category
	 * @param string $subcategory Subcategory or name of the setting
	 * @param bool $status Used for internal use but could be used to find a setting that is currently disabled
	 * @return array Two-dimensional array assigned but using key/value pairs. The keys are:<br>
	 * <ul>
	 *   <li>uuid: Primary UUID that would be chosen by the settings object
	 *   <li>uuids: Array of all matching category and subcategory strings
	 *   <li>table: Table name that the primary UUID was found
	 *   <li>status: bool true/false
	 * </ul>
	 * @access public
	 */
	public static function find_uuid(database $database, string $category, string $subcategory, bool $status = true): array {
		//first look for false setting then override with enabled setting
		if ($status) {
			$uuids = self::find_uuid($database, $category, $subcategory, false);
		} else {
			//set defaults to not found
			$uuids = [];
			$uuids['uuid'] = '';
			$uuids['uuids'] = [];
			$uuids['table'] = '';
			$uuids['status'] = false;
		}
		$status_string = ($status) ? 'true' : 'false';
		//
		// Get the settings for false first then override the 'false' setting with the setting that is set to 'true'
		//
		//get global setting
		$result = self::get_uuids($database, 'default', $category, $subcategory, $status_string);
		if (!empty($result)) {
			$uuids['uuid'] = $result[0];
			$uuids['count'] = count($result);
			if (count($result) > 1) {
				$uuids['uuids'] = $result;
			} else {
				$uuids['uuids'] = [];
			}
			$uuids['table'] = 'default';
			$uuids['status'] = $status;
		}
		//override default with domain setting
		$result = self::get_uuids($database, 'domain', $category, $subcategory, $status_string);
		if (!empty($result)) {
			if ($uuids['count'] > 0) {
				$uuids['count'] += count($result);
				if (count($uuids['uuids']) > 0) {
					array_merge($uuids['uuids'], $result);
				} else {
					$ids[] = $uuids['uuid'];
					$uuids['uuids'] = array_merge($result, $ids);
				}
			} else {
				$uuids['count'] = count($result);
				if (count($result) > 1) {
					$uuids['uuids'] = $result;
				} else {
					$uuids['uuids'] = [];
				}
			}
			$uuids['uuid'] = $result[0];
			$uuids['table'] = 'domain';
			$uuids['status'] = $status;
		}
		//override domain with user setting
		$result = self::get_uuids($database, 'user', $category, $subcategory, $status_string);
		if (!empty($result)) {
			$uuids['uuid'] = $result[0];
			$uuids['count'] = count($result);
			if (count($result) > 1) {
				$uuids['uuids'] = $result;
			} else {
				$uuids['uuids'] = [];
			}
			$uuids['table'] = 'user';
			$uuids['status'] = $status;
		}
		return $uuids;
	}

	/**
	 * Finds all UUIDs of a maintenance setting searching in the default settings, domain settings, and user settings tables.
	 * @param database $database Already connected database object
	 * @param string $category Main category
	 * @param string $subcategory Subcategory or name of the setting
	 * @param bool $status Used for internal use but could be used to find a setting that is currently disabled
	 * @return array Two-dimensional array of matching database records<br>
	 * @access public
	 */
	public static function find_all_uuids(database $database, string $category, string $subcategory, bool $status = true): array {
		$matches = [];
		//first look for false settings
		if ($status) {
			$matches = self::find_all_uuids($database, $category, $subcategory, false);
		}

		$status_string = ($status) ? 'true' : 'false';

		$tables = ['default', 'domain', 'user'];
		foreach ($tables as $table) {
			$sql = "select {$table}_setting_uuid, {$table}_setting_value from v_{$table}_settings s";
			$sql .= " where s.{$table}_setting_category = :category";
			$sql .= " and s.{$table}_setting_subcategory = :subcategory";
			$sql .= " and s.{$table}_setting_enabled = '$status_string'";

			//set search params
			$params = [];
			$params['category'] = $category;
			$params['subcategory'] = $subcategory;
			$result = $database->select($sql, $params, 'all');
			if (!empty($result)) {
				foreach ($result as $record) {
					$uuid = $record["{$table}_setting_uuid"];
					$value = $record["{$table}_setting_value"];
					$domain_uuid = $database->select("select domain_uuid from v_{$table}_settings where {$table}_setting_uuid = '$uuid'", null, 'column');
					if ($domain_uuid == false) {
						$domain_uuid = null;
					}
					$matches[] = [
						'uuid' => $uuid,
						'table' => $table,
						'category' => $category,
						'subcategory' => $subcategory,
						'status' => $status,
						'value' => $value,
						'domain_uuid' => $domain_uuid,
					];
				}
			}
		}
		return $matches;
	}

	/**
	 * Called by the find_uuid function to actually search database using prepared data structures
	 * @param database $database Database object
	 * @param string $table Either 'default' or 'domain'
	 * @param string $category Category value to match
	 * @param string $subcategory Subcategory value to match
	 * @param string $status Either 'true' or 'false'
	 * @return array
	 */
	public static function get_uuids(database $database, string $table, string $category, string $subcategory, string $status): array {
		$uuid = [];
		$sql = "select {$table}_setting_uuid from v_{$table}_settings s";
		$sql .= " where s.{$table}_setting_category = :category";
		$sql .= " and s.{$table}_setting_subcategory = :subcategory";
		$sql .= " and s.{$table}_setting_enabled = '$status'";

		//set search params
		$params = [];
		$params['category'] = $category;
		$params['subcategory'] = $subcategory;
		if ($table === 'domain' && !empty($_SESSION['domain_uuid']) && is_uuid($_SESSION['domain_uuid'])) {
			$sql .= " and s.domain_uuid = :domain_uuid";
			$params['domain_uuid'] = $_SESSION['domain_uuid'];
		}
		if ($table === 'user' && !empty($_SESSION['user_uuid']) && is_uuid($_SESSION['user_uuid'])) {
			$sql .= " and s.user_uuid = :user_uuid";
			$params['user_uuid'] = $_SESSION['user_uuid'];
		}
		$result = $database->select($sql, $params);
		if (!empty($result)) {
			if (is_array($result)) {
				$uuids = array_map(function ($value) use ($table) {
					if (is_array($value)) {
						return $value["{$table}_setting_uuid"];
					} else {
						return $value;
					}
				}, $result);
				$uuid = $uuids;
			} else {
				$uuid[] = $result;
			}
		}
		return $uuid;
	}

	/**
	 * Returns the record set of the UUID in the table or an empty array
	 * @param database $database
	 * @param string $table Either 'domain' or 'default'
	 * @param string $uuid
	 * @return array
	 */
	public static function get_value_by_uuid(database $database, string $table, string $uuid): array {
		if ($table === 'domain' || $table === 'default') {
			$sql = "select * from v_{$table}_settings"
			. " where {$table}_setting_uuid = :uuid";
			$parameters = [];
			$parameters['uuid'] = $uuid;
			$result = $database->select($sql, $parameters, 'row');
			if (!empty($result)) {
				return $result;
			}
		}
		return [];
	}

	public static function has_database_maintenance($object_or_class): bool {
		return method_exists($object_or_class, 'database_maintenance');
	}

	public static function has_filesystem_maintenance($object_or_class): bool {
		return method_exists($object_or_class, 'filesystem_maintenance');
	}

}

?>
