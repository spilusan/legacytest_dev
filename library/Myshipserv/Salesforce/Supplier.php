<?php
/**
 * Initially created by Elvir
 * Extended and somewhat refactored by Yuriy Akopov on 2016-02-03 when working on S15735
 * to separate sandbox and real account credentials in the config
 */

class Myshipserv_Salesforce_Supplier extends Myshipserv_Salesforce
{
	/*
	function __construct() {
	    $this->config = Zend_Registry::get('config');
        $this->sfConnection = new SforceEnterpriseClient();

        if( $useSandbox === true )
        {
        	$wsdl = "/var/www/libraries/SalesForce/enterprise.sandbox.wsdl.xml";
        }
        else
        {
        	$wsdl = "/var/www/libraries/SalesForce/enterprise.wsdl.xml";
        }

		$this->sfProxy = new ProxySettings;
		$this->soapClient = $this->sfConnection->createConnection( ( ($this->sandbox === true)?Myshipserv_Salesforce_Base::SANDBOX_WSDL:Myshipserv_Salesforce_Base::PRODUCTION_WSDL ), $this->sfProxy );
		$this->loginObj = $this->sfConnection->login($this->config->shipserv->salesforce->integration->username, $this->config->shipserv->salesforce->integration->password);

        $this->db = Shipserv_Helper_Database::getDb();

        if ($tnid != null) {
            $this->tnid = $tnid;
            $query = "Select Id, Name, TNID__c, Trading_TNID__c, Pages_Listing_Level__c,
						Owner.Name, Owner.Email, Owner.Phone, Owner.Title
						FROM Account where TNID__c = " . $tnid . " LIMIT 1";

            $queryResult = $this->sfConnection->query($query);
        }	}
	*/

    public static function updateBuyerSupplierBranchWithSalesforceId()
    {

        $sfAdapter = new Shipserv_Adapters_Salesforce();

        $query = "Select Id, Tnid__c from Account where Tnid__c > 10000 order by Tnid__c Asc";
        $queryResult = $sfAdapter->query($query);

        $db = self::getDb();

        if (count($queryResult) > 0) {
            //If query isnt done we need to loop using the queryMore function provided through the soap Connection
            foreach ($queryResult as $record) {
                if ($record->TNID__c < 50000) {
                    $updateSQL = "Update Buyer_Branch set byb_salesforce_id = :id where BYB_BRANCH_CODE = :tnid";
                } else {
                    $updateSQL = "Update Supplier_Branch set spb_salesforce_id = :id where SPB_BRANCH_CODE = :tnid";
                }
                $params = array(
                    'id' => $record->Id,
                    'tnid' => $record->TNID__c,
                );

                try {
                    $db->query($updateSQL, $params);
                } catch (Exception $ex) {
                    //Do nothing
                    $error = $ex;
                }
            }
        }
    }

    /**
     * Not being used anywhere
     * @param unknown $tnid
     * @param number $score
     * @return unknown
     */
    public static function updateTradeRankScore($tnid, $score = 0) {
    	//TODO: Refactor this to run from new classes.
    	//First, get the accountId we are updating.
    	$soqlQuery = "Select Id from Account where Tnid__c = " . mysql_escape_string($tnid) . " LIMIT 1";

    	$queryResult = $connection->query($query);

    	$id = $queryResult->Id;

    	$updateObj = new stdClass();

    	if (!empty($score)) {
    		$updateObj->Traderank__c = $score;
    		$updateObj->Traderank_as_of__c = date("Y/d/m", timestamp());
    		$response = $connection->upsert('Tnid__c', array($updateObj), 'Account');
    	}

    	return $response;
    }

