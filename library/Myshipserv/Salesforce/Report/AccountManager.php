<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly account manager so Sales can work on the right leads
 * from Salesforce.
 */

class Myshipserv_Salesforce_Report_AccountManager extends Myshipserv_Salesforce_Base
{
	const TMP_DIR = '/tmp/sf-monthly-account-manager-report';

	protected $data = array();

	public function __construct($month, $year)
	{
		ini_set("memory_limit", -1);

        $this->customPeriodMode = false;
        $this->test = false;
        $this->month		= $month;
		$this->year			= $year;
		$this->period		= array("start" => date('Y-m-d', mktime(0, 0, 0, $month, 1, $year)), "end" => date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)));
		$this->periodForCSV = array("start" => date('Y-m-d', mktime(0, 0, 0, $month-1, 1, $year)), "end" => date('Y-m-t', mktime(0, 0, 0, $month-1, 1, $year)));
		$this->logger 		= new Myshipserv_Logger_File('salesforce-monthly-account-manager-report');
		$this->adapterSF 	= new Shipserv_Adapters_Salesforce();
	}

	/**
	 * Refactored by Yuriy Akopov on 2016-08-19, DE6906
	 *
	 * Main workflow entry point
	 *
	 * @return  bool
	 */
	public function process()
	{
		$this->initialiseConnection();

		$this->logger->log("Retrieving accounts from SalesForce...");
		if ($this->getAllSFAccountIds()) {

			$this->logger->log("Retrieving data from Report Service...");
			if ($this->getDataFromReportService()) {

				$this->logger->log("Converting data to CSV...");
				if ($this->prepareCSVForUpload()) {

					if ($this->isTestTransaction() == false) {

						$this->logger->log("Uploading CSV in batches...");
						$this->uploadToSF();
						$this->logger->log("Uploaded the CSV to SalesForce");

					} else {
						$this->logger->log("Skipping uploading to SalesForce because a test run was requested");
					}
				} else {
					$this->logger->log("Failed to prepare a CSV for uploading");
					return false;
				}
			} else {
				$this->logger->log("Failed to retrieve data from Report Service");
				return false;
			}
		} else {
			$this->logger->log("Failed to retrieve accounts from SalesForce");
			return false;
		}

		$this->logger->log("End");
		return true;
	}

	/**
	 * Get SF accountId based on the TNIDs selected
	 * @return boolean
	 */
	private function getAllSFAccountIds()
	{
		$this->logger->log("getting all accountIds from SF");

		if( count($this->tnids)>0 )
		{
			foreach ( $this->tnids as $tnid )
			{
				$conditions[] = ' TNID__c=' . $tnid;
			}
			$where = implode(" OR ", $conditions);
		}
		else
		{
			$where = "TNID__c > 10000";
		}

		$soql = "SELECT Id, TNID__c FROM Account WHERE " . $where . " AND Pipelinestatus__c!='Invalid account' ORDER BY TNID__c Asc";
		$options = new QueryOptions(self::QUERY_ROWS_AT_ONCE);
		$this->sfConnection->setQueryOptions($options);
		$response = $this->sfConnection->query($soql);

		$done = false;
		$this->logger->log($response ->size . " found");

		if ($response->size > 0)
		{
			while ($done == false)
			{
				$iteration++;
				$this->logger->log(" getting more resultset from SF (Job #$iteration)");

				foreach ((array)$response->records as $result)
				{
					$this->data[$result->TNID__c] = array('Id' => $result->Id);
				}

				if ($response->done != true)
				{
					try
					{
						$response = $this->sfConnection->queryMore($response->queryLocator);
					}
					catch (Exception $e)
					{
						$this->logger->log("  [error] on getting more data: " . $e->faultstring, print_r($this->sfConnection->getLastRequest(), true));
					}
				}
				else
				{
					$done = true;
				}
			}
		}


		return true;
	}

	/**
	 * Sends an email to responsible developers in case there is an unexpected response from Report Service
	 *
	 * Was added to debug S17185 (occasional empty responses)
	 *
	 * @author  Yuriy Akopov
	 * @story   S17185
	 * @date    2016-08-19
	 *
	 * @param   Exception           $e
	 * @param   Zend_Http_Response  $response
	 * @param   string              $requestUrl
	 * @param   int                 $requestCount
	 * @param   float               $elapsed
	 */
	protected function emailReportServiceError(Exception $e, Zend_Http_Response $response = null, $requestUrl = null, $requestCount = null, $elapsed = null)
	{
		$this->logger->log("Report Service workflow error " . get_class($e) . ": " . $e->getMessage());

		$email = new Myshipserv_SimpleEmail();
		$email->setSubject("AccountManagerReport ReportService failure");

		$bodyLines = array(
			"Exception: " . get_class($e),
			"Exception message: " . $e->getMessage()
		);

		if ($response) {
			$bodyLines[] = "Report service URL: " . $requestUrl;
			$bodyLines[] = "Request time: " . $elapsed . " sec";
			$bodyLines[] = "Request number " . $requestCount . " has failed";
			$bodyLines[] = "Response status: " . $response->getStatus();
			$bodyLines[] = "";
			$bodyLines[] = "Response body:";
			$bodyLines[] = "";
			$bodyLines[] = $response->getBody();
		}

		$email->setBody(implode("\n", $bodyLines));

		$email->send(array(
			// @todo: should be in the config, but this is to keep number of files affected by hotfix at minimum
			'yakopov@shipserv.com',
			'acayanan@shipserv.com'
		));

		$this->logger->log("Emailed the error details");
	}

	/**
	 * Logging added by Yuriy Akopov on 2016-08-19, DE6906
	 *
	 * @param array $tnid
	 * @param string $startDate
	 * @param string $endDate
	 * @param string $startDate2
	 * @param string $endDate2
	 *
	 * @return  array
	 * @throws  Exception
	 */
	public function getSupplierKPI($tnid = array(), $startDate = '', $endDate = '', $startDate2 = '', $endDate2 = '')
	{
		$client = new Zend_Http_Client();

		$config = $this->getConfig();
		$requestUri = $config->shipserv->services->report->url . '/json/stats/get-supplier-kpi';
		$client->setUri($requestUri);

		$client->resetParameters();
		$client->setHeaders("Accept-Language", "en");

		if( $startDate == '' || $startDate2 == '' || $endDate == '' || $endDate2 == '' ) {
			throw new Exception("Please input two date periods");
		}

		$requestParams = array(
			'tnids'	=> (($tnid != "" && count($tnid) > 0) ? implode(",", $tnid) : ""),
			'mindate1' => $startDate,
			'maxdate1' => $endDate,
			'mindate2' => $startDate2,
			'maxdate2' => $endDate2
		);

		$requestUrl = $requestUri . '?' . http_build_query($requestParams);
		$this->logger->log("ReportService URL: " . $requestUrl);

		$client->setParameterGet($requestParams);
		$client->setMethod(Zend_Http_Client::GET);

		$maxWait = 60 * 60 * 3; // total wait is 3 hours, everything above it is suspicious
		$requestCount = 1;

		// make the first request to Report Service
		$timeStart = microtime(true);
		$response = $client->request();
		$elapsed = round(microtime(true) - $timeStart, 2);

		$data = json_decode($response->getBody(), true);

		try {
			if ($response->getStatus() != 200) {
				throw new Exception("First request to Report Service failed");
			}

			if (strlen($data['message']) === 0) {
				// cached reply straight away?
				$this->logger->log("Response from the first request in " . $elapsed . " sec, cached reply?");
				return $data;

			} else if (
				// we have requested the data first
				(strtolower($data['message']) === 'supplier kpi generation initiated.') or  // what was in the legacy code
				(strtolower($data['message']) === 'supplier kpi generation started.') or       // what is actually returned
				// another session of the script has started the calculation earlier
				(strtolower($data['message']) === 'supplier kpi not ready yet.')
			) {
				// loop until report generation is completed
				$this->logger->log("Report requested from Report Service in " . $elapsed . " sec, now looping...");

				// sleep for shorter period to allow script to switch into "not ready yet" mode...
				sleep(5);

				$delay = 60;
				$waitStart = microtime(true);

				while ((microtime(true) - $waitStart) <= $maxWait) {
					$requestCount++;

					$timeStart = microtime(true);
					$response = $client->request();
					$elapsed = round(microtime(true) - $timeStart, 2);
					$this->logger->log("Checked on Report Service, attempt " . $requestCount . ", " . $elapsed . " sec");

					if ($response->getStatus() != 200) {
						throw new Exception("Check-up request to Report Service failed");
					}

					$data = json_decode($response->getBody(), true);

					if (strtolower($data['message']) === 'supplier kpi not ready yet.') {
						sleep($delay);
						continue;

					} else if (strlen($data['message']) !== 0) {
						throw new Exception("Unexpected message received from Report Service");
					}

					// if we made it here through the checks, response is expected to contain supplier data
					return $data;
				}

				throw new Exception("Report Service didn't finish in " . $maxWait . " sec");

			} else {
				throw new Exception("Unexpected message received from Report Service");
			}

		} catch (Exception $e) {
			$this->emailReportServiceError($e, $response, $requestUrl, $requestCount, $elapsed);
			throw $e;
		}

		// just a catch up alert if I amend the workflow below and accidentally let the execution slip through it
		throw new Exception("Unexpected workflow course while requested supplier KPI");
	}


	private function testSlowResponse()
	{
		$this->logger->log("slow page is called");

		$client = new Zend_Http_Client();
		$client->setUri(Myshipserv_Config::getApplicationProtocol().'://'.Myshipserv_Config::getApplicationHostName().'/help/ping-pong');

		// reseting the parameters to avoid clash from the previous call
		$client->resetParameters();
		$response = $client->request();

		if( $response->getStatus() == 500 )
		{
			$this->reportError( $response );
		}
		$this->logger->log("got the response" . json_decode($response->getBody(), true));

		return json_decode($response->getBody(), true);

	}

	/**
	 * Data structure from service
	 * ===========================
	 * [tnid] => 52323
	 * [gmv_cur] => 62664519.43125
	 * [gmv_prev] => 50306087.910938
	 * [buyer] => 101
	 * [pv] => 4998
	 * [cv] => 125
	 * [rfq] => 50462
	 * [pcs] => 75
	 * [expert-supplier] => Y
	 * [smart-supplier] => N
	 * [premium-listing] => Y
	 * [basic-listing] => N
	 * [updated-date] => 20140302
	 * [main-category] => Chandlery
	 * ===========================
	 * @return boolean
	 */
	private function getDataFromReportService()
	{
		//http://jonah.myshipserv.com:8980/report-service/json/stats/get-data-for-sf?mindate1=20130301&maxdate1=20140228&mindate2=20120301&maxdate2=20130228&tnids=52323,67536
		$adapter = new Shipserv_Adapters_Report();
		$this->logger->log(" getting dataset from report service for " . ((count($this->tnids)>0)?implode(",", $this->tnids):"ALL") . " suppliers");

		$this->period = array(
			"start" => new DateTime($this->year-1 . "-" . $this->month . "-" . "01"),
			"end" => new DateTime($this->year . "-" . $this->month . "-" . "01"),
		);

		$this->period2 = array(
			"start" => new DateTime($this->year-2 . "-" . $this->month . "-" . "01"),
			"end" => new DateTime($this->year-1 . "-" . $this->month . "-" . "01"),
		);

		// only process tnids that are found in SF
		//$this->tnids = array_keys($this->data);

		// getting the report from report service
		$timeStart = microtime(true);
		$data = $this->getSupplierKPI(
			(($this->tnids===null)?"":$this->tnids),
			$this->period['start']->format("Ymd"),
			$this->period['end']->format("Ymd"),
			$this->period2['start']->format("Ymd"),
			$this->period2['end']->format("Ymd")
		);
		$this->logger->log(
			"Received ReportService response in " . ceil(microtime(true) - $timeStart) . " sec, it contains " .
			count($data['suppliers']) . " supplier items"
		);

		$totalProcessed = 0;
		// preparing the data to be uploaded to SF
		foreach ((array) $data['suppliers'] as $row) {
			//$this->data[$result->TNID__c] = array('Id' => $result->Id);
			$tnid = $row['tnid'];

			if (
				($this->data[$tnid]['Id'] != "")
				&& (
					($row['gmv_cur'] != "")
					|| ($row['gmv_prev'] != "")
					|| ($row['buyer'] != "")
					|| ($row['pv'] != "")
					|| ($row['cv'] != "")
					|| ($row['pcs'] != "")
					|| ($row['rfq'] != "")
					|| ($row['main-category'] != "")
					|| ($row['qot-resp-time'] != "")
				)
			) {
				// only proceeding here when there TNID is found both in SalesForce and and in what Report Service returned,
				// and the values returned by Report Service are meaningful

				// S17948: supplier type is from 2016-09-01 determined in report service and supplier as is
				$tradingTools = $row['supplier-type'];
				/*
				if ($row['expert-supplier'] == 'Y') {
					$tradingTools = 'Integrated Supplier';
				} else if ($row['smart-supplier'] == 'Y') {
					$tradingTools = 'SmartSupplier';
				} else {
					$tradingTools = 'StartSupplier';
				}
				*/

				if ($row['premium-listing'] == 'Y') {
					$pagesProfile = 'Premium Profile';
				} else {
					$pagesProfile = 'Basic Listing';
				}

				// comment by Yuriy Akopov: seems a bit fragile, order of fields should the same as in CSV saving method
				// refactored by Yuriy Akopov to make assignments more compact
				$kpiRow = array(
					'GMV_trailing_12_months_USD__c' 	=> (($row['gmv_cur'] !== null) ? $row['gmv_cur'] : 0),
					'GMV_previous_12_months_USD__c' 	=> (($row['gmv_prev'] !== null) ? $row['gmv_prev'] : 0),
					'TN_No_of_Buyers__c' 			    => (($row['buyer'] !== null) ? $row['buyer'] : 0),
					'Pages_Profile_Views__c' 		    => (($row['pv'] !== null) ? $row['pv'] : 0),
					'Pages_Contact_Views__c' 		    => (($row['cv'] !== null) ? $row['cv'] : 0),
					'ProfileCompletionScore__c' 	    => (($row['pcs'] !== null) ? $row['pcs'] : 0),
					'Pages_RFQs__c' 			        => (($row['rfq'] !== null) ? $row['rfq'] : 0),
					'Pages_Main_Category__c'            => (($row['main-category'] !== null) ? str_replace(",", " ", $row['main-category']) : "NA"),
					'Average_quote_response_hours__c'   => (($row['qot-resp-time'] !== null) ? $row['qot-resp-time'] : 0),
					'Trading_Tool__c'                   => $tradingTools,
					'Pages_Profile__c'                  => $pagesProfile,
					// S15833 by Yuriy Akopov on 2016-02-23
					'Transaction_Data_Extracted__c'     => date('Y-m-d')   // changed from 'GMV_Trailing_Last_Updated__c'
				);

				$this->data[$tnid] = array_merge($this->data[$tnid], $kpiRow);

				if (++$totalProcessed % 100 == 0) {
					$this->logger->log(" processing " . $totalProcessed);
				}
			} else {
				$this->logger->log("Invalid supplier row: " . print_r($row, true));
			}
		}

		if ($totalProcessed === 0) {
			// added by Yuriy Akopov on 2016-08-19, S17185
			$message = "No valid rows returned by ReportService";
			if (!array_key_exists('suppliers', $data)) {
				$message .= ", no suppliers node in the response";
			} else {
				$message .= ", " . count($data['suppliers']) . " suppliers returned in total";
			}

			$e = new Exception($message);
			$this->emailReportServiceError($e);

			throw $e;
		}

		return true;
	}

	private function getTemporaryFileName()
	{
		return self::TMP_DIR . '/report-on-' . date('Y-m-d') . ".csv";
	}

	/**
	 * Getting the name of the temporary file
	 * @return resource
	 */
	private function getTemporaryFileResource()
	{
		if( is_dir( self::TMP_DIR ) == false )
		{
			mkdir( self::TMP_DIR, 0777 );
		}
		return fopen($this->getTemporaryFileName(), "w+");
	}

	/**
	 * Attempts to resolve TNID to a supplier branch name or returns a stub on failure
	 *
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-05-23
	 * @story   S16669
	 *
	 * @param   int $tnid
	 *
	 * @return  string
	 */
	protected function _getAccountName($tnid) {
		$supplier = Shipserv_Supplier::getInstanceById($tnid, null, true);
		if (strlen($supplier->tnid) > 0) {
			return $supplier->name;
		}

		return "No Database TNID";
	}

	/**
	 * Preparing CSV
	 * @return boolean
	 */
	private function prepareCSVForUpload()
	{
		$this->logger->log(" preparing CSV to be uploaded");
		$fileIO = $this->getTemporaryFileResource();

		// comment by Yuriy Akopov: seems a bit fragile, order of fields should be the same as in getSupplierKPI
		// should be re-written to depend on field names rather than order
		$headerArray = array(
			'Id',                               // first column added to $this->data by getAllSFAccounts method
			// rest of the columns that come from getSupplierKPI method
			'GMV_trailing_12_months_USD__c',
			'GMV_previous_12_months_USD__c',
			'TN_No_of_Buyers__c',
			'Pages_Profile_Views__c',
			'Pages_Contact_Views__c',
			'ProfileCompletionScore__c',
			'Pages_RFQs__c',
			'Pages_Main_Category__c',
			'Average_quote_response_hours__c',
			'Trading_Tool__c',
			'Pages_Profile__c',
			'Transaction_Data_Extracted__c',     // S15833 by Yuriy Akopov on 2016-02-23 // 'GMV_Trailing_Last_Updated__c'
		);

		// a column which is added in this loop below only
		$extraFields = array('Gateway_Account_Name__c');           // S16669 by Yuriy Akopov on 2016-05-23

		// a column which only exists in CSV (no automatic upload) mode
		if ($this->isTestTransaction()) {
			$extraFields[] = 'TNID';
		}

		$rowNo = 0;
		foreach ((array) $this->data as $tnid => $br)
		{
			if ($rowNo === 0) {
				fputcsv($fileIO, array_merge($headerArray, $extraFields));
			}

			$rowNo++;

			// special treatment for 2 fields below because one doesn't come from Report Service's $this->data,
			// and another is optional
			$csvRow = $br;
			if (count($csvRow) < count($headerArray)) {
				$missingColCount = (count($headerArray) - count($csvRow));
				for ($missingCols = 0; $missingCols < $missingColCount; $missingCols++) {
					$csvRow[] = null;
				}
			}

			$csvRow['Gateway_Account_Name__c'] = $this->_getAccountName($tnid);;

			if ($this->isTestTransaction() === true) {
				$csvRow['TNID'] = $tnid;
			}

			fputcsv($fileIO, $csvRow);
		}

		$this->logger->log("  content of CSV: ", file_get_contents($this->getTemporaryFileName()), true);
		fclose($fileIO);

		return true;
	}

	/**
	 * Uploads the generated or provided file to CSV
	 *
	 * Reworked by Yuriy Akopov on 2016-08-19, DE6906
	 *
	 * @param   string  $filename
	 *
	 * @return bool
	 */
	public function uploadToSF($filename = null)
	{
		$sfObj = new Shipserv_Adapters_Salesforce();

		$params = array(
			'operation' => 'update',
			'objectName' => 'Account',
			'concurrencyMode' => 'Parallel'
		);

		if (is_null($filename)) {
			$csvFilename = $this->getTemporaryFileName();
		} else {
			$csvFilename = $filename;
		}

		$operationResults = $sfObj->bulkUpdateFromCSV($params, $csvFilename);
		$this->logger->log("Uploading CSV to SalesForce: " . $csvFilename, print_r($operationResults, true), true);

		return true;
	}
}
