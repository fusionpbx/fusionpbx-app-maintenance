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
  Tim Fry <tim.fry@hotmail.com>
 */

/**
 * base service class
 * @version 1.00
 */
abstract class service {

	const VERSION = "1.00";

	/**
	 * Track the internal loop. It is recommended to use this variable to control the loop inside the run function. See the example
	 * below the class for a more complete explanation
	 * @var bool
	 */
	protected $running;

	/**
	 * current debugging level for output to syslog
	 * @var int Syslog level
	 */
	protected static $log_level = LOG_INFO;

	/**
	 * config object
	 * @var config config object
	 */
	protected static $config;

	/**
	 * Holds the parsed options from the command line
	 * @var array
	 */
	protected static $parsed_cli_options;

	/**
	 * Operating System process identification file
	 * @var string
	 */
	private static $pid_file = "";

	/**
	 * Cli Options Array
	 * @var array
	 */
	private static $available_cli_options = [];

	/**
	 * Holds the configuration file location
	 * @var string
	 */
	protected static $config_file = "";

	/**
	 * Child classes must provide a mechanism to reload settings
	 */
	abstract protected function reload_settings(): void;

	/**
	 * Method to start the child class internal loop
	 */
	abstract public function run(): int;

	/**
	 * Display version notice
	 */
	abstract protected static function display_version(): void;

	/**
	 * Called when the display_help_message is run in the base class for extra command line parameter explanation
	 */
	abstract protected static function set_cli_options();

	/**
	 * Constructor sets the log options of the class
	 */
	protected function __construct() {
		openlog('[php][' . self::class . ']', LOG_CONS | LOG_NDELAY | LOG_PID, LOG_DAEMON);
	}

	public function __destruct() {
		//ensure we unlink the correct PID file if needed
		if (self::is_running()) {
			unlink(self::$pid_file);
			self::log("Initiating Shutdown...", LOG_NOTICE);
			$this->running = false;
		}
		//this should remain the last statement to execute before exit
		closelog();
	}

	/**
	 * Shutdown process gracefully
	 */
	public function shutdown() {
		exit();
	}

	public static function send_shutdown() {
		if (self::is_any_running()) {
			self::send_signal(SIGTERM);
		} else {
			die("Service Not Started\n");
		}
	}

	// register signal handlers
	private function register_signal_handlers() {
		// Allow the calls to be made while the main loop is running
		pcntl_async_signals(true);

		// A signal listener to reload the service for any config changes in the database
		pcntl_signal(SIGUSR1, [$this, 'reload_settings']);
		pcntl_signal(SIGHUP, [$this, 'reload_settings']);

		// A signal listener to stop the service
		pcntl_signal(SIGUSR2, [$this, 'shutdown']);
		pcntl_signal(SIGTERM, [$this, 'shutdown']);
	}

	/**
	 * Extracts the short options from the cli options array and returns a string. The resulting string must
	 * return a single string with all options in the string such as 'rxc:'.
	 * This can be overridden by the child class.
	 * @return string
	 */
	protected static function get_short_options(): string {
		return implode('' , array_map(function ($option) { return $option['short_option']; }, self::$available_cli_options));
	}

	/**
	 * Extracts the long options from the cli options array and returns an array. The resulting array must
	 * return a single dimension array with an integer indexed key but does not have to be sequential order.
	 * This can be overridden by the child class.
	 * @return array
	 */
	protected static function get_long_options(): array {
		return array_map(function ($option) { return $option['long_option']; }, self::$available_cli_options);
	}

	/**
	 * Method that will retrieve the callbacks from the cli options array
	 * @param string $set_option
	 * @return array
	 */
	protected static function get_user_callbacks_from_available_options(string $set_option): array {
		//match the available option to the set option and return the callback function that needs to be called
		foreach(self::$available_cli_options as $option) {
			$short_option = $option['short_option'] ?? '';
			if (str_ends_with($short_option, ':')) {
				$short_option = rtrim($short_option, ':');
			}
			$long_option = $option['long_option'] ?? '';
			if (str_ends_with($long_option, ':')) {
				$long_option = rtrim($long_option, ':');
			}
			if ($short_option === $set_option ||
				$long_option  === $set_option) {
					return $option['functions'] ?? [$option['function']] ?? [];
			}
		}
		return [];
	}

