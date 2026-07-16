<?php

require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

// Validate permissions
if (!permission_exists('maintenance_edit')) {
        echo "access denied";
        exit;
}

// Add multi-lingual support
$language = new text;
$text = $language->get();

// Reload autoloader
$autoload->update();

// Reload default settings
settings::clear_cache();

// Reset others
$classes_to_clear = array_filter($autoload->get_interface_list('clear_cache'), function ($class) { return $class !== 'settings'; });
foreach ($classes_to_clear as $class_name) {
        $class_name::clear_cache();
}

// Reset domains
$domain = new domains();
$domain->set();

// Send the reload command to the maintenance service
maintenance_service::send_reload();

// Notify the user
message::add($text['message-settings_reloaded']);

// Redirect to the maintenance page and exit
header("Location: maintenance.php");
exit;
