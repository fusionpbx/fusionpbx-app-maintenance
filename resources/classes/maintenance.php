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

	public function __construct(array $params = []) {
		if (!empty($params['config'])) {
			$this->config = $params['config'];
		} else {
			//try global variable config before loading new one
			global $config;
			if (isset($config)) {
				$this->config = $config;
			} else {
				$this->config = new config();
			}
		}
		if (!empty($params['database'])) {
			$this->database = $params['database'];
		} else {
			$this->database = new database(['config' => $this->config]);
		}
		if (!empty($params['settings'])) {
			$this->settings = $params['settings'];
		} else {
			$this->settings = new settings(['database' => $this->database]);
		}
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
	 * Update the default settings to reflect all classes implementing the maintenance interfaces in the project
	 * @param database $database
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
		$class_files = glob(dirname(__DIR__, 2) . '/*/*/resources/classes/*.php');
		foreach ($class_files as $file) {
			//register the class name
			require_once $file;
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
	 * Registers the list of applications given in the $maintenance_apps array to the global default settings
	 * @param database $database
	 * @param array $maintenance_apps
	 * @return bool
	 */
	public static function register_applications(database $database, array $maintenance_apps): bool {
		//make sure there is something to do
		if (count($maintenance_apps) === 0) {
			return false;
		}

		//query the database for the already registered applications
		$registered_apps = self::get_registered_applications($database);

		//register each app
		$new_maintenance_apps = [];
		$index = 0;
		foreach ($maintenance_apps as $application) {
			//format the array for what the database object needs for saving data in the global default settings
			self::add_maintenance_app_to_array($registered_apps, $application, $new_maintenance_apps, $index);

			//get the application settings from the class for database maintenance
			self::add_database_maintenance_to_array($database, $application, $new_maintenance_apps, $index);

			//get the application settings from the class for filesystem maintenance
			self::add_filesystem_maintenance_to_array($database, $application, $new_maintenance_apps, $index);
		}
		if (count($new_maintenance_apps) > 0) {
			$database->app_name = self::APP_NAME;
			$database->app_uuid = self::APP_UUID;
			$database->save($new_maintenance_apps);
			return true;
		}
		return false;
	}

	//updates the array with a maintenance app using a format the database object save method can use
	private static function add_maintenance_app_to_array(&$registered_applications, $application, &$array, &$index) {
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

	//updates the array with a database maintenance app using a format the database object save method can use
	private static function add_database_maintenance_to_array($database, $application, &$array, &$index) {
		//get the application settings from the object for database maintenance
		if (has_trait($application, 'database_maintenance')) {
			$category = $application::$database_retention_category;
			$subcategory = $application::$database_retention_subcategory;
			//check if the default setting already exists in global settings
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				//does not exist so create it
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = $application::database_retention_default_value();
				$array['default_settings'][$index]['default_setting_enabled'] = 'true';
				$array['default_settings'][$index]['default_setting_description'] = '';
				$index++;
			} else {
				//already exists
			}
		}
	}

	//updates the array with a filesystem maintenance app using a format the database object save method can use
	private static function add_filesystem_maintenance_to_array($database, $application, &$array, &$index) {
		if (has_trait($application, 'filesystem_maintenance')) {
			$category = $application::$filesystem_retention_category;
			$subcategory = $application::$filesystem_retention_subcategory;
			//check if the default setting already exists in global settings
			$uuid = self::default_setting_uuid($database, $category, $subcategory);
			if (empty($uuid)) {
				$array['default_settings'][$index]['default_setting_category'] = $category;
				$array['default_settings'][$index]['default_setting_subcategory'] = $subcategory;
				$array['default_settings'][$index]['default_setting_uuid'] = uuid();
				$array['default_settings'][$index]['default_setting_name'] = 'numeric';
				$array['default_settings'][$index]['default_setting_value'] = $application::filesystem_retention_default_value();
				$array['default_settings'][$index]['default_setting_enabled'] = 'true';
				$array['default_settings'][$index]['default_setting_description'] = '';
				$index++;
			}
		}
	}
}
