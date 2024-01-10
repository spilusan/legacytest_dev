<?php
// test
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		$logger = new Myshipserv_Logger;
		
		$logger->log("Start");
		
		$logger->log("  Myshipserv_Salesforce_Supplier::updateProfileRecordsWithAccountOwner()");
		$result = Myshipserv_Salesforce_Supplier::updateProfileRecordsWithAccountOwner();
		echo print_r($result, true);
		
		$logger->log("  Myshipserv_Salesforce_ContractedVessel");
		$app = new Myshipserv_Salesforce_ContractedVessel();
		$result = $app->start();
		echo print_r($result, true);
		
		$logger->log("  Myshipserv_Salesforce_Supplier::updateSuppliersWithEInvoicing");
		$result = Myshipserv_Salesforce_Supplier::updateSuppliersWithEInvoicing();
		echo print_r($result, true);
		
		$logger->log("  Myshipserv_Salesforce_Email::updateSalesforceEmailOptoutTable");
		$result = Myshipserv_Salesforce_Email::updateSalesforceEmailOptoutTable();
		
		$logger->log("  Myshipserv_Salesforce_HotScore::updateHotscoreEmaiLinks");
		$result = Myshipserv_Salesforce_HotScore::updateHotscoreEmaiLinks();
		echo $result;
		
		$logger->log("  Myshipserv_Salesforce_Supplier::updateBuyerSupplierBranchWithSalesforceId");
		$result = Myshipserv_Salesforce_Supplier::updateBuyerSupplierBranchWithSalesforceId();
		echo $result;
		
		$logger->log("  Myshipserv_Salesforce_Report_Billing::upload");
		$job = new Myshipserv_Salesforce_Report_Billing(date("m"),date("Y"), false);
		$job->upload();
		
		$logger->log("  Myshipserv_Salesforce_Supplier::updateSuppliersPagesProfileLink");
		$result = Myshipserv_Salesforce_Supplier::updateSuppliersPagesProfileLink(false);
		echo $result;
		
		$logger->log("  Myshipserv_Salesforce_Supplier::updateTradenetKPIs");
		$result = Myshipserv_Salesforce_Supplier::updateTradenetKPIs(false);
		echo $result;
		
		$logger->log("  Myshipserv_Salesforce_Supplier::pullVBPTransitionDate");
		$app = new Myshipserv_Salesforce_Supplier();
		$result = $app->pullVBPTransitionDate(true);
		$result = $app->pullVBPTransitionDate(false);
		
		// commented out because it's not being used in PRODUCTION
		//$logger->log("  Myshipserv_Salesforce_Supplier::updatePagesRFQsKpis");
		//$result = Myshipserv_Salesforce_Supplier::updatePagesRFQsKpis(false);
		//echo $result;
		
		// commented out because it's not being used in PRODUCTION
		//$logger->log("  Myshipserv_Salesforce_Supplier::updateProfileRecords");
		//$result = Myshipserv_Salesforce_Supplier::updateProfileRecords();
		//echo $result;
		
		
		$logger->log("End");
	}
}

Cl_Main::main();
