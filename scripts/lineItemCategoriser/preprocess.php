<?php


// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

// Include library
//require_once 'lib/common.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		// Load file specified via CLI
		$result = Shipserv_Match_Processor::PreProcess();
		
	}
}

Cl_Main::main();