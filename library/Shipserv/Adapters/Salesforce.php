<?php
/**
 *
 * @author Shane O'Connor 
 * @
 */

// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');
// require_once ('/var/www/libraries/SalesForce/BulkApiClient.php');

/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */
class Shipserv_Adapters_Salesforce {

	private $sfBatchSize = 5000;

	/**
	 *
	 * @var SalesForce Soap connection 
	 * @access protected
	 */
	private $connection;

	/**
	 * 	
	 * @var object 
	 */
	private $loginObj;

	/**
	 * Returned from the login object.
	 * @var string 
	 */
	private $endpoint;

	/**
	 * Returned from the login object.
	 * @var string 
	 */
	private $sessionId;

	/**
	 *
	 * @var Salesforce Soap client
	 */
	private $soapClient;

	public function __construct() {
		ini_set("soap.wsdl_cache_enabled", "0");
		
		$credentials = Myshipserv_Config::getSalesForceCredentials();

		$this->connection = new SforceEnterpriseClient();
		$this->soapClient = $this->connection->createConnection($credentials->wsdl);

		$this->loginObj = $this->connection->login($credentials->username, $credentials->password . $credentials->token);
		$this->endpoint = $this->loginObj->serverUrl;
		$this->sessionId = $this->loginObj->sessionId;
	}

	/**
	 * 	Updates a single record in Salesforce. For multiple opbjects use the Bulk updater.
	 * @param string $objectName The Salesforce object we are updating
	 * @param type $params An Array of items that we are updating, this will create a stdClass that is passed to SF. The array MUST contain an Id value.  
	 */
	public function updateObject($objectName, array $params) {

		if ( empty($params['Id']) || !ctype_alnum($params['Id']) ) {
			return new Myshipserv_Response("1", "Invalid ID passed");
		}
		
		$updateObject = (object) $params;
		$updateObject->Id = $this->handleId($updateObject->Id);

		$response = $this->connection->update(array($updateObject), $objectName);

		$success = (bool) $response[0]->success;
		if ($success) {
			$message = "Success";
		} else {
			$message = "Failure\n\n";
			foreach ($response[0]->errors as $value) {
				$message .= $value->message . " [" . $value->statusCode . "]\n";
			}
		}

		$responseObj = new Myshipserv_Response($response[0]->id, $message);

		return $responseObj;
	}

	public function query($soql) {
		$returnArr = Array();
		$queryResults = $this->connection->query($soql);

		if (count($queryResults->records) > 0) {
			//Start the update.
			while (!$done) {
				$returnArr = array_merge($returnArr, $queryResults->records);

				if ($queryResults->done != true) {
					$queryResults = $this->connection->queryMore($queryResults->queryLocator);
				} else {
					$done = true;
				}
			}
		}

		return $returnArr;
	}

	/**
	 * Refactored and fixed by Yuriy Akopov on 2016-08-19, DE6906
	 *
	 * Params should be an array containing the following items:
	 *
	 * objectName : Name of the table we are updating
	 * operation : insert or update or upsert (Case sensitive)
	 * concurrencyMode: Parallel (recommended) or Serial (Case sensitive)
	 * 
	 * @param   array   $params         An array containing fields mentioned above for initiating the job for the bulk.
	 * @param   string  $strCSVFile
	 * @param   string  $external_id
	 * @param   string  $accountIdColumn
	 *
	 * @return  array|bool
	 */
	public function bulkUpdateFromCSV(array $params, $strCSVFile, $external_id = '', $accountIdColumn = 'Id') {
		$bulkApiConnection = new BulkApiClient($this->endpoint, $this->sessionId);
		$bulkApiConnection->setLoggingEnabled(false);
		$bulkApiConnection->setCompressionEnabled(true);

		$job = new JobInfo();
		$job->setObject($params['objectName']);
		$job->setOpertion($params['operation']);
		$job->setContentType('CSV');
		$job->setConcurrencyMode($params['concurrencyMode']);

		if ($params['operation'] == 'upsert' && $external_id != '') {
			$job->setExternalIdFieldName($external_id);
		}

		$job = $bulkApiConnection->createJob($job);

		// read from CSV file and loop through its content to create batches of SALESFORCE_BATCH_SIZE (5000)
		$file = new SplFileObject($strCSVFile);
		$batchHeaderStr = $file->current();                 // first line is always expected to be the header
		$batchHeaderItems = str_getcsv($batchHeaderStr);    // validate the header by the presence of the mandatory ID column

		if (!is_null($accountIdColumn)) {
			if (($idLoc = array_search($accountIdColumn, $batchHeaderItems)) === false) {
				// expected SalesForce ID column is not found in the CSV - legacy behaviour is to quit
				return false;
			}
		} else {
			$idLoc = false;
		}

		$batches = array();
		$batchRows = array();

		$file->next();

		while ($file->valid()) {
			$batchPartItems = $file->fgetcsv();  // read the row as a parsed array
			if ($idLoc !== false) {
				// convert SalesForce ID between 18 and 15 character long
				$batchPartItems[$idLoc] = $this->handleId($batchPartItems[$idLoc]);
			}

			$batchPartItems = Myshipserv_View_Helper_String::simplifyForCsv($batchPartItems); // DE6937, by Yuriy Akopov on 2016-09-14
			$batchRows[] = Myshipserv_View_Helper_String::csvRowToString($batchPartItems);

			if ((count($batchRows) >= $this->sfBatchSize) or $file->eof()) {
				$batchStr = $batchHeaderStr . implode("\n", $batchRows);
				$batches[] = $bulkApiConnection->createBatch($job, $batchStr);
				$batchRows = array();
			}
			
			$file->next();
		}
		
		$bulkApiConnection->updateJobState($job->getId(), "Closed");

		/*
		while($batch0->getState() == "Queued" || $batch0->getState() == "InProgress" && $batch0->getState() != "Failed") {
			$batch0 = $bulkApiConnection->getBatchInfo($job->getId(), $batch0->getId());
			sleep(5); //wait for 5 seconds before polling again. in the real world, probably make this exponential as to not ping the server so much
		}
		*/

		return array(
			'jobId'      => $job->getId(),
			'batchCount' => count($batches)
		);
	}

	/**
	 * This function will accept a 15 character Salesforce id and convert it to a case insensitive
	 * 18 char id, adding the necessary 3 checksum bits onto the end.
	 *
	 * @param   string $truncatedId
	 *
	 * @return  string
	 */
	public function convertToInternalId($truncatedId)
	{
		$chunks = str_split($truncatedId, 5);
		$extra = '';

		foreach ($chunks as $chunk) {
			$chars = str_split($chunk, 1);
			$bits = '';

			foreach ($chars as $char) {
				$bits .= (!is_numeric($char) && $char == strtoupper($char)) ? '1' : '0';
			}

			$map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
			$extra .= substr($map, base_convert(strrev($bits), 2, 10), 1);
		}

		return $truncatedId . $extra;
	}
	
	/**
	 * If the id passed is 15 chars and IS alpha numeric, hand it over to the convertToInternalId function for conversion to
	 * case insensitive 18 chars id.
	 *
	 * @param   string  $id
	 *
	 * @return  string
	 */
	private function handleId($id)
	{
		if ((strlen($id) === 15) and ctype_alnum($id)) {
			return $this->convertToInternalId($id);
		}

		return $id;
	}
}
