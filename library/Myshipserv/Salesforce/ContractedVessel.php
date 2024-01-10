<?php
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Myshipserv_Salesforce_ContractedVessel 
{
	
	public function __construct()
	{
		$credentials = Myshipserv_Config::getSalesForceCredentials();

		$this->sfConnection = new SforceEnterpriseClient();
		$this->soapClient 	= $this->sfConnection->createConnection($credentials->wsdl);
		$this->loginObj 	= $this->sfConnection->login($credentials->username, $credentials->password . $credentials->token);
		$this->db 			= Shipserv_Helper_Database::getDb();

		$this->month		= $month;
		$this->year			= $year;

		$this->logger 		= new Myshipserv_Logger_File('salesforce-contracted-vessel');
		$this->adapterSF 	= new Shipserv_Adapters_Salesforce();
	}
	
	/**
	 * Main process
	 */
	public function start()
	{
		return $this->initialise();
	}
	
	/**
	 * Removing any valuetest event
	 */
	public function initialise()
	{
		$this->logger->log("Syncing No of Trading Vessel from SF to SSERVDBA.BUYER_BRANCH.BYB_NO_OF_REGISTERED_SHIPS: " . date("d-m-Y h:i:S"));
		
		// delete all data
		$soql = "
			SELECT No_of_vessels_trading__c, TNID__c FROM Account WHERE TNID__c != null AND No_of_vessels_trading__c != null
		";
		
		$options = new QueryOptions(Myshipserv_Salesforce_Base::QUERY_ROWS_AT_ONCE);
		$this->sfConnection->setQueryOptions($options);
		$response = $this->sfConnection->query($soql);
		if ($response->size > 0)
		{
			foreach ((array)$response->records as $result)
			{
				try
				{
					$sql = "
						UPDATE 
							buyer_branch 
						SET 
							BYB_NO_OF_REGISTERED_SHIPS=:totalShip
							, BYB_POTENTIAL_UNITS=:totalShip
							, byb_updated_by='SF_IMPORT'
							, byb_updated_date=SYSDATE 
						WHERE 
							byb_branch_code=:tnid
					";
					
					$this->db->query($sql, array("totalShip" => $result->No_of_vessels_trading__c, 'tnid' => $result->TNID__c));
					$this->db->commit();
					$this->logger->log("Storing: " . $result->TNID__c . " ::: " . $result->No_of_vessels_trading__c . " ships." );
					$totalImported++;
				}
				catch(Exception $e)
				{
					$this->logger->log("Failed: " . $e->getMessage());
				}
			}
			$this->logger->log("Total imported: " . $totalImported);
			return $this->logger->getBuffer();	
		}
		
	}
}