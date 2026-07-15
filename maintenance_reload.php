<?php
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

if (!permission_exists('maintenance_edit')) {
        echo "access denied";
        exit;
}

$language = new text;
$text = $language->get();

//reload autoloader
$autoload->update();

//reload default settings
settings::clear_cache();

//reset others
$classes_to_clear = array_filter($autoload->get_interface_list('clear_cache'), function ($class) { return $class !== 'settings'; });
foreach ($classes_to_clear as $class_name) {
        $class_name::clear_cache();
}

//reset domains
$domain = new domains();
$domain->set();

message::add($text['message-settings_reloaded']);

header("Location: maintenance.php");
exit;