	/**
	 *  Parse CLI options using getopt()
	 * @return void
	 */
	protected static function parse_service_cli_options(): void {
		//base class short options
		self::$available_cli_options = self::base_cli_options();

		//get the options from the child class
		static::set_cli_options(self::$available_cli_options);

		//collapse short options to a string
		$short_options = self::get_short_options();

		//isolate long options
		$long_options = self::get_long_options();

		//parse the short and long options
		$options = getopt($short_options, $long_options);

		//make the options available to the child object
		if ($options !== false) {
			self::$parsed_cli_options = $options;
		} else {
			//make sure the cli_options are reset
			self::$parsed_cli_options = [];
			//if the options are empty there is nothing left to do
			return;
		}

		//notify user
		self::log("CLI Options detected: " . implode(",", self::$parsed_cli_options), LOG_DEBUG);

		//loop through the parsed options given on the command line
		foreach ($options as $option_key => $option_value) {

			//get the function responsible for handling the cli option
			$funcs = self::get_user_callbacks_from_available_options($option_key);

			//ensure it was found before we take action
			if (!empty($funcs)) {
				//check for more than one function to be called is permitted
				if (is_array($funcs)) {
					//call each one
					foreach($funcs as $func) {
						//use the best method to call the function
						self::call_function($func, $option_value);
					}
				} else {
					//single function call
					self::call_function($func, $option_value);
				}
			}
		}
	}

	//
	// Calls a function using the best suited PHP method
	//
	private static function call_function($function, $args) {
		if ($function === 'exit') {
			//check for exit
			exit($args);
		} elseif ($function instanceof Closure || function_exists($function)) {
			//globally available function or closure
			$function($args);
		} else {
			static::$function($args);
		}
	}

	/**
	 * Checks the file system for a pid file that matches the process ID from this running instance
	 * @return bool true if pid exists and false if not
	 */
	public static function is_running(): bool {
		return posix_getpid() === self::get_service_pid();
	}

	public static function is_any_running(): bool {
		return self::get_service_pid() !== false;
	}

	/**
	 * Returns the operating system service PID or false if it is not yet running
	 * @return bool|int PID or false if not running
	 */
	protected static function get_service_pid() {
		if (file_exists(self::$pid_file)) {
			$pid = file_get_contents(self::$pid_file);
			if (function_exists('posix_getsid')) {
				if (posix_getsid($pid) !== false) {
					//return the pid for reloading configuration
					return $pid;
				}
			} else {
				if (file_exists('/proc/' . $pid)) {
					//return the pid for reloading configuration
					return $pid;
				}
			}
		}
		return false;
	}

	/**
	 * Create an operating system PID file removing any existing PID file
	 */
	private function create_service_pid() {
		// Set the pid filename
		$basename = basename(self::$pid_file, '.pid');
		$pid = getmypid();

		// Remove the old pid file
		if (file_exists(self::$pid_file)) {
			unlink(self::$pid_file);
		}

		// Show the details to the user
		self::log("Service   : $basename", LOG_INFO);
		self::log("Process ID: $pid", LOG_INFO);
		self::log("PID File  : " . self::$pid_file, LOG_INFO);

		// Save the pid file
		file_put_contents(self::$pid_file, $pid);
	}

	/**
	 * Creates the service directory to store the PID
	 * @throws Exception thrown when the service directory is unable to be created
	 */
	private function create_service_directory() {
		//make sure the /var/run/fusionpbx directory exists
		if (!file_exists('/var/run/fusionpbx')) {
			$result = mkdir('/var/run/fusionpbx', 0777, true);
			if (!$result) {
				throw new Exception('Failed to create /var/run/fusionpbx');
			}
		}
	}

