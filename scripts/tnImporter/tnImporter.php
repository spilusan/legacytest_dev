<?php

// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

// Include lib
require_once 'lib/common.php';

class Tni_Cli
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
		Tni_Logger::log("Start");
		$o = new Tni_Importer();
		$o->run();
		Tni_Logger::log("Finished");
	}
}

Tni_Cli::run();
