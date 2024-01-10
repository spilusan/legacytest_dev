<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Myshipserv_Salesforce_Email::updateSalesforceEmailOptoutTable' );
		$cronLogger->log();
		
		
		// Load file specified via CLI
		$result = Myshipserv_Salesforce_Email::updateSalesforceEmailOptoutTable();
	}
}

Cl_Main::main();