	/**
	 * Parses the debug level to an integer and stores it in the class for syslog use
	 * @param string $debug_level Debug level with any of the Linux system log levels
	 */
	protected static function set_debug_level(string $debug_level) {
		// Map user input log level to syslog constant
		switch ($debug_level) {
			case '0':
			case 'emergency':
				self::$log_level = LOG_EMERG; // Hardware failures
				break;
			case '1':
			case 'alert':
				self::$log_level = LOG_ALERT; // Loss of network connection or a condition that should be corrected immediately
				break;
			case '2':
			case 'critical':
				self::$log_level = LOG_CRIT; // Condition like low disk space
				break;
			case '3':
			case 'error':
				self::$log_level = LOG_ERR;  // Database query failure, file not found
				break;
			case '4':
			case 'warning':
				self::$log_level = LOG_WARNING; // Deprecated function usage, approaching resource limits
				break;
			case '5':
			case 'notice':
				self::$log_level = LOG_NOTICE; // Normal conditions
				break;
			case '6':
			case 'info':
				self::$log_level = LOG_INFO; // Informational
				break;
			case '7':
			case 'debug':
				self::$log_level = LOG_DEBUG; // Debugging
				break;
			default:
				self::$log_level = LOG_NOTICE; // Default to NOTICE if invalid level
		}
	}

	/**
	 * Show memory usage to the user
	 */
	protected static function show_mem_usage() {
		//current memory
		$memory_usage = memory_get_usage();
		//peak memory
		$memory_peak = memory_get_peak_usage();
		self::log('Current memory: ' . round($memory_usage / 1024) . " KB", LOG_INFO);
		self::log('Peak memory: ' . round($memory_peak / 1024) . " KB", LOG_INFO);
	}

	/**
	 * Logs to the system log
	 * @param string $message
	 * @param int $level
	 */
	protected static function log(string $message, int $level = null) {
		// Use default log level if not provided
		if ($level === null) {
			$level = self::$log_level;
		}

		// Log the message to syslog
		syslog($level, 'fusionpbx[' . posix_getpid() . ']: ['.self::class.'] '.$message);
	}

	/**
	 * Returns a file safe class name with \ from namespaces converted to _
	 * @return string file safe name
	 */
	protected static function base_file_name(): string {
		return str_replace('\\', "_", static::class);
	}

	/**
	 * Returns only the name of the class without namespace
	 * @return string base class name
	 */
	protected static function base_class_name(): string {
		$class_and_namespace = explode('\\', static::class);
		return array_pop($class_and_namespace);
	}

	/**
	 * Write a standard copyright notice to the console
	 * @return void
	 */
	public static function display_copyright(): void {
		echo "FusionPBX\n";
		echo "Version: MPL 1.1\n";
		echo "\n";
		echo "The contents of this file are subject to the Mozilla Public License Version\n";
		echo "1.1 (the \"License\"); you may not use this file except in compliance with\n";
		echo "the License. You may obtain a copy of the License at\n";
		echo "http://www.mozilla.org/MPL/\n";
		echo "\n";
		echo "Software distributed under the License is distributed on an \"AS IS\" basis,\n";
		echo "WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License\n";
		echo "for the specific language governing rights and limitations under the\n";
		echo "License.\n";
		echo "\n";
		echo "The Original Code is FusionPBX\n";
		echo "\n";
		echo "The Initial Developer of the Original Code is\n";
		echo "Mark J Crane <markjcrane@fusionpbx.com>\n";
		echo "Portions created by the Initial Developer are Copyright (C) 2008-2023\n";
		echo "the Initial Developer. All Rights Reserved.\n";
		echo "\n";
		echo "Contributor(s):\n";
		echo "Mark J Crane <markjcrane@fusionpbx.com>\n";
		echo "Tim Fry <tim.fry@hotmail.com>\n";
		echo "\n";
	}

	/**
	 * Sends the shutdown signal to the service using a posix signal.
	 * <p>NOTE:<br>
	 * The signal will not be received from the service if the
	 * command is sent from a user that has less privileges then
	 * the running service. For example, if the service is started
	 * by user root and then the command line option '-r' is given
	 * as user www-data, the service will not receive this signal
	 * because the OS will not allow the signal to be passed to a
	 * more privileged user due to security concerns. This would
	 * be the main reason why you must run a 'systemctl' or a
	 * 'service' command as root user. It is possible to start the
	 * service with user www-data and then the web UI would in fact
	 * be able to send the reload signal to the running service.</p>
	 */
	public static function send_signal($posix_signal) {
		$signal_name = "";
		switch ($posix_signal) {
			case SIGHUP:
			case SIGUSR1:
				$signal_name = "Reload";
				break;
			case SIGTERM:
			case SIGUSR2:
				$signal_name = "Shutdown";
				break;
		}
		$pid = self::get_service_pid();
		if ($pid === false) {
			self::log("service not running", LOG_EMERG);
		} else {
			if (posix_kill((int) $pid, $posix_signal) ) {
				echo "Sent $signal_name\n";
			} else {
				$err = posix_strerror(posix_get_last_error());
				echo "Failed to send $signal_name: $err\n";
			}
		}
	}

