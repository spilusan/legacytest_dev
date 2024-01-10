<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 *
 * Reworked by Yuriy Akopov on 2016-08-03, S17177 in order to consolidate billable GMV calculation logic
 */
class Myshipserv_Salesforce_Report_Billing_Supplier extends Myshipserv_Salesforce_Base
{
	const MEMCACHE_TTL = 86400;

	/**
	 * @var array
	 */
	protected $data = array();

	/**
	 * @var array
	 */
	protected $billingReport = array();

	/**
	 * @var string
	 */
	protected $accountId = null;

	/**
	 * @var array
	 */
	protected $salesForceAccount = array();

	/**
	 * @var DateTime
	 */
	protected $periodStart = null;

	/**
	 * @var DateTime
	 */
	protected $periodEnd = null;

	/**
	 * @var Myshipserv_Logger_File|null
	 */
	protected $logger = null;

	/**
	 * Myshipserv_Salesforce_Report_Billing_Supplier constructor.
	 */
	public function __construct()
	{
		$this->logger = new Myshipserv_Logger_File('salesforce-monthly-billing-report-supplier');

		$this->initialiseConnection();  // @todo: can be bumped down the parent class in all child classes
	}

	/**
	 * Validates the date components as they are supplier separately from the legacy code
	 * Returns DateTime or throws an exception if invalid
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-08-03
	 * @story   S17177
	 *
	 * @param   int $month
	 * @param   int $year
	 *
	 * @return  DateTime
	 * @throws  Exception
	 */
	protected function _getDateFromMonthAndYear($month, $year)
	{
		if (!is_numeric($month) or !is_numeric($year)) {
			throw new Exception("Month or year supplier are not numeric");
		}

		if (strlen($month) === 1) {
			$month = '0' . $month;
		}

		$dateStr = $year . '-' . $month . '-01';
		try {
			$date = new DateTime($dateStr);
		} catch (Exception $e) {
			throw new Exception("Failed to convert the date: " . $dateStr);
		}

		return $date;
	}
	
	/**
	 * Sets the first month included in the considered period
	 *
	 * @param   int     $month
	 * @param   int     $year
	 *
	 * @throws  Exception
	 */
	public function setPeriodStart($month, $year)
	{
		$dateFrom = $this->_getDateFromMonthAndYear($month, $year);

		if ($this->periodEnd) {
			if ($dateFrom >= $this->periodEnd) {
				throw new Exception("Start date should be set before the end date");
			}
		}

		$this->periodStart = $dateFrom;
	}

	/**
	 * Received the last month included in the considered period, sets the next month as where to stop
	 *
	 * @param   int $month
	 * @param   int $year
	 *
	 * @throws  Exception
	 */
	public function setPeriodEnd($month, $year)
	{
		$dateTo = $this->_getDateFromMonthAndYear($month, $year);
		$dateTo->modify('+1 month');

		if ($this->periodStart) {
			if ($this->periodStart >= $dateTo) {
				throw new Exception("Start date should be set before the end date");
			}
		}

		$this->periodEnd = $dateTo;
	}

	/**
	 * Assigns SalesForce ID
	 *
	 * @param   string  $id
	 * @throws  Exception
	 */
	public function setSFAccountId($id)
	{
		if (!self::validateSalesForceId($id)) {
			throw new Exception($id . " doesn't not looks like a valid SalesForce ID");
		}

		$this->accountId = $id;
	}
	
	/**
	 * Retrieves value events for the given supplier and date interval
	 *
	 * @throws  Exception
	 */
	public function getCurrentValueEventInSalesforce()
	{
		if ((strlen($this->accountId) === 0) or is_null($this->periodStart) or is_null($this->periodEnd)) {
			throw new Exception("SalesForce value events query parameters not initialised");
		}

		$startDate = $this->periodStart->format('Y-m-d');
		$endDate = $this->periodEnd->format('Y-m-d');
		
		$soql = "
			SELECT
				Accounts_With_GMV__c, 
				Closing_balance__c, 
				CreatedById, 
				CreatedDate, 
				CurrencyIsoCode, 
				days_examined__c, 
				Fee_for_targeted_impressions__c, 
				Fee_for_Unactioned_RFQs__c, 
				Fee_Unique_contact_views__c, 
				Gross_Merchandise_Value__c, 
				Id, 
				IsDeleted, 
				LastModifiedById, 
				LastModifiedDate, 
				LastReferencedDate, 
				LastViewedDate, 
				Name, 
				Opening_balance__c, 
				Period_end__c, 
				Period_start__c, 
				Po_Fee__c, 
				Profile_Complete_Score__c,
				Rate_Set_Type__c,
				Rate_value__c,
				Rate__c, 
				Summary_of_fees_for_children__c, 
				SystemModstamp, 
				Targeted_impressions__c, 
				TNID__c, 
				Total_fees__c, 
				Total_Fees_Checker__c, 
				Total_fees_to_credit__c, 
				TransactionAccount__c, 
				Unactioned_RFQs__c, 
				Unique_contact_views__c 
			FROM
				Value_event__c
			WHERE 
				TransactionAccount__c = '" . $this->accountId . "'
				AND Period_start__c >= " . $startDate . "
				AND Period_start__c < " . $endDate . "
			ORDER BY Period_end__c
		";		

		$response = $this->querySf($soql);
		return $response->records;
	}

