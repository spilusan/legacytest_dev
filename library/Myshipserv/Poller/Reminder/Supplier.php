<?php

/**
 * todo: add exclusion list for supplier that cannot send POC
 */
class Myshipserv_Poller_Reminder_Supplier extends Shipserv_Object
{
	/**
	 * Constructor - initialising logger
	 */
	public function __construct()
	{
		$this->logger = new Myshipserv_Logger_File('reminder-for-supplier');
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
		if (!Myshipserv_Config::isSupplierAutomaticReminderEnabled()) {
			// do not send anything if disabled in config
			return false;
		}

		$this->logger->log('==================================================================================');

		// checking if supplier replaced the document
		$this->processAllBuyer();

		$this->logger->log('==================================================================================');
	}

	/**
	 * Processing all buyers specified in buyer_branch_setting table
	 *
	 */
	public function processAllBuyer()
	{
		$this->logger->log('Processing buyer which enabled automatic reminder');
		$sql = "SELECT * FROM buyer_branch_setting WHERE bbs_rmdr_rfq_is_enabled=1 OR bbs_rmdr_ord_is_enabled=1";
		foreach ((array)$this->getDb()->fetchAll($sql) as $row) {
			$this->logger->log(' > ' . $row['BBS_BYB_BRANCH_CODE']);
			try {
				Shipserv_Buyer::getBuyerBranchInstanceById($row['BBS_BYB_BRANCH_CODE']);
				$this->processBuyer($row['BBS_BYB_BRANCH_CODE'], $row);
			} catch (Exception $e) {
				$this->logger->log(' > Error has happened for: ' . $row['BBS_BYB_BRANCH_CODE'] . '. Skipping this TNID and notify DEV and QA', $e->getMessage());
				$newmail = new Zend_Mail();
				$newmail->setFrom('info@shipserv.com');
				$newmail->addTo('pages.automatic.reminder.cron@shipserv.com');
				$newmail->setSubject('[Automatic Reminder] Error when processing buyer: ' . $row['BBS_BYB_BRANCH_CODE']);
				$newmail->setBodyText(print_r($row, true) . ' ---- Error: ' . $e->getMessage());
				$newmail->send();
			}
		}
	}

	/**
	 * Process a single buyer
	 * 
	 * @param int $buyerId
	 * @param array $setting
	 * 
	 * @return null
	 */
	public function processBuyer($buyerId, $setting)
	{
		if ($setting['BBS_RMDR_RFQ_IS_ENABLED'] == "1") {
			// process RFQ which are due
			$this->processRfqSentByBuyer($buyerId, $setting);

			// process Match RFQs
			if ($setting['BBS_RMDR_INCLUDE_MATCH'] === "1") {
				$this->processRfqSentByBuyer($buyerId, $setting, true);
			}
		} else {
			$this->logger->log(' >> RFQ Reminder turned off');
		}

		if ($setting['BBS_RMDR_ORD_IS_ENABLED'] == "1") {
			// check PO
			$this->processOrdSentByBuyer($buyerId, $setting);
		} else {
			$this->logger->log(' >> PO Reminder turned off');
		}
	}