    /**
     * Get e-Invoicing Asset details from Salesforce and update the eInvoicing flags in Supplier_Branch table
     */
    public static function updateSuppliersWithEInvoicing() {
    	$sfObj = new Shipserv_Adapters_Salesforce();

    	$soql = "Select Account.Name, Account.TNID__c, InstallDate, Downgraded__c
				   from Asset
				where
					Account.TNID__c > 50000
				and
					Product2.Id = '01t000000003MQYAA2'
				and
					Live__c = true
				and Hide_in_pages__c = false";

    	$results = $sfObj->query($soql);

    	$db = self::getDb();

    	//Flag to continue pulling records in batches if there are large numbers of them. Unlikely with eInvoicing but there for scalabiity.
    	$errors = array();

    	if (count($results) > 0) {
    		//If query isnt done we need to loop using the queryMore function provided through the soap Connection
    		$updateSQL = "
    			UPDATE supplier_branch
				SET
					spb_einvoicing_install_date = to_date(:install, 'yyyy/mm/dd'),
					spb_einvoicing_downgraded = :downgraded
				WHERE
					spb_branch_code = :tnid
    		";

    		$i = 1;
    		foreach ($results as $record) {
    			$downgraded = $record->Downgraded__c ? 'Y' : 'N';
    			$params = array(
    				'install' => $record->InstallDate,
    				'downgraded' => $downgraded,
    				'tnid' => $record->Account->TNID__c,
    			);

    			try {
    				$db->query($updateSQL, $params);
    			} catch (Exception $ex) {
    				//DoNothing
    				$errors[] = $ex;
    			}
    		}
    	}
    }


    /**
     *
     */
    public static function updatePagesRFQsKpis( $uploadToSalesforce = true ) {

    	if( $uploadToSalesforce === true && $_SERVER['APPLICATION_ENV'] != "production" )
    	{
    		$uploadToSalesforce = false;
    	}

    	$sfObj = new Shipserv_Adapters_Salesforce();
    	//STEP 1 GET account ID, KPI ID and TNID and stope them in an array so we can process
    	$soql = "Select Account__r.Id,Account__r.TNID__c, Id from Account_KPIs__c ";

    	$sfResults = $sfObj->query($soql);

    	$sql = "
SELECT
  SPB_BRANCH_CODE,
  SPB_NAME,
  SPB_SALESFORCE_ID,
  SPB_PCS_SCORE,
  SPB_UPDATED_DATE,
  SPB_ACCT_MNGR_NAME,
  SPB_ACCT_MNGR_EMAIL,
  (
    SELECT
      COUNT(*)
    FROM
      PAGES_INQUIRY,
      PAGES_INQUIRY_RECIPIENT
    WHERE
      PIR_SPB_BRANCH_CODE = spb_branch_code
      AND PIR_PIN_ID = PIN_ID
      AND PIR_RELEASED_DATE BETWEEN sysdate-365 AND sysdate
      AND not exists (
        select 1 from pages_statistics_email
        where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
      )
  ) SENT
  ,
  (
    SELECT
      COUNT(*)
    FROM
      PAGES_INQUIRY,
      PAGES_INQUIRY_RECIPIENT
    WHERE
      PIR_SPB_BRANCH_CODE = spb_branch_code
      AND PIR_PIN_ID = PIN_ID
      AND PIR_RELEASED_DATE BETWEEN sysdate-365 AND sysdate
      AND PIR_IS_READ IS NOT NULL
      AND PIR_IS_REPLIED IS NULL
      AND PIR_IS_DECLINED IS NULL
      AND not exists (
        select 1 from pages_statistics_email
        where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
      )
  ) READ
  ,
  (
    SELECT
      COUNT(*)
    FROM
      PAGES_INQUIRY,
      PAGES_INQUIRY_RECIPIENT
    WHERE
      PIR_SPB_BRANCH_CODE = spb_branch_code
      AND PIR_PIN_ID = PIN_ID
      AND PIR_RELEASED_DATE BETWEEN sysdate-365 AND sysdate
      AND PIR_IS_REPLIED IS NOT NULL
      AND PIR_IS_READ IS NOT NULL
      AND not exists (
        select 1 from pages_statistics_email
        where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
      )

  ) REPLIED
  ,
  (
      SELECT
        COUNT(*)
      FROM
        PAGES_INQUIRY,
        PAGES_INQUIRY_RECIPIENT
      WHERE
        PIR_SPB_BRANCH_CODE = spb_branch_code
        AND PIR_DECLINE_SOURCE = 'EMAIL'
        AND PIR_PIN_ID = PIN_ID
        AND PIR_RELEASED_DATE BETWEEN sysdate-365 AND sysdate
        AND PIR_IS_DECLINED IS NOT NULL
        AND not exists (
          select 1 from pages_statistics_email
          where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
        )
  ) DECLINED_FROM_EMAIL
  ,
  (
      SELECT
        COUNT(*)
      FROM
        PAGES_INQUIRY,
        PAGES_INQUIRY_RECIPIENT
      WHERE
        PIR_SPB_BRANCH_CODE = spb_branch_code
        AND PIR_DECLINE_SOURCE = 'VIEW'
        AND PIR_PIN_ID = PIN_ID
        AND PIR_RELEASED_DATE BETWEEN sysdate-365 AND sysdate
        AND PIR_IS_DECLINED IS NOT NULL
        AND not exists (
          select 1 from pages_statistics_email
          where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
        )
  ) DECLINED_FROM_WEB

FROM
  supplier_branch
WHERE
  spb_account_deleted = 'N'
  AND directory_listing_level_id=4
  AND spb_test_account = 'N'
  AND spb_branch_code <= 999999
  AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)
";
    	$db = self::getDb();
    	$results = $db->fetchAll($sql);

