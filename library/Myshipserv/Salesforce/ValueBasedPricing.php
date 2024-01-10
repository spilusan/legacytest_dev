<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 *
 * Code style updated by Yuriy Akopov on 2016-11-01
 */
class Myshipserv_Salesforce_ValueBasedPricing extends Myshipserv_Salesforce_Base
{
	const MEMCACHE_TTL = 86400;

	protected $data = array();
	protected $billingReport = array();
	protected $sf = array();
	protected $period = array();

	/**
	 * Myshipserv_Salesforce_ValueBasedPricing constructor.
	 */
	public function __construct()
	{
		$this->db = Shipserv_Helper_Database::getDb();
		$this->logger = new Myshipserv_Logger_File('salesforce-vbp-object-sync');

		if (!Myshipserv_Config::isInProduction()) {
			$this->runInSandbox();
			$this->runAsTestTransaction();
		}

		$this->initialiseConnection();
	}

	/**
	 * Updates serialised copies of Salesforce objects (which I don't know why are collected - Yuriy)
	 */
	public function downloadAllVBPObjects()
	{
		$this->downloadAccount();
		$this->downloadContract();
		$this->downloadRateSet();
		$this->downloadValueEvent();
	}

	/**
	 * Pulls and saved value event objects
	 */
	public function downloadValueEvent()
	{
		$this->logger->log("STEP 4 - Downloading all value events");

		$soql = "
			SELECT
				days_examined__c,
				Accounts_With_GMV__c,
				Closing_balance__c,
				CreatedById,
				CreatedDate,
				CurrencyIsoCode,
				IsDeleted,
				Fee_Unique_contact_views__c,
				Fee_for_Unactioned_RFQs__c,
				Fee_for_targeted_impressions__c,
				Gross_Merchandise_Value__c,
				LastModifiedById,
				LastModifiedDate,
				Opening_balance__c,
				Period_end__c,
				Period_start__c,
				Po_Fee__c,
				Profile_Complete_Score__c,
				Rate__c,
				Id,
				Summary_of_fees_for_children__c,
				SystemModstamp,
				TNID__c,
				Targeted_impressions__c,
				Total_Fees_Checker__c,
				Total_fees__c,
				Total_fees_to_credit__c,
				TransactionAccount__c,
				Unactioned_RFQs__c,
				Unique_contact_views__c,
				Name
			FROM
				Value_event__c
		";
		$records = $this->querySFAllRows($soql);

		$this->logger->log("STEP 4 - " . count($records) . " found");

		$totalRows = 0;
		if (count($records) > 0) {
			foreach ($records as $data) {
				$sql = "
					MERGE INTO SALESFORCE_VALUE_EVENT USING DUAL ON (SFV_ID=:id)
						WHEN NOT MATCHED THEN
							INSERT (
								SFV_ID,
								SFV_SFR_ID,
								SFV_SFC_ID,
								SFV_SFA_ID,
								SFV_START_DATE,
								SFV_END_DATE,
								SFV_DATA
							)
							VALUES (
								:id,
								:rateSetId,
								:contractId,
								:accountId,
								:startDate,
								:endDate,
								:data
							)
						WHEN MATCHED THEN
							UPDATE SET
								SFV_SFR_ID = :rateSetId,
								SFV_SFC_ID = :contractId,
								SFV_SFA_ID = :accountId,
								SFV_START_DATE = TO_DATE(:startDate),
								SFV_END_DATE = TO_DATE(:endDate),
								SFV_DATA = :data
				";

				$params = array(
					'id'         => $data->Id,
					'rateSetId'  => $data->Rate__c,
					'accountId'  => $data->TransactionAccount__c,
					'contractId' => '',
					'startDate'  => $this->convertDate($data->Period_start__c),
					'endDate'    => $this->convertDate($data->Period_end__c),
					'data'       => serialize($data)
				);
				$this->getDb()->query($sql, $params);

				if (++$totalRows % 10 == 0) {
					$this->logger->log("STEP 4 - >> " . $totalRows . " merged");
				}
			}
		}

		$this->logger->log("STEP 4 - Done");
	}

