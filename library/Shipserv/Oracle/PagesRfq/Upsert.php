<?php

/**
 * Provides RFQ upsert functionality, including saving to PAGES_RFQ,
 * PAGES_RFQ_LINE_ITEM, PAGES_RFQ_ATTACHMENT & PAGES_RFQ_RECIPIENT.
 */
class Shipserv_Oracle_PagesRfq_Upsert
{
	private $rfqTableName = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_TABLE_NAME;
	private $rfqColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_COL_PREFIX;
	private $liTableName = Shipserv_Oracle_Util_PagesRfqTableDef::LI_TABLE_NAME;
	private $liColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::LI_COL_PREFIX;
	private $recipTableName = Shipserv_Oracle_Util_PagesRfqTableDef::RECIPIENTS_TABLE_NAME;
	private $recipColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RECIPIENTS_COL_PREFIX;
	private $db;
	private $rfqDao;
	private $attachAd;
	
	public function __construct ($db, Shipserv_Oracle_PagesRfq $rfqDao)
	{
		$this->db = $db;
		$this->rfqDao = $rfqDao;
		$this->attachAd = new Shipserv_Adapters_Attachment();
	}
	
	/**
	 * Upsert RFQ to DB.
	 * 
	 * If no 'pagesControlReference' supplied in $rfq, generate new ID and do insert.
	 * If supplied, remove existing RFQ and do insert.
	 *
	 * @returns int Pages Control Reference (existing or newly generated).
	 * @throws Exception if supplied Pages Control Reference does not exist.
	 */
	public function save (Myshipserv_Purchasing_Rfq_Saveable $rfq)
	{
		// If this RFQ has an ID, first remove existing RFQ & line items.
		$pcr = $rfq->getPagesControlRef();
		if ($pcr !== null)
		{
			// Throws exception if not found
			$rfqFromDb = $this->rfqDao->loadRfq($pcr);
			
			// Check status suitable for update
			if ($rfqFromDb[$this->rfqColPrefix . 'STS'] != 'DFT')
			{
				throw new Exception("Update requires RFQ to have status = DFT");
			}
			
			// Remove current RFQ & line items
			$this->deleteRfq($pcr);
		}
		// If this RFQ has no ID, generate a new one from sequence.
		else
		{
			$pcr = $this->makeNewPagesControlRef();
		}
		
		$this->executeInsert($rfq, $pcr);
		$this->executeInsertLi($rfq, $pcr);
		$this->executeInsertRecipients($rfq, $pcr);
		$this->executeUpdateAttachments($rfq, $pcr);
		
		return $pcr;
	}
	
	/**
	 * @return int
	 */
	private function makeNewPagesControlRef ()
	{
		$stmtSql = "SELECT SEQ_PAGES_RFQ.NEXTVAL AS ID FROM DUAL";
		$rows = $this->db->fetchAll($stmtSql);
		return $rows[0]['ID'];
	}
	
