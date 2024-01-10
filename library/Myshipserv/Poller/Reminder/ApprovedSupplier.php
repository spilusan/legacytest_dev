<?php
/**
 * Send reminder to approved suppliers, is someone send a document and not on the approved supplier list 
 */
class Myshipserv_Poller_Reminder_ApprovedSupplier extends Shipserv_Object
{
		
	/**
	 * Constructor - initialising logger
	 */
	public function __construct()
	{
		$this->logger = new Myshipserv_Logger_File('reminder-for-approved-supplier');
		$this->db = $this->getSSReport2Db();
		$this->slow = false;
		$this->delay = 1;
		$this->verboseLogging = true;
	}

	/**
	 * Main functionality/function
	 * @return boolean
	 */
	public function poll()
	{
		$env = (getenv('APPLICATION_ENV')) ? getenv('APPLICATION_ENV') : 'production';

		if (!Myshipserv_Config::isApprovedSupplierRaiseAlertEnabled()) {
		$this->logger->log('==================================================================================');
		$this->logger->log('Sending alerts was disabled in config: '.$env);
		$this->logger->log('==================================================================================');
			// do not send anything if disabled in config
			return false;
		}

		$this->logger->log('==================================================================================');
		$this->processAllBuyer();
		$this->logger->log('==================================================================================');
	}
	
	/**
	* Send motification to all buyer orgs, where document is sent to a supplier, notification was enabled, and transaction was sent to a supplier, who is not on the list.
	* 
	*/
	protected function processAllBuyer()
	{
		$sql = "
			SELECT
				bos_byo_org_code
			FROM
				buyer_org_setting
			WHERE
 				bos_approved_supplier_enabled = 1";

		$res = $this->getDb()->fetchAll($sql);
		foreach ($res as $record) {
			$this->processAllDocuments($record['BOS_BYO_ORG_CODE']);
		}
	}

	/**
	 * Processing all buyers specified in buyer_branch_setting table
	 * 
	 */
	public function processAllDocuments( $orgCode )
	{
		$dataRange = 0;

		$sql = "(SELECT DISTINCT
        			'RFQ' doctype
        			,rfq.rfq_internal_ref_no
					,rfq.spb_branch_code
					,rfq.byb_branch_code
					,rfq.rfq_subject
					,rfq.rfq_ref_no
					,rfq.rfq_submitted_date
          			,bb.byb_address_1
          			,bb.byb_address_2
          			,bb.byb_name
          			,spb.spb_name
				FROM
		  			rfq@ssreport2 rfq
		            left join supplier_branch spb
		            on rfq.SPB_BRANCH_CODE = spb.SPB_BRANCH_CODE
		            left join buyer_branch bb
		            on rfq.BYB_BRANCH_CODE = bb.BYB_BRANCH_CODE
            
            	WHERE
					rfq.byb_byo_org_code = :orgCode
					AND rfq.rfq_submitted_date BETWEEN trunc(sysdate - :dateRange) AND trunc(sysdate - :dateRangeFrom)
					AND NOT EXISTS
					(
						SELECT
							bsb_spb_branch_code 
		    			FROM
							buyer_supplier_blacklist 
		        		WHERE
		          			bsb_type = 'whitelist'
		        			AND bsb_byo_org_code = :orgCode
		        			AND bsb_spb_branch_code=rfq.spb_branch_code
      				)
		) UNION
			(SELECT DISTINCT
          			'PO' doctype
          			,ord.ord_internal_ref_no
					,ord.spb_branch_code
					,ord.byb_branch_code
					,ord.ord_subject
					,ord.ord_ref_no
					,ord.ord_submitted_date
          			,bb.byb_address_1
          			,bb.byb_address_2
          			,bb.byb_name
          			,spb.spb_name
				FROM
		  			ord@ssreport2 ord
		            inner join buyer_branch bb
		            on ord.BYB_BRANCH_CODE = bb.BYB_BRANCH_CODE
		            left join supplier_branch spb
		            on ord.SPB_BRANCH_CODE = spb.SPB_BRANCH_CODE
				WHERE
					bb.BYB_BYO_ORG_CODE = :orgCode
					AND ord.ord_submitted_date BETWEEN trunc(sysdate - :dateRange) AND trunc(sysdate - :dateRangeFrom)
					AND NOT EXISTS
					(
						SELECT
							bsb_spb_branch_code 
		    			FROM
							buyer_supplier_blacklist 
		        		WHERE
		          			bsb_type = 'whitelist'
		        			AND bsb_byo_org_code = :orgCode
		        			AND bsb_spb_branch_code=ord.spb_branch_code
      				)
              )
		";

		$params = array(
			'orgCode' => $orgCode,
			'dateRange' => $dataRange,
			'dateRangeFrom' => $dataRange-1,
		);
		$branchCodes = $this->getDb()->fetchAll($sql, $params);
		if (count($branchCodes) > 0) {
			$this->logger->log('Sending notification to buyer org: '.$orgCode);
			//echo 'Sending notification to buyer org: '.$orgCode;
			$this->sendEmailNotification($branchCodes,$orgCode);
		}
	}

	/**
	 * Inserting to email alert queue
	 * @param unknown $docId
	 * @param unknown $spbBranchCode
	 * @param unknown $docType
	 */
	protected function sendEmailNotification( $branchCodes, $orgCode )
	{
		$db = $this->db;
		foreach ($branchCodes as $branchCode) {
			$nm = new Myshipserv_NotificationManager($db);
	       	$nm->companyApprovedSuppliers($branchCode, $orgCode);
		}
	}
}