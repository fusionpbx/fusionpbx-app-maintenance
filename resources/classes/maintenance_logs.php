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

/**
 * Description of maintenance_logs
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class maintenance_logs {

	use database_maintenance;

	const APP_NAME = 'maintenance_logs';
	const APP_UUID = '5be7b4c2-1a4f-4236-b91a-60e3c33904d7';
	const PERMISSION_PREFIX = 'maintenance_log_';
	const LIST_PAGE = 'maintenance_logs.php';
	const TABLE = 'maintenance_logs';
	const UUID_PREFIX = 'maintenance_log_';
	const TOGGLE_FIELD = 'maintenance_log_enable';
	const TOGGLE_VALUES = ['true', 'false'];

	private $database;
	private $settings;
	private $domain_uuid;
	private $user_uuid;

	public function __construct(database $database, settings $settings) {
		if ($database !== null) {
			$this->database = $database;
		} else {
			$this->database = new $database;
		}

		$this->domain_uuid = $_SESSION['domain_uuid'] ?? '';
		$this->user_uuid = $_SESSION['user_uuid'] ?? '';

		if ($settings !== null) {
			$this->settings = $settings;
		} else {
			$this->settings = new settings([
				'database' => $database
				, 'domain_uuid' => $this->domain_uuid
				, 'user_uuid' => $this->user_uuid
			]);
		}

		$database->app_name = self::APP_NAME;
		$database->app_uuid = self::APP_UUID;
	}

	public static function database_maintenance_sql(string $domain_uuid, string $retention_days): string {
		return "delete from v_maintenance_logs"
			. " where insert_date < NOW() - INTERVAL '$retention_days days'"
			. " and (domain_uuid = '$domain_uuid' or domain_uuid is null)"
		;
	}

	/**
	 * delete records
	 */
	public function delete(array $records) {
		//add multi-lingual support
		$language = new text;
		$text = $language->get();

		if (!permission_exists(self::PERMISSION_PREFIX . 'delete') || empty($records)) {
			message::add($text['message-no_records'], 'negative');
			header('Location: ' . self::LIST_PAGE);
			exit;
		}

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'], 'negative');
			header('Location: ' . self::LIST_PAGE);
			exit;
		}

		//delete multiple records by building an array of records to remove
		$remove_array = [];
		foreach ($records as $x => $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$remove_array[self::TABLE][$x][self::UUID_PREFIX . 'uuid'] = $record['uuid'];
				$remove_array[self::TABLE][$x]['domain_uuid'] = $this->domain_uuid;
			}
		}

		//delete the checked rows
		if (!empty($remove_array)) {
			//execute delete
			$this->database->delete($remove_array);
			//set message
			message::add($text['message-delete']);
		}
	}

	/**
	 * toggle records
	 */
	public function toggle(array $records) {
		//add multi-lingual support
		$language = new text;
		$text = $language->get();

		//check that we have something to do
		if (empty($records) || !permission_exists(self::PERMISSION_PREFIX . 'edit')) {
			message::add($text['message-no_records'], 'negative');
			header('Location: ' . self::LIST_PAGE);
			return;
		}

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'], 'negative');
			header('Location: ' . self::LIST_PAGE);
			exit;
		}

		//toggle the checked records to get current toggle state
		$uuids = [];
		foreach ($records as $x => $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$uuids[] = "'" . $record['uuid'] . "'";
			}
		}
		if (!empty($uuids)) {
			$sql = "select " . self::UUID_PREFIX . "uuid as uuid, " . self::TOGGLE_FIELD . " as toggle from v_" . self::TABLE . " ";
			$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
			$sql .= "and " . self::UUID_PREFIX . "uuid in (" . implode(', ', $uuids) . ") ";
			$parameters['domain_uuid'] = $this->domain_uuid;

			$rows = $this->database->select($sql, $parameters, 'all');
			if (!empty($rows)) {
				$states = [];
				foreach ($rows as $row) {
					$states[$row['uuid']] = $row['toggle'];
				}
			}
		}

		//build update array
		$x = 0;
		$array = [];
		foreach ($states as $uuid => $state) {
			$array[self::TABLE][$x][self::UUID_PREFIX . 'uuid'] = $uuid;
			$array[self::TABLE][$x][self::TOGGLE_FIELD] = $state == self::TOGGLE_VALUES[0] ? self::TOGGLE_VALUES[1] : self::TOGGLE_VALUES[0];
			$x++;
		}

		//save the changes
		if (!empty($array)) {

			//save the array
			$database->app_name = self::APP_NAME;
			$database->app_uuid = self::APP_UUID;
			$this->database->save($array);

			//set message
			message::add($text['message-toggle']);
		}
	}

	/**
	 * copy records
	 */
	public function copy(array $records) {
		//check that we have something to do
		if (empty($records) || !permission_exists(self::PERMISSION_PREFIX . 'add')) {
			return;
		}
		//add multi-lingual support
		$language = new text;
		$text = $language->get();

		//validate the token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'], 'negative');
			header('Location: ' . self::LIST_PAGE);
			exit;
		}


		//get checked records
		$uuids = [];
		foreach ($records as $record) {
			if (!empty($record['checked']) && $record['checked'] == 'true' && is_uuid($record['uuid'])) {
				$uuids[] = "'" . $record['uuid'] . "'";
			}
		}

		//create insert array from existing data
		if (!empty($uuids)) {
			$sql = "select * from v_" . self::TABLE
				. " where (domain_uuid = :domain_uuid or domain_uuid is null)"
				. " and " . self::UUID_PREFIX . "uuid in (" . implode(', ', $uuids) . ")";
			$parameters['domain_uuid'] = $this->domain_uuid;
			$rows = $this->database->select($sql, $parameters, 'all');
			if (!empty($rows)) {
				$array = [];
				foreach ($rows as $x => $row) {
					//copy data
					$array[self::TABLE][$x] = $row;
					//overwrite
					$array[self::TABLE][$x][self::UUID_PREFIX . 'uuid'] = uuid();
					$array[self::TABLE][$x]['bridge_description'] = trim($row['bridge_description'] . ' (' . $text['label-copy'] . ')');
				}
				$this->database->save($array);
			}

			//set message
			message::add($text['message-copy']);
		}
	}
}
