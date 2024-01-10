<?php
/**
 * CLI Script to forward incoming quote to pages buyer/user
 * @author Elvir
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Shipserv_Tradenet_PagesRfqBuyerAlert::processWaitingQuotes' );
		$cronLogger->log();
		
		// Load file specified via CLI
		$result = Shipserv_Tradenet_PagesRfqBuyerAlert::processWaitingQuotes(); // Shipserv_Match_BuyerAlert::processWaitingPagesEnquiriesQuotes();
		echo print_r($result, true);
	}
}

Cl_Main::main();