	/**
	 * Reads, remembers and returns SalesForce account data
	 *
	 * @return null|array
	 */
	public function getAccountDetail()
	{
		$soql = "
             SELECT
				Account.Id,
				Account.Tnid__c,
				Contracted_under__c,
				Account_ID__c
			FROM
				Account
			WHERE
				Account.Id = '" . $this->accountId . "'
		";
		$response = $this->querySf($soql);
		
		if (!$response->records[0]) {
			return null;
		}

		$account = $response->records[0];

		// if parent account
		if ($account->Account_ID__c != null) {
			$this->salesForceAccount['accountIdForRate'] = $account->Account_ID__c;
			$this->salesForceAccount['contractId'] = $account->Contracted_under__c;

			$soql = "
	            SELECT
					Account.Name
				FROM
					Account
				WHERE
					Account.Id = '" . $account->Account_ID__c . "'
			";

			$response = $this->querySf($soql);
			$this->salesForceAccount['parentId'] = $account->Account_ID__c;
			$this->salesForceAccount['parentName'] = $response->records[0]->Name;

		} else {
			$this->salesForceAccount['accountIdForRate'] = $account->Id;
		}

		$this->salesForceAccount['tnid'] = $account->TNID__c;
		$this->salesForceAccount['accountId'] = $this->accountId;

		return $this->salesForceAccount;
	}
	
	/**
	 * Gets the list of supplier rate sets. If account has parent account, returns the rate set of the parent account
	 *
	 * @returns array
	 * @throws Exception
	 */
	public function getRateSetFromSalesforce()
	{
		if (!array_key_exists('accountIdForRate', $this->salesForceAccount)) {
			throw new Exception("SalesForce account details not inialised");
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
						, Fee_per_Off_ShipServ_Lead__c
						, Fee_per_mile_targeted_impressions__c	
						, PO_percentage_fee__c
						, Target_PO_Fee__c
						, Target_PO_Fee_Lock_Period_Days__c
						, Fee_per_Off_ShipServ_Lead_UCV__c
						, Integrated_maintenance_fee__c
					FROM
						c.Rates__r
				)
			FROM
				Contract c
			WHERE
				" . self::_getValueBasedStatusConstraint('c.Type_of_agreement__c'). "
				AND c.Account.Id = '" . $this->salesForceAccount['accountIdForRate'] . "'				
		";
		
		$response = $this->querySf($soql);
		
		if ($response->records[0] == null ) {
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
							, Fee_per_Off_ShipServ_Lead__c
							, Fee_per_mile_targeted_impressions__c
							, PO_percentage_fee__c
							, Target_PO_Fee__c
							, Target_PO_Fee_Lock_Period_Days__c
							, Fee_per_Off_ShipServ_Lead_UCV__c
							, Integrated_maintenance_fee__c
						FROM
							c.Rates__r
					)
				FROM
					Contract c
				WHERE
					" . self::_getValueBasedStatusConstraint('c.Type_of_agreement__c') . "
					AND c.Account.Id = '" . $this->salesForceAccount['accountIdForRate'] . "'
			";
			
			$response = $this->querySf($soql);
		}
		
		$this->salesForceAccount['rates'] = $response->records[0]->Rates__r->records;
		$this->salesForceAccount['contracts'] = $response->records;
		$this->salesForceAccount['contractId'] = $response->records[0]->Id;
		
