<?php

// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

// Include emailer lib
require_once 'lib/common.php';

class Eml_Cli
{
	private function __construct ()
	{
		// Do nothing
	}
	
	public static function run ()
	{
		// No max execution time
		ini_set('max_execution_time', 0);
		
		// No upper memory limit
		ini_set('memory_limit', -1);
		
		// Change working dir
		chdir(dirname(__FILE__));
		
		// Run
		Eml_Logger::log("Start");
		$o = new Eml_Emailer();
		$o->run();
		Eml_Logger::log("Finished");
	}
}

Eml_Cli::run();
