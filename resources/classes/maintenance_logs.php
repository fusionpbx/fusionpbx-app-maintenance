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
 * Description of maintenance_logs
 *
 * @author Tim Fry <tim.fry@hotmail.com>
 */
class maintenance_logs implements database_maintenance {

	public function database_maintenance(database $database, settings $settings): void {
		//get retention days
		$days = $settings->get($this->database_retention_category(), $this->database_retention_subcategory(), '');
		//look for old entries
		if (!empty($days)) {
			$sql = "delete from v_maintenance_logs where insert_date < NOW() - INTERVAL '$days days'";
			$database->execute($sql);
			if ($database->message['code'] === '200') {
				maintenance_service::log_write($this, "Removed maintenance log entries older than $days days.");
			} else {
				maintenance_service::log_write($this, "Failed to clear entries", maintenance_service::LOG_ERROR);
			}
		} else {
			maintenance_service::log_write($this, 'Retention days not set', maintenance_service::LOG_ERROR);
		}
	}

	public function database_retention_category(): string {
		return 'maintenance';
	}

	public function database_retention_subcategory(): string {
		return 'database_retention_days';
	}

	public function database_retention_default_value(): string {
		return '30';
	}
}