	/**
	 * Processing all RFQs sent by this buyer
	 * @param int $buyerId
	 * @param array $setting
	 * @param string $processMatch
	 */
	protected function processRfqSentByBuyer($buyerId, $setting, $processMatch = false)
	{
		// getting the date
		$startDate = null;
		$endDate = null;
		Shipserv_DateTime::daysAgo($setting['BBS_RMDR_RFQ_SEND_AFTER'], $startDate, $endDate);

		$this->logger->log(' >> Processing All ' . ($processMatch ? 'Match ' : '') . 'RFQ(s) sent on ' . $startDate->format('d-M-Y'));

		// parameter for SQL
		$params = array(
			'sendFirstReminder' => $startDate->format('d-M-Y'), 'dateEnabled' => $setting['BBS_DATE_RFQ_ENABLED'], 'bybBranchCode' => $buyerId
		);

		if ($processMatch === false) {
			$sql = $this->getSql('RFQ', false);
		} else if ($processMatch === true) {
			// Process match forwarded RFQs
			$sql = $this->getSql('RFQ', true);
		}

		// go through each RFQs
		foreach ($this->db->fetchAll($sql, $params) as $row) {
			if ($this->verboseLogging === true) $this->logger->log(' >>> RFQ: ' . $row['RFQ_INTERNAL_REF_NO'] . ' sent on ' . $row['RFQ_SUBMITTED_DATE']);

			// check total notification which already  being sent
			if (($row['TOTAL_ALERT'] == 0) || ($setting['BBS_RMDR_RFQ_REPEAT'] > 0 && $row['TOTAL_ALERT'] < $setting['BBS_RMDR_RFQ_REPEAT'])) {
				try {
					$lastDateOfReminder = Shipserv_DateTime::fromString($row['LAST_DATE_REMINDER']);
					$now = Shipserv_DateTime::now();

					// check the last reminder date
					$days = Shipserv_DateTime::getNumOfDay($now, $lastDateOfReminder);
					if ($this->verboseLogging === true) $this->logger->log(' >>>> DEBUG :: ' . $days . ' since last sent');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> DEBUG :: sent on ' . $row['LAST_DATE_REMINDER'] . '');
				} catch (Exception $e) {
					$lastDateOfReminder = null;
					$days = null;
				}

				// if reminder never been sent before, and if it's after BBS_RMDR_RFQ_REPEAT_AFTER then send the reminder
				// and mark the RFQ as ongoing so on the next run, it'll be picked up again
				if ($row['TOTAL_ALERT'] == 0 || ($row['TOTAL_ALERT'] > 0 && $days >= $setting['BBS_RMDR_RFQ_REPEAT_AFTER'])) {
					$this->sendEmail($row['RFQ_INTERNAL_REF_NO'], $row['SPB_BRANCH_CODE'], 'RFQ');
					$this->markTransactionAsOngoing($row['RFQ_INTERNAL_REF_NO'], 'RFQ');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> ' . ($row['RFQ_REMINDER_TOTAL'] + 1) . '  were sent so far');
				} else {
					// this blog will not send the reminder since buyer's just been reminded
					if ($this->verboseLogging === true) $this->logger->log(' >>>> ' . $days . ' days since last reminder');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> Buyer\'s set for ' . $setting['BBS_RMDR_RFQ_REPEAT_AFTER'] . ' days to be reminded again');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> We\'ve sent ' . $row['TOTAL_ALERT'] . ' reminders in total');
				}
			} else if (($setting['BBS_RMDR_RFQ_REPEAT'] > 0) && ($row['TOTAL_ALERT'] >= $setting['BBS_RMDR_RFQ_REPEAT'])) {
			// if supplier's been reminder more than what's on  BBS_RMDR_RFQ_REPEAT
			// mark rfq as completed so it won't be picked up again
				if ($this->verboseLogging === true) $this->logger->log(' >>>> We have reminded ' . $row['TOTAL_ALERT'] . 'x');
				if ($this->verboseLogging === true) $this->logger->log(' >>>> Buyer\'s set for: ' . $setting['BBS_RMDR_RFQ_REPEAT'] . 'x');
				$this->markTransactionAsCompleted($row['RFQ_INTERNAL_REF_NO'], 'RFQ');
			} else {
				// if any other thing happen, debug goes here
				if ($this->verboseLogging === true) $this->logger->log(' >>>> Debug: TOTAL_ALERT: ' . $row['TOTAL_ALERT'] . ' and ' . ((is_null($setting['BBS_RMDR_RFQ_REPEAT']) || $setting['BBS_RMDR_RFQ_REPEAT'] == 0) ? 'No' : '') . ' Repeats allowed');
				$this->markTransactionAsCompleted($row['RFQ_INTERNAL_REF_NO'], 'RFQ');
			}

			if ($this->verboseLogging === true) $this->logger->log(' >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');

