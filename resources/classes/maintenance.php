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
	 * Config object
	 * @var config $config object
	 */
	private $config;

	/**
	 * Database object
	 * @var database $database object
	 */
	private $database;

	/**
	 * Settings object
	 * @var settings $settings object
	 */
	private $settings;

	/**
	 * Constructs a maintenance object
	 * <p>Each parameter is checked to ensure it is a valid object matching the expected type of object needed.</p>
	 * <p>Valid values:<br>
	 * <ul>
	 *   <li>config - must be a valid config object or one will be created
	 *   <li>database - must be a valid database object or one will be created
	 *   <li>settings - must be a valid settings object or one will be created
	 * </ul>
	 * </p>
	 * @param array $params Key/value pairs of object class name and the object
	 */
	public function __construct(array $params = []) {
		//try to use config object passed in the constructor
		if (!empty($params['config']) && $params['config'] instanceof config) {
			$this->config = $params['config'];
		} else {
			//fallback to creating our own object
			$this->config = new config();
		}
		//try to use database object passed in the constructor
		if (!empty($params['database']) && $params['database'] instanceof database) {
			$this->database = $params['database'];
		} else {
			//fallback to creating our own object
			$this->database = new database(['config' => $this->config]);
		}
		//try to use settings object passed in the constructor
		if (!empty($params['settings']) && $params['settings'] instanceof settings) {
			$this->settings = $params['settings'];
		} else {
			$this->settings = new settings(['database' => $this->database]);
		}
		//set the database object to remember this app for any transactions
		$this->database->app_name = self::APP_NAME;
		$this->database->app_uuid = self::APP_UUID;
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
	 * Update the default settings to reflect all classes implementing the maintenance traits in the project
	 * <p>This is called when the <i>App Defaults</i> is executed either from the command line or the web
	 * user interface.</p>
	 * @param database $database
	 * @access public
	 */
	public static function app_defaults(database $database) {
		//require the maintenance functions for processing traits
		if (!function_exists('has_trait')) {
			if (file_exists(dirname(__DIR__) . '/functions.php')) {
				require_once dirname(__DIR__) . '/functions.php';
			} else {
				return;
			}
		}

		//load all classes in the project
		$project_root = dirname(__DIR__, 4);
		$class_files = glob($project_root . '/*/*/resources/classes/*.php');
		foreach ($class_files as $file) {
			try {
				//register the class name
				include_once $file;
			} catch (Throwable $t) {
				//do nothing
			}
		}

		//get the loaded declared classes in an array from the php engine
		$declared_classes = get_declared_classes();

		//initialize the array
		$found_applications = [];

		//iterate over each class and check for it implementing the maintenance trait
		foreach ($declared_classes as $class) {
			// Check if the class implements the interfaces
			if (has_trait($class, 'database_maintenance') || has_trait($class, 'filesystem_maintenance')) {
				// Add the class to the array so it can be added and default settings can be applied
				$found_applications[] = $class;
			}
		}

		//check if we have to add classes not already in default settings
		if (count($found_applications) > 0) {
			self::register_applications($database, $found_applications);
		}

		//check the type of chart and make sure that it has 'none' as default
		$result = $database->select("select dashboard_chart_type from v_dashboard where dashboard_name='Maintenance'", null, 'column');
		if ($result !== 'none' || $result !== 'doughnut') {
			$database->execute("update v_dashboard set dashboard_chart_type='none' where dashboard_name='Maintenance'");
		}

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
	 * Returns a list of default settings and domain settings tables allowing to ignore the default_setting_enabled
	 * @param database $database Database object
	 * @param string $subcategory_name
	 * @return array List of retention days with the default setting UUID as the key and the retention days as the value
	 */
	public static function get_default_retention_days(database $database, string $subcategory_name, bool $omit_default_setting_enabled = false, bool $default_setting_enabled = true): array {
		$default_setting_enabled_string = $default_setting_enabled ? 'true' : 'false';
		$retention_settings = [];
		$sql = "select default_setting_uuid, default_setting_category, default_setting_value from v_default_settings";
		$sql .= " where default_setting_subcategory='$subcategory_name'";
		if (!$omit_default_setting_enabled) {
			$sql .= " and default_setting_enabled='$default_setting_enabled_string'";
		}
		$sql .= " group by default_setting_category, default_setting_uuid, default_setting_value";
		$result = $database->select($sql, null, 'all');
		if (!empty($result)) {
			foreach ($result as $row) {
				$app_name = $row['default_setting_category'];
				$uuid = $row['default_setting_uuid'];
				$value = $row['default_setting_value'];
				$retention_settings[$app_name][$uuid] = $value;
			}
		}

		return $retention_settings;
	}

	/**
	 * Returns a list of default settings and domain settings tables allowing to ignore the default_setting_enabled
	 * @param database $database Database object
	 * @param string $category_name
	 * @return array List of retention days with the default setting UUID as the key and the retention days as the value
	 */
	public static function get_domain_retention_days(database $database, string $category_name, bool $omit_default_setting_enabled = false, bool $default_setting_enabled = true): array {
		//set defaults
		$retention_settings = [];
		$default_setting_enabled_string = $default_setting_enabled ? 'true' : 'false';
		//build sql statement
		$sql = "select default_setting_uuid, default_setting_category, default_setting_value from v_domain_settings";
		$sql .= " where default_setting_subcategory='$category_name'";
		if (!$omit_default_setting_enabled) {
			$sql .= " and default_setting_enabled='$default_setting_enabled_string'";
		}
		//ensure the returned list is already sorted
		$sql .= " group by default_setting_category, default_setting_uuid, default_setting_value";
		//execute the query
		$result = $database->select($sql, null, 'all');
		//check result
		if (!empty($result)) {
			//map the results
			foreach ($result as $row) {
				$app_name = $row['default_setting_category'];
				$uuid = $row['default_setting_uuid'];
				$value = $row['default_setting_value'];
				//two-dimensional array of name & uuid set to value
				$retention_settings[$app_name][$uuid] = $value;
			}
		}
		//return settings
		return $retention_settings;
	}

	/**
	 * Returns an array of domain names with their domain UUID as the array key
	 * @param database $database
	 * @param bool $ignore_domain_enabled
	 * @return array
	 */
	public static function get_domain_names(database $database, bool $ignore_domain_enabled = false): array {
		$domains = [];
		$sql = "select domain_uuid, domain_name from v_domains";
		if (!$ignore_domain_enabled) {
			$sql .= " where domain_enabled='true'";
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
		$text = (new text())->get();

		//register each app
		$new_maintenance_apps = [];
		$index = 0;
		foreach ($maintenance_apps as $application) {
			//format the array for what the database object needs for saving data in the global default settings
			self::add_maintenance_app_to_array($registered_apps, $application, $new_maintenance_apps, $index);

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
	 * updates the array with a maintenance app using a format the database object save method can use to save in the default settings
	 * default settings category: maintenance, subcategory: application, value: name of new application
	 * @param array $registered_applications List of already registered applications
	 * @param string $application Application class name
	 * @param array $array Array in a format ready to use for the database save method
	 * @param int $index Index pointing to the location to save within $array
	 * @access private
	 */
	private static function add_maintenance_app_to_array(&$registered_applications, $application, &$array, &$index) {
		//verify that the application we need to add is not already listed in the registered applications array
		if (!in_array($application, $registered_applications)) {
			$array['default_settings'][$index]['default_setting_uuid'] = uuid();
			$array['default_settings'][$index]['default_setting_category'] = 'maintenance';
			$array['default_settings'][$index]['default_setting_subcategory'] = 'application';
			$array['default_settings'][$index]['default_setting_name'] = 'array';
			$array['default_settings'][$index]['default_setting_value'] = $application;
			$array['default_settings'][$index]['default_setting_enabled'] = 'true';
			$array['default_settings'][$index]['default_setting_description'] = '';
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
		if (has_trait($application, 'database_maintenance')) {
			//the trait has this value defined
			$category = $application::database_retention_category();
			//the trait has this value defined
			$subcategory = $application::database_retention_subcategory();
			//check if the default setting already exists in global default settings table
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				//does not exist so create it
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = $application::database_retention_default_value();
				$array['default_settings'][$index]['default_setting_enabled'] = 'true';
				$array['default_settings'][$index]['default_setting_description'] = $description;
				$index++;
			} else {
				//already exists
			}
		}
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
		if (has_trait($application, 'filesystem_maintenance')) {
			//the trait has this value defined
			$category = $application::filesystem_retention_category();
			//the trait has this value defined
			$subcategory = $application::filesystem_retention_subcategory();
			//check if the default setting already exists in global settings
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = $application::filesystem_retention_default_value();
				$array['default_settings'][$index]['default_setting_enabled'] = 'true';
				$array['default_settings'][$index]['default_setting_description'] = $description;
				$index++;
			}
		}
	}
}
