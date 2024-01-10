<?php
/**
* Monitoring testorder@shipserv.com/order@shipserv.com if buyer want to change the quote from the supplier
*/
class Myshipserv_Poller_ErroneousTransactionMonitor extends Shipserv_Object
{

	const ERRONEOUS_CURRENCY_QOT_PO = 'ERRONEOUS_CURRENCY_QOT_PO';
	const ERRONEOUS_CURRENCY_PO_POC = 'ERRONEOUS_CURRENCY_PO_POC';
	const DAYS_BEFORE_SECOND_REMINDER = 5;
	const DAYS_BEFORE_ALERTING_GSD = 5;

	const
	BASE_SQL_TYPE_SUPPLIER = 0,
	BASE_SQL_TYPE_SECOND = 1,
	BASE_SQL_TYPE_GSD = 2,
	BASE_SQL_TYPE_BUYER = 3
	;

	protected $db;
	protected $canLog;
	protected $jobInformation;
	/**
	* Constructor - initialising logger
	* @param boolean $canLog Enable or disable logging 
	*/
	public function __construct($canLog = true)
	{
		if ($canLog === true) {
			$this->logger = new Myshipserv_Logger_File('erroneous-transaction-monitor');
		}
		$this->canLog = $canLog;
		$this->db = $this->getSSReport2Db();
		$this->jobInformation = null;
	}

	/**
	* Actual processing data and sending out emails
	*
	* @return boolean if erroneous txn was enabled returns true
	*/
	public function poll()
	{
		if (!Myshipserv_Config::isErroneousTransactionNotificationEnabled()) {
			// do not send anything if disabled in config
			return false;
		}

		$this->log('==================================================================================');

		// checking if supplier replaced the document
		$this->processCheckingIfPurchaseOrderAlreadyReplaced();

		// processing new PO
		$this->processPurchaseOrder();

		// processiong PO for  to send
		$this->processSecondReminder();

		// checking if it's been 5 days since the alert sent to buyer/supplier
		$this->processNotificationToGsd();

		$this->log('==================================================================================');

		return true;
	}

	/**
	* Public function, can be called by the landing page, where the supplier goes after clicking on the link in the reminder email
	* @param integer $ordInternalRefNo Order Internal Reference number
	* @param boolean $log              Set if the process should be logged
	* @param boolean $skipCheck        Skip checking if buyer already notified
	*
	* @return bool indicatges if the email was sent or not
	*/
	public function sendToBuyer($ordInternalRefNo, $log = true, $skipCheck = false)
	{
		return $this->processBuyer($ordInternalRefNo, $log, $skipCheck);
	}

	/**
	* Resend notification to supplier
	* @param integer $ordInternalRefNo Order Internal Reference Nr
	* @param boolean $log              If the process should be logged
	*
	* @return bool If the process was successfull
	*/
	public function sendToSupplier($ordInternalRefNo, $log = true)
	{
		return $this->processSupplier($ordInternalRefNo, $log);
	}

	/**
	* Check if the Purchase Order was already replaced, and update the erroneous_txn_notification table
	* @return null
	*/
	protected function processCheckingIfPurchaseOrderAlreadyReplaced()
	{
		$this->log('Checking if any erroneous transaction have been replaced');

		$sql = "
			UPDATE
			  erroneous_txn_notification
			SET
			  etn_corrected_date=(
			    SELECT ord_submitted_date
			    FROM ord
			    WHERE
			      ord_original=etn_ord_internal_ref_no
			      and rownum = 1
			  )
			WHERE
			  etn_corrected_date IS null
			  AND etn_notification_date IS NOT null
		";

		$this->db->query($sql);
		$this->log('End');
	}

	/**
	* Get all rows for the purchase order
	* @return array list of rows
	*/
	protected function getRowsForPurchaseOrder()
	{
		$params = $this->getJobInformation();

		$sql = $this->getBaseSql(self::BASE_SQL_TYPE_SUPPLIER);
		$lastOrderNo = $params[0]['LAST_ORDER_NO'];
		$lastOrderId =$params[0]['ERRONEOUS_LAST_ORD_ID'];
		$params = array(
			'lastOrderNo' => $lastOrderNo,
			'lastOrderId' => $lastOrderId
		);
		
		$this->log('Processing ' . ($lastOrderNo - $lastOrderId) . ' POs Start with ord_internal_ref_no=' . $params['lastOrderId']);
		$this->log(' getting all suspicious Purchase Order');

		$rows = $this->db->fetchAll($sql, $params);
		$rows = $this->groupLineItemByOrder($rows);
		return $rows;
	}