	/**
	 * Performs insert into PAGES_RFQ. RFQ_PAGES_CONTROL_REF column value is taken from
	 * $pagesControlRef, not from $rfq.
	 * 
	 * @return void
	 */
	private function executeInsert (Myshipserv_Purchasing_Rfq_Saveable $rfq, $pagesControlRef)
	{
		$sch = new Shipserv_Oracle_Util_StmtColHelper($this->rfqColPrefix);
		
		$sch->setCol('STS', 'DFT');
		$sch->setCol('PAGES_CONTROL_REF', $pagesControlRef);
		$sch->setCol('BYB_BRANCH_CODE', $rfq->getBuyerBranchCode());
		$sch->setCol('BYB_BYO_ORG_CODE', $rfq->getBuyerOrgCode());
		$sch->setCol('LINE_ITEM_COUNT', count($rfq->getLineItems()));
		$sch->setDateTimeCol('ESTIMATED_ARRIVAL_TIME', $rfq->getVesselEta());
		$sch->setDateTimeCol('ESTIMATED_DEPARTURE_TIME', $rfq->getVesselEtd());
		$sch->setCol('TERMS_OF_DELIVERY', $rfq->getDeliveryTerms());
		$sch->setCol('TRANSPORTATION_MODE', $rfq->getTransportMode());
		$sch->setCol('TAX_STS', $rfq->getTaxStatus());
		$sch->setDateTimeCol('DATE_TIME', $rfq->getDeliveryRequestedTime());
		$sch->setDateTimeCol('ADVICE_BEFORE_DATE', $rfq->getQotAdviseByTime());
		$sch->setCol('REF_NO', $rfq->getReference());
		$sch->setCol('SUBJECT', $rfq->getSubject());
		$sch->setCol('COMMENTS', $rfq->getBuyerComments());
		$sch->setCol('GENERAL_TERMS_CONDITIONS', $rfq->getGeneralTermsAndConditions());
		$sch->setCol('PACKAGING_INSTRUCTIONS', $rfq->getPackagingInstructions());
		$sch->setCol('CURRENCY_INSTRUCTIONS', $rfq->getCurrencyInstructions());
		$sch->setCol('TERMS_OF_PAYMENT', $rfq->getTermsOfPayment());
		$sch->setCol('VESSEL_NAME', $rfq->getVesselName());
		$sch->setCol('VESSEL_CLASSIFICATION', $rfq->getVesselClass());
		$sch->setCol('IMO_NO', $rfq->getVesselImo());
		$sch->setCol('DELIVERY_PORT', $rfq->getDeliveryPort());
		$sch->setCol('ADDRESS', $rfq->getDeliveryAddress());
		$sch->setCol('SUGGESTED_SHIPPER', $rfq->getSuggestedFreightForwarder());
		$sch->setCol('CONTACT', $rfq->getContactName());
		$sch->setCol('PHONE_NO', $rfq->getContactPhone());
		$sch->setCol('EMAIL_ADDRESS', $rfq->getContactEmail());
		$sch->setCol('ACCOUNT_REF_NO', $rfq->getBuyerAcctRef());
		$sch->setCol('BILLING_ADDRESS_1', $rfq->getStreet1());
		$sch->setCol('BILLING_ADDRESS_2', $rfq->getStreet2());
		$sch->setCol('BILLING_STATE_PROVINCE', $rfq->getStateOrProvince());
		$sch->setCol('BILLING_POSTAL_ZIP_CODE', $rfq->getZip());
		$sch->setCol('BILLING_CITY', $rfq->getCity());
		$sch->setCol('BILLING_COUNTRY', $rfq->getCountry());
		$sch->setDateTimeCol('UPDATED_DATE', time());
		
		$colSql = join(', ', $sch->getColNames());
		$colExprSql = join(', ', $sch->getColExprs());
		$sql = "INSERT INTO {$this->rfqTableName} ($colSql) VALUES ($colExprSql)";
		
		$stmt = $this->db->query($sql, $sch->getParamVals());
	}
	
	/**
	 * Performs insert into PAGES_RFQ_LINE_ITEM. RFL_RFQ_PAGES_CONTROL_REF column value is taken from
	 * $pagesControlRef, not from $rfq.
	 * 
	 * @return void
	 */	
	private function executeInsertLi (Myshipserv_Purchasing_Rfq_Saveable $rfq, $pagesControlRef)
	{
		$sql = null;
		foreach ($rfq->getLineItems() as $li)
		{
			$sch = new Shipserv_Oracle_Util_StmtColHelper($this->liColPrefix);
			
			$sch->setCol('STS', null);
			$sch->setCol('RFQ_PAGES_CONTROL_REF', $pagesControlRef);
			$sch->setCol('LINE_ITEM_NO', $li->getIdxNumber());
			$sch->setCol('QUANTITY', $li->getQuantity());
			$sch->setCol('ID_TYPE', $li->getPartType());
			$sch->setCol('ID_CODE', $li->getPartCode());
			$sch->setCol('PRODUCT_DESC', $li->getDescription());
			$sch->setCol('UNIT', $li->getUnit());
			$sch->setCol('COMMENTS', $li->getComment());
			$sch->setCol('CONFG_NAME', $li->getConfName());
			$sch->setCol('CONFG_DESC', $li->getConfDescription());
			$sch->setCol('CONFG_MANUFACTURER', $li->getConfManufacturer());
			$sch->setCol('CONFG_MODEL_NO', $li->getConfModelNumber());
			$sch->setCol('CONFG_SERIAL_NO', $li->getConfSerialNumber());
			$sch->setCol('CONFG_DRAWING_NO', $li->getConfDrawingNumber());
			$sch->setCol('CONFG_DEPT_TYPE', $li->getConfDepartmentType());
			
			if ($sql === null)
			{
				$colSql = join(', ', $sch->getColNames());
				$colExprSql = join(', ', $sch->getColExprs());
				$sql = "INSERT INTO {$this->liTableName} ($colSql) VALUES ($colExprSql)";
			}
			
			$stmt = $this->db->query($sql, $sch->getParamVals());
		}
	}
	
