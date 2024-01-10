<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Request_Generator_Cli
{
	public static function run ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_Update_Pages_Rfqs_Kpis' );
		$cronLogger->log();
		
		$result = Myshipserv_Salesforce_Supplier::updatePagesRFQsKpis();
		echo $result;
	}
}

Request_Generator_Cli::run();