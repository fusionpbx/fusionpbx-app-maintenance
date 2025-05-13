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

//application details
	$apps[$x]['name'] = "Maintenance";
	$apps[$x]['uuid'] = "8a209bac-3bba-46eb-828e-bd4087fb9cee";
	$apps[$x]['category'] = "";
	$apps[$x]['subcategory'] = "";
	$apps[$x]['version'] = "1.0";
	$apps[$x]['license'] = "Mozilla Public License 1.1";
	$apps[$x]['url'] = "https://www.fusionpbx.com";
	$apps[$x]['description']['en-us'] = "Performs maintenance work on database and filesystem";
	$apps[$x]['description']['en-gb'] = "Performs maintenance work on database and filesystem";
	$apps[$x]['description']['ar-eg'] = "يقوم بأعمال الصيانة على قاعدة البيانات ونظام الملفات";
	$apps[$x]['description']['de-at'] = "Führt Wartungsarbeiten an Datenbank und Dateisystem durch";
	$apps[$x]['description']['de-ch'] = "Führt Wartungsarbeiten an Datenbank und Dateisystem durch";
	$apps[$x]['description']['de-de'] = "Führt Wartungsarbeiten an Datenbank und Dateisystem durch";
	$apps[$x]['description']['es-cl'] = "Realiza trabajos de mantenimiento en la base de datos y el sistema de archivos.";
	$apps[$x]['description']['es-mx'] = "Realiza trabajos de mantenimiento en la base de datos y el sistema de archivos.";
	$apps[$x]['description']['fr-ca'] = "Effectue des travaux de maintenance sur la base de données et le système de fichiers";
	$apps[$x]['description']['fr-fr'] = "Effectue des travaux de maintenance sur la base de données et le système de fichiers";
	$apps[$x]['description']['he-il'] = "מבצע עבודות תחזוקה על מסד נתונים ומערכת קבצים";
	$apps[$x]['description']['it-it'] = "Esegue lavori di manutenzione su database e filesystem";
	$apps[$x]['description']['nl-nl'] = "Voert onderhoudswerkzaamheden uit aan de database en het bestandssysteem";
	$apps[$x]['description']['pl-pl'] = "Wykonuje prace konserwacyjne na bazie danych i systemie plików";
	$apps[$x]['description']['pt-br'] = "Executa trabalhos de manutenção em banco de dados e sistema de arquivos";
	$apps[$x]['description']['pt-pt'] = "Executa trabalhos de manutenção em base de dados e sistema de ficheiros";
	$apps[$x]['description']['ro-ro'] = "Efectuează lucrări de întreținere a bazei de date și a sistemului de fișiere";
	$apps[$x]['description']['ru-ru'] = "Выполняет работы по обслуживанию базы данных и файловой системы.";
	$apps[$x]['description']['sv-se'] = "Utför underhållsarbete på databas och filsystem";
	$apps[$x]['description']['uk-ua'] = "Виконує роботу з обслуговування бази даних і файлової системи";
	$apps[$x]['description']['zh-cn'] = "执行数据库和文件系统的维护工作";
	$apps[$x]['description']['ja-jp'] = "データベースとファイルシステムのメンテナンス作業を実行します";
	$apps[$x]['description']['ko-kr'] = "데이터베이스 및 파일 시스템에 대한 유지 관리 작업을 수행합니다.";

	//maintenance log view permissions
	$y = 0;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_show_all';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_log_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_log_delete';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_status_view';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_log_all';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	$y++;
	$apps[$x]['permissions'][$y]['name'] = 'maintenance_edit';
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';

	//database table
	$table_index = 0;
	$apps[$x]['db'][$table_index]['table']['name'] = 'v_maintenance_logs';
	$apps[$x]['db'][$table_index]['table']['parent'] = '';
	$field_index = 0;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'maintenance_log_uuid';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'uuid';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'text';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'char(36)';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['key']['type'] = 'primary';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "domain_uuid";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = "uuid";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = "text";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = "char(36)";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['key']['type'] = "foreign";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['key']['reference']['table'] = "v_domains";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['key']['reference']['field'] = "domain_uuid";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'maintenance_log_application';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type'] = 'text';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['search_by'] = '1';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'maintenance_log_epoch';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'numeric';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'numeric';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'bigint';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'maintenance_log_message';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type'] = 'text';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'maintenance_log_status';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type'] = 'text';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "insert_date";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'date';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "insert_user";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = "uuid";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = "text";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = "char(36)";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "update_date";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'date';
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	$field_index++;
	$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "update_user";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = "uuid";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = "text";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = "char(36)";
	$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
	//default settings
	$y = 0;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "3a6c49d6-dc4a-412e-b23e-f7880214e064";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "maintenance";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable or Disable execution of the maintenance service.";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "288af9fe-98e6-49a0-95ab-d32ee7638cf9";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "maintenance";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "check_interval";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "33";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Number of seconds to wait before testing for the execute time.";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "13c9211f-fa66-4255-86dc-bd73094a8f52";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "maintenance_logs";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "database_retention_days";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "30";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Number of days to retain the maintenance logs in the database.";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a74c6ccf-9790-49ee-83f3-ec137def8a29";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "maintenance";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "time_of_day";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "03:00";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Server time to start executing the maintenance applications. Must be in 00:00 format.";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "2228ddc5-44c8-4c6f-8b32-ad9432c37edf";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "maintenance";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "application";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "array";
	$apps[$x]['default_settings'][$y]['default_setting_order'] = "000";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "maintenance_logs";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "Maintenance log application to remove old entries.";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "074addbd-c010-49f8-bb84-72f14d4ed197";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "theme";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "dashboard_maintenance_chart_main_color";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "#2a9df4";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "";
	$y++;
	$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "2b39f31a-69ad-4083-98c7-e97f50bd30ce";
	$apps[$x]['default_settings'][$y]['default_setting_category'] = "theme";
	$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "dashboard_maintenance_chart_sub_color";
	$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
	$apps[$x]['default_settings'][$y]['default_setting_value'] = "#d4d4d4";
	$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
	$apps[$x]['default_settings'][$y]['default_setting_description'] = "";
