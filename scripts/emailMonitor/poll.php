<?php

$a = $argv;


// Bootstrap Zend & app
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

// Include library
//require_once 'lib/common.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ($argv)
	{
		// No max execution time
		ini_set('max_execution_time', 0);
		
		// No upper memory limit
		ini_set('memory_limit', -1);

		$method = $argv[3];
		
		// This will download imp ression/clicks data for SIR from Google DFP
		if( $method == "match" )
		{
			$cronLogger = new Myshipserv_Logger_Cron( 'Shipserv_Match_QuoteImport' );
			$cronLogger->log();
				
			$poller = New Shipserv_Match_QuoteImport();
			$poller->poll();
		}
		else if( $method == "monitor-quote-change")
		{
			$cronLogger = new Myshipserv_Logger_Cron( 'Myshipserv_Poller_QuoteChangeMonitor' );
			$cronLogger->log();
				
			$poller = new Myshipserv_Poller_QuoteChangeMonitor();
			$poller->poll();
		}
		else
		{
			echo "-- invalid command\n";
			echo "-- usage: php run.php development|testing|production -c match|monitor-quote-change \n";
		}
	}
}

Cl_Main::main($argv);
