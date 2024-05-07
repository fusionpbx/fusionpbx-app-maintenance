<?php

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
				if (version_compare(PHP_VERSION, "8.0.0", "<")) {
					$backtrace = debug_backtrace();
					$classname = !empty($backtrace[1]['class']) ? $backtrace[1]['class'] : 'object';
				} else {
					// PHP 8.0 and higher can extract the class from a dynamic name
					$classname = $string_or_object::class;
				}
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
