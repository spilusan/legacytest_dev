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
		
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_Update_Buyer_Supplier_Branch_With_SFID' );
		$cronLogger->log();
		
		$result = Myshipserv_Salesforce_Supplier::updateBuyerSupplierBranchWithSalesforceId();
		echo $result;
	}
}

Cl_Main::main();