			if ($this->slow === true) {
				sleep($this->delay);
			}
		}
	}


	/**
	 * With similar logic as RFQ, this function is sending reminders for Orders
	 * @param int $buyerId
	 * @param string $setting
	 * @param string $processMatch
	 */
	protected function processOrdSentByBuyer($buyerId, $setting, $processMatch = false)
	{

		// getting the date
		$startDate = null;
		$endDate = null;
		Shipserv_DateTime::daysAgo($setting['BBS_RMDR_ORD_SEND_AFTER'], $startDate, $endDate);

		$this->logger->log(' >> Processing All ' . ($processMatch ? 'Match ' : '') . 'PO(s) sent on ' . $startDate->format('d-M-Y'));

		// parameter for SQL
		$params = array(
			'sendFirstReminder' => $startDate->format('d-M-Y'), 'dateEnabled' => $setting['BBS_DATE_ORD_ENABLED'], 'bybBranchCode' => $buyerId
		);

		if ($processMatch === false) {
			$sql = $this->getSql('ORD', false);
		} else if ($processMatch === true) {
			// Process match forwarded RFQs
			$sql = $this->getSql('ORD', true);
		}

		// go through each RFQs
		foreach ($this->db->fetchAll($sql, $params) as $row) {
			if ($this->verboseLogging === true) $this->logger->log(' >>> ORD: ' . $row['ORD_INTERNAL_REF_NO'] . ' sent on ' . $row['ORD_SUBMITTED_DATE']);

			// check total notification which already  being sent
			if (($row['TOTAL_ALERT'] == 0) // if no alert's been sent
			|| ($setting['BBS_RMDR_ORD_REPEAT'] > 0 && $row['TOTAL_ALERT'] < $setting['BBS_RMDR_ORD_REPEAT']) // if total alert < repeats
			) {
				try {
					$lastDateOfReminder = Shipserv_DateTime::fromString($row['LAST_DATE_REMINDER']);
					$now = Shipserv_DateTime::now();

					// check the last reminder date
					$days = Shipserv_DateTime::getNumOfDay($now, $lastDateOfReminder);
				} catch (Exception $e) {
					$lastDateOfReminder = null;
					$days = null;
				}

				// if reminder never been sent before, and if it's after BBS_RMDR_RFQ_REPEAT_AFTER then send the reminder
				// and mark the RFQ as ongoing so on the next run, it'll be picked up again
				if ($row['TOTAL_ALERT'] == 0 || ($row['TOTAL_ALERT'] > 0 && $days >= $setting['BBS_RMDR_ORD_REPEAT_AFTER'])) {
					$this->sendEmail($row['ORD_INTERNAL_REF_NO'], $row['SPB_BRANCH_CODE'], 'ORD');
					$this->markTransactionAsOngoing($row['ORD_INTERNAL_REF_NO'], 'ORD');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> ' . ($row['ORD_REMINDER_TOTAL'] + 1) . '  were sent so far');

				} else {
					// this blog will not send the reminder since buyer's just been reminded
					if ($this->verboseLogging === true) $this->logger->log(' >>>> ' . $days . ' days since last reminder');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> Buyer\'s set for ' . $setting['BBS_RMDR_ORD_REPEAT_AFTER'] . ' days to be reminded again');
					if ($this->verboseLogging === true) $this->logger->log(' >>>> We\'ve sent ' . $row['TOTAL_ALERT'] . ' reminders in total');
				}
			} else if (($setting['BBS_RMDR_ORD_REPEAT'] > 0)
				&& ($row['TOTAL_ALERT'] >= $setting['BBS_RMDR_ORD_REPEAT'])) {
				// if supplier's been reminder more than what's on  BBS_RMDR_RFQ_REPEAT
				// mark rfq as completed so it won't be picked up again
				if ($this->verboseLogging === true) $this->logger->log(' >>>> We have reminded ' . $row['TOTAL_ALERT'] . 'x');
				if ($this->verboseLogging === true) $this->logger->log(' >>>> Buyer\'s set for: ' . $setting['BBS_RMDR_ORD_REPEAT'] . 'x');
				$this->markTransactionAsCompleted($row['ORD_INTERNAL_REF_NO'], 'ORD');
			} else {
				// if any other thing happen, debug goes here
				if ($this->verboseLogging === true) $this->logger->log(' >>>> Debug: TOTAL_ALERT: ' . $row['TOTAL_ALERT'] . ' and ' . ((is_null($setting['BBS_RMDR_ORD_REPEAT']) || $setting['BBS_RMDR_ORD_REPEAT'] == 0) ? 'No' : '') . ' Repeats allowed');
				$this->markTransactionAsCompleted($row['ORD_INTERNAL_REF_NO'], 'ORD');
			}

			if ($this->verboseLogging === true) $this->logger->log(' >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
			if ($this->slow === true) {
				sleep($this->delay);
			}
		}
	}


	/**
	 * Marking document as ongoing and increment the total reminder sent flag
	 * @param int $documentId
	 * @param string $documentType
	 */
	protected function markTransactionAsOnGoing($documentId, $documentType)
	{
		$this->logger->log(' >>> Marking document as ongoing');

		if ($documentType == 'RFQ') {
			$sql = "UPDATE rfq SET rfq_reminder_last_sent=SYSDATE, rfq_reminder_ongoing=1, rfq_reminder_total=(CASE WHEN rfq_reminder_total IS NULL THEN 1 ELSE rfq_reminder_total+1 END) WHERE rfq_internal_ref_no=:documentId";
		} else if ($documentType == 'ORD') {
			$sql = "UPDATE ord SET ord_reminder_last_sent=SYSDATE, ord_reminder_ongoing=1, ord_reminder_total=(CASE WHEN ord_reminder_total IS NULL THEN 1 ELSE ord_reminder_total+1 END) WHERE ord_internal_ref_no=:documentId";
		}

		$this->db->query($sql, compact('documentId'));
		$this->db->commit();
	}

	/**
	 * Marking document as processed so it won't be picked up again
	 * @param int $documentId
	 * @param string $documentType
	 */
	protected function markTransactionAsCompleted($documentId, $documentType)
	{
		$this->logger->log(' >>>>> Marking document as processed');
		if ($documentType == 'RFQ') {
			$sql = "UPDATE rfq SET rfq_reminder_completed=1 WHERE rfq_internal_ref_no=:documentId";
		} else if ($documentType == 'ORD') {
			$sql = "UPDATE ord SET ord_reminder_completed=1 WHERE ord_internal_ref_no=:documentId";
		}
		$this->db->query($sql, compact('documentId'));
		$this->db->commit();
	}

	/**
	 * Inserting to email alert queue
	 * @param int $docId
	 * @param int $spbBranchCode
	 * @param string $docType
	 */
	protected function sendEmail($docId, $spbBranchCode, $docType)
	{
		$sql = "
			INSERT INTO email_alert_queue(eaq_id, eaq_internal_ref_no, eaq_spb_branch_code, eaq_alert_type, eaq_created_date)
			VALUES (sq_email_alert_queue.nextval,:docId, :spbBranchCode, :alertType, TO_DATE(:dateTime))
		";

		$d = new DateTime;

		$params = array(
			'docId' => $docId,
			'spbBranchCode' => $spbBranchCode,
			'alertType' => (($docType == 'RFQ') ? 'RFQ_RMD' : 'ORD_RMD'),
			'dateTime' => $d->format('d-M-Y')
		);

		$this->getDb()->query($sql, $params);

		if ($this->verboseLogging === true) {
			$this->logger->log(' >>> Sent');
		}
	}


	/**
	 * Returning SQL for each use case
	 *
	 * @param   string  $docType
	 * @param   bool    $isMatch
	 *
	 * @return  string
	 */
	protected function getSql($docType, $isMatch)
	{
		if ($docType == 'RFQ') {
			if ($isMatch === true) {
				// for match, we need to remind supplier selected by match
				$sql = "
					SELECT
					*
					FROM
					(
					SELECT
						rfq.rfq_internal_ref_no
						, rfq.rfq_submitted_date
						, rfq.rfq_reminder_total
						, rfq.rfq_reminder_total total_alert
						, rfq.rfq_reminder_last_sent last_date_reminder
						, qot.qot_internal_ref_no
						, rfq_resp.rfq_resp_date
						, rs2m.byb_branch_code
						, rfq.spb_branch_code
						, rfq.rfq_ref_no
						, (
							SELECT spb_smart_product_name FROM supplier_branch@livedb_link
							WHERE spb_branch_code=rfq.spb_branch_code
						) is_on_smart_supplier
						, (
							SELECT
							COUNT(*)
							FROM
							rfq r
							WHERE
							r.rfq_event_hash=rfq.rfq_event_hash
							AND r.rfq_linkable_ord IS NOT null
						) po_for_rfq_event
					FROM
						rfq rs2m JOIN rfq ON (rs2m.rfq_internal_ref_no=rfq.rfq_pom_source)
						JOIN request_for_quote@livedb_link ON (rfq.rfq_internal_ref_no=request_for_quote.rfq_internal_ref_no)

						-- added by Yuriy Akopov on 2015-12-23 to stop sending emails for cancelled RFQ but the query seems ugly and should be re-written later
						JOIN rfq_quote_relation@livedb_link rqr ON
							rqr.rqr_rfq_internal_ref_no = rfq.rfq_internal_ref_no
							AND rqr.rqr_spb_branch_code = rfq.spb_branch_code
							AND rqr.rqr_rfq_cancelled_date IS NULL

						LEFT OUTER JOIN qot ON (rfq.rfq_internal_ref_no=qot.rfq_internal_ref_no)
							LEFT OUTER JOIN rfq_resp ON (rfq_resp.rfq_internal_ref_no=rfq.rfq_internal_ref_no AND rfq_resp_sts='DEC')
					WHERE
						(
							(
								rfq.rfq_submitted_date > TO_DATE(:dateEnabled)
								AND rfq.rfq_submitted_date BETWEEN TO_DATE(:sendFirstReminder) AND TO_DATE(:sendFirstReminder)+1
							)
							OR rfq.rfq_reminder_ongoing=1
						)
						AND rs2m.byb_branch_code=:bybBranchCode
						AND rs2m.spb_branch_code=999999
						AND qot.qot_internal_ref_no IS NULL
						AND rfq_resp.rfq_resp_date IS NULL
						AND rfq.spb_branch_code!=999999
						AND rfq.rfq_linkable_ord IS NULL
						AND rfq.rfq_reminder_completed IS NULL

						-- S14834 - Change cron behaviour to check 'quote by date'
						AND (
							REQUEST_FOR_QUOTE.RFQ_ADVICE_BEFORE_DATE > SYSDATE
							-- added by Yuriy Akopov, DE6306
							OR REQUEST_FOR_QUOTE.RFQ_ADVICE_BEFORE_DATE IS NULL
						)

					)
					WHERE
					po_for_rfq_event=0";
			} else {
				// pull all RFQ that isn't responded (quoted or declined)
				$sql = "
					SELECT
					*
					FROM
					(
					SELECT
						rfq.rfq_internal_ref_no
						, rfq.rfq_submitted_date
						, rfq.rfq_reminder_total

						, rfq.rfq_reminder_total total_alert
						, rfq.rfq_reminder_last_sent last_date_reminder

						, qot.qot_internal_ref_no
						, rfq_resp.rfq_resp_date
						, rfq.byb_branch_code
						, rfq.spb_branch_code
						, (
							SELECT
							COUNT(*)
							FROM
							rfq r
							WHERE
							r.rfq_event_hash=rfq.rfq_event_hash
							AND r.rfq_linkable_ord IS NOT null
						) po_for_rfq_event
					FROM
						rfq JOIN request_for_quote@livedb_link ON (rfq.rfq_internal_ref_no=request_for_quote.rfq_internal_ref_no)

						-- added by Yuriy Akopov on 2015-12-23 to stop sending emails for cancelled RFQ but the query seems ugly and should be re-written later
						JOIN rfq_quote_relation@livedb_link rqr ON
						rqr.rqr_rfq_internal_ref_no = rfq.rfq_internal_ref_no
						AND rqr.rqr_spb_branch_code = rfq.spb_branch_code
						AND rqr.rqr_rfq_cancelled_date IS NULL

						LEFT OUTER JOIN qot ON (rfq.rfq_internal_ref_no=qot.rfq_internal_ref_no)
						LEFT OUTER JOIN rfq_resp ON (rfq_resp.rfq_internal_ref_no=rfq.rfq_internal_ref_no AND rfq_resp_sts='DEC')
					WHERE
						(
							(
								rfq.rfq_submitted_date > TO_DATE(:dateEnabled)
								AND rfq.rfq_submitted_date BETWEEN TO_DATE(:sendFirstReminder) AND TO_DATE(:sendFirstReminder)+1
							)
							OR rfq.rfq_reminder_ongoing=1
						)

						AND rfq.byb_branch_code=:bybBranchCode
						AND qot.qot_internal_ref_no IS NULL
						AND rfq_resp.rfq_resp_date IS NULL
						AND rfq.spb_branch_code!=999999
						AND rfq.rfq_linkable_ord IS NULL
						AND rfq.rfq_is_latest=1
						AND rfq.rfq_reminder_completed IS NULL

						-- S14834 - Change cron behaviour to check 'quote by date'
						AND (
							REQUEST_FOR_QUOTE.RFQ_ADVICE_BEFORE_DATE > SYSDATE
							-- added by Yuriy Akopov, DE6306
							OR REQUEST_FOR_QUOTE.RFQ_ADVICE_BEFORE_DATE IS NULL
						)

					)
					WHERE
					po_for_rfq_event=0";
			}
		} else if ($docType == 'ORD') {
			/**
			 * for PO, we don't care if it's match or not
			 * since they have to import the qot and raise PO off it
			 */
			$sql = "
				SELECT
					ord.ord_internal_ref_no
				, ord.rfq_internal_ref_no
				, ord.ord_submitted_date
				, ord.ord_reminder_total

				, ord.ord_reminder_total total_alert
				, ord.ord_reminder_last_sent last_date_reminder

				, poc.poc_submitted_date
				, ord_resp.ord_resp_date
				, ord.byb_branch_code
				, ord.spb_branch_code
				, ord.ord_ref_no
				, (
					SELECT spb_smart_product_name FROM supplier_branch@livedb_link
					WHERE spb_branch_code=ord.spb_branch_code
				) is_on_smart_supplier
				, (
					SELECT COUNT(*)
					FROM
						rfq r JOIN ord o
						ON (
						r.rfq_linkable_ord=o.ord_internal_ref_no
						)
					WHERE
						o.byb_branch_code=ord.byb_branch_code
						AND o.spb_branch_code!=ord.spb_branch_code
						AND r.rfq_event_hash=rfq.rfq_event_hash
				) is_partial_order

				FROM
				ord LEFT OUTER JOIN request_for_quote@livedb_link rfq 
				ON (ord.rfq_internal_ref_no=rfq.rfq_internal_ref_no)
					LEFT OUTER JOIN poc
					ON (ord.ord_internal_ref_no=poc.ord_internal_ref_no)
					LEFT OUTER JOIN ord_resp
					ON (ord_resp.ord_internal_ref_no=ord.ord_internal_ref_no)
				WHERE
					(
						(
							ord.ord_submitted_date > TO_DATE(:dateEnabled)
							AND ord.ord_submitted_date BETWEEN TO_DATE(:sendFirstReminder) AND TO_DATE(:sendFirstReminder)+1
						)
						OR ord.ord_reminder_ongoing=1
					)
					AND ord.byb_branch_code=:bybBranchCode
					AND ord_resp.ord_resp_date IS NULL
					AND poc.poc_submitted_date IS NULL
					AND ord.ord_is_latest=1
					AND ord.ord_reminder_completed IS NULL";
		}
		return $sql;
	}
}