	/**
	* Get the list of reminder to be sent
	* @return array The list
	*/
	protected function getRowsForSecondReminder()
	{
		$this->log('Getting all PO which need to be notified to Supplier as a reminder');
		$sql = $this->getBaseSql(self::BASE_SQL_TYPE_SECOND);
	
		$params = array(
			'numOfDaysThreshold' => self::DAYS_BEFORE_SECOND_REMINDER
		);

		$rows = $this->db->fetchAll($sql, $params);
		$rows = $this->groupLineItemByOrder($rows);

		return $rows;
	}

	/**
	* This function is called outside the PHP CLI cronjob when we press the button of resend 
	* @param integer $ordInternalRefNo Order internal reference number
	*
	* @return array  The row(s) of the erroneous txn items  
	*/
	public function getRowsForBuyer($ordInternalRefNo)
	{
		$sql = $this->getBaseSql(self::BASE_SQL_TYPE_BUYER);
	
		$params = array(
			'ordInternalRefNo' => $ordInternalRefNo
		);

		$rows = $this->db->fetchAll($sql, $params);
		$rows = $this->groupLineItemByOrder($rows);

		return $rows;
	}

	/**
	* We have to group the line items per order
	* @param array $rows The rows we have to group
	* @return array the groupped list
	*/
	protected function groupLineItemByOrder($rows)
	{
		$data = array();
		$lineItemData = array();
		foreach ($rows as $row) {
			$data[$row['ORD_INTERNAL_REF_NO']] = $row;
			$data[$row['ORD_INTERNAL_REF_NO']]['lineItems'] = array();

			if ($row['ORDER_LI_DESC']!="") {
				$lineItemData[$row['ORD_INTERNAL_REF_NO']][] = array(
					'OLI_UNIT_COST' => $row['OLI_UNIT_COST']
					, 'QLI_UNIT_COST' => $row['QLI_UNIT_COST']
					, 'OLI_UNIT' => $row['OLI_UNIT']
					, 'QLI_UNIT' => $row['QLI_UNIT']
					, 'ORDER_LI_DESC' => $row['ORDER_LI_DESC']
					, 'QUOTE_LI_DESC' => $row['QUOTE_LI_DESC']
					, 'QLI_LINE_ITEM_NUMBER' => $row['QLI_LINE_ITEM_NUMBER']
					, 'OLI_ORDER_LINE_ITEM_NO' => $row['OLI_ORDER_LINE_ITEM_NO']
				);
			}
		}

		foreach ($data as $key => $d) {
			$data[$key] = $d;
			$data[$key]['lineItems'] = $lineItemData[$key];
		}

		return $data;
	}

	/**
	* Insert the reason into the datatable
	* @param integer $orderId Order internal reference nr
	* @param string  $reason  The reason of erroneous transaction
	*
	* @return null
	*/
	protected function insertReason($orderId, $reason)
	{
		$sql = "
			INSERT INTO
				erroneous_txn_check_result (etc_etn_ord_internal_ref_no, etc_description)
			VALUES
				(:orderId, :reason)
		";

		$this->db->query($sql, array('orderId' => $orderId, 'reason' => $reason));
	}

	/**
	* Process the purchase orders
	* @return null
	*/
	protected function processPurchaseOrder()
	{
		$rows = $this->getRowsForPurchaseOrder();
		foreach ($rows as $row) {
			// processing each order when the amount is the same
			// but the currency is different
			$this->log('');
			$this->log(' Order: ' . $row['ORD_INTERNAL_REF_NO'] . ' is erroneous; reasons: ');

			if ($row['CURRENCY_CHECK'] == 1) {
				$this->log('   > currency differs');
				$this->insertReason($row['ORD_INTERNAL_REF_NO'], 'PO_QOT_DIFFERENT_CURRENCY');
			}

			if ($row['UNIT_PRICE_CHECK'] == 1) {
				$this->log('   > unit price differs');
				$this->insertReason($row['ORD_INTERNAL_REF_NO'], 'PO_QOT_LINE_ITEM_DIFFERENT_UNIT_PRICE');
			}

			if ($row['BIG_ORDER_CHECK'] == 1) {
				$this->log('   > large order');
				$this->insertReason($row['ORD_INTERNAL_REF_NO'], 'PO_LARGE_ORDER');
			}

			try {
				$this->sendNotification($row['ORD_INTERNAL_REF_NO'], $row);
			} catch(Exception $e) {
				$this->log('Cannot send email; reason: ' . $e->getMessage());
			}
		}

		$jobInfo = $this->getJobInformation();
		$lastOrderNo = $jobInfo[0]['LAST_ORDER_NO'];

		$sql = "
				UPDATE
					config
				SET
					ERRONEOUS_LAST_ORD_ID=:lastOrderNo
		";
		$this->db->query($sql, array('lastOrderNo' => $lastOrderNo));

		$this->log('End');
	}

