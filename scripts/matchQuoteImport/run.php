<?php

// Bootstrap Zend & app
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';


class Cl_Main
{
	/**
	 * Main entry point for script
 	 */
 	public static function main ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Shipserv_Match_QuoteImport::Poll' );
		$cronLogger->log();
		
		$poller = New Shipserv_Match_QuoteImport();
		$poller->poll();
	}
}

Cl_Main::main();

