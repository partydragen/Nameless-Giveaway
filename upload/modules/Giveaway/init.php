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
require_once(ROOT_PATH . '/modules/Giveaway/autoload.php');

// Initialise module
require_once(ROOT_PATH . '/modules/Giveaway/module.php');
$module = new Giveaway_Module($language, $giveaway_language, $pages, $endpoints);