		return $this->salesForceAccount['rates'];
	}

	/**
	 * @param   string  $key
	 *
	 * @return  array|mixed
	 */
	public function getSalesForceAccountData($key = null)
	{
		if (is_null($key)) {
			return $this->salesForceAccount;
		}

		return $this->salesForceAccount[$key];
	}

	/**
	 * @param   string  $contractId
	 *
	 * @return  array|null
	 */
	public function getAccountContractedUnderContractId($contractId)
	{
		$soql = "
             SELECT
				Account.Id,
				Account.Tnid__c,
				Account.Name
			FROM
				Account
			WHERE
				Account.Contracted_under__c= '" . $contractId . "'
		";	
		
		$response = $this->querySf($soql);
		
		if ($response->records[0] == null) {
			return null;
		}

		return $response->records;
	}

	/**
	 * Returns the rate which is actual at the moment supplied amongh the retrieved ones
	 *
	 * @param   DateTime        $date
	 *
	 * @return  string|null
	 * @throws  Exception
	 */
	public function getRateIdByDate(DateTime $date)
	{
		if (!array_key_exists('rates', $this->salesForceAccount)) {
			throw new Exception("SalesForce rates are not initialised");
		}

		foreach((array) $this->salesForceAccount['rates'] as $rate) {
			$tmp = explode("-", $rate->Valid_from__c);

			$startDate = new DateTime();
			$startDate->setDate($tmp[0], (int) $tmp[1], (int) $tmp[2]);
			
			$endDate = null;
			if ($rate->Valid_to__c != null ) {
				$tmp = explode("-", $rate->Valid_to__c);
				$endDate = new DateTime();
				$endDate->setDate($tmp[0], (int) $tmp[1], (int) $tmp[2]);
			}

			if (
				($date->format('U') >= $startDate->format('U')) and
				(is_null($endDate) or ($date->format('U') < $endDate->format('U')))
			) {
				return $rate->Id;
			}
		}

		return null;
	}
	
	/**
	 * Function to query SF, it'll keep trying it if throws error
	 *
	 * @param   string  $soql
	 *
	 * @return  mixed
	 * @throws  Exception
	 */
	public function querySf($soql)
	{
		$this->sfConnection->setQueryOptions(new QueryOptions(self::QUERY_ROWS_AT_ONCE));
	
		while (true) {
			try {
				$response = $this->sfConnection->query($soql);
				return $response;

			} catch (Exception $e) {
				$this->logger->log(
				    "\tSF's not responding when querying; reason: " . $e->getMessage() .
                    " - pausing for " . Myshipserv_Salesforce_Base::REQUEST_PAUSE . "s and retry"
                );

				if ($e->getMessage() == "Could not connect to host") {
					sleep(Myshipserv_Salesforce_Base::REQUEST_PAUSE);
				} else {
					throw $e;
				}
			}
		}
	}

	/**
	 * A new method to replace getValueEventDataFromReportService() in order to use the same logic as the value events
	 * billing script
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-08-05
	 * @story   S17177
	 *
	 * @return  array
	 * @throws  Exception
	 */
	public function getValueEventsFromDb()
	{
		if (is_null($this->periodStart) or is_null($this->periodEnd)) {
			throw new Exception("Value events date interval is not initialised");
		}

		if (!array_key_exists('tnid', $this->salesForceAccount)) {
			throw new Exception("SalesForce account data in not initialised so TNID is unknown");
		}

		$eventCsvRows = array();

		// walk through the required interval, one months at a time
		$monthStart = $this->periodStart;
		while ($monthStart < $this->periodEnd) {
			$monthEnd = clone($monthStart);
			$monthEnd->modify('+1 month');

			$report = new Myshipserv_Salesforce_ValueBasedPricing_RateDb($this->logger, $monthStart, $monthEnd);
			$rows = $report->getSupplierVbpCsvRows($this->salesForceAccount['tnid'], false);

			foreach ($rows as $row) {
				$eventCsvRows[] = $row;
			}

			$monthStart = $monthEnd;
		}

		return $eventCsvRows;
	}

	/**
	 * Calculates the value events basing on the DB data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getValueEventDataFromReportService()
	{
		// $startDate = $this->period['start']['year'] . "-" . $this->period['start']['month'] . "-01";
		// $endDate = $this->period['end']['year'] . "-" . $this->period['end']['month'] . "-01";

		$startDate = $this->periodStart->format('Y-m') . '-01';
		$endDate = $this->periodEnd->format('Y-m') . '-01';

		// legacy code below

		$sql = "
		    select 
				to_char( add_months( start_date, level-1 ), 'YYYY-MM' ) mo
		    from 
			(
				select 
					date '" . $startDate . "' start_date,
		            add_months(date '" . $endDate . "', 1) end_date
		        from 
		            dual
			)
		    connect by 
		    	level <= months_between(trunc(end_date,'MM'), trunc(start_date,'MM') ) * + 1
		";

		$data = array();

		foreach ((array) $this->db->fetchAll($sql) as $row) {
			$t = explode("-", $row['MO']);

			$r = $this->getValueEventPerMonth((int) $t[1], $t[0]);

			$date = new DateTime();
			$date->setDate($t[0], (int) $t[1], 1);
			$rateId = $this->getRateIdByDate($date);

			if ($r !== false) {
				$r['rateSetId'] = $rateId;
				$r['transactingAccount'] = $this->salesForceAccount['accountId'];

				$data[] = $r;
			}
		}

		return $data;
	}
	
	protected function getValueEventPerMonth($month, $year)
	{
		$supplier = Shipserv_Supplier::getInstanceById($this->salesForceAccount['tnid'] , "", true);
		$report = $supplier->getValueBasedEventForBillingReport($month, $year);
		
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
			
			return $report;
		}
		
		return false;
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
	 * Refactored by Yuriy Akopov on 2016-08-09, S17177
	 *
	 * @param   array|string   $ids
	 *
	 * @return  array
	 * @throws  Exception
	 */
	public function deleteExistingValueEvents($ids)
	{
		if ((!is_array($ids) and (strlen($ids) === 0)) or (is_array($ids) and empty($ids))) {
			return false; // nothing to delete
		}

		if (!is_array($ids)) {
			$ids = array($ids);
		}

		foreach ($ids as $id) {
			if (!self::validateSalesForceId($id)) {
				throw new Exception($id . " doesn't not looks like a valid SalesForce ID");
			}
		}

		$soql = "
			SELECT
			    Id
			FROM
			    Value_event__c
			WHERE
			    Id IN ('" . implode("','", $ids) . "')
		";

		$options = new QueryOptions(self::QUERY_ROWS_AT_ONCE);
		$this->sfConnection->setQueryOptions($options);
		$response = $this->sfConnection->query($soql);

		$idsToBeDeleted = array();
		$errors = array();

		if ($response->size > 0) {
			$i = 0;
			$idsToBeDeleted[$i] = array();

			foreach ((array) $response->records as $result) {
				array_push($idsToBeDeleted[$i], $result->Id);

				if (count($idsToBeDeleted[$i]) - 199 == 1) {    // legacy limitation - delete no more than 200 records at once
					$i++;
					$idsToBeDeleted[$i] = array();
				}
			}

			foreach ($idsToBeDeleted as $iteration => $dataToBeDeleted) {
				$deletedIterationResult = $this->sfConnection->delete($dataToBeDeleted);
				// by Yuriy Akopov on 2016-08-26, S17901
				foreach ($deletedIterationResult as $index => $item) {
					$deletedId = $dataToBeDeleted[$index];

					if ($item->errors) {
						if (!array_key_exists($deletedId, $errors)) {
							$errors[$deletedId] = array();
						}

						foreach ($item->errors as $err) {
							$errors[$deletedId][] = array(
								'message' => $err->message,
								'code' => $err->code
							);
						}
					}
				}
			}
		}

		return $errors;
	}

	/**
	 * Refactored by Yuriy Akopov on 2016-08-09, S17177
	 *
	 * @param   string  $csvAsString
	 *
	 * @return  array
	 * @throws  Exception
	 */
	public function uploadToSF($csvAsString)
	{
		if (($tmpCsvFilename = tempnam(sys_get_temp_dir(), 'SFVLUPLOAD')) === false) {
			throw new Exception("Failed to create a temporary file for upload");
		}

		if (($tmpCsv = fopen($tmpCsvFilename, 'w')) === false) {
			throw new Exception("Failed to open temporary file " . $tmpCsvFilename);
		}

		fwrite($tmpCsv, $csvAsString);
		fclose($tmpCsv);
		
		$sfObj = new Shipserv_Adapters_Salesforce();
		$params = array(
			'operation'       => 'insert',
			'objectName'      => 'Value_event__c',
			'concurrencyMode' => 'Parallel'
		);

		$operationResults = $sfObj->bulkUpdateFromCSV($params, $tmpCsvFilename, '', null);

		return $operationResults;
	}

	/**
	 * @author  Yuriy Akopov
	 * @date    2016-08-09
	 * @story   S17177
	 *
	 * @return array
	 */
	public function getCsvHeaders()
	{
		return Myshipserv_Salesforce_ValueBasedPricing_RateDb::getCsvHeaders(false);
	}
}