	/**
	 * Display a basic help message to the user for using service
	 */
	protected static function display_help_message(): void {
		//get the classname of the child class
		$class_name = self::base_class_name();

		//get the widest options for proper alignment
		$width_short = max(array_map(function ($arr) { return strlen($arr['short_description'] ?? ''); }, self::$available_cli_options));
		$width_long  = max(array_map(function ($arr) { return strlen($arr['long_description' ] ?? ''); }, self::$available_cli_options));

		//display usage help using the class name of child
		echo "Usage: php $class_name [options]\n";

		//display the options aligned to the widest short and long options
		echo "Options:\n";
		foreach (self::$available_cli_options as $option) {
			printf("%-{$width_short}s %-{$width_long}s %s\n",
				$option['short_description'],
				$option['long_description'],
				$option['description']
			);
		}
	}

	public static function send_reload() {
		if (self::is_any_running()) {
			self::send_signal(SIGUSR1);
		} else {
			die("Service Not Started\n");
		}
		exit();
	}

	//
	// Options built-in to the base service class. These can be overridden with the child class
	// or they can be extended using the array
	//
	private static function base_cli_options(): array {
		//put the display for help in an array so we can calculate width
		$help_options = [];
		$index = 0;
		$help_options[$index]['short_option'] = 'v';
		$help_options[$index]['long_option'] = 'version';
		$help_options[$index]['description'] = 'Show the version information';
		$help_options[$index]['short_description'] = '-v';
		$help_options[$index]['long_description'] = '--version';
		$help_options[$index]['functions'][] = 'display_version';
		$help_options[$index]['functions'][] = 'shutdown';
		$index++;
		$help_options[$index]['short_option'] = 'h';
		$help_options[$index]['long_option'] = 'help';
		$help_options[$index]['description'] = 'Show the version and help message';
		$help_options[$index]['short_description'] = '-h';
		$help_options[$index]['long_description'] = '--help';
		$help_options[$index]['functions'][] = 'display_version';
		$help_options[$index]['functions'][] = 'display_help_message';
		$help_options[$index]['functions'][] = 'shutdown';
		$index++;
		$help_options[$index]['short_option'] = 'a';
		$help_options[$index]['long_option'] = 'about';
		$help_options[$index]['description'] = 'Show the version and copyright information';
		$help_options[$index]['short_description'] = '-a';
		$help_options[$index]['long_description'] = '--about';
		$help_options[$index]['functions'][] = 'display_version';
		$help_options[$index]['functions'][] = 'display_copyright';
		$help_options[$index]['functions'][] = 'shutdown';
		$index++;
		$help_options[$index]['short_option'] = 'r';
		$help_options[$index]['long_option'] = 'reload';
		$help_options[$index]['description'] = 'Reload settings for an already running service';
		$help_options[$index]['short_description'] = '-r';
		$help_options[$index]['long_description'] = '--reload';
		$help_options[$index]['functions'][] = 'send_reload';
		$index++;
		$help_options[$index]['short_option'] = 'd:';
		$help_options[$index]['long_option'] = 'debug:';
		$help_options[$index]['description'] = 'Set the syslog level between 0 (EMERG) and 7 (DEBUG). 5 (INFO) is default';
		$help_options[$index]['short_description'] = '-d <level>';
		$help_options[$index]['long_description'] = '--debug <level>';
		$help_options[$index]['functions'][] = 'set_debug_level';
		$index++;
		$help_options[$index]['short_option'] = 'c:';
		$help_options[$index]['long_option'] = 'config:';
		$help_options[$index]['description'] = 'Full path and file name of the configuration file to use. /etc/fusionpbx/config.conf or /usr/local/etc/fusionpbx/config.conf on FreeBSD is default';
		$help_options[$index]['short_description'] = '-c <path>';
		$help_options[$index]['long_description'] = '--config <path>';
		$help_options[$index]['functions'][] = 'set_config_file';
		$index++;
		$help_options[$index]['short_option'] = 'x';
		$help_options[$index]['long_option'] = 'exit';
		$help_options[$index]['description'] = 'Exit the service gracefully';
		$help_options[$index]['short_description'] = '-x';
		$help_options[$index]['long_description'] = '--exit';
		$help_options[$index]['functions'][] = 'send_shutdown';
		$help_options[$index]['functions'][] = 'shutdown';
		return $help_options;
	}

	/**
	 * Set the configuration file location to use for a config object
	 */
	public static function set_config_file(string $file = '/etc/fusionpbx/config.conf') {
		if (empty(self::$config_file)) {
			self::$config_file = $file;
		}
		self::$config = new config(self::$config_file);
	}

	/**
	 * Appends the CLI option to the list given to the user as a command line argument.
	 * @param cli_option $option
	 * @return int The index of the item added
	 */
	public static function append_cli_option(cli_option $option): int {
		$index = count(self::$available_cli_options);
		self::$available_cli_options[$index] = $option->to_array();
		return $index;
	}

	/**
	 * Adds an option to the command line parameters
	 * @param string $short_option
	 * @param string $long_option
	 * @param string $description
	 * @param string $short_description
	 * @param string $long_description
	 * @param string $callback
	 * @return int The index of the item added
	 */
	public static function add_cli_option(string $short_option, string $long_option, string $description, string $short_description = '', string $long_description = '', ...$callback): int {
		//use the option as the description if not filled in
		if (empty($short_description)) {
			$short_description = '-' . $short_option;
			if (str_ends_with($short_option, ':')) {
				$short_description .= " <setting>";
			}
		}
		if (empty($long_description)) {
			$long_description = '-' . $long_option;
			if (str_ends_with($long_option, ':')) {
				$long_description .= " <setting>";
			}
		}
		$index = count(self::$available_cli_options);
		self::$available_cli_options[$index]['short_option'] = $short_option;
		self::$available_cli_options[$index]['long_option'] = $long_option;
		self::$available_cli_options[$index]['description'] = $description;
		self::$available_cli_options[$index]['short_description'] = $short_description;
		self::$available_cli_options[$index]['long_description'] = $long_description;
		self::$available_cli_options[$index]['functions'] = $callback;
		return $index;
	}

	/**
	 * Returns the process ID filename used for a service
	 * @return string file name used for the process identifier
	 */
	public static function get_pid_filename(): string {
		return '/var/run/fusionpbx/' . self::base_file_name() . '.pid';
	}

	/**
	 * Sets the following:
	 *   - execution time to unlimited
	 *   - location for PID file
	 *   - parses CLI options
	 *   - ensures folder structure exists
	 *   - registers signal handlers
	 */
	private function init() {

		// Increase limits
		set_time_limit(0);
		ini_set('max_execution_time', 0);
		ini_set('memory_limit', '512M');

		//set the PID file
		self::$pid_file = self::get_pid_filename();

		//register the shutdown function
		register_shutdown_function([$this, 'shutdown']);

		// Ensure we have only one instance
		if (self::is_any_running()) {
			self::log("Service already running", LOG_ERR);
			exit();
		}

		// Ensure directory creation for pid location
		$this->create_service_directory();

		// Create a process identifier file
		$this->create_service_pid();

		// Set the signal handlers for reloading
		$this->register_signal_handlers();

		// We are now considered running
		$this->running = true;
	}

	/**
	 * Creates a system service that will run in the background
	 * @return self
	 */
	public static function create(): self {
		//can only start from command line
		defined('STDIN') or die('Unauthorized');

		//force launching in a seperate process
		if ($pid = pcntl_fork()) {
			exit;
		}

		if ($cid = pcntl_fork()) {
			exit;
		}

		//parse the cli options and store them statically
		self::parse_service_cli_options();

		//create the config object if not already created
		if (self::$config === null) {
			self::$config = new config(self::$config_file);
		}

		//get the name of child object
		$class = self::base_class_name();

		//create the child object
		$service = new $class();

		//initialize the service
		$service->init();

		return $service;
	}

}
