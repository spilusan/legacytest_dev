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
		$cronLogger = new Myshipserv_Logger_Cron( 'Myshipserv_Salesforce_Supplier::updateProfileRecordsWithAccountOwner' );
		$cronLogger->log();
		ini_set('memory_limit', '1024M');
		$result = Myshipserv_Salesforce_Supplier::updateProfileRecordsWithAccountOwner();
	}
}

Cl_Main::main();
