<?php

/*
	  FusionPBX
	  Version: MPL 1.1

	  The contents of this file are subject to the Mozilla Public License Version
	  1.1 (the "License"); you may not use this file except in compliance with
	  the License. You may obtain a copy of the License at
	  http://www.mozilla.org/MPL/

	  Software distributed under the License is distributed on an "AS IS" basis,
	  WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	  for the specific language governing rights and limitations under the
	  License.

	  The Original Code is FusionPBX

	  The Initial Developer of the Original Code is
	  Mark J Crane <markjcrane@fusionpbx.com>
	  Portions created by the Initial Developer are Copyright (C) 2008-2018
	  the Initial Developer. All Rights Reserved.

	  Contributor(s):
	  Mark J Crane <markjcrane@fusionpbx.com>
	  Tim Fry <tim@fusionpbx.com>
	 */

if (!function_exists('default_setting_uuid')) {
	function default_setting_uuid(string $category, string $subcategory): string {
		//database object
		global $config, $database;
		if ($config === null) {
			$config = new config();
		}
		if ($database === null) {
			$database = new database(['config' => $config]);
		}
		$sql = 'select default_setting_uuid'
			. ' from v_default_settings'
			. ' where default_setting_category = :category'
			. ' and default_setting_subcategory = :subcategory'
		;
		$params = [];
		$params['category'] = $category;
		$params['subcategory'] = $subcategory;
		return $database->select($sql, $params, 'column');
//		if(!empty($result) && is_uuid($result)) {
//			$uuid = $result;
//		} else {
//			$uuid = uuid();
//		}
//		return $uuid;
	}
}

if (!function_exists('register_maintenance_applications')) {
	//update the default settings to reflect all classes implementing the maintenance interfaces in the project
	function register_maintenance_applications() {

		//database object
		global $config, $database;
		if ($config === null) {
			$config = new config();
		}
		if ($database === null) {
			$database = new database(['config' => $config]);
		}

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

		//load all classes in the project
		$class_files = glob(dirname(__DIR__, 2) . '/*/*/resources/classes/*.php');
		foreach ($class_files as $file) {
			//register the class name
			require_once $file;
		}

		//get the loaded declared classes in an array
		$declared_classes = get_declared_classes();

		//initialize the array
		$found_applications = [];

		//iterate over each class and check for it implementing the maintenance trait
		foreach ($declared_classes as $class) {
			// Check if the class implements the interfaces and is not already in the default settings
			if (has_trait($class, 'database_maintenance') || has_trait($class, 'filesystem_maintenance')) {
//				&& !in_array($class, $registered_applications)) {
//
				// Add the class to the array so it can be added and default settings can be applied
				$found_applications[] = $class;
			}
		}

		//check if we have to add classes not already in the list but disable them by default
		if (count($found_applications) > 0) {
			$array = [];
			$index = 0;
			foreach ($found_applications as $application) {
				//check if the application is registered
				if (!in_array($application, $registered_applications)) {
					//format the array for what the database object needs for saving data in the global default settings
					$array['default_settings'][$index]['default_setting_uuid'] = uuid();
					$array['default_settings'][$index]['default_setting_category'] = 'maintenance';
					$array['default_settings'][$index]['default_setting_subcategory'] = 'application';
					$array['default_settings'][$index]['default_setting_name'] = 'array';
					$array['default_settings'][$index]['default_setting_value'] = $application;
					$array['default_settings'][$index]['default_setting_enabled'] = 'true';
					$array['default_settings'][$index]['default_setting_description'] = '';
					$index++;
				}
				//get the application settings from the object for database maintenance
				if (has_trait($application, 'database_maintenance')) {
					$category = $application::$database_retention_category;
					$subcategory = $application::$database_retention_subcategory;
					//check if the default setting already exists in global settings
					$uuid = default_setting_uuid($category, $subcategory);
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
				//get the application settings from the object for filesystem maintenance
				if (has_trait($application, 'filesystem_maintenance')) {
					$category = $application::$filesystem_retention_category;
					$subcategory = $application::$filesystem_retention_subcategory;
					//check if the default setting already exists in global settings
					$uuid = default_setting_uuid($category, $subcategory);
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
			if (count($array) > 0) {
				$database->save($array);
			}
		}
	}
}

if ($domains_processed == 1) {
	//require the function for process
	if (!function_exists('has_trait')) {
		if (file_exists(dirname(__DIR__, 2) . '/app/maintenance/resources/functions.php')) {
			require_once dirname(__DIR__, 2) . '/app/maintenance/resources/functions.php';
		} else {
			return;
		}
	}
	//run in a function to avoid variable name collisions
	register_maintenance_applications();
}