	/**
	 * Pulls and stores serialised rateset objects
	 */
	public function downloadRateSet()
	{
		$this->logger->log("STEP 3 - Downloading all rate set");

		$soql = "
			SELECT
				c.Id
				, c.Account.Id
				, (
					SELECT
						Active_Rates__c
						, Contract__c
						, CreatedById
						, CreatedDate
						, Fee_per_Off_ShipServ_Lead_UCV__c
						, Fee_per_Off_ShipServ_Lead__c
						, Fee_per_mile_targeted_impressions__c
						, Integrated_maintenance_fee__c
						, LastModifiedById
						, LastModifiedDate
						, Membership_fee__c
						, PO_percentage_fee__c
						, Name
						, Id
						, Valid_from__c
						, Valid_to__c
						, CurrencyIsoCode
 					FROM
						c.Rates__r
				)

			FROM
				Contract c
			WHERE
				c.Type_of_agreement__c='Membership - Value based'
		";
		$records = $this->querySFAllRows($soql);

		$this->logger->log("STEP 3 - " . count($records) . " found");

		$totalRows = 0;
		if (count($records) > 0) {
			foreach ($records as $data) {
				$contractId = $data->Id;
				$accountId = $data->Account->Id;

				foreach ((array) $data->Rates__r->records as $rate) {

					$sql = "
						MERGE INTO SALESFORCE_RATESET USING DUAL ON (SFR_ID=:id)
							WHEN NOT MATCHED THEN
								INSERT (
									SFR_ID,
									SFR_SFC_ID,
									SFR_SFA_ID,
									SFR_START_DATE,
									SFR_END_DATE,
									SFR_DATA
								)
								VALUES (
									:id,
									:contractId,
									:accountId,
									:startDate,
									:endDate,
									:data
								)
							WHEN MATCHED THEN
								UPDATE SET
									SFR_SFC_ID = :contractId,
									SFR_SFA_ID = :accountId,
									SFR_START_DATE = TO_DATE(:startDate),
									SFR_END_DATE = TO_DATE(:endDate),
									SFR_DATA = :data
					";

					$params = array(
						'id'         => $rate->Id,
						'contractId' => $contractId,
						'accountId'  => $accountId,
						'startDate'  => $this->convertDate($rate->Valid_from__c),
						'endDate'    => $this->convertDate($rate->Valid_to__c),
						'data'       => serialize($rate)
					);
					$this->getDb()->query($sql, $params);

					if (++$totalRows % 10 == 0) {
						$this->logger->log("STEP 3 - >> " . $totalRows . " merged");
					}
				}
			}

			$this->logger->log("STEP 3 - Done");
		}
	}

	/**
	 * Pulls and stores serialised Account objects
	 */
	public function downloadAccount()
	{
		$this->logger->log("STEP 1 - Downloading all accounts");

		$soql = "
			SELECT
				c.Id
				, c.Account.Id
				, c.Account.Contracted_under__c
				, c.Account.DNC_Expiry_date__c
				, c.Account.DNC_date__c
				, c.Account.DO_NOT_CALL__c
				, c.Account.DO_NOT_CONTACT__c
				, c.Account.Pages_Profile_Link__c
				, c.Account.TNID__c
				, c.Account.TNID_of_Contracted__c
				, c.Account.Trading_TNID__c
				, c.Account.Tradenet_Phone__c
				, c.Account.Tradenet_Trading_E_mail__c
				, c.Account.Name
				, c.Account.Account_verified__c
				, c.Account.Account_verified_by__c
				, c.Account.CreatedDate
				, c.Account.Customer_Under_TN1_or_TN2__c
				, c.Account.TN_No_of_Buyers__c
				, c.Account.No_of_vessels_trading__c
				, c.Account.Vesses_Updated_date__c
			FROM
				Contract c
			WHERE
				c.Type_of_agreement__c = 'Membership - Value based'
		";
		$records = $this->querySFAllRows($soql);

		$this->logger->log("STEP 1 - " . count($records) . " found");

		$totalRows = 0;
		if (count($records) > 0) {
			foreach ($records as $data) {
				$data = $data->Account;
				$sql = "
					MERGE INTO SALESFORCE_ACCOUNT USING DUAL ON (SFA_ID=:id)
						WHEN NOT MATCHED THEN
							INSERT (
								SFA_ID,
								SFA_TNID,
								SFA_NAME,
								SFA_CONTRACTED_UNDER_TNID,
								SFA_DATA
							)
							VALUES (
								:id,
								:tnid,
								:name,
								:contractedUnderTnid,
								:data
							)
						WHEN MATCHED THEN
							UPDATE SET
								SFA_TNID = :tnid,
								SFA_NAME = :name,
								SFA_CONTRACTED_UNDER_TNID = :contractedUnderTnid,
								SFA_DATA = :data
				";

				$params = array(
					'id'                  => $data->Id,
					'tnid'                => $data->TNID__c,
					'contractedUnderTnid' => $data->TNID_of_Contracted__c,
					'name'                => $data->Name,
					'data'                => serialize($data)
				);
				$this->getDb()->query($sql, $params);

				if (++$totalRows % 10 == 0) {
					$this->logger->log("STEP 1 - >> " . $totalRows . " merged");
				}
			}
		}

		$this->logger->log("STEP 1 - Done");
	}