	/**
	 * Deletes from PAGES_RFQ, PAGES_RFQ_LINE_ITEM & PAGES_RFQ_RECIPIENT.
	 *
	 * @return bool True if specified RFQ existed.
	 */
	private function deleteRfq ($pagesControlRef)
	{
		// Remove RFQ and record whether record existed
		$stmtSql = "DELETE FROM {$this->rfqTableName} WHERE {$this->rfqColPrefix}PAGES_CONTROL_REF = :PAGES_CONTROL_REF";
		$stmt = $this->db->query($stmtSql, array('PAGES_CONTROL_REF' => $pagesControlRef));
		if ($stmt->rowCount() == 0)
		{
			$rfqExisted = false;
		}
		else
		{
			$rfqExisted = true;
		}
		
		// Remove child line items
		$stmtSql = "DELETE FROM {$this->liTableName} WHERE {$this->liColPrefix}RFQ_PAGES_CONTROL_REF = :PAGES_CONTROL_REF";
		$this->db->query($stmtSql, array('PAGES_CONTROL_REF' => $pagesControlRef));
		
		// Remove recipients
		$stmtSql = "DELETE FROM {$this->recipTableName} WHERE {$this->recipColPrefix}PGS_CTRL_REF = :PAGES_CONTROL_REF";
		$this->db->query($stmtSql, array('PAGES_CONTROL_REF' => $pagesControlRef));
		
		return $rfqExisted;
	}
	
	/**
	 * Inserts into PAGES_RFQ_RECIPIENT.
	 */
	private function executeInsertRecipients (Myshipserv_Purchasing_Rfq_Saveable $rfq, $pagesControlRef)
	{
		$rStmtSql = "INSERT INTO {$this->recipTableName} ";
		$rStmtSql .= "({$this->recipColPrefix}PGS_CTRL_REF, {$this->recipColPrefix}RECIPIENT_ID, {$this->recipColPrefix}TN_CTRL_REF) ";
		$rStmtSql .= "VALUES (:pagesControlRef, :recipientId, NULL)";
		
		$paramArr = array();
		foreach ($rfq->getSupplierIds() as $r)
		{
			$paramArr = array('pagesControlRef' => $pagesControlRef, 'recipientId' => $r);
			$stmt = $this->db->query($rStmtSql, $paramArr);
		}
	}
	
	/**
	 * Executes put to attachment service & update of PAGES_RFQ_ATTACHMENT.
	 */
	private function executeUpdateAttachments (Myshipserv_Purchasing_Rfq_Saveable $rfq, $pagesControlRef)
	{
		$sql =
			"MERGE INTO PAGES_RFQ_ATTACHMENT USING DUAL ON (PRA_PRR_PGS_CTRL_REF = :pcr AND PRA_ATF_ID = :atfId)
				WHEN MATCHED THEN
					UPDATE SET PRA_STS = :sts
				WHEN NOT MATCHED THEN
					INSERT (PRA_PRR_PGS_CTRL_REF, PRA_ATF_ID, PRA_STS) VALUES (:pcr, :atfId, :sts)";
		
		foreach ($rfq->getAttachmentCmds() as $cmd)
		{
			if ($cmd['add'] !== null)
			{
				// Put file to attachment service and receive file id
				$unbind = false;
				$attachRes = $this->attachAd->putFile('/tmp/uploads' . '/' . $cmd['add']->getTmpFilename() . '.dat', $cmd['add']->getOrigBasename(), $rfq->getBuyerBranchCode());
				$atfId = $attachRes['atfId'];
			}
			
			if ($cmd['rm'] !== null)
			{
				$unbind = true;
				$atfId = $cmd['rm'];
			}
			
			// Update DB
			$stmt = $this->db->query($sql, array('pcr' => $pagesControlRef, 'atfId' => $atfId, 'sts' => $unbind ? 'I' : 'A'));
		}
	}
}
