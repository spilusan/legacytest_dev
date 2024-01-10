<?php

// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

// Include library
require_once 'lib/common.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		// Check number of CLI parameters
		if ($GLOBALS['argc'] != 3)
		{
			echo "Usage: "; // todo
			exit(1);
		}
		
		// Load file specified via CLI
		$fl = new Cl_FileLoader(new Cl_ModelFacade($GLOBALS['application']->getBootstrap()->getResource('db')));
		$fl->loadFile($GLOBALS['argv'][2]);
	}
}

Cl_Main::main();
