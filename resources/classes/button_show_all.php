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
 * Description of button_show_all
 *
 * @author Tim Fry <tim@fusionpbx.com>
 * @property string $label Alias for title
 */
class button_show_all extends button {

	/**
	 * Hypertext Link
	 * @var string
	 */
	private $link;

	/**
	 * Icon
	 * @var string
	 */
	private $icon;

	/**
	 *
	 * @var array
	 */
	private $properties;

	/**
	 *
	 * @var array
	 */
	private $styles;

	/**
	 *
	 * @var array
	 */
	private $classes;

	public function __construct() {
		$this->properties = [];
		$this->styles = [];
		$this->classes = [];
		$this->properties['type'] = 'button';
	}

	public function __set(string $name, mixed $value): void {
		if ($value !== null) {
			if ($name === 'label') {
				$name = 'title';
			}
			if (property_exists($this, $name)) {
				$this->{$name} = $value;
			} else {
				$this->properties[$name] = $value;
			}
		}
	}

	public function add_link($link) {
		$this->link = $link;
		return $this;
	}

	public function append_class($class) {
		$this->classes[] = $class;
		return $this;
	}

	public function has_class($class): bool {
		return array_search($class, $this->classes) !== false;
	}

	public function remove_class($class) {
		$key = array_search($class, $this->classes);
		if ($key !== false) {
			unset ($this->classes[$key]);
		}
		return $this;
	}

	public function add_property($name, $value) {
		if ($name === null || $name === '') {
			throw new \InvalidArgumentException('Button property name must not be null or empty string');
		}
		if (!empty($name)) {
			if ($value === null) {
				$value = '';
			}
			if (property_exists($this, $name)) {
				//add special tracked attribute
				$this->{$name} = $value;
			} elseif ($name === 'style') {
				//redirect to use the add_style method
				$this->add_style($name, $value);
			} elseif ($name === 'class') {
				//redirect to use the append_class method
				$this->append_class($value);
			} else {
				//add dynamic property
				$this->properties[$name] = $value;
			}
		}
		return $this;
	}

	public function add_style($name, $value) {
		if (!empty($name)) {
			$this->styles[$name] = $value;
		}
		return $this;
	}

	public function has_style($name): bool {
		if (empty($name)) {
			return false;
		}
		return key_exists($name, $this->styles);
	}

	public function has_property(string $name): bool {
		if (empty($name)) {
			return false;
		}
		if (property_exists($this, $name) || key_exists($name, $this->property)) {
			return true;
		}
		return false;
	}

	public function remove_property($name) {
		if ($this->has_property($name)) {
			if (property_exists($this, $name)) {
				$this->{$name} = '';
			}
			if (key_exists($name, $this->property)) {
				unset ($this->property[$name]);
			}
		}
		return $this;
	}

	public function get_classes(): string {
		return  implode(' ', $this->classes);
	}

	public function get_styles(): string {
		$html = "";
		//put in styles if there any
		if (!empty($this->styles)) {
			$html .= "style='";
			foreach ($this->styles as $key => $value) {
				$html .= "$key: $value; ";
			}
			//remove trailing space from style properties
			$html = rtrim($html);
			//add last quote
			$html .= "'";
		}
		return $html;
	}

	public function get_dynamic_properties(): string {
		//check for the standard class and add it if needed
		if (empty($this->classes)) {
			$this->properties['class'] = 'btn btn-default';
		} else {
			$this->properties['class'] = $this->get_classes();
		}
		$html = "";
		//put in properties if there are any
		foreach ($this->properties as $name => $value) {
			$html .= "$name='$value' ";
		}
		//remove trailing space before closing tag
		return rtrim($html);
	}

	public function get_properties(): string {
		$html = "";
		foreach ($this as $name => $value) {
			if ($name === 'link' || $name === 'icon') {
				continue;
			}
			if (!is_array($value)) {
				$html .= "$name='$value' ";
			}
		}
		return rtrim($html);
	}

	public function __toString(): string {
		$html = "";
		if (!empty($this->link)) {
			$html .= "<a href='$this->link' target='_self'>";
		}
		$html .= "<button ";
		$html .= $this->get_properties();
		$html .= $this->get_dynamic_properties();
		$html .= $this->get_styles();
		$html .= ">";
		if (!empty($this->icon)) {
			$html .= "<span class='fas fa-{$this->icon} fa-fw'></span>";
		}
		if (!empty($this->properties['title'])) {
			$html .= "<span class='button-label pad'>" . $this->properties['title'] . "</span>";
		}
//		$html .= $this->title;
		$html .= '</button>';
		if (!empty($this->link)) {
			$html .= '</a>';
		}
		return $html;
	}

	public static function create($array = []): string {
		global $text;
		$default_values = [
			'type'  => 'button',
			'alt'   => $text['button-show_all'],
			'label' => $text['button-show_all'],
			'class' => 'btn btn-default',
			'icon'  => $_SESSION['theme']['button_icon_all'] ?? 'globe',
			'link'=>'?show=all',
		];
		return parent::create(array_merge($default_values, $array));
	}

	//dynamically assign values to the button object
	public static function parse_properties(button $button, ...$properties) {
		foreach($properties as $key => $property) {
			if (is_array($property)) {
				foreach($property as $name => $value) {
					if (is_array($value)) {
						self::parse_properties($button, $value);
					} else {
						$button->{$name} = $value;
					}
				}
			} else {
				if (is_array($property)) {
					self::parse_properties($button, $property);
				} else {
					$button->{$key} = $property;
				}
			}
		}
	}
}
