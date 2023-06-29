<?php 
/*
 *  Made by Partydragen
 *  https://partydragen.com
 *
 *  Giveaway module initialisation file
 */
 
// Initialise giveaway language
$giveaway_language = new Language(ROOT_PATH . '/modules/Giveaway/language', LANGUAGE);

// Load classes
spl_autoload_register(function ($class) {
    $path = join(DIRECTORY_SEPARATOR, [ROOT_PATH, 'modules', 'Giveaway', 'classes', $class . '.php']);
    if (file_exists($path)) {
        require_once($path);
    }
});

// Load classes
spl_autoload_register(function ($class) {
    $path = join(DIRECTORY_SEPARATOR, [ROOT_PATH, 'modules', 'Giveaway', 'classes', 'Tasks', $class . '.php']);
    if (file_exists($path)) {
        require_once($path);
    }
});

// Initialise module
require_once(ROOT_PATH . '/modules/Giveaway/module.php');
$module = new Giveaway_Module($language, $giveaway_language, $pages);