    	$tmpName = tempnam("\tmp", "kpi");
    	$tmpCSV = fopen($tmpName, "w");

    	//Write header
    	fwrite($tmpCSV, "Id,Total_Pages_RFQS_Past_Year__c,Total_Pages_RFQs_Read_Past_Year__c,Total_Pages_RFQs_Replied_Past_Year__c,Total_Pages_RFQs_Dec_by_Email_Past_Year__c,Total_Pages_RFQs_Dec_from_Web_Past_Year__c,Profile_Completion_Score__c,Profile_Last_Updated__c\n");

    	foreach ($sfResults as $result) {
    		$tnid = $result->Account__r->TNID__c;
    		$id = $result->Id;

    		$data = self::searchKPIs($results, $tnid);
    		if ($data) {
    			fputcsv($tmpCSV, array($id, $data['SENT'], $data['READ'], $data['REPLIED'], $data['DECLINED_FROM_EMAIL'], $data['DECLINED_FROM_WEB'], $data['SPB_PCS_SCORE'], date('Y-m-d', strtotime($data['SPB_UPDATED_DATE']))));
    		}
    	}

    	fclose($tmpCSV);

    	$params = array('operation' => 'update', 'objectName' => 'Account_KPIs__c', 'concurrencyMode' => 'Parallel');

    	if( $uploadToSalesforce === true )
    	$results = $sfObj->bulkUpdateFromCSV($params, $tmpName);

