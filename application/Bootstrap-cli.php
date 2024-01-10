<?php

// Bootstrap the application from the CLI

// Query CLI arguments
if ($argc >= 2) {
	if (in_array($argv[1], array('development', 'testing', 'test', 'test2', 'test3', 'production', 'ukdev', 'manila-dev', 'dev-live-salesforce'))) {
		// Note: in the Apache bootstrap, these are defined in the vhost
		putenv("APPLICATION_ENV={$argv[1]}");
		
		$_SERVER['APPLICATION_ENV'] = $argv[1];
		
		// Set server HTTP host
		if ($argv[1] == 'development' || $argv[1] == 'development-production-data' || $argv[1] == 'manila-dev' || $argv[1] == 'dev-live-salesforce') {
			$_SERVER['HTTP_HOST'] = 'dev.shipserv.com';
		} elseif ($argv[1] == 'testing' || $argv[1] == 'test') {
			$_SERVER['HTTP_HOST'] = 'test.shipserv.com';
		} elseif ($argv[1] == 'test2') {
			$_SERVER['HTTP_HOST'] = 'test2.shipserv.com';
		} elseif ($argv[1] == 'test3') {
		    $_SERVER['HTTP_HOST'] = 'test3.shipserv.com';			
		} elseif ($argv[1] == 'ukdev') {
			$_SERVER['HTTP_HOST'] = 'ukdev.shipserv.com';
		} elseif ($argv[1] == 'production') {
			$_SERVER['HTTP_HOST'] = 'www.shipserv.com';
		}
	} else {
		echo "Illegal environment specified\n";
		echo "Usage: php <script_name> development|manila-dev|testing|test|test2|test3|ukdev|production\n";
		die(1);
	}
} else {
	echo "Incorrect number of arguments\n";
	echo "Usage: php <script_name> development|manila-dev|testing|test|test2|test3|ukdev|production\n";
	die(1);
}

set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    '.',
    '/var/www/libraries',
    '/usr/share/php'
)));

/**
 * set the session garbage collection to 2 years so that the cookie expiry matches
 * 
 */
ini_set('session.gc_maxlifetime', 3600);

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', dirname(__FILE__));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));
set_include_path(implode(PATH_SEPARATOR, array(
        realpath(APPLICATION_PATH . '/../vendor/Zend'),
        get_include_path(),
)));

// initialised Composer autoloader
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

/** Zend_Application */
require_once 'Zend/Application.php';

$iniFile = '/prod/application.ini';

$application = new Zend_Application(
    APPLICATION_ENV, 
    $iniFile
);
$application->bootstrap();
