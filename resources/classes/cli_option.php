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
 * Container object for creating command line options
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class cli_option {

	private $short_option;
	private $long_option;
	private $description;
	private $short_description;
	private $long_description;
	private $functions;

	public function __construct() {
		$this->short_option = '';
		$this->long_option = '';
		$this->description = '';
		$this->short_description = '';
		$this->long_description = '';
		$this->functions = [];
	}

	public static function new(...$options): cli_option {
		$obj = new cli_option();

		//automatically assign properties to the object that were passed in key/value pairs
		self::parse_options($obj, $options);


		//return the cli_option with all properties filled in that were passed
		return $obj;
	}

	private static function parse_options($obj, $options) {
		foreach ($options as $key => $value) {
			if (is_array($value)) {
				self::parse_options($obj, $value);
			}
			//call the method with the name of $key and pass it $value
			if (method_exists($obj, $key)) {
				$obj->{$key}($value);
			} elseif (property_exists($obj, $key)) {
				$obj->{$key} = $value;
			}
		}
	}

	public function short_option(?string $short_option = null) {
		if (!empty($short_option)) {
			$this->short_option = $short_option;
			return $this;
		}
		return $this->short_option;
	}

	public function long_option(?string $long_option = null) {
		if (!empty($long_option)) {
			$this->long_option = $long_option;
			return $this;
		}
		return $this->long_option;
	}

	public function description(?string $description = null) {
		if (!empty($description)) {
			$this->description = $description;
			return $this;
		}
		return $this->description;
	}

	public function short_description(?string $short_description = null) {
		if (!empty($short_description)) {
			$this->short_description = $short_description;
			return $this;
		}
		if (empty($this->short_description)) {
			if (str_ends_with($this->short_option, ':')) {
				$short = rtrim($this->short_option, ':');
				$short_description = "-$short <value>";
			} else {
				$short_description = '-' . $this->short_option;
			}
		} else {
			$short_description = $this->short_description;
		}
		return $short_description;
	}

	public function long_description(?string $long_description = null) {
		if ($long_description !== null) {
			$this->long_description = $long_description;
			return $this;
		}
		if (empty($this->long_description)) {
			if (str_ends_with($this->long_option, ':')) {
				$long = rtrim($this->long_option, ':');
				$long_description = "--$long <value>";
			} else {
				$long_description = '--' . $this->long_option;
			}
		} else {
			$long_description = $this->long_description;
		}
		return $long_description;
	}

	public function functions(?array $functions = null) {
		if ($functions !== null) {
			$this->functions = $functions;
			return $this;
		}
		return $this->functions;
	}

	public function function(?string $function = null) {
		if ($function !== null) {
			$this->functions += [$function];
			return $this;
		}
		return $this->functions;
	}

	public function to_array(): array {
		$arr['short_option'] = $this->short_option();
		$arr['long_option'] = $this->long_option();
		$arr['description'] = $this->description();
		$arr['short_description'] = $this->short_description();
		$arr['long_description'] = $this->long_description();
		$arr['functions'] = $this->functions();
		return $arr;
	}
}
