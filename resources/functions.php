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

	if (!function_exists('has_trait')) {
		function has_trait($object_or_class, $trait): bool {
			if (trait_exists($trait) && in_array($trait, class_uses($object_or_class))) {
				//class has the trait
				return true;
			}
			//class does not have the trait
			return false;
		}
	}

	if (!function_exists('has_interface')) {
		/**
		 * Returns true when the class has implemented the interface
		 * @param string|object $object_or_class
		 * @param string|object $interface
		 * @return bool
		 */
		function has_interface ($object_or_class, $interface): bool {
			//convert object to string
			if (gettype($object_or_class) === 'object') {
				$class_name = get_classname($object_or_class);
			} else {
				$class_name = $object_or_class;
			}
			//check in the list of interfaces the class name implements for a match
			if (class_exists($class_name) && in_array($interface, class_implements($class_name))) {
				//class implements the interface
				return true;
			}
			//class does not implement the interface
			return false;
		}
	}

	if (!function_exists('get_classname')) {
		/**
		 *
		 * @param type $string_or_object
		 * @return string|object
		 */
		function get_classname($string_or_object): string {
			if (gettype($string_or_object) === 'object') {
//				if (version_compare(PHP_VERSION, "8.0.0", "<")) {
					$backtrace = debug_backtrace();
					$classname = !empty($backtrace[1]['class']) ? $backtrace[1]['class'] : 'object';
//				} else {
//					// PHP 8.0 and higher can extract the class from a dynamic name
//					$classname = $string_or_object::class;
//				}
			} else {
				$classname = $string_or_object;
			}
			return $classname;
		}
	}

	if (!function_exists('implementing_classes')) {
		/**
		 * Returns an array of classes that implement the interface name passed to the function
		 * @param string $interface_name Name of interface to search for in classes
		 * @return array Array of class names that have the implemented <i>interface_name</i>
		 */
		function implementing_classes(string $interface_name): array {
			//initialize the array
			$found_classes = [];

			//load all objects available in the project
			$class_files = glob(dirname(__DIR__) . '/*/*/resources/classes/*.php');
			foreach ($class_files as $file) {
				//register the class name
				include_once $file;
			}

			//load all declared classes in an array
			$declared_classes = get_declared_classes();

			// Iterate over each class
			foreach ($declared_classes as $class) {
				// Check if the class implements the interfaces
				if (in_array($interface_name, class_implements($class))) {
					// Add the class to the array
					$found_classes[] = $class;
				}
			}
			return $found_classes;
		}
	}
	if (!function_exists('implementing_classes_arr')) {
		/**
		 * Returns an array of classes that implement the interface name passed to the function
		 * @param array $interface_names Names of interfaces to search for in classes
		 * @return array Array of class names that have the implemented <i>interface_name</i>
		 */
		function implementing_classes_arr(...$interface_names): array {
			//initialize the array
			$found_classes = [];

			//load all objects available in the project
			$class_files = glob(dirname(__DIR__) . '/*/*/resources/classes/*.php');
			foreach ($class_files as $file) {
				//register the class name
				include_once $file;
			}

			//load all declared classes in an array
			$declared_classes = get_declared_classes();

			// Iterate over each class
			foreach ($interface_names as $interface_name) {
				foreach ($declared_classes as $class) {
					// Check if the class implements the interfaces
					if (in_array($interface_name, class_implements($class))) {
						// Add the class to the array
						$found_classes[] = $class;
					}
				}
			}
			return $found_classes;
		}
	}

	if (!function_exists('user_defined_classes')) {
		function user_defined_classes () {
			return array_filter(
			   get_declared_classes(),
			   function($className) {
				   return !call_user_func(
					   array(new ReflectionClass($className), 'isInternal')
				   );
			   }
			);
		}
	}

	if (!function_exists('trait_classes')) {
		function trait_classes(string $trait) {
			// get user defined classes
			$user_classes = user_defined_classes();

			// select only classes that use trait $trait
			$trait_classes = array_filter(
			   $user_classes,
			   function($className) use($trait) {
				 $traits = class_uses($className);
				 return isset($traits[$trait]);
			   }
			);
			return $trait_classes;
		}
	}

	if (!function_exists('trait_classes_arr')) {
		function trait_classes_arr(...$traits) {
			// get user defined classes
			$user_classes = user_defined_classes();

			// select only classes that use trait $trait
			$trait_classes = array_filter(
			   $user_classes,
			   function($classname) use($traits) {
				 $trait_class = class_uses($classname);
				 return count(array_intersect($trait_class, $traits)) > 0;
			   }
			);
			return $trait_classes;
		}
	}

?>
