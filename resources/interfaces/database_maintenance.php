<?php

/**
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
interface database_maintenance {
	public static function database_maintenance(settings $settings): void;
	public static function database_maintenance_category(): string;
	public static function database_maintenance_subcategory(): string;
}

?>