	/**
	* Process a particluar order, and send email to the buyer
	* @param integer $ordInternalRefNo Order internal reference number
	* @param boolean $log              Flag if we have to log it
	* @param boolean $skipCheck        Check if already notified
	*
	* @return boolean true, or false, indicating if the message was sent
	*/
	protected function processBuyer($ordInternalRefNo, $log = true, $skipCheck = false)
	{
		
		$result = false;
		$rows = $this->getRowsForBuyer($ordInternalRefNo);

		foreach ($rows as $row) {
			try {
				$result = $this->sendNotificationToBuyer($row['ORD_INTERNAL_REF_NO'], $row, $log, $skipCheck);
			} catch(Exception $e) {
				$result = false;
			}
		} 

		return $result;
	}

	/**
	* Process a particluar order, and send email to the supplier
	* @param integer $ordInternalRefNo Order internal reference number
	* @param boolean $log              Flag to log the event
	*
	* @return boolean true, or false, indicating if the message was sent
	*/
	protected function processSupplier($ordInternalRefNo, $log = true)
	{
		$result = false;
		$rows = $this->getRowsForBuyer($ordInternalRefNo);

		foreach ($rows as $row) {
			try {
				$result = $this->sendNotificationToSupplier($row['ORD_INTERNAL_REF_NO'], $row, false, $log);
			} catch(Exception $e) {
				$result = false;
			}
		} 

		return $result;
	}

	/**
	* Process reminders
	* @return null
	*/
	protected function processSecondReminder()
	{
		$rows = $this->getRowsForSecondReminder();
		foreach ($rows as $row) {
			// processing each order when the amount is the same
			// but the currency is different
			$this->log('');
			$this->log(' Reminder: Order: ' . $row['ORD_INTERNAL_REF_NO'] . ' is erroneous; reasons: ');

			try {
				$this->sendNotification($row['ORD_INTERNAL_REF_NO'], $row, true);
			} catch(Exception $e) {
				$this->log('Cannot send email; reason: ' . $e->getMessage());
			}
		}

		$this->log('End');
	}

	/**
	* Get the rows send to GSD
	* @return array List of erroneous txns 
	*/
	protected function getRowsForGsd()
	{
		$this->log('Getting all PO which need to be notified to GSD');
		$sql = $this->getBaseSql(self::BASE_SQL_TYPE_GSD);
	
		$params = array(
			'numOfDaysThreshold' => self::DAYS_BEFORE_ALERTING_GSD
		);

		$rows = $this->db->fetchAll($sql, $params);
		$rows = $this->groupLineItemByOrder($rows);

		return $rows;
	}

	/**
	* Process sending notifiction to GSD
	* @return null
	*/
	protected function processNotificationToGsd()
	{
		$this->log('Processing Notification to GSD');

		$rows = $this->getRowsForGsd();

		foreach ($rows as $row) {
			// processing each order when the amount is the same
			// but the currency is different
			$this->log('');
			$this->log(' Order: ' . $row['ORD_INTERNAL_REF_NO'] . ' is erroneous; reasons: ');

			if( $row['CURRENCY_CHECK'] == 1 ) $this->log('   > currency differs');
			if( $row['UNIT_PRICE_CHECK'] == 1 ) $this->log('   > unit price differs');
			if( $row['BIG_ORDER_CHECK'] == 1 ) $this->log('   > large order');

			try {
				$this->sendNotificationToGsd($row['ORD_INTERNAL_REF_NO'], $row);
			} catch(Exception $e) {
				$this->log('Cannot send email; reason: ' . $e->getMessage());
			}
		}

		$this->log('End');
	}

	/**
	* Get the batch size and last order ID we have to use for processing 
	* @return array Array keys erroneous_last_ord_id, erroneous_batch_size, last_order_no (where erroneous_batch_size is deprecated now)
	*/
	protected function getJobInformation()
	{
		if ($this->jobInformation === null) {
			$sql = "
				SELECT
					erroneous_last_ord_id,
					erroneous_batch_size,
					(
						SELECT
							max(ord_internal_ref_no) ord_internal_ref_no
						FROM
							ord
					) last_order_no
				FROM
					config
			";

			$this->jobInformation = $this->db->fetchAll($sql);

		}

		return $this->jobInformation;
	}

	/**
	* This will check if buyer already been notified
	* @param integer $orderId Order Internal Ref No
	*
	* @return boolean if Supplier is notified set to true
	*/
	protected function isSupplierNotified($orderId)
	{
		$sql = "
			SELECT
				COUNT(*)
			FROM
				erroneous_txn_notification
			WHERE
				etn_ord_internal_ref_no=:orderId
				AND etn_notification_date IS NOT null
		";

		$params = array( 'orderId' => $orderId );
		return ($this->db->fetchOne($sql, $params) > 0);
	}

	/**
	* This will check if GSD's already been notified
	* @param integer $orderId Order Internal Ref No
	*
	* @return boolean if GSD is notified set to true
	*/
	protected function isGSDNotified($orderId)
	{
		$sql = "
			SELECT
				COUNT(*)
			FROM
				erroneous_txn_notification
			WHERE
				etn_ord_internal_ref_no=:orderId
				AND ETN_GSD_NOTIFIED_DATE IS NOT null
		";

		$params = array('orderId' => $orderId);
		return ($this->db->fetchOne($sql, $params) > 0);
	}

	/**
	* This will check if BUYER is already been notified
	* @param integer $orderId Order internal reference nr.
	*
	* @return boolear Set to true if buyer already notified
	*/
	protected function isBuyerNotified($orderId)
	{
		$sql = "
			SELECT
				COUNT(*)
			FROM
				erroneous_txn_notification
			WHERE
				etn_ord_internal_ref_no=:orderId
				AND etn_buy_notification_date IS NOT null
		";

		$params = array('orderId' => $orderId);
		return ($this->db->fetchOne($sql, $params) > 0);
	}


	/**
	* Check if buyer's notified - if they have and it's been 5 days, then
	* @param integer $orderId Order internal refenence No
	* @param array   $data      List of erroneous Txn
	* @param boolean $second    Reminder
	* @param boolead $skipCheck Skip checking if alrady notified 
	*
	* @return boolean success
	*/
	protected function sendNotification($orderId, $data, $second = false, $skipCheck = false)
	{
		// first check if buyer's been notified previously or not

		if ($this->isSupplierNotified($orderId) === false || $second === true || $skipCheck === true) {
			$message = ($second) ? '   sending second notification to '.$orderId : '   sending notification to '.$orderId ;
			$this->log($message);
			$this->sendNotificationToSupplier($orderId, $data, $second);
		} else {
			// if buyer's already been notified then we need to check if it's been 5 days since the buyer's
			// been notified. if it has; then escalate it to GSD
			$this->log('   buyer already notified');
			// check if it's been 5 days
			return false;
		}
	}

	/**
	* Send notifications to GSD
	* @param integer $orderId The order internal ref no
	* @param array   $data    The list of erroneous transactions
	*
	* @return boolean Set true if success
	*/
	protected function sendNotificationToGsd($orderId, $data)
	{

		if ($this->isGSDNotified($orderId) === true) {
			$this->log('   GSD already notified');
			return false;
		}

		$notificationManager = new Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());

		// if notification's successfully sent to buyer, then mark it on the db so
		// we do not resend this again.
		if ($notificationManager->sendErroneousTransactionNotificationToSupplier($orderId, $data, true) === true) {
			$this->log('   GSD notification sent, logging it to db');

			$sql = "
				UPDATE
					erroneous_txn_notification
				SET
					etn_gsd_notified_date=sysdate
				WHERE
					etn_ord_internal_ref_no=:ordId
			";

			$params = array(
				'ordId' => $orderId
			);

			$this->db->query($sql, $params);
			$this->log('   inserted.');
		}
	}

	/**
	* Sending notificatin to buyer, This function is called, if the buyer clicked the send ling in the email
	* @param integer $orderId Order internal refenence No
	* @param array   $data      List of erroneous Txn
	* @param boolean $log       if we have to log
	* @param boolead $skipCheck Skip checking if alrady notified 
	*
	* @return boolean success
	*/
	protected function sendNotificationToBuyer($orderId, $data, $log = true, $skipCheck = false)
	{

		if ($this->isBuyerNotified($orderId) === true && $skipCheck === false) {
			return false;
		} else {
			$notificationManager = new Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());

			// if notification's successfully sent to buyer, then mark it on the db so
			// we do not resend this again.
			if ($notificationManager->sendErroneousTransactionNotificationToBuyer($orderId, $data) === true) {
				if ($log == true) {
					$sql = "
						UPDATE erroneous_txn_notification
							SET etn_buy_notification_date = sysdate
						WHERE
							etn_ord_internal_ref_no = :ordId
							and etn_doc_type = :docType
						";
					$params = array(
						'ordId' => $orderId
						, 'docType' => 'ORD'
					);
					$this->db->query($sql, $params);
				}
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	* Send notification the Supplier
	* @param integer $orderId Order internal ref no
	* @param array   $data    Array of row
	* @param boolean $second  Is reminder
	* @param boolean $log     We have to log it or not
	*
	* @return null
	*/
	protected function sendNotificationToSupplier($orderId, $data, $second = false, $log = true)
	{
		$notificationManager = new Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());

		// if notification's successfully sent to buyer, then mark it on the db so
		// we do not resend this again.
		//echo var_export($data, true);
		if ($notificationManager->sendErroneousTransactionNotificationToSupplier($orderId, $data, false, $second) === true) {
			if ($log == true) {
				if ($second) {
					$this->log('   second notification sent to supplier, logging it to db');
					$sql = "
						UPDATE erroneous_txn_notification
							SET etn_sec_notification_date = sysdate
						WHERE
							etn_ord_internal_ref_no = :ordId
							and etn_doc_type = :docType
						";
					$params = array(
						'ordId' => $orderId
						, 'docType' => 'ORD'
					);
					$this->db->query($sql, $params);
					$this->log('   inserted.');
				} else {
					$this->log('   notification sent to supplier, logging it to db');
					$sql = "
						INSERT INTO
							erroneous_txn_notification(
								etn_ord_internal_ref_no
								, etn_doc_type
								, etn_notification_date
							)
							VALUES (
								:ordId
								, :docType
								, sysdate
							)
					";
					$params = array(
						'ordId' => $orderId
						, 'docType' => 'ORD'
					);
					$this->db->query($sql, $params);
					$this->log('   inserted.');
				}
			}
		}
	}

	/**
	* Get the base SQL query accordint to the SQL type set
	* Possible values are: Myshipserv_Poller_ErroneousTransactionMonitor::BASE_SQL_TYPE_SUPPLIER , BASE_SQL_TYPE_SECOND , BASE_SQL_TYPE_GSD = 2, 	BASE_SQL_TYPE_BUYER 
	* @param integer $baseSqlType The paramaters explained above
	*
	* @return string SQL
	*/
	protected function getBaseSql($baseSqlType)
	{

		$sql = "
			WITH base_data AS
			(
			    SELECT
			      /*+ FIRST_ROWS(100) */
			      -- document id
			      o.ord_internal_ref_no
			      , q.qot_internal_ref_no

			      -- unit cost per line item
			      , ol.oli_unit_cost
			      , ql.qli_unit_cost
  				  , ol.oli_unit
				  , ql.qli_unit
				  , ql.qli_quantity
				  , ol.oli_quantity
				  , ql.qli_line_item_number
				  , ol.oli_order_line_item_no

			      -- line items
			      , ol.oli_desc || '<ss>' ||  ol.oli_confg_name || '<ss>' || ol.oli_confg_manufacturer || '<ss>' || ol.oli_confg_model_no || '<ss>' || ol.oli_confg_serial_no || '<ss>' || ol.oli_id_code order_li_desc
			      , ql.qli_desc || '<ss>' || ql.qli_confg_name || '<ss>' || ql.qli_confg_manufacturer || '<ss>' || ql.qli_confg_model_no || '<ss>' || ql.qli_confg_serial_no || '<ss>' || ql.qli_id_code quote_li_desc

			      -- currency
			      , o.ord_currency
			      , q.qot_currency

			      -- total cost
			      , o.ord_total_cost
			      , q.qot_total_cost

			      -- attributes
			      , o.ord_submitted_date
				  , (
					  SELECT
					  	COUNT(*) declined
					  FROM
					  	order_response@livedb_link orp 
					  WHERE
					  	orp.orp_ord_internal_ref_no = o.ord_internal_ref_no
					  	and orp.orp_byb_branch_code = o.byb_branch_code
					  	and orp.orp_spb_branch_code = o.spb_branch_code
					  	and (
					  		orp.orp_ord_sts = 'DEC'
					  		or orp.orp_ord_sts = 'ACC'
					  	)
				  ) ord_is_dec_or_acc
				, (
					SELECT
						p.poc_total_cost
					FROM
						poc p
					WHERE
						p.ord_internal_ref_no=o.ord_internal_ref_no
						and p.byb_branch_code = o.byb_branch_code
						and p.spb_branch_code = o.spb_branch_code
						and p.poc_total_cost = o.ord_total_cost
						and rownum = 1
				) poc_total_cost
			    FROM\n";
        $sql .= $this->getBaseSqlFromBody($baseSqlType);
		$sql .="), validation_data AS
			(
			  SELECT
				DISTINCT
			    ( CASE WHEN ord_currency!=qot_currency AND ord_total_cost=qot_total_cost THEN 1 ELSE 0 END ) currency_check
			    , (
					(
						CASE
							WHEN
								qli_unit_cost > 0
								AND ord_currency=qot_currency
								AND oli_unit=qli_unit
								AND order_li_desc=quote_li_desc
								AND qli_quantity = oli_quantity
								AND
								(
									( CASE WHEN oli_unit_cost>0 THEN qli_unit_cost/oli_unit_cost ELSE 0 END ) > 10 OR
									( CASE WHEN oli_unit_cost>0 THEN qli_unit_cost/oli_unit_cost ELSE 0 END) <= 0.1
								)
							THEN 1
							ELSE 0
						END
					)
				) unit_price_check
			    , ( CASE WHEN GET_DOLLAR_VALUE(ord_currency, ord_total_cost, ord_submitted_date) > 500000 THEN 1 ELSE 0 END) big_order_check
			    , base_data.*
			  FROM
			    base_data
			  WHERE
			    -- line item comparison - 100%
			    order_li_desc=quote_li_desc
			)
			SELECT
			  DISTINCT
			  currency_check
			  , unit_price_check
			  , big_order_check
			  , ord_internal_ref_no
			  , qot_internal_ref_no
			  , (CASE WHEN unit_price_check=1 THEN oli_unit ELSE null END ) oli_unit
			  , (CASE WHEN unit_price_check=1 THEN qli_unit ELSE null END ) qli_unit
			  , (CASE WHEN unit_price_check=1 THEN oli_unit_cost ELSE null END ) oli_unit_cost
			  , (CASE WHEN unit_price_check=1 THEN qli_unit_cost ELSE null END ) qli_unit_cost
			  , (CASE WHEN unit_price_check=1 THEN order_li_desc ELSE null END ) order_li_desc
			  , (CASE WHEN unit_price_check=1 THEN quote_li_desc ELSE null END ) quote_li_desc
			  , ord_currency
			  , qot_currency
			  , ord_total_cost
			  , qot_total_cost
			  , ord_submitted_date
			  , qli_line_item_number
			  , oli_order_line_item_no
			FROM
			  validation_data
			WHERE
				ord_is_dec_or_acc = 0
				and poc_total_cost is null
				and (
					unit_price_check=1
					OR currency_check=1
					OR big_order_check=1
			  	)
			ORDER BY
			  ord_internal_ref_no, 
			  qli_line_item_number";
		return $sql;
	}

	/**
	* Get SQL part which must be inserted into BaseSQL accordung to the SQL type
	* @param integer $baseSqlType Enum of baseSQL type see at above function
	*
	* @return string The actual SQL
	*/
	protected function getBaseSqlFromBody($baseSqlType)
	{

		switch ($baseSqlType) {
			//Sending first email to supplier
			case self::BASE_SQL_TYPE_SUPPLIER:
				return "ord o JOIN qot q ON
				        (
				          o.ord_internal_ref_no > :lastOrderId
				          AND o.ord_internal_ref_no <= :lastOrderNo
				          AND o.qot_source_internal_ref_no=q.qot_internal_ref_no
				          AND (o.ord_po_sts is null OR (o.ord_po_sts != 'DEC' and o.ord_po_sts != 'CON'))
				        )
				      INNER JOIN quote_line_item@livedb_link ql
				        ON (q.qot_internal_ref_no=ql.qli_qot_internal_ref_no)
				      INNER JOIN order_line_item@livedb_link ol
				        ON (o.ord_internal_ref_no=ol.oli_order_internal_ref_no)
		            WHERE 
		            	ol.oli_unit_cost > 0
					    AND ql.qli_unit_cost > 0
   					    AND o.ord_upd_sts is NULL
						AND ql.qli_line_item_number = ol.oli_order_line_item_no\n";
				break;
			//Sending second notification, if supplier not responded
			case self::BASE_SQL_TYPE_SECOND:
	    		return "erroneous_txn_notification n JOIN ord o ON
						(
							n.etn_ord_internal_ref_no=o.ord_internal_ref_no
							AND sysdate-n.etn_notification_date > :numOfDaysThreshold
						  	AND n.etn_notification_date IS NOT null
						  	AND n.etn_sec_notification_date IS null
	              			AND n.etn_gsd_notified_date IS null
							AND n.etn_corrected_date IS null
							AND (n.etn_supplier_response is null OR n.etn_supplier_response != 'ACC')
						)
					  JOIN qot q ON
				        (
				        	o.qot_source_internal_ref_no=q.qot_internal_ref_no
				        )
				      INNER JOIN quote_line_item@livedb_link ql
				        ON (q.qot_internal_ref_no=ql.qli_qot_internal_ref_no)
				      INNER JOIN order_line_item@livedb_link ol
				        ON (o.ord_internal_ref_no=ol.oli_order_internal_ref_no)
				      WHERE 
		            	ol.oli_unit_cost > 0
					    AND ql.qli_unit_cost > 0
					    AND o.ord_poc_count = 0
					    AND ord_po_sts != 'DEC'\n";
				break;
			//Sending notification to GSD, if Supplier not responded, and second notification already sent
			case self::BASE_SQL_TYPE_GSD:
	    		return "erroneous_txn_notification n JOIN ord o ON
						(
							n.etn_ord_internal_ref_no=o.ord_internal_ref_no
							AND sysdate-n.etn_sec_notification_date > :numOfDaysThreshold
						  	AND n.etn_notification_date IS NOT null
						  	AND n.etn_sec_notification_date IS NOT null
	              			AND n.etn_gsd_notified_date IS null
							AND n.etn_corrected_date IS null
							AND (n.etn_supplier_response is null OR n.etn_supplier_response != 'ACC')
						)
					  JOIN qot q ON
				        (
				        	o.qot_source_internal_ref_no=q.qot_internal_ref_no
				        )
				      INNER JOIN quote_line_item@livedb_link ql
				        ON (q.qot_internal_ref_no=ql.qli_qot_internal_ref_no)
				      INNER JOIN order_line_item@livedb_link ol
				        ON (o.ord_internal_ref_no=ol.oli_order_internal_ref_no)
				      WHERE 
		            	ol.oli_unit_cost > 0
					    AND ql.qli_unit_cost > 0
					    AND o.ord_poc_count = 0
					    AND ord_po_sts != 'DEC'\n";
				break;
				//Sending email to buyer, It is used outside of cron, when the user clicks on send email to buyer option in the mail
			case self::BASE_SQL_TYPE_BUYER:
	    		return "erroneous_txn_notification n JOIN ord o ON
						(
							n.etn_ord_internal_ref_no=o.ord_internal_ref_no
						)
					  JOIN qot q ON
				        (
				        	o.qot_source_internal_ref_no=q.qot_internal_ref_no
				        )
				      INNER JOIN quote_line_item@livedb_link ql
				        ON (q.qot_internal_ref_no=ql.qli_qot_internal_ref_no)
				      INNER JOIN order_line_item@livedb_link ol
				        ON (o.ord_internal_ref_no=ol.oli_order_internal_ref_no)
	     			  WHERE n.etn_ord_internal_ref_no = :ordInternalRefNo
	     			  AND ol.oli_unit_cost > 0
					  AND ql.qli_unit_cost > 0
	     			  \n";
				break;
			default:
				//Invalid notification type. should not occure
				break;
		}
	}

	/**
	* Log the following text into the log file
	* @param string $logText The text to log
	* @return null
	*/
	protected function log($logText)
	{
		if ($this->canLog === true) {
			$this->logger->log($logText);
		}
	}

}
