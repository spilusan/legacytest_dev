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
		$cronLogger = new Myshipserv_Logger_Cron( 'Shipserv_Match_BuyerAlert::processWaitingMatchQuotes' );
		$cronLogger->log();

		$result = Shipserv_Match_BuyerAlert::processWaitingMatchQuotes();
		echo print_r($result, true);
	}
}

Cl_Main::main();