    	return $results;
    }

    public static function updateSuppliersPagesProfileLink( $uploadToSalesforce = true ) {

    	if( $uploadToSalesforce === true && $_SERVER['APPLICATION_ENV'] != "production" )
    	{
    		$uploadToSalesforce = false;
    	}

    	// Method modified to be a push only, without calling to Salesforce first, I think long queries were causing disconnects from the Salesforce side preventing the updates from working.
    	$sfObj = new Shipserv_Adapters_Salesforce();
    	$sql = "Select spb_branch_code, spb_Name, spb_salesforce_id FROM SUPPLIER_BRANCH where spb_salesforce_id is not null and spb_sts = 'ACT' and spb_list_in_suppliier_dir = 'Y' and spb_account_deleted = 'N'";

    	$db = self::getDb();
    	$results = $db->fetchAll($sql);

    	$tmpName = tempnam("\tmp", "profile");
    	$tmpCSV = fopen($tmpName, "w");

    	//Write header
    	fwrite($tmpCSV, "Id,Pages_Profile_Link__c\n");

    	foreach ($results as $sfResult) {
    		$tnid = $sfResult['SPB_BRANCH_CODE'];
    		$id = $sfResult['SPB_SALESFORCE_ID'];
    		$url = 'https://www.shipserv.com/supplier/profile/s/' . preg_replace('/(\W){1,}/', '-', $sfResult['SPB_NAME']) . '-' . $sfResult['SPB_BRANCH_CODE'];
    		fputcsv($tmpCSV, array($id, $url));
    	}

    	fclose($tmpCSV);

    	$params = array('operation' => 'update', 'objectName' => 'Account', 'concurrencyMode' => 'Parallel');

    	if( $uploadToSalesforce === true )
    	$operationResults = $sfObj->bulkUpdateFromCSV($params, $tmpName);

    	unlink($tmpName);

    	return $operationResults;
    }

    public static function updateTradenetKPIs( $uploadToSalesforce = true) {

    	if( $uploadToSalesforce === true && $_SERVER['APPLICATION_ENV'] != "production" )
    	{
    		$uploadToSalesforce = false;
    	}

    	$sql = "Select
                        spb_branch_code, spb_salesforce_id, coalesce(spb_pcs_score, 0) as spb_pcs_score
                    from
                        supplier_branch
                    where
                        spb_salesforce_id is not null
                        and spb_sts = 'ACT'
                        and spb_list_in_suppliier_dir = 'Y'
                        and spb_account_deleted = 'N'";
    	$db = self::getDb();

    	$sfObj = new Shipserv_Adapters_Salesforce();

    	$results = $db->fetchAll($sql);
    	if (count($results) > 0) {
    		//Generate temp file to push to salesforce
    		$tmpName = tempnam("\tmp", "TradenetKpis");
    		$tmpCSV = fopen($tmpName, "w");
    		//Generate CSV Header
    		$header = "Id,ProfileCompletionScore__c,Pages_Profile_Views__c,Pages_Contact_Views__c,Pages_RFQs__c,X4_Pages_Banner_Impressions__c,Tradenet_RFQs__c,TradeNet_QOTs__c,TradeNet_POs__c,Pages_statistics_as_of__c\n";
    		fwrite($tmpCSV, $header);

    		//Create Reports object.
    		$report = new Shipserv_Report();

    		$i = 0;
    		foreach ($results as $result) {
    			if ($i > 100) {
    				break;
    			}
    			$statsContainer = $report->getSupplierValueReport($result['SPB_BRANCH_CODE'], date("Ymd", strtotime('1 year ago')), date("Ymd"));
    			$stats = $statsContainer->data['supplier'];
    			$statsArray = array($result['SPB_SALESFORCE_ID'], $result['SPB_PCS_SCORE'], $stats['impression-summary']['impression']['count'], $stats['impression-summary']['contact-view']['count'], $stats['enquiry-summary']['enquiry-sent']['count'], $stats['banner-summary']['impression']['count'], $stats['tradenet-summary']['RFQ']['count'], $stats['tradenet-summary']['QOT']['count'], $stats['tradenet-summary']['PO']['count'], date("Y-m-d"));
    			fputcsv($tmpCSV, $statsArray);
    			$i++;
    		}
    		echo "Completed writing " . count($results) . " records to file. Pushing to Salesforce.\n";
    		fclose($tmpCSV);

    		$params = array('operation' => 'update', 'objectName' => 'Account', 'concurrencyMode' => 'Parallel');

    		if( $uploadToSalesforce === true )
    		$operationResults = $sfObj->bulkUpdateFromCSV($params, $tmpName);

    		echo "Push to Salesforce complete.\nJOB DETAILS: " . print_r($operationResults, true);
    		unlink($tmpName);

    		return $operationResults;
    	}
    }

    private function searchKPIs($array, $tnid) {
    	reset($array);
    	foreach ($array as $item) {
    		if ((int) $item['SPB_BRANCH_CODE'] == $tnid) {
    			return $item;
    		}
    	}
    	return false;
    }

    /*
     * This function will simply pull owner/sales rep info for all accounts with a tnid from salesforce and
    * update the Pages_Salesforce Record with that info. Used separately from updateProfileRecords
    * as that function assumes relevant accounts have valid assets which, as yet, not all do.
    */

    public static function updateProfileRecordsWithAccountOwner() {

    	$logger = new Myshipserv_Logger_File('salesforce-sync-account-manager');
		$config = parent::getConfig();
		$sfAdapter = new Shipserv_Adapters_Salesforce ();

		$logger->log('Querying SF');

		$updateAnnualEstimatedSpendPerVessel = ($config->shipserv->salesforce->buyerGmv->pullPotentialGMVPerVessel==1);

		$query = "
			Select
				Account.Name,
				Account.Sales_rep__r.Name,
				Account.Sales_rep__r.E_mail__c,
				Account.Tnid__c,
				Account.Owner.Name,
				Account.Owner.Email,
				Account.Annual_Estimated_Spend_per_Vessel__c
			from
				Account where Account.Tnid__c > 0
		";

		$queryResult = $sfAdapter->query ( $query );
		$db = self::getDb ();
		$updatedCount = 0;
		$reasons = array ();
		if (count ( $queryResult ) > 0) {

			// $updateSQL = "Update Pages_Salesforce set psf_owner_name = :owner_name, psf_owner_email = :owner_email,
			// psf_sales_rep_name = :rep_name, psf_sales_rep_email = :rep_email where PSF_TNID = :tnid";


			$i = 1;
			foreach ( $queryResult as $record ) {
				$thisTNID = $record->TNID__c;

				if( $thisTNID > 50000 ) {
					$updateSQL = "Update supplier_branch set spb_acct_mngr_name = :rep_name, spb_acct_mngr_email = :rep_email where spb_branch_code = :tnid ";
					$params = array (
						'rep_name' => $record->Sales_Rep__r->Name,
						'rep_email' => $record->Sales_Rep__r->E_mail__c,
						'tnid' => $thisTNID
					);
				} else {

					if( $updateAnnualEstimatedSpendPerVessel == true ){
						$updateSQL = "Update buyer_branch set byb_acct_mngr_name = :rep_name, BYB_POTENTIAL_GMV_PER_UNIT=:potentialGmvPerUnit where byb_branch_code = :tnid ";
						$params = array (
							'rep_name' => $record->Owner->Name,
							'tnid' => $thisTNID,
							'potentialGmvPerUnit' => $record->Annual_Estimated_Spend_per_Vessel__c
						);
					}
					else{
						$updateSQL = "Update buyer_branch set byb_acct_mngr_name = :rep_name where byb_branch_code = :tnid ";
						$params = array (
							'rep_name' => $record->Owner->Name,
							'tnid' => $thisTNID
						);
					}
				}

				// post process for buyer branches, this is to update the children which doesn't have BYB_POTENTIAL_GMV_PER_UNIT to
				// get the BYB_POTENTIAL_GMV_PER_UNIT from its parent
				$sql = "
				UPDATE
				  buyer_branch c
				SET
				  c.byb_potential_gmv_per_unit = (
				    SELECT
				      p.byb_potential_gmv_per_unit
				    FROM
				      buyer_branch p
				    WHERE
				      c.byb_under_contract=p.byb_branch_code
				      -- making sure that we're only picking up child
				      AND c.byb_branch_code!=p.byb_branch_code
				      AND (
				        c.byb_potential_gmv_per_unit IS NULL
				        OR c.byb_potential_gmv_per_unit=0
				      )
				      AND p.byb_potential_gmv_per_unit>0
				  )
				WHERE
				  c.byb_under_contract IS NOT null
				  AND c.byb_branch_code!=c.byb_under_contract
				";

				if (! empty ( $thisTNID )) {
					try {
						$db->query ( $updateSQL, $params );
						$logger->log($thisTNID . ' updated with: ' . $params['rep_name'] . '(' . $params['rep_email'] . ')');
						$updatedCount ++;
					} catch ( Exception $ex ) {
						$logger->log('Failed updating: ' . $thisTNID . '; Reason: ' . $ex->getMessage());
						$reasons [] = $ex->getMessage ();
					}
				}
				$i ++;
			}
		} else {
			throw new Exception ( "No records found on salesforce." );
		}

		$logger->log('Done; ' . $updatedCount . ' was updated');


		return $reasons;
	}

	/**
	 * Retreives all records from Salesforce where a valid asset is found and updates account info including expiry date for SVR tracking info.
	 */
	public static function updateProfileRecords() {

		$credentials = Myshipserv_Config::getSalesForceCredentials();

		$connection = new SforceEnterpriseClient();
		$soapClient = $connection->createConnection($credentials->wsdl);
		$loginObj = $connection->login($credentials->username, $credentials->password . $credentials->token);

		$query = "Select Account.Name, Account.Sales_rep__r.Name,
					Account.Sales_rep__r.E_mail__c, Account.Owner.Name, Account.Owner.Email,
					Account.Tnid__c from Account where Account.Tnid__c > 0 order by Account.Tnid__c Asc";

		/* $query = "Select UsageEndDate,Account.Name, Account.Sales_rep__r.Name,
		 Account.Sales_rep__r.E_mail__c, Account.Owner.Name, Account.Owner.Email,
		Account.Tnid__c, InstallDate
		from asset
		where Product2Id = '01t000000000wKCAAY'
		and (UsageEndDate <= NEXT_N_DAYS:365) and Account.Tnid__c > 0
		ORDER BY Account.Tnid__c Asc";
		*
		*/
		$queryResult = $connection->query($query);
		$done = false;

		if (count($queryResult->records) > 0) {
			//Start the update.
			while (!$done) {
				foreach ($queryResult->records as $record) {
					$thisTNID = $record->Account->TNID__c;

					$account = $record->Account;

					$updateSQL = "Update Pages_Salesforce set psf_renewal_date = TO_DATE(:renewal, 'YYYY-MM-DD'), psf_install_date = TO_DATE(:install_date, 'YYYY-MM-DD'), psf_owner_name = :owner_name, psf_owner_email = :owner_email,
							psf_sales_rep_name = :rep_name, psf_sales_rep_email = :rep_email where PSF_TNID = :tnid";
					$params = array(
							'renewal' => $record->UsageEndDate,
							'install_date' => $record->InstallDate,
							'owner_name' => $account->Owner->Name,
							'owner_email' => $account->Owner->Email,
							'rep_name' => $account->Sales_Rep__r->Name,
							'rep_email' => $account->Sales_Rep__r->E_Mail__c,
							'tnid' => $thisTNID
					);

					self::getDb()->query($updateSQL, $params);
				}

				if ($queryResult->done != true) {

					$queryResult = $connection->queryMore($queryResult->queryLocator);
				} else {
					$done = true;
				}
			}
		}
	}

	public function querySalesforce($soql, $retryWhenFails = false)
	{
		$this->sfConnection->setQueryOptions ( new QueryOptions ( 2000 ) );
		if( $retryWhenFails === false )
		{
			$response = $this->sfConnection->query ( $soql );
		}
		else
		{
			$response = $this->querySalesforceRetryWhenFails($soql);
		}
		return $response;
	}

	/**
	 * Function to query SF, it'll keep trying it if throws error
	 * @param unknown $soql
	 * @return unknown
	 */
	public function querySalesforceRetryWhenFails($soql)
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
				if( $e->getMessage() == "Could not connect to host" )
				{
					sleep(Myshipserv_Salesforce_Base::REQUEST_PAUSE);
					$ok = false;
				}
				else
				{
					throw $e;
				}
			}
		}
		return $response;
	}


	public function updateVBPTransitionTable($row)
	{
		$db = $this->db;

		try
		{
			$supplierUpdate [$tnid] = $date;
			$sql = "
					MERGE INTO vbp_transition_date USING DUAL ON (vst_spb_branch_code = :tnid)
						WHEN MATCHED THEN
							UPDATE SET
								vst_transition_date=TO_DATE(:transitionDate, 'DD-MON-YYYY'),
								vst_contracted_under=:contractedUnder,
								vst_sf_rate_id=:rateId,
								vst_sf_contract_id=:contractId,
								vst_sf_account_id=:accountId,
								vst_date_updated_from_sf=SYSDATE
						WHEN NOT MATCHED THEN
							INSERT
								(
									vst_spb_branch_code,
									vst_transition_date,
									vst_date_updated_from_sf,
									vst_date_created,
									vst_contracted_under,
									vst_sf_rate_id,
									vst_sf_contract_id,
									vst_sf_account_id
								)
							VALUES
								(
									:tnid2,
									TO_DATE(:transitionDate, 'DD-MON-YYYY'),
									SYSDATE,
									SYSDATE,
									:contractedUnder,
									:rateId,
									:contractId,
									:accountId
								)
				";
			if ($row['tnid'] != "")
			{
				$this->logger->log( "- Updating VBP_TRANSITION_DATE for: " . $row['tnid']);
				$db->query ($sql, $row);
			}
		}
		catch ( Exception $ex )
		{
			$this->logger->log($ex->getMessage ());
			$this->logger->log(var_dump ( array (
				'tnid' => $row['tnid'],
				'transitionDate' => $row['transitionDate']
			) ) );
		}

		return true;
	}
}
