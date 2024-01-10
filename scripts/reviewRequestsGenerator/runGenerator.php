<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Request_Generator_Cli
{
	private function __construct ()
	{
		// Do nothing
	}

	public static function run ()
	{
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);
		
		$cronLogger = new Myshipserv_Logger_Cron( 'Myshipserv_ReviewRequest_Generator' );
		$cronLogger->log();
		
		$generator = new Myshipserv_ReviewRequest_Generator();
		$generator->generate();
	}
}

Request_Generator_Cli::run();
