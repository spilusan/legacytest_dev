<?php
/**
 * Send Supplier Recommendation email alert
 */

class Myshipserv_Poller_SupplierRecommendations extends Shipserv_Object
{
	
	/**
	 * Constructor - initialising logger
	 */
	public function __construct()
	{
		$this->logger = new Myshipserv_Logger_File('reminder-for-supplier-recommendations');
		$this->db = $this->getDb();

	}
	
	/**
	 * Main functionality/function
	 * @return boolean
	 */
	public function poll()
	{
		$this->nm = new Myshipserv_NotificationManager($this->db);
		$this->logger->log('==================================================================================');
		$this->processAll();
		$this->logger->log('==================================================================================');
	}
	
	/**
	 * Send motification to all buyer orgs, where document is sent to a supplier, notification was enabled, and transaction was sent to a supplier, who is not on the list.
	 *
	 */
	protected function processAll()
	{
		$sql = "
			SELECT
			  mbs_byb_branch_code byb
			FROM
			  MATCH_BUYER_SETTINGS mbs
			WHERE
			  mbs.mbs_automatch = 1
			  and mbs.mbs_byb_branch_code is not null
			  and (
			    mbs.mbs_rmd_date_sent is null
			    or mbs_rmd_date_sent < SYSDATE - 28 
			  )";
		
		$res = $this->getDb()->fetchAll($sql);
		foreach ($res as $record) {
			$this->logger->log('Sending message to buyer: ' . $record['BYB']);
			$this->nm->supplierRecommendations($record['BYB']);
			$this->updateSentStatus($record['BYB']);
		}
	}
	
	/**
	 * Update the status, if the email is sent out
	 * @param integer $buyerBranch
	 */
	protected function updateSentStatus($buyerBranch)
	{
		$sql = "
			UPDATE
				MATCH_BUYER_SETTINGS
			SET
				mbs_rmd_date_sent = SYSDATE
			WHERE
				mbs_byb_branch_code = :bybBranchCode";
		$params = array(
				'bybBranchCode' => (int)$buyerBranch
		);
		
		$this->getDb()->query($sql, $params);
		
	}


}