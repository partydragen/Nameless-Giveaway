<?php
/*
 *  Made by Partydragen
 *  https://partydragen.com
 *
 *  Giveaway autoload file
 */

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

// Load classes
spl_autoload_register(function ($class) {
    $path = join(DIRECTORY_SEPARATOR, [ROOT_PATH, 'modules', 'Giveaway', 'classes', 'Events', $class . '.php']);
    if (file_exists($path)) {
        require_once($path);
    }
});