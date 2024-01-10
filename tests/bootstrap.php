<?php
/**
 * This is a proxy for the application bootstrap so far as we don't need anything special for tests
 *
 * @author  Yuriy Akopov
 * @date    2013-08-20
 */

global $phpUnitStrict;
if ($phpUnitStrict !== true) {
    // legacy code generates lots of warnings which break PHPUnit default workflow
    // converting warnings to exceptions can be disable in PHPUnit XML configs, but warnings will be still displayed
    // making test output almost unreadable, so we leave ourselves an option to disable them here
    error_reporting(E_ERROR);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
}

// emulating command line argument for the environment choice
global $phpUnitEnv;
$argv = array(
    __FILE__,
    $phpUnitEnv
);
$argc = count($argv);

// calling application bootstrap
$bootstrapPath = implode(DIRECTORY_SEPARATOR, array(
    dirname(dirname(__FILE__)),
    'application',
    'Bootstrap-cli.php'
));
require $bootstrapPath;

// adding PHPUnit path to includes so the framework can find its files
set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/pear');


