<?php
/**
 * set the session garbage collection to 2 months so that the cookie expiry matches
 */
//ini_set('session.gc_maxlifetime',    5184000);

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

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

// Create application, bootstrap, and run
/*
 * The size of the application.ini increased by time, and takes significant time to process per request,
 * as it is processed by a recursive function "processkey" as all environments, and all hierarchical emements must be interpreted
 * Saving page response time we are memcaching the application.ini parsed version, so the parsing will not take place.
 * the setting APPLICATION_PATH . '/configs/application.ini' was changed to APPLICATION_PATH . '/configs/application_ini.php', where we are managing the caching
 */
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application_ini.php'
);
$application->bootstrap()
            ->run();
