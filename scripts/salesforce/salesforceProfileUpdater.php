<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main
{
	public static function main ()
	{
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_Update_Profile_Records' );
		$cronLogger->log();
		
		$result = Myshipserv_Salesforce_Supplier::updateProfileRecords();
		echo $result;
	}
}

Cl_Main::main();
