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
		// Load file specified via CLI
		
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_UpdateHotscore_Email_Links' );
		$cronLogger->log();
		
		$result = Myshipserv_Salesforce_HotScore::updateHotscoreEmaiLinks();
		echo $result;
	}
}

Cl_Main::main();