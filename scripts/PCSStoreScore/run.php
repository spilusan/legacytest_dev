<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

// Include lib
require_once 'lib/Common.php';
require_once 'lib/Logger.php';

class PCS_Cli
{
	private function __construct ()
	{
		// Do nothing
	}
	
	public static function run ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Update_PCS' );
		$cronLogger->log();
		
		// No max execution time
		ini_set('max_execution_time', 0);
		
		// No upper memory limit
		ini_set('memory_limit', -1);
		
		// Change working dir
		chdir(dirname(__FILE__));
		
		// Run
		Logger::log("Start");
		$o = new PCS_Score();
		
		$d = getopt('r:');
		if( isset( $r['r'] ) && $r['r'] == 'true' )
		{
			$o->resetScores();
		} 
		
		$o->run();
		
		Logger::log("Finished");
	}
}

PCS_Cli::run();
