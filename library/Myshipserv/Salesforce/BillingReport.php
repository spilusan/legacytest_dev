<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 * 
 * @todo: implement reset function
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Myshipserv_Salesforce_BillingReport extends Shipserv_Object
{
	const MEMCACHE_TTL = 86400;
	
	protected $data = array();
	protected $billingReport = array();
	
	
	public function __construct($month, $year, $randomiseBillingReportValue = false)
	{
		
        $this->customPeriodMode = false;
        $this->test = false;

		// SF related
		$credentials = Myshipserv_Config::getSalesForceCredentials();
		$this->sfConnection = new SforceEnterpriseClient();
		$this->soapClient 	= $this->sfConnection->createConnection($credentials->wsdl);
		$this->loginObj 	= $this->sfConnection->login($credentials->username, $credentials->password . $credentials->token);
		
		// get SSERVDBA
		$this->db 			= Shipserv_Helper_Database::getDb();
		$this->month		= $month;
		$this->year			= $year;
		$this->period		= array("start" => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)), "end" => date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)));
		$this->periodForCSV = array("start" => date('Y-m-d', mktime(0, 0, 0, $month-1, 1, $year)), "end" => date('Y-m-t', mktime(0, 0, 0, $month-1, 1, $year)));
		
		//$this->suppliers	= Shipserv_Supplier::getSuppliersOnValueBasedPricing();
		$this->logger 		= new Myshipserv_Logger_File('salesforce-monthly-billing-report');
		$this->adapterSF 	= new Shipserv_Adapters_Salesforce();
		$this->sfObject		= ($_SERVER['APPLICATION_ENV'] == "production")?"Value_event__c":"Value_Events_Test__c";
		$this->randomiseBillingReportValue = $randomiseBillingReportValue;
		
	}
	
	public function runAsCustomPeriodMode()
	{
		$this->customPeriodMode = true;
	}
	
	public function isCustomPeriodOfTransaction()
	{
		return $this->customPeriodMode;
	}
	
	public function runAsTestTransaction()
	{
		$this->test = true;
	}
	
	public function isTestTransaction()
	{
		return $this->test;
	}
	
	public function setTnidToProcess( $companies )
	{
		$this->customTnid = true;
		
		$suppliers = array();
		foreach($companies as $id)
		{
			$supplier = Shipserv_Supplier::getInstanceById($id, "", true);
			if( $supplier->tnid !== null )
			{
				$suppliers[$id] = $supplier;
			}
		}
	
		if( count($suppliers) == 0 )
		{
			throw new Exception("Error found, we cannot find a single TNID that you specified.");
		}
	
		// this is the flag to tell other logic so it will only
		// remove records affected by given tnids and given period
		$this->suppliers = $suppliers;
	}
	
	public function process()
	{
		if( $this->isTestTransaction() == false )
		{
			$this->removeExistingRecords();
		}
			
		if( $this->processChildAccount() && $this->processParentAccount() )
		{
			// getting the value event based on the startDate of the contract
			// if it starts in the middle of the month
			$this->getBillingReportForGivenSupplier();
				
			if( $this->prepareCSVForUpload() )
			{
				// if it's a LIVE mode
				if( $this->isTestTransaction() == false )
				{
					// upload to sf
					$this->uploadToSF();
				}
			}
		}
	}	
	
	public function getBillingReportForGivenSupplier()
	{
		$this->logger->log("There are " . count($this->data) . " suppliers found in SF. ");
		$this->logger->log("Getting billing report for those suppliers");
		
		foreach($this->data as $tnid => $row)
		{
			try
			{
				$supplier = Shipserv_Supplier::getInstanceById($row['tnid'], "", true);
				
				// if it's in DEV, then store the report into memcache
				if(in_array($_SERVER['APPLICATION_ENV'], array('development-production-data', 'development')) )
				{
					$key = "billingReportxForSupplier_" . $supplier->tnid . "_for_" . $this->month . "-" . $this->year;
					$memcache = $this::getMemcache();
					if( $memcache )
					{
						$result = $memcache->get($key);
					}
					
					if( !$memcache || !$result)
					{
						$report = $supplier->getValueBasedEventForBillingReport($this->month, $this->year);
						$memcache->set($key, $report, false, self::MEMCACHE_TTL);
					}
					else
					{
						$report = $result;
					}
				}
				else
				{
					$report = $supplier->getValueBasedEventForBillingReport($this->month, $this->year);
				}
				
				unset($report['supplier']);
								
				if( $report['gmv'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED 
					&& $report['unactioned'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED 
					&& $report['uniqueContactView'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED 
					&& $report['searchImpression'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED)
				{
					unset($report['supplier']);
				
					$report['gmv'] 					= ($report['gmv']==Shipserv_Report_MonthlyBillingReport::NO_DATA)?0:$report['gmv'];
					$report['unactioned'] 			= ($report['unactioned']==Shipserv_Report_MonthlyBillingReport::NO_DATA)?0:$report['unactioned'];
					$report['searchImpression'] 	= ($report['searchImpression']==Shipserv_Report_MonthlyBillingReport::NO_DATA)?0:$report['searchImpression'];
					$report['uniqueContactView'] 	= ($report['uniqueContactView']==Shipserv_Report_MonthlyBillingReport::NO_DATA)?0:$report['uniqueContactView'];
						
					$this->billingReport[$supplier->tnid] = $report;
					
				}			
				
				if($this->billingReport[$supplier->tnid] == null )
				{
					$this->logger->log("- billing report: " . $supplier->tnid . " is not found");
				}
				else
				{
					$this->logger->log("- billing report: " . $supplier->tnid . " found");
				}
			}
			catch(Exception $e)
			{
				$this->logger->log("- warning: " . $supplier->tnid . " is not in VBP pricing - " . $e->getMessage);
			}
		}
		
		return true;
	}
	
	
	/**
	 * Processing parent account
	 */
	public function processParentAccount()
	{
		// getting detail for parentIds
		$response = $this->getSfAccounts();
		
		if ($response->size > 0)
		{
			// going through all suppliers from SF
			foreach ((array)$response->records as $r)
			{				
				$d = $this->getDataForSalesforceColumn($r);
				$this->data[$d['tnid']] = $d;
			}
		}
		
		return true;
	}
	
	/**
	 * Child account is using RateId, ContractId from their parent.
	 * But using AccountId of the transacting account (childId)
	 * 
	 * @return boolean
	 */
	public function processChildAccount()
	{
		// check db for all accounts that are contracted under different account
		$sql = "
			SELECT
				vst_spb_branch_code child_id,
				vst_contracted_under parent_id
			FROM
				vbp_transition_date
			WHERE
				vst_contracted_under IS NOT null
		";
		
		if( count($this->suppliers)>0 && array_keys($this->suppliers) > 0 )
		{
			$sql .= " AND vst_spb_branch_code IN (" . implode(",", array_keys($this->suppliers)) . ")";
		}
		
	
		foreach( (array) $this->db->fetchAll($sql) as  $row )
		{
			
			$data[ $row['CHILD_ID'] ] = $row['PARENT_ID'];
				
			$parentIds[] = $row['PARENT_ID'];
			$childIds [] = $row['CHILD_ID'];
		}
		
		// getting detail for parentIds
		$response = $this->getSfAccounts($parentIds);
		if ($response->size > 0)
		{
			// going through all suppliers from SF
			foreach ((array)$response->records as $r)
			{
				$d = $this->getDataForSalesforceColumn($r);
				$parentData[ $d['tnid'] ] = $d;
			}
		
			foreach( (array)$data as $childId => $parentId )
			{
				$this->data[$childId] = $parentData[$parentId];
				$this->data[$childId]['tnid'] = $childId;
				
				// putting accountId of the child into the list of items
				// that will be uploaded to salesforce
				$this->data[$childId]['accountId'] = $this->getSFAccountIdByTnid($childId);
			}
		}
		
		return true;
	}
	
	/**
	 * Getting SF AccountId by the TNID of the supplier
	 * @param string $tnid
	 */
	public function getSFAccountIdByTnid($tnid)
	{
		$soql = "
			SELECT
				Id
			FROM
				Account
			WHERE
				Tnid__c=" . $tnid . "
		";
	
		$this->sfConnection->setQueryOptions(new QueryOptions(Myshipserv_Salesforce_Base::QUERY_ROWS_AT_ONCE));
	
		$ok = false;
		while($ok == false)
		{
			try
			{
				$response = $this->sfConnection->query($soql);
				
				$ok = true;
			}
			catch (Exception $e)
			{
				$this->logger->log(
				    "\tError pulling contract detail for: " . $tnid . "; reason: " . $e->getMessage() .
                    " - pausing for " . Myshipserv_Salesforce_Base::REQUEST_PAUSE . "s and retry"
                );
				sleep(Myshipserv_Salesforce_Base::REQUEST_PAUSE);
				$ok = false;
			}
		}
		return $response->records[0]->Id;
	}
	
	/**
	 * Getting RateId, AccountId from the Salesforce
	 * @param unknown $result
	 * @return multitype:NULL unknown
	 */
	public function getDataForSalesforceColumn($result)
	{
		$account = $result->Account;
		$rates = $result->Rates__r->records;
		$contractId = $result->Id;
		
		// report bad things
		if( count($rates) == 0 )
		{
			$this->logger->log("\tRate not found for: " . $account->TNID__c);
		}
		
		// process rates
		foreach( (array)$rates as $rate )
		{
			$rateId = $rate->Id;
			$rateStartDate = $rate->Valid_from__c;
			$rateEndDate = $rate->Valid_to__c;
			$rateIsActive = $rate->Active_Rates__c;				
		}
		
		// check if account is contracted under different account
		$sql = "SELECT vst_contracted_under FROM vbp_transition_date WHERE vst_spb_branch_code=:tnid";
		$rows = $this->db->fetchAll($sql, array('tnid' => $tnid));
		
		// if it's contracted under different account, then find the rate id of that account
		if( $rows[0]['VST_CONTRACTED_UNDER'] != "" )
		{
			$ownTnid = $tnid;
			$tnid = $rows[0]['VST_CONTRACTED_UNDER'];
		
			// getting it's own accountId
			$response = $this->getSFAccountIdByTnid($ownTnid);
		
			if ($response->size > 0)
			{
				foreach ((array)$response->records as $result)
				{
					$childAccountId = $result->Id;
				}
			}
		}
		
		$data = array(
			"tnid" => $account->TNID__c,
			"rateId" => $rateId,
			"rateStartDate" => $rateStartDate,
			"rateEndDate" => $rateEndDate,
			"rateIsActive" => $rateIsActive,
			"accountId" => (($childAccountId !== null)?$childAccountId:$account->Id), 
			"contractId" => $contractId,
			"contractStartDate" => $result->StartDate,
			"contractEndDate" => $result->EndDate,
		);
		
		return $data;
	}

	
	/**
	 * Getting the account detail from SF
	 * @param string $tnid
	 * @return unknown
	 */
	public function getSfAccounts( $tnid = null)
	{
		if( $tnid == null && count($this->suppliers) > 0 )
		{
			$tnid = array_keys($this->suppliers);
		}

		// if tnid is specified
		if( $tnid !== null )
		{
			if( is_array($tnid) === true )
			{
				$soqlWhere[] .= "c.Account.Tnid__c IN (" . implode(",", $tnid) . ")";
			}
			else 
			{
				$soqlWhere[] .= "c.Account.Tnid__c=" . $tnid . "";
			}
		}
		
		// if user wants to process a specific period
		if( $this->isCustomPeriodOfTransaction() === true )
		{
			$tmp = explode("-", $this->periodForCSV['start']);
			$endOfMonth = $tmp[0] . "-" . $tmp[1] . "-" . cal_days_in_month(CAL_GREGORIAN, $tmp[1], $tmp[0]);
			
			// do not need to check the date of the contract - just need to check the date of the rateset
			// as requested by Mia
			//$soqlWhere[] .= "(c.StartDate <= " . $this->periodForCSV['start'] . " OR c.StartDate <= " . $endOfMonth . ")" ;
			$soqlForRateWhere[] .="
				( 
					(
						Valid_from__c <= " . $this->periodForCSV['start'] . " OR Valid_from__c <= " . $endOfMonth . "
					) 
					AND Valid_to__c != null 
					AND Valid_to__c >= " . $endOfMonth . "
				)
				OR 
				( 
					(
						Valid_from__c <= " . $this->periodForCSV['start'] . " OR Valid_from__c <= " . $endOfMonth . "
					) 
					AND Valid_to__c = null
				)
			";				
		}
		
		$soql = "
             SELECT
				c.Account.Id,
				c.Account.Tnid__c,
				c.Id,
				c.StartDate,
				c.EndDate,
				c.Type_of_agreement__c,
				(
					SELECT
						Id
						, Name
						, Valid_from__c
						, Valid_to__c
						, Active_Rates__c
					FROM
						c.Rates__r
					" . ((count($soqlForRateWhere)>0)
							? "WHERE " . implode(" AND ", $soqlForRateWhere):"") . "
				)
			FROM
				Contract c
			WHERE
				c.Type_of_agreement__c='Membership - Value based'
				" . ((count($soqlWhere)>0)
					? " AND (" . implode(" AND ", $soqlWhere) . ")":"") . "
									
		";
		$response = $this->querySf($soql);
		return $response;
	}
	
	/**
	 * Function to query SF, it'll keep trying it if throws error
	 * @param unknown $soql
	 * @return unknown
	 */
	public function querySf($soql)
	{
		$this->sfConnection->setQueryOptions(new QueryOptions(Myshipserv_Salesforce_Base::QUERY_ROWS_AT_ONCE));
		
		$ok = false;
		while($ok == false)
		{
			try
			{
				$response = $this->sfConnection->query($soql);
				$ok = true;
			}
			catch (Exception $e)
			{
				$this->logger->log(
				    "\tSF's not responding when querying; reason: " . $e->getMessage() .
                    " - pausing for " . Myshipserv_Salesforce_Base::REQUEST_PAUSE . "s and retry"
                );
				sleep(Myshipserv_Salesforce_Base::REQUEST_PAUSE);
				$ok = false;
			}
		}
		
		return $response;
	}
	
	public function getCsvFilename()
	{
		return '/tmp/sf-billing-' . (($this->customTnid===true)?"custom":"all") . '-supplier-' . $this->year . '-' . str_pad($this->month, 2, "0", STR_PAD_LEFT). '.csv';
	}
	
	public function prepareCSVForUpload()
	{
		$this->logger->log("Preparing CSV to be uploaded");
	
		$tmpCSV = fopen($this->getCsvFilename(), "w");
		
		if( $this->isTestTransaction() )
		{
			$headerForCsv = "Period_start__c,Period_end__c,Gross_Merchandise_Value__c,Unactioned_RFQs__c,Unique_contact_views__c,Targeted_impressions__c,Rate__c,TransactionAccount__c,TNID\n";
		}
		else
		{
			$headerForCsv = "Period_start__c,Period_end__c,Gross_Merchandise_Value__c,Unactioned_RFQs__c,Unique_contact_views__c,Targeted_impressions__c,Rate__c,TransactionAccount__c\n";
		}
		
		fwrite($tmpCSV, $headerForCsv);
		
		$startArray = explode("-", $this->periodForCSV['start']);
		
		
		foreach ((array) $this->billingReport as $tnid => $br)
		{
			$supplier = Shipserv_Supplier::getInstanceById($tnid, "", true);
			$transitionDate = $supplier->getVBPTransitionDate();
			
			if( $transitionDate !== null && $startArray[1] == $transitionDate->format("m") && $startArray[0] == $transitionDate->format("Y") )
			{
				$startDate = $transitionDate->format("Y-m-d");
			}
			else
			{
				$startDate = $this->periodForCSV['start'];
			}
	
			$data = array($startDate, $this->periodForCSV['end'], $br['gmv'], $br['unactioned'], $br['uniqueContactView'], $br['searchImpression'], $this->data[$tnid]['rateId'], $this->data[$tnid]['accountId']);
	
			if( $this->isTestTransaction() )
			{
				$data[] = $tnid;
			}
	
			fputcsv($tmpCSV, $data);
		}
	
		$this->logger->log("Content of CSV to be uploaded: ", file_get_contents($this->getCsvFilename()), true);
		fclose($tmpCSV);
		
		chmod($this->getCsvFilename(),  0777);
		
	
		return true;
	}
	
	public function uploadToSF()
	{
		$params = array(
			'operation' => 'insert',
			'objectName' => $this->sfObject,
			'concurrencyMode' => 'Parallel'
		);
	
		$operationResults = $this->adapterSF->bulkUpdateFromCSV($params, $this->getCsvFilename());
		$this->logger->log("Uploading CSV to salesforce: " . $this->getCsvFilename(), print_r($operationResults, true), true);
	
		return true;
	}
	
	/**
	 * Removing any valuetest event for selected month and year
	 * @todo this need to be modified as well to honor the custom period
	 */
	public function removeExistingRecords()
	{
		// delete all data
		$soql = "
			SELECT
			    Id
			FROM
			    " . $this->sfObject . "
			WHERE
			    Period_end__c=".$this->periodForCSV['end']."
		";
	
		$this->logger->log("Deleting ValueEvents data for All suppliers in: " . $this->month . "-" . $this->year . " period");
	
		$options = new QueryOptions(Myshipserv_Salesforce_Base::QUERY_ROWS_AT_ONCE);
		$this->sfConnection->setQueryOptions($options);
		$response = $this->sfConnection->query($soql);
	
		$idsToBeDeleted = array();
		$this->logger->log("Deleting: ".$response ->size . " rows");
	
		if ($response->size > 0)
		{
			$i = 0;
			$idsToBeDeleted[$i] = array();
			foreach ((array)$response->records as $result)
			{
				array_push($idsToBeDeleted[$i], $result->Id);
				if( count($idsToBeDeleted[$i]) - 199 == 1 )
				{
					$i++;
					$idsToBeDeleted[$i] = array();
				}
			}
	
			foreach($idsToBeDeleted as $iteration => $dataToBeDeleted)
			{
				$deleteResult = $this->sfConnection->delete($dataToBeDeleted);
				$this->logger->log("Delete Result: ", print_r($deleteResult, true));
			}
		}
	}
}