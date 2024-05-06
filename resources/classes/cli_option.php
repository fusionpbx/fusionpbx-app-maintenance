<?php

/**
 * Description of cli_option
 *
 * @author Tim Fry <tim.fry@hotmail.com>
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

	public static function new(...$options): self {
		$class_name = self::class;
		foreach ($options as $key => $value) {
			if (property_exists($class_name, $key)) {
				$class_name->{$key} = $value;
			}
		}
		return new $class_name();
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
			$short_description = '-' . $this->short_option;
			if (str_ends_with($this->short_option, ':')) {
				$short_description .= " <value>";
			}
		} else {
			$short_description = $this->short_description;
		}
		return $short_description;
	}

	public function long_description(?string $long_description = null) {
		if (!empty($long_description)) {
			$this->long_description = $long_description;
			return $this;
		}
		if (empty($this->long_description)) {
			$long_description = '-' . $this->long_option;
			if (str_ends_with($this->long_option, ':')) {
				$long_description .= " <value>";
			}
		} else {
			$long_description = $this->long_description;
		}
		return $long_description;
	}

	public function functions(?array $functions = null) {
		if (!empty($functions)) {
			$this->functions = $functions;
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