	/**
	 * Legacy contract data sync. Was replaces along qith other procedures by Active Promotion sync, but
	 * has not yet been shut down.
	 *
	 * Updated on Yuriy Akopov on 2016-10-01, DE7068 to reflect the schema update and for some refactoring
	 */
	public function downloadContract()
	{
		$this->logger->log("STEP 2 - Downloading contracts");

		$soql = "
			SELECT
				child_TNIDs_included_in_this_contract__c,
				AccountId,
				ActivatedDate,
				ShipServ_Signed_By__c,
				Contact_person__c,
				EndDate,
				Id,
				Name,
				StartDate,
				CreatedDate,
				LastModifiedDate,
				LastModifiedById,
				LastApprovedDate,
				LastActivityDate,
				Status,
				Last_price_renewal__c,
				Maintenance_fee__c,
				Maintenance_subscription_period__c,
				Next_price_renewal__c,
				Opportunity__c,
				Setup_fee__c
			FROM
				Contract
			WHERE
				Type_of_agreement__c = 'Membership - Value based'
		";

		$records = $this->querySFAllRows($soql);
		$totalRows = 0;
		$this->logger->log("STEP 2 - " . count($records) . " found");

		if (count($records) > 0) {
			foreach ($records as $data) {
				$sql = "
					MERGE INTO SALESFORCE_CONTRACT USING DUAL ON (SFC_ID = :id)
						WHEN NOT MATCHED THEN
							INSERT (
								SFC_ID,
								SFC_SFA_ID,
								SFC_START_DATE,
								SFC_END_DATE,
								SFC_DATA
							)
							VALUES(
								:id,
								:accountId,
								:startDate,
								:endDate,
								:data
							)
						WHEN MATCHED THEN
							UPDATE SET
								SFC_SFA_ID     = :accountId,
								SFC_START_DATE = TO_DATE(:startDate),
								SFC_END_DATE   = TO_DATE(:endDate),
								SFC_DATA       = :data
				";

				$params = array(
					'id'        => $data->Id,
					'accountId' => $data->AccountId,
					'startDate' => $this->convertDate($data->StartDate),
					'endDate'   => $this->convertDate($data->EndDate),
					'data'      => serialize($data)
				);
				$this->getDb()->query($sql, $params);

				if (++$totalRows % 10 == 0) {
					$this->logger->log("STEP 2 - >> " . $totalRows . " merged");
				}
			}
		}
	}

	/**
	 * @param   string  $date
	 *
	 * @return  string
	 */
	protected function convertDate($date)
	{
		if ($date == null) {
			return $date;
		}

		if (strstr("T", $date) !== false) {
			$tmp = explode("T", $date);
			$tmp = explode("-", $tmp);

			$d = new DateTime();
			$d->setDate((int) $tmp[0], (int) $tmp[1], (int) $tmp[2]);
		} else {
			$tmp = explode("-", $date);
			$d = new DateTime();
			$d->setDate((int) $tmp[0], (int) $tmp[1], (int) $tmp[2]);
		}

		return $d->format("d-M-Y");
	}

	/**
	 * Function to query SF, it'll keep trying it if throws error
	 *
	 * @param   string $soql
	 *
	 * @return  QueryResult
	 * @throws  Exception
	 */
	public function querySf($soql)
	{
		$this->sfConnection->setQueryOptions(new QueryOptions(self::QUERY_ROWS_AT_ONCE));

		$ok = false;
		while ($ok == false) {
			try {
				$response = $this->sfConnection->query($soql);
				$ok = true;

			} catch (Exception $e) {
				$this->logger->log(
				    "\tSF's not responding when querying; reason: " . $e->getMessage() .
                    " - pausing for " . self::REQUEST_PAUSE . "s and retry"
                );

				if ($e->getMessage() == "Could not connect to host") {
					sleep(self::REQUEST_PAUSE);
					$ok = false;
				} else {
					throw $e;
				}
			}
		}

		return $response;
	}

	/**
	 * @param   string  $soql
	 *
	 * @return  array
	 */
	public function querySFAllRows($soql)
	{
		$response = $this->querySf($soql);
		$done = false;
		$iteration = 0;

		if ($response->size > 0) {
			$records = array();

			while (!$done) {
				if ($response->records[0] != null) {
					$records = array_merge((array) $records, (array) $response->records);
				}

				if ($response->done != true) {
					$this->logger->log("[SF] querying SF for next batch (Job #" . (++$iteration) . ")");

					try {
						$response = $this->sfConnection->queryMore($response->queryLocator);
					} catch (Exception $e) {
						$this->logger->log(
							"[SF] Error when trying to get the next batch: Exception: " .
							$e->faultstring, print_r($this->sfConnection->getLastRequest(), true)
						);
					}
				} else {
					$done = true;
				}
			}

			return $records;
		}
	}
}