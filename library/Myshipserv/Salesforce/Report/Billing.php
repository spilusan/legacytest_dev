<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Myshipserv_Salesforce_Report_Billing extends Shipserv_Object
{
	const MEMCACHE_TTL = 86400;
	
	protected $data = array();
	protected $billingReport = array();

	const
		RATE_LABEL_STANDARD    = 'Standard',
		RATE_LABEL_TARGET      = 'Active Promo',
		RATE_LABEL_IMPRESSIONS = 'Targeted Imp'
	;

	/**
	 * @var bool
	 */
	protected $customPeriodMode = false;

	/**
	 * @var bool
	 */
	protected $test = false;

	/**
	 * @var null|SforceEnterpriseClient
	 */
	protected $sfConnection = null;

	/**
	 * @var null|SoapClient
	 */
	protected $soapClient = null;

	/**
	 * @var LoginResult|null
	 */
	protected $loginObj = null;

	/**
	 * @var null|Zend_Db_Adapter_Oracle
	 */
	protected $db = null;

	/**
	 * @var string
	 */
	protected $month = null;

	/**
	 * @var string
	 */
	protected $year = null;

	/**
	 * @var array
	 */
	protected $period = array();

	/**
	 * @var array
	 */
	protected $periodForCSV = array();

	/**
	 * @var Myshipserv_Logger_File|null
	 */
	protected $logger = null;

	/**
	 * @var null|Shipserv_Adapters_Salesforce
	 */
	protected $adapterSF = null;

	/**
	 * @var null|string
	 */
	protected $sfObject = null;

	/**
	 * @var bool
	 */
	protected $randomiseBillingReportValue = false;

	public function __construct($month, $year, $randomiseBillingReportValue = false)
	{
		// SF related
		$credentials = Myshipserv_Config::getSalesForceCredentials();
		$this->sfConnection = new SforceEnterpriseClient();
		$this->soapClient 	= $this->sfConnection->createConnection($credentials->wsdl);
		$this->loginObj 	= $this->sfConnection->login($credentials->username, $credentials->password . $credentials->token);
		
		$this->db 			= Shipserv_Helper_Database::getDb();

		$this->month		= $month;
		$this->year			= $year;

		$this->period		= array(
			'start' => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)),
			'end'   => date('Y-m-t', mktime(0, 0, 0, $month, 1, $year))
		);

		$this->periodForCSV = array(
			'start' => date('Y-m-d', mktime(0, 0, 0, $month - 1, 1, $year)),
			'end'   => date('Y-m-t', mktime(0, 0, 0, $month - 1, 1, $year))
		);
		
		//$this->suppliers	= Shipserv_Supplier::getSuppliersOnValueBasedPricing();
		$this->logger 	 = new Myshipserv_Logger_File('salesforce-monthly-billing-report');
		$this->adapterSF = new Shipserv_Adapters_Salesforce();
		$this->sfObject	 = (Myshipserv_Config::isInProduction() ? "Value_event__c" : "Value_Events_Test__c");

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

	/**
	 * @author  Yuriy Akopov
	 * @date    2016-03-23
	 * @story   S15989
	 *
	 * @throws Exception
	 */
	public function generateAndEmailCsv() {
		// build legacy CSV (before Active Promotion changes)
		$timeStart = microtime(true);
		$csvLegacy = $this->prepareCSVForUpload();
		$elapsedLegacy = microtime(true) - $timeStart;

		// build Active Promotion aware CSV
		$timeStart = microtime(true);
		$csvActivePromotion = $this->prepareActivePromotionCsv();
		$elapsedActivePromotion = microtime(true) - $timeStart;

		$validationErrors = $this->validateLegacyVsActivePromotion($csvLegacy, $csvActivePromotion);

		// email legacy and Active Promotion value event files for evaluating
		$mail = new Myshipserv_SimpleEmail();
		$mail->setSubject("Value Events report in " . Myshipserv_Config::getEnv());

		$supplierIds = $this->getSupplierIds();
		$mail->setBody(implode("\n", array(
			"Please review the legacy and Active Promotion value events reports attached.",
			"",
			"Legacy report generated in " . round($elapsedLegacy, 2) . " sec",
			"Active promotion report generated in " . round($elapsedActivePromotion, 2) . " sec",
			"",
			"Supplier IDs included: " . (empty($supplierIds) ? "all contracted" : implode(", ", $supplierIds)),
			"Period covered: " . $this->periodForCSV['start'] . " - " . $this->periodForCSV['end'],
		)));

		if (empty($validationErrors)) {
			$validationMessage = "Total GMV and impression numbers in legacy and Active Promotion CSVs tally.";
		} else {
			$validationMessage = "There are differences in total GMV and impression numbers between CSVs:\n\n";

			foreach ($validationErrors as $tnid => $errors) {
				foreach ($errors as $errMsg) {
					$validationMessage .= "Supplier " . $tnid . "\t" . $errMsg . "\n";
				}
			}
		}

		$mail->setBody($mail->getBody() . "\n\n" . $validationMessage);

		$mail->addAttachment($csvLegacy, 'text/csv');
		$mail->addAttachment($csvActivePromotion, 'text/csv');

		// adding log as an attachment as well
		$mail->addAttachment($this->logger->getFilename(), 'text/plain');

		$mail->send(Myshipserv_Config::getSalesForceSyncReportEmail(), basename(__FILE__));
	}


	public function process()
	{
		if (!$this->isTestTransaction()) {
			$this->removeExistingRecords(); // removed Value Events from SalesForce
		}
			
		// pulls suppliers that are currently contracted from SalesForce along with their contract dates
		$this->processAccount();

		// getting the value event based on the startDate of the contract
		// if it starts in the middle of the month
		$this->getBillingReportForGivenSupplier();

		$this->generateAndEmailCsv();

		if (!$this->isTestTransaction()) {
			$this->uploadToSF();    // proceed with the upload of if requested
		}
	}


	/**
	 * Compares legacy and Active Promotion CSV
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-23
	 * @story   S15989
	 *
	 * @param   string  $pathLegacy
	 * @param   string  $pathActivePromotion
	 *
	 * @return  array
	 */
	protected function validateLegacyVsActivePromotion($pathLegacy, $pathActivePromotion) {
		// read legacy CSV content
		$csvLegacy = fopen($pathLegacy, "r");

		$skippedHeader = false;
		$supplierLegacy = array();

		while ($row = fgetcsv($csvLegacy)) {
			if (!$skippedHeader) {
				$skippedHeader = true;
				continue;
			}

			$tnid = (int) $row[8];
			$gmv  = (float) $row[2];
			// $impressions = (int) $row[5];

			$supplierLegacy[$tnid] = array(
				'gmv' => $gmv
				// 'impressions' => $impressions
			);
		}

		// read Active Promotion CSV content
		$csvActivePromotion = fopen($pathActivePromotion, "r");

		$skippedHeader = false;
		$supplierActivePromotion = array();

		while ($row = fgetcsv($csvActivePromotion)) {
			if (!$skippedHeader) {
				$skippedHeader = true;
				continue;
			}

			$tnid = (int) $row[7];
			if (!array_key_exists($tnid, $supplierActivePromotion)) {
				$supplierActivePromotion[$tnid] = array(
					'gmv' => 0
					// 'impressions' => 0
				);
			}

			$gmv = (float) $row[2];
			// $impressions = (int) $row[5];

			$supplierActivePromotion[$tnid]['gmv'] += $gmv;
			// $supplierActivePromotion[$tnid]['impressions'] += $impressions;
		}

		// loop through Active Promotion CSV and check the values against the legacy ones for the same supplier
		$errors = array();

		foreach ($supplierActivePromotion as $tnid => $stats) {
			if (!in_array($tnid, array_keys($supplierLegacy))) {
				if (!array_key_exists($tnid, $errors)) {
					$errors[$tnid] = array();
				}

				$errors[$tnid][] = "Only found in Active Promotion CSV";
			}

			// identical double values still fail here - floating point issue? converting to string...
			if (((string) $supplierLegacy[$tnid]['gmv']) !== ((string) $stats['gmv'])) {
				if (!array_key_exists($tnid, $errors)) {
					$errors[$tnid] = array();
				}

				$errors[$tnid][] = "GMV different: " . $supplierLegacy[$tnid]['gmv'] . " in the legacy CSV vs. " . $stats['gmv'] . " in the new one";
			}

			// impressions are excluded from the Active Promotion CSV following James' request on 2016-04-07 (Yuriy Akopov)
			/*
			if ($supplierLegacy[$tnid]['impressions'] !== $stats['impressions']) {
				if (!array_key_exists($tnid, $errors)) {
					$errors[$tnid] = array();
				}

				$errors[$tnid][] = "Impressions number different: " . $supplierLegacy[$tnid]['impressions'] . " in legacy CSV vs. " . $stats['impressions'] . " in the new one";
			}
			*/
		}

		foreach ($supplierLegacy as $tnid => $stats) {
			if (!in_array($tnid, array_keys($supplierActivePromotion))) {
				if (!array_key_exists($tnid, $errors)) {
					$errors[$tnid] = array();
				}

				$errors[$tnid][] = "Only found in legacy CSV";
			}
		}

		return $errors;
	}

	/**
	 * Collects value events for a list of suppliers (list comes from suppliers having a contract in SalesForce)
	 *
	 * Refactored and somewhat reworked by Yuriy Akopov on 2016-03-07 as a part of S15989, but the whole thing
	 * should be purged and re-written when we have enough time
	 */
	public function getBillingReportForGivenSupplier(){
		if (!is_array($this->data)) {
			throw new Myshipserv_Salesforce_Exception("No suppliers prepared to collect value events for");
		}

		$this->logger->log("There are " . count($this->data) . " contracted suppliers found in SF");
		$this->logger->log("Getting billing report for those suppliers for Report Service");

		foreach($this->data as $tnid => $row) {
			$row['tnid'] = $tnid;

			$report = false;

			try {
				$supplier = Shipserv_Supplier::getInstanceById($row['tnid'], "", true);
				if (strlen($supplier->tnid) === 0) {
					throw new Exception("Supplier " . $row['tnid'] . " is not found in the database");
				}

				// if it's in DEV, then store the report into memcache
				if (Myshipserv_Config::isInDevelopment()) {
					$key = "billingReportxForSupplier_" . $supplier->tnid . "_for_" . $this->month . "-" . $this->year;

					if ($memcache = $this::getMemcache()) {
						if ($cachedResult = $memcache->get($key)) {
							$report = $cachedResult;
						}
					}
				}

				if (!$report) {
					$report = $supplier->getValueBasedEventForBillingReport($this->month, $this->year);

					if (Myshipserv_Config::isInDevelopment() and $memcache) {
						$memcache->set($key, $report, false, self::MEMCACHE_TTL);
					}
				}
				
				unset($report['supplier']);
								
				if (
					$report['gmv']               != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED and
					$report['unactioned']        != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED and
					$report['uniqueContactView'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED and
					$report['searchImpression']  != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED
				) {
					$report['gmv'] 					= ($report['gmv'] == Shipserv_Report_MonthlyBillingReport::NO_DATA) ? 0 : $report['gmv'];
					$report['unactioned'] 			= ($report['unactioned'] == Shipserv_Report_MonthlyBillingReport::NO_DATA) ? 0 : $report['unactioned'];
					$report['searchImpression'] 	= ($report['searchImpression'] == Shipserv_Report_MonthlyBillingReport::NO_DATA) ? 0 : $report['searchImpression'];
					$report['uniqueContactView'] 	= ($report['uniqueContactView'] == Shipserv_Report_MonthlyBillingReport::NO_DATA) ? 0 : $report['uniqueContactView'];
						
					$this->billingReport[$supplier->tnid] = $report;

					$this->logger->log("- billing report: " . $supplier->tnid . " found");
				} else {
					$this->logger->log("- billing report: " . $supplier->tnid . " is NOT found");
				}
				
			} catch(Exception $e) {
				$this->logger->log("- warning: " . $supplier->tnid . " is NOT in VBP pricing - " . $e->getMessage);
			}
		}
	}
	
	public function getSupplierIds() {
		if ($this->suppliers == null) return null;
		return array_keys($this->suppliers);
	}
	
	/**
	 * firstly get rate for all VBP customers
	 */
	public function processAccount()
	{
		$app = new Myshipserv_Salesforce_ValueBasedPricing_Rate;
		
		// if it's in DEV, then store the report into memcache
		if(in_array($_SERVER['APPLICATION_ENV'], array('development-production-data', 'development')) )
		{
			$key = "Myshipserv_Salesforce_Report_Billing::processAccount_". "_for_" . $this->month . "-" . $this->year;
			$memcache = $this::getMemcache();
			if( $memcache )
			{
				$result = $memcache->get($key);
			}
				
			if( !$memcache || !$result)
			{
				$result = $app->pullVBPSupplier($this->logger, $this->getSupplierIds());
				$memcache->set($key, $result, false, self::MEMCACHE_TTL);
			}
			
			$report = $result;	
		}
		else
		{
			$report = $app->pullVBPSupplier($this->logger, $this->getSupplierIds());
		}
		$this->data = $report;		
		
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
			//$endOfMonth = str_replace("-01", "-" . cal_days_in_month(CAL_GREGORIAN, $tmp[1], $tmp[0]), $this->periodForCSV['start']);
			
			$endOfMonth = $tmp[0] . "-" . $tmp[1] . "-" . cal_days_in_month(CAL_GREGORIAN, $tmp[1], $tmp[0]);
			
			
			$soqlWhere[] .= "(c.StartDate <= " . $this->periodForCSV['start'] . " OR c.StartDate <= " . $endOfMonth . ")" ;
			$soqlForRateWhere[] .= "(Valid_from__c <= " . $this->periodForCSV['start'] . " OR Valid_from__c <= " . $endOfMonth . ")";				
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

	/**
	 * Refactored by Yuriy on 2016-03-18
	 *
	 * @param string $postfix
	 *
	 * @return string
	 */
	public function getCsvFilename($postfix = '') {
		$bits = array(
			"sf-billing",
			(($this->customTnid === true) ? "custom" : "all"),
			'supplier',
			$this->year,
			str_pad($this->month, 2, "0", STR_PAD_LEFT)
		);

		if (strlen($postfix)) {
			$bits[] = $postfix;
		}

		return "/tmp/" . implode('-', $bits) . ".csv";
	}

	/**
	 * Unlike legacy prepareCSVUpload(), builds a CSV in a new format - with multiple rows allowrd per supplier,
	 * GMV grouped by the applied rate, separate row for non-GMV figures etc.
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-18
	 * @story   S15989
	 *
	 * @throws  Exception
	 */
	public function prepareActivePromotionCsv() {
		$csvFilename = $this->getCsvFilename();
		$this->logger->log("Preparing to build a legacy CSV at " . $csvFilename);

		if (($csv = fopen($csvFilename, 'w')) === false) {
			throw new Exception("Failed to create " . $csvFilename);
		}

		$headers = array(
			'Period_start__c',
			'Period_end__c',

			'Gross_Merchandise_Value__c',

			// 'ShipServ_Rate_ID__c',
			'Rate_Set_Type__c',
			'Rate_value__c',

			// 'Targeted_impressions__c',

			'Rate__c',
			'TransactionAccount__c'
		);

		if ($this->isTestTransaction()){
			$headers[] = 'TNID';
		}
		fputcsv($csv, $headers);

		$startArray = explode("-", $this->periodForCSV['start']);

		foreach ((array) $this->billingReport as $supplierId => $reportServiceData) {
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, "", true);
			if (strlen($supplier->tnid) === 0) {
				$this->logger->log("Supplier " . $supplierId . " failed to instantiate when building a CSV");
				continue;
			}

			// write GMV rows which potentially might be multiple
			$gmvIntervals = $this->getSupplierBillableGmvByRate($supplierId);
			foreach ($gmvIntervals as $interval) {
				$rateGmvRow = array(
					// time interval during which the rate in the row was applicable
					$interval['start']->format('Y-m-d'),
					$interval['end']->format('Y-m-d'),
					// GMV to be charged at the listed rate
					round($interval['gmv'], 2, PHP_ROUND_HALF_DOWN),
					// rate to be applied to the GMV
					// $interval['rateId'],
					$interval['rateType'],
					$interval['rateValue'],
					// account information
					//0,
					$this->data[$supplierId]['rateId'],
					$this->data[$supplierId]['accountId']

				);

				if ($this->isTestTransaction()) {
					$rateGmvRow[] = $supplierId;
				}

				fputcsv($csv, $rateGmvRow);
			}

			if (count($gmvIntervals) === 0) {
				// supplier has 0 GMV but is still under contract and should be reflected
				$transitionDate = $supplier->getVBPTransitionDate();
				if (
					!is_null($transitionDate) and
					($startArray[1] == $transitionDate->format("m")) and
					($startArray[0] == $transitionDate->format("Y"))
				) {
					$startDate = $transitionDate->format("Y-m-d");
				} else {
					$startDate = $this->periodForCSV['start'];
				}

				$rateObj = new Shipserv_Supplier_Rate($supplierId);
				try {
					// Yuriy Akopov:
					// @todo: to be fixed in the future as it will return current rate even if the report is run for a month in the past!
					// @todo: but this is being added as an urgent fix on vacations so should work for one report we need right now
					$curRate = $rateObj->getRate();
					$rateValue = $curRate[Shipserv_Supplier_Rate::COL_RATE_STANDARD];
				} catch (Shipserv_Supplier_Rate_Exception $e) {
					// should not really happen for contracted suppliers, mmmkay?
					$rateValue = $supplier->monetisationPercent;
				}

				$zeroGmvRow = array(
					$startDate,
					$this->periodForCSV['end'],

					0,

					self::RATE_LABEL_STANDARD,
					$rateValue,

					$this->data[$supplierId]['rateId'],
					$this->data[$supplierId]['accountId']
				);

				if( $this->isTestTransaction()) {
					$zeroGmvRow[] = $supplierId;
				}

				fputcsv($csv, $zeroGmvRow);
			}

			// targeted impressions row removed on James' request on 2016-04-07
			/*
			// write a row with non-GMV stats and their own interval
			$transitionDate = $supplier->getVBPTransitionDate();
			if (
				!is_null($transitionDate) and
				($startArray[1] == $transitionDate->format("m")) and
				($startArray[0] == $transitionDate->format("Y"))
			) {
				$startDate = $transitionDate->format("Y-m-d");
			} else {
				$startDate = $this->periodForCSV['start'];
			}

			$nonGmvRow = array(
				$startDate,
				$this->periodForCSV['end'],
				0,
				//0,
				self::RATE_LABEL_IMPRESSIONS,
				0,
				$reportServiceData['searchImpression'],
				$this->data[$supplierId]['rateId'],
				$this->data[$supplierId]['accountId']
			);

			if( $this->isTestTransaction()) {
				$nonGmvRow[] = $supplierId;
			}

			// fputcsv($csv, $nonGmvRow);
			*/
		}

		$this->logger->log("Content of CSV to be uploaded: ", file_get_contents($csvFilename), true);

		fclose($csv);
		chmod($csvFilename,  0666); // legacy bit, not sure why this is needed (Yuriy)

		return $csvFilename;
	}

	/**
	 * CSV in legacy format
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function prepareCSVForUpload() {
		$csvFilename = $this->getCsvFilename('legacy');
		$this->logger->log("Preparing legacy CSV to be uploaded at " . $csvFilename);

		if (($tmpCSV = fopen($csvFilename, 'w')) === false) {
			throw new Exception("Failed to create " . $csvFilename);
		}

		$headers = array(
			'Period_start__c',
			'Period_end__c',
			'Gross_Merchandise_Value__c',
			'Unactioned_RFQs__c',
			'Unique_contact_views__c',
			'Targeted_impressions__c',
			'Rate__c',
			'TransactionAccount__c'
		);
		
		if( $this->isTestTransaction()){
			$headers[] = 'TNID';
		}

		fputcsv($tmpCSV, $headers);

		$startArray = explode("-", $this->periodForCSV['start']);
		foreach ((array) $this->billingReport as $tnid => $br) {

			$supplier = Shipserv_Supplier::getInstanceById($tnid, "", true);
			if (strlen($supplier->tnid) === 0) {
				$this->logger->log("Supplier " . $tnid . " failed to instantiate when building a CSV");
				continue;
			}

			$transitionDate = $supplier->getVBPTransitionDate();
			if (
				!is_null($transitionDate) and
				($startArray[1] == $transitionDate->format("m")) and
				($startArray[0] == $transitionDate->format("Y"))
			) {
				$startDate = $transitionDate->format("Y-m-d");
			} else {
				$startDate = $this->periodForCSV['start'];
			}

			$data = array(
				$startDate,
				$this->periodForCSV['end'],
				$br['gmv'],
				$br['unactioned'],
				$br['uniqueContactView'],
				$br['searchImpression'],
				$this->data[$tnid]['rateId'],
				$this->data[$tnid]['accountId']
			);

			if( $this->isTestTransaction()) {
				$data[] = $tnid;
			}
	
			fputcsv($tmpCSV, $data);
		}
	
		$this->logger->log("Content of CSV to be uploaded: ", file_get_contents($csvFilename), true);

		fclose($tmpCSV);
		chmod($csvFilename,  0666);
		
		return $csvFilename;
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

	/**
	 * Returns billable GMV for the provided date interval
	 *
	 * A modified version of the query from Report Service with the difference that is groups GMV by rate
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-18
	 * @story   S15989
	 *
	 * @param   int         $supplierId
	 * @param   DateTime    $dateStart
	 * @param   DateTime    $dateEnd
	 *
	 * @return  array
	 */
	protected function getBilledIntervalCurrentGmv($supplierId, DateTime $dateStart, DateTime $dateEnd) {
		$params = array(
			'tnid'      => $supplierId,
			'lowerDate' => $dateStart->format('Ymd'),
			'upperDate' => $dateEnd->format('Ymd')
		);

		$sql = "
			SELECT
			  -- ord_sbr_id,
			  ord_sbr_rate_std,
			  ord_sbr_rate_value,
			  NVL(SUM(gmv), 0) AS gmv
			FROM
			  (
			    -- easy part - original new orders with no replacements
			    SELECT
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(orig.ord_total_cost_discounted_usd), 0) AS gmv
			    FROM
			      billable_po_orig orig
			      JOIN ord ON
			        ord.ord_internal_ref_no = orig.ord_internal_ref_no
			    WHERE
			      orig.ord_submitted_date BETWEEN TO_DATE(:lowerDate , 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999
			      AND orig.spb_branch_code = :tnid
			      -- orders that have no replacements in the billed time interval
			      AND NOT EXISTS(
			        SELECT /*+UNNEST*/
			          NULL
			        FROM
			          billable_po_rep
			        WHERE
			          ord_submitted_date BETWEEN TO_DATE(:lowerDate , 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999
			          AND spb_branch_code = :tnid
			          AND ord_original_no = orig.ord_internal_ref_no
			      )
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value

			    UNION ALL

			    -- order replacements in the billed interval
			    SELECT
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(rep.ord_total_cost_discounted_usd), 0) AS gmv
			    FROM
			      billable_po_rep rep
			      JOIN ord ON
			        ord.ord_internal_ref_no = rep.ord_internal_ref_no
			    WHERE
			      -- only the most recent replacements in the chain, if there were more than one for the same order
			      rep.primary_id IN (
			        SELECT /*+HASH_SJ*/
			          MAX(PRIMARY_ID)
			        FROM
			          billable_po_rep
			        WHERE
			          ord_submitted_date BETWEEN TO_DATE(:lowerDate , 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999 and
			          spb_branch_code = :tnid
			        GROUP BY
			          ord_original_no
			    )
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value
			  )
			GROUP BY
			  -- ord_sbr_id,
			  ord_sbr_rate_std,
			  ord_sbr_rate_value
		";

		$key = implode('_', array(
			__FUNCTION__,
			md5($sql),
			md5(print_r($params, true))
		));
		$rows = $this->fetchCachedQuery($sql, $params, $key, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $rows;
	}

	/**
	 * Returns previous billed GMV which was replaced in the current interval for the provided date interval
	 *
	 * A modified version of the query from Report Service with the difference that is groups GMV by rate
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-18
	 * @story   S15989
	 *
	 * @param   int         $supplierId
	 * @param   DateTime    $dateStart
	 * @param   DateTime    $dateEnd
	 *
	 * @return  array
	 */
	protected function getBilledIntervalReplacedGmv($supplierId, DateTime $dateStart, DateTime $dateEnd) {
		$params = array(
			'tnid'      => $supplierId,
			'lowerDate' => $dateStart->format('Ymd'),
			'upperDate' => $dateEnd->format('Ymd')
		);

		$sql = "
			SELECT
			  -- ord_sbr_id,
			  ord_sbr_rate_std,
			  ord_sbr_rate_value,
			  NVL(SUM(gmv), 0) AS gmv
			FROM
			  (
			    -- original orders placed outside of billed interval
			    SELECT
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(orig.ord_total_cost_discounted_usd),0) AS gmv
			    FROM
			      billable_po_orig orig
			      JOIN ord ON
			        ord.ord_internal_ref_no = orig.ord_internal_ref_no
			    WHERE
			      orig.ord_submitted_date BETWEEN ADD_MONTHS(TO_DATE(:lowerDate , 'yyyymmdd'), -12) AND TO_DATE(:lowerDate, 'yyyymmdd') - 1/86400
			      AND orig.spb_branch_code = :tnid
			      -- there is a replacement in the billed interval
			      AND EXISTS(
			        SELECT /*+HASH_SJ*/
			          NULL
			        FROM
			          billable_po_rep
			        WHERE
			          ord_submitted_date BETWEEN TO_DATE(:lowerDate, 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999
			          AND spb_branch_code = :tnid
			          AND ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			      )
			      -- but no replacements before the billed interval
			      AND NOT EXISTS(
			        SELECT /*+UNNEST*/
			          NULL
			        FROM
			          billable_po_rep repPrev
			        WHERE
			          ord_submitted_date BETWEEN ADD_MONTHS(TO_DATE(:lowerDate , 'yyyymmdd'), -12) AND TO_DATE(:lowerDate, 'yyyymmdd') - 1/86400
			          AND spb_branch_code = :tnid
			          AND EXISTS(
			            SELECT /*+HASH_SJ*/
			              NULL
			            FROM
			              billable_po_rep repCur
			            WHERE
			              repCur.ord_submitted_date BETWEEN TO_DATE(:lowerDate, 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999
			              AND repCur.spb_branch_code = :tnid
			              AND repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
			          )
			          AND repPrev.ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			      )
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value

			    UNION ALL

			    -- replacements replaced again in the billed interval
			    SELECT
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value,
			      NVL(SUM(rep.ord_total_cost_discounted_usd), 0) AS total
			    FROM
			      billable_po_rep rep
			      JOIN ord ON
			        ord.ord_internal_ref_no = rep.ord_internal_ref_no
			    WHERE
			      rep.PRIMARY_ID IN (
			        SELECT /*+HASH_SJ*/
			          MAX(PRIMARY_ID)
			        FROM
			          billable_po_rep repPrev
			        WHERE
			          ord_submitted_date BETWEEN ADD_MONTHS(TO_DATE(:lowerDate , 'yyyymmdd'), -12) AND TO_DATE(:lowerDate, 'yyyymmdd') - 1/86400
			          AND spb_branch_code = :tnid
			          -- which were replaced in the billed interval
			          AND EXISTS(
			            SELECT /*+HASH_SJ*/
			              NULL
			            FROM
			              billable_po_rep repCur
			            WHERE
			              repCur.ord_submitted_date BETWEEN TO_DATE(:lowerDate, 'yyyymmdd') AND TO_DATE(:upperDate, 'yyyymmdd') + 0.99999
			              AND repCur.spb_branch_code = :tnid
			              AND repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
			          )
			        GROUP by ord_original_no
			      )
			    GROUP BY
			      -- ord.ord_sbr_id,
			      ord.ord_sbr_rate_std,
			      ord.ord_sbr_rate_value
			  )
			GROUP BY
			  -- ord_sbr_id,
			  ord_sbr_rate_std,
			  ord_sbr_rate_value
		";

		$key = implode('_', array(
			__FUNCTION__,
			md5($sql),
			md5(print_r($params, true))
		));
		$rows = $this->fetchCachedQuery($sql, $params, $key, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $rows;
	}

	/**
	 * Prepares raw data returned by current and replaced GMV queries for being put in the CSV / uploading
	 *
	 * @param   int     $supplierId
	 * @param   array   $rows
	 *
	 * @return  array
	 */
	protected function prepareSupplierGmvRows($supplierId, array $rows) {
		$supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
		$rateObj = new Shipserv_Supplier_Rate($supplierId);

		$gmvData = array();
		foreach ($rows as $row) {
			// commented out on 2016-04-05 by Yuriy Akopov and rate ID is no longer a part of GROUP BY
			// and is not returned (James has requested to remove it from the CSV)

			/*
			if ((strlen($row['ORD_SBR_ID']) === 0) or ($row['ORD_SBR_ID'] == 0)) {
				// fall back to supplier monetisation percent, GMV is not attributed to a multi-tiered rate
				$rateId    = null;
				$rateValue = $supplier->monetisationPercent;
				$rateType  = self::RATE_LABEL_STANDARD;

				$startDate = null;
				$endDate   = null;

			} else {
				$rate = $rateObj->getRate($row['ORD_SBR_ID']);

				$rateId    = $row['ORD_SBR_ID'];
				$rateValue = $row['ORD_SBR_RATE_VALUE'];
				$rateType  = (($row['ORD_SBR_RATE_STD'] == 1) ? self::RATE_LABEL_STANDARD : self::RATE_LABEL_TARGET);

				$startDate = $rate[Shipserv_Supplier_Rate::COL_VALID_FROM];
				$endDate  = $rate[Shipserv_Supplier_Rate::COL_VALID_TILL];
			}
			*/

			$rateValue = (float) $row['ORD_SBR_RATE_VALUE'];
			$rateType  = (($row['ORD_SBR_RATE_STD'] == 1) ? self::RATE_LABEL_STANDARD : self::RATE_LABEL_TARGET);

			$startDate = null;
			$endDate   = null;


			$gmvData[] = array(
				'start' => $startDate,
				'end'   => $endDate,

				// 'rateId'    => $rateId,
				'rateType'  => $rateType,
				'rateValue' => $rateValue,

				'gmv'   => $row['GMV']
			);
		}

		return $gmvData;
	}

	/**
	 * Returns the period in the requested month during which supplier was under contract according to rate history in the DB
	 * Only checks the current contract, so if another conract was active for the requested month in the past, it will not pick it up
	 * This is because the legacy mechanism also only follows the current contract, and so far we are following it to be able to compare
	 * GMV to be billed
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-24
	 * @story   S15989
	 *
	 * @param   int $supplierId
	 *
	 * @return  array
	 * @throws  Exception
	 */
	protected function getContractedPeriodInRequestedMonth($supplierId) {
		$monthStart = new DateTime($this->periodForCSV['start']);
		$monthEnd   = new DateTime($this->periodForCSV['end']);

		$activePromotionStartDate = Shipserv_Supplier_Rate::getActivePromotionStartDate();

		if (($activePromotionStartDate === false) or ($monthEnd < $activePromotionStartDate)) {
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
			$contractFrom = $supplier->getVBPTransitionDate();

		} else {
			$rateObj = new Shipserv_Supplier_Rate($supplierId);

			if (!$rateObj->isUnderContract()) {
				throw new Exception("Supplier " . $supplierId . " is not under contract according to DB but is contracted according to earlier SalesForce check");
			}

			$rate = $rateObj->getRate();
			$contractFrom = $rate[Shipserv_Supplier_Rate::COL_VALID_FROM];
		}


		return array(
			'start' => ($monthStart < $contractFrom) ? $contractFrom : $monthStart,
			'end'   => $monthEnd
		);
	}

	/**
	 * Returns the uninterrupted contracted interval start date looking back from the give date
	 *
	 * This is a temp method to match legacy behavior for the Active Promotion upload in order to be able to compare
	 * GMV figures produced.
	 *
	 * @param   Shipserv_Supplier_Rate  $rateObj
	 * @param   DateTime    $dateEnd
	 * @param   int         $recursionLevel
	 *
	 * @return  array
	 * @throws  Shipserv_Supplier_Rate_Exception
	 * @throws  Exception
	 */
	public function getUninterruptedIntervalForThePeriod(Shipserv_Supplier_Rate $rateObj, DateTime $dateEnd, $recursionLevel = 0) {
		// safety check
		if ($recursionLevel > 30) {
			throw new Exception("Too many recursive rates for supplier " . $rateObj->getSupplierId());
		}

		$db = Shipserv_Helper_Database::getDb();
		$select = new Zend_Db_Select($db);

		$select
			->from(
				array('sbr' => Shipserv_Supplier_Rate::TABLE_NAME),
				array(
					Shipserv_Supplier_Rate::COL_ID            => 'sbr.' . Shipserv_Supplier_Rate::COL_ID,
					Shipserv_Supplier_Rate::COL_SUPPLIER      => 'sbr.' . Shipserv_Supplier_Rate::COL_SUPPLIER,
					Shipserv_Supplier_Rate::COL_SF_SRC_TYPE   => 'sbr.' . Shipserv_Supplier_Rate::COL_SF_SRC_TYPE,
					Shipserv_Supplier_Rate::COL_SF_SRC_ID     => 'sbr.' . Shipserv_Supplier_Rate::COL_SF_SRC_ID,
					Shipserv_Supplier_Rate::COL_RATE_STANDARD => 'sbr.' . Shipserv_Supplier_Rate::COL_RATE_STANDARD,
					Shipserv_Supplier_Rate::COL_RATE_TARGET   => 'sbr.' . Shipserv_Supplier_Rate::COL_RATE_TARGET,
					Shipserv_Supplier_Rate::COL_LOCK_TARGET   => 'sbr.' . Shipserv_Supplier_Rate::COL_LOCK_TARGET,
					Shipserv_Supplier_Rate::COL_VALID_FROM    => new Zend_Db_Expr('TO_CHAR(sbr.' . Shipserv_Supplier_Rate::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
					Shipserv_Supplier_Rate::COL_VALID_TILL    => new Zend_Db_Expr('TO_CHAR(sbr.' . Shipserv_Supplier_Rate::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')")
				)
			)
			->where('sbr.' . Shipserv_Supplier_Rate::COL_SUPPLIER . ' = ?', $rateObj->getSupplierId())
			->where('sbr.' . Shipserv_Supplier_Rate::COL_VALID_FROM . ' < ' . Shipserv_Helper_Database::getOracleDateExpr($dateEnd)) // @todo: not sure about <= or < here
			->where(implode(' OR ', array(
				'sbr.' . Shipserv_Supplier_Rate::COL_VALID_TILL . ' IS NULL',
				'sbr.' . Shipserv_Supplier_Rate::COL_VALID_TILL . ' >= ' . Shipserv_Helper_Database::getOracleDateExpr($dateEnd)
			)))
			->order('sbr.' . Shipserv_Supplier_Rate::COL_VALID_FROM . ' DESC')
		;

		$row = $select->getAdapter()->fetchRow($select);
		if ($row === false) {
			throw new Shipserv_Supplier_Rate_Exception("No requested rate for supplier " . $rateObj->getSupplierId() . " for the interval ending on " . Shipserv_Helper_Database::getOracleDateExpr($dateEnd));
		}

		$record = Shipserv_Supplier_Rate::prepareRecord($row);

		try {
			$record = $this->getUninterruptedIntervalForThePeriod($rateObj, $record[Shipserv_Supplier_Rate::COL_VALID_FROM], $recursionLevel + 1);

		} catch (Shipserv_Supplier_Rate_Exception $e) {
			if ($recursionLevel === 0) {
				throw $e;   // not even one record found which was active at the requested date
			}
		}

		return $record;
	}

	/**
	 * Copied a private function from MonthlyBillingReport in an attempt to align GMV figures with the legacy CSV
	 *
	 * @todo: to be ripped out and resolved properly ;ater
	 *
	 * Calculate the previous month based on the month and year provided
	 * @param int $month
	 * @param int $year
	 * @return multitype:DateTime
	 */
	protected function getPreviousMonthPeriods($supplier, $month, $year)
	{
		//echo "INPUT: " . $month . "-" . $year . "<br />";
		$upperDate = new DateTime();
		$lowerDate = new DateTime();

		// for january
		if($month == 1)
		{
			$upperDate->setDate($year-1, $month+11, cal_days_in_month(CAL_GREGORIAN, $month+11, $year-1));
			$lowerDate->setDate($year-1, $month+11, 1);
		}
		else
		{
			$upperDate->setDate($year, $month-1, cal_days_in_month(CAL_GREGORIAN, $month-1, $year));
			$lowerDate->setDate($year, $month-1, 1);
		}

		$transitionDate = $supplier->getVBPTransitionDate();

		if( $transitionDate !== null )
		{
			//echo $lowerDate->format("d M Y") . " to ";
			//echo $upperDate->format("d M Y");
			//echo "---<br />";
			if( $transitionDate->format("d") != 1 && $transitionDate->format('m') == $lowerDate->format('m') && $transitionDate->format('Y') == $lowerDate->format('Y') )
			{
				$lowerDate->setDate($lowerDate->format('Y'), $lowerDate->format('m'), $transitionDate->format('d'));
				//echo $lowerDate->format("d M Y") . " to ";
				//echo $upperDate->format("d M Y");
			}
		}

		return array('start' => $lowerDate, 'end' => $upperDate );
	}

	/**
	 * Returns supplier's billable GMV grouped by rate so it can be presented
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-03-17
	 * @story   S15989
	 *
	 * @param   int $supplierId
	 *
	 * @return  array
	 */
	public function getSupplierBillableGmvByRate($supplierId) {
		$contractedPeriod = $this->getContractedPeriodInRequestedMonth($supplierId);
		$dateStart = $contractedPeriod['start'];
		$dateEnd   = $contractedPeriod['end'];

		/*
		try {
			$rateObj = new Shipserv_Supplier_Rate($supplierId);
			$earliestRate = $this->getUninterruptedIntervalForThePeriod($rateObj, new DateTime($this->periodForCSV['end']));

			$dateStart = ($earliestRate[Shipserv_Supplier_Rate::COL_VALID_FROM] > new DateTime($this->periodForCSV['start'])) ? $earliestRate[Shipserv_Supplier_Rate::COL_VALID_FROM] : new DateTime($this->periodForCSV['start']);
			$dateEnd = new DateTime($this->periodForCSV['end']);
		} catch (Shipserv_Supplier_Rate_Exception $e) {
			// mimicing legacy time interval as a temp fix (Yuriy) for GMV to tally
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, "", true);
			$defaultStartDate = new DateTime($this->periodForCSV['start']);

			$startArray = explode("-", $this->periodForCSV['start']);
			$transitionDate = $supplier->getVBPTransitionDate();
			if (
				!is_null($transitionDate) and
				($startArray[1] == $transitionDate->format("m")) and
				($startArray[0] == $transitionDate->format("Y"))
			) {
				$dateStart = $transitionDate;
				if ($dateStart < $defaultStartDate) {
					$dateStart = $defaultStartDate;
				}
			} else {
				$dateStart = $defaultStartDate;
			}

			$dateEnd = new DateTime($this->periodForCSV['end']);
		}
		*/

		/*
		$supplier = Shipserv_Supplier::getInstanceById($supplierId, "", true);
		$legacyDatePeriod = $this->getPreviousMonthPeriods($supplier, $this->month, $this->year);
		$dateStart = $legacyDatePeriod['start'];
		$dateEnd = $legacyDatePeriod['end'];
		*/

		// $dateStart = new DateTime($this->periodForCSV['start']);
		// $dateEnd = new DateTime($this->periodForCSV['end']);

		$timeStart = microtime(true);
		$currentGmvRows = $this->getBilledIntervalCurrentGmv($supplierId, $dateStart, $dateEnd);
		$this->logger->log("Retrieved current GMV for supplier " . $supplierId . " in " . round(microtime(true) - $timeStart, 2) . " sec");

		$timeStart = microtime(true);
		$replacedGmvRows = $this->getBilledIntervalReplacedGmv($supplierId, $dateStart, $dateEnd);
		$this->logger->log("Retrieved replaced GMV for supplier " . $supplierId . " in " . round(microtime(true) - $timeStart, 2) . " sec");

		// building rows for CSV/upload by deducting replaced GMV from the corresponding rows of the deducted GMv
		$currentGmvData  = $this->prepareSupplierGmvRows($supplierId, $currentGmvRows);
		$replacedGmvData = $this->prepareSupplierGmvRows($supplierId, $replacedGmvRows);

		$combinedGmvData = $currentGmvData;

		foreach ($replacedGmvData as $replaced) {
			$this->logger->log(
				"Looking to deduct " . $replaced['gmv'] . " GMV for supplier " . $supplierId .
				" at " . $replaced['rateType'] . "rate ID " . $replaced['rateId'] . ", rate value " . $replaced['rateValue'] .
				"..."
			);

			// looking from where to deduct - searching for the same rate ID (or the lack of it)
			foreach ($currentGmvData as $index => $current) {
				if (
					// ($current['rateId'] === $replaced['rateId']) and
					($current['rateType'] === $replaced['rateType']) and
					($current['rateValue'] === $replaced['rateValue'])
				) {
					// a current GMV row charged at the same rate was found
					$combinedGmvData[$index]['gmv'] -= $replaced['gmv'];
					$this->logger->log("...deducted from an existing row");
					continue(2);
				}
			}

			// no current GMV row from which to deduct, an new row with a negative amount should be added
			// theoretically, this should not happen in our current model because all the replacements are
			// charged at the same rate as the original
			$replaced['gmv'] *= (-1);
			$combinedGmvData[] = $replaced;
			$this->logger->log("...added as a new row");
		}

		// now align time intervals in the resulting set because rate intervals might stretch beyond the billed period
		foreach ($combinedGmvData as $index => $row) {
			if (is_null($row['start']) or ($row['start'] < $dateStart)) {
				$row['start'] = $dateStart;
			}

			if (is_null($row['end']) or ($row['end'] > $dateEnd)) {
				$row['end'] = $dateEnd;
			}

			$combinedGmvData[$index] = $row;
		}

		return $combinedGmvData;
	}
}