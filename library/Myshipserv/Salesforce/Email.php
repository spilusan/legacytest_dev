<?php

class Myshipserv_Salesforce_Email extends Myshipserv_Salesforce
{
	
	public static function updateSalesforceEmailOptoutTable() 
	{
		$logger = new Myshipserv_Logger_File('salesforce-optout-sync');
		$logger->log("Start");
		
		$sfObj = new Shipserv_Adapters_Salesforce();
		$db = self::getDb();
		$errors = array();
		
		$soql = "SELECT email FROM contact WHERE HasOptedOutOfEmail = true AND email != ''";	
		$results = $sfObj->query($soql);
		
		$logger->log(" ". count($results) . " emails found");
		
		//Flag to continue pulling records in batches if there are large numbers of them. Unlikely with eInvoicing but there for scalabiity.
		if (count($results) > 0) 
		{
			$dropSQL = "Truncate table Salesforce_EMail_optout";
			$db->query($dropSQL);
	
			$updateSQL = "Insert into Salesforce_Email_Optout (seo_email) values (:email)";
			$unsuccessful = $success = 0;
			
			foreach ($results as $record) 
			{
				$params = array('email' => $record->Email);
				try 
				{
					$db->query($updateSQL, $params);
					$success++;
				}
				catch (Exception $ex) 
				{
					$unsuccessful++;
					$errors[] = $ex;
				}
			}
			$logger->log(" " . $success . " inserted, " . $unsuccessful . ", failed. reasons " . implode(",", $errors));
			$logger->log("End");
				
		}
	}
	
	public static function getEmailOfAllAccountsFromSalesforce()
	{
		
		ini_set('memory_limit', '512M');
		$soql = "Select Id, TNID__c, (Select FirstName, LastName, Email From Contacts WHERE Email!='') from Account where TNID__c > 0 AND DO_NOT_CALL__c=false";
		$objSalesforce = new Shipserv_Adapters_Salesforce();
		
		$results = $objSalesforce->query($soql);
		$emailList = array();
		
		foreach ($results as $result) {
			if (isset($result->Contacts->records)) {
				foreach ($result->Contacts->records as $contact) {
					$arrDetails = array('tnid' => $result->TNID__c, 'email' => $contact->Email);
					$emailList[] = $arrDetails;
				}
			}
		}
		unset($results);
		ini_set('memory_limit', '138M');
		
		return $emailList;
	}
}