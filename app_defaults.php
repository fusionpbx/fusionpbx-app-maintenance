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

if ($domains_processed == 1) {
	if (class_exists('framework')) {
		//use global config and database objects if available
		maintenance::app_defaults(framework::database());
	} else {
		global $database;
		if ($database === null) {
			$database = database::new();
		}

		//set the app information for database accounting
		$database->app_name = maintenance::APP_NAME;
		$database->app_uuid = maintenance::APP_UUID;

		//maintenance class handles setting app defaults
		maintenance::app_defaults($database);
	}
}

?>
