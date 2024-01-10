<?php

/**
 * Read from REQUEST_FOR_QUOTE table. This is the main TN RFQ table: do not write to it.
 * Don't confuse this table with PAGES_RFQ.
 */
class Shipserv_Oracle_Rfq extends Shipserv_Oracle
{
	private $select;
	
	public function __construct ($db)
	{
		parent::__construct($db);
		$this->select = new Shipserv_Oracle_Rfq_Select($this->db);
	}
	
	/**
	 * Fetch an RFQ by it's internal reference number.
	 */
	public function fetchById ($internalRefNo, $useArchive = false)
	{
		return $this->select->fetchById($internalRefNo, $useArchive);
	}
	
	public function fetchLineItems ($rfqInternalRefNo)
	{
		$sql = "SELECT * FROM RFQ_LINE_ITEM WHERE RFL_RFQ_INTERNAL_REF_NO = :rfqInternalRefNo ORDER BY RFL_LINE_ITEM_NO";
		$sql = "
		  SELECT 
		    RFQ_LINE_ITEM.*,
		    MSU_CODE_DESC AS RFL_UNIT_DESC,
		    CATALOGUE_ITEM_IDENTIFIER_TYPE.DESCRIPTION AS RFL_TYPE_DESC
		  FROM 
		    RFQ_LINE_ITEM LEFT JOIN CATALOGUE_ITEM_IDENTIFIER_TYPE ON (RFL_ID_TYPE=TYPE_CODE)
		    LEFT JOIN MTML_STD_UNIT ON (MSU_CODE=RFL_UNIT) 
		  WHERE 
		    RFL_RFQ_INTERNAL_REF_NO = :rfqInternalRefNo 
		  ORDER BY 
		    RFL_LINE_ITEM_NO
		";
		return $this->db->fetchAll($sql, array('rfqInternalRefNo' => $rfqInternalRefNo));
	}
	
}

/**
 * Wraps SELECT queries on RFQ table.
 * Converts datetime fields into Shipserv_Oracle_Util_DbTime objects.
 */
class Shipserv_Oracle_Rfq_Select
{
	private $db;
	private $selectSql;
	private $dateTimeCols = array();
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->initSql();
	}
	
	private function initSql ()
	{
		$colArr = array();
		
		$colArr[] = 'RFQ_INTERNAL_REF_NO';
		$colArr[] = 'RFQ_BBU_USR_USER_CODE';
		$colArr[] = 'RFQ_BYB_BYO_ORG_CODE';
		$colArr[] = 'RFQ_BYB_BRANCH_CODE';
		$colArr[] = 'RFQ_REQ_INTERNAL_REF_NO';
		$colArr[] = 'RFQ_SS_TRACKING_NO';
		$colArr[] = 'RFQ_LINE_ITEM_COUNT';
		$colArr[] = 'RFQ_VENDOR_COUNT';
		$colArr[] = 'RFQ_STS';
		$colArr[] = 'RFQ_REQ_REF_NO';
		$colArr[] = 'RFQ_REF_NO';
		$colArr[] = 'RFQ_SUBJECT';
		$colArr[] = 'RFQ_VESSEL_NAME';
		$colArr[] = 'RFQ_MTML_CATEGORY';
		$colArr[] = 'RFQ_VESSEL_CLASSIFICATION';
		$colArr[] = 'RFQ_IMO_NO';
		$colArr[] = 'RFQ_VESSEL_ASSOCIATED';
		$colArr[] = 'RFQ_ACCOUNT_REF_NO';
		$colArr[] = 'RFQ_CONTACT';
		$colArr[] = 'RFQ_COMMENTS';
		$colArr[] = 'RFQ_PHONE_NO';
		$colArr[] = 'RFQ_EMAIL_ADDRESS';
		$colArr[] = 'RFQ_HOMEPAGE_URL';
		$this->makeDateTimeCol('RFQ_ESTIMATED_ARRIVAL_TIME', $colArr);
		$this->makeDateTimeCol('RFQ_ESTIMATED_DEPARTURE_TIME', $colArr);
		$colArr[] = 'RFQ_DELIVERY_PORT';
		$colArr[] = 'RFQ_ADDRESS';
		$this->makeDateTimeCol('RFQ_DATE_TIME', $colArr);
		$colArr[] = 'RFQ_SUGGESTED_SHIPPER';
		$colArr[] = 'RFQ_SUGGESTED_VENDOR';
		$colArr[] = 'RFQ_GENERAL_TERMS_CONDITIONS';
		$colArr[] = 'RFQ_PACKAGING_INSTRUCTIONS';
		$colArr[] = 'RFQ_TERMS_OF_DELIVERY';
		$colArr[] = 'RFQ_TRANSPORTATION_MODE';
		$colArr[] = 'RFQ_TAX_STS';
		$colArr[] = 'RFQ_CURRENCY_INSTRUCTIONS';
		$this->makeDateTimeCol('RFQ_ADVICE_BEFORE_DATE', $colArr);
		$colArr[] = 'RFQ_PRIORITY';
		$colArr[] = 'RFQ_TOTAL_COST';
		$colArr[] = 'RFQ_CURRENCY';
		$colArr[] = 'RFQ_NOTES';
		$colArr[] = 'RFQ_REQUESTED_BY';
		$colArr[] = 'RFQ_DELIVERY_STS';
		$colArr[] = 'RFQ_ARCHIVAL_STS';
		$colArr[] = 'RFQ_QUOTE_ARCHIVAL_STS';
		$colArr[] = 'RFQ_TXN_TRANSACTION_ID';
		$colArr[] = 'RFQ_CREATED_BY';
		$this->makeDateTimeCol('RFQ_CREATED_DATE', $colArr);
		$colArr[] = 'RFQ_UPDATED_BY';
		$this->makeDateTimeCol('RFQ_UPDATED_DATE', $colArr);
		$colArr[] = 'RFQ_TERMS_OF_PAYMENT';
		$colArr[] = 'RFQ_BILLING_ADDRESS_1';
		$colArr[] = 'RFQ_BILLING_ADDRESS_2';
		$colArr[] = 'RFQ_BILLING_STATE_PROVINCE';
		$colArr[] = 'RFQ_BILLING_POSTAL_ZIP_CODE';
		$colArr[] = 'RFQ_BILLING_CITY';
		$colArr[] = 'RFQ_BILLING_COUNTRY';
		$colArr[] = 'RFQ_INTEGRATION_TYPE';
		$colArr[] = 'RFQ_REPLACEMENT_FOR';
		$colArr[] = 'RFQ_UPDATE_STS';
		$colArr[] = 'RFQ_CURR_APPEND_IN_TRADENETID';
		$colArr[] = 'RFQ_CREATED_FOR_SUBSUPPLIER';
		$colArr[] = 'RFQ_SOURCERFQ_INTERNAL_NO';
		$colArr[] = 'RFQ_DUPLICATE_TXN';
		$colArr[] = 'RFQ_ARCHIVE_BY';
		$this->makeDateTimeCol('RFQ_ARCHIVE_DATE', $colArr);
		$this->makeDateTimeCol('RFQ_ALERT_LAST_SEND_DATE', $colArr);
        // added by Yuriy Akopov on 2014-04-17, S10029
        $colArr[] = 'RAWTOHEX(RFQ_EVENT_HASH) AS ' . Shipserv_Rfq::COL_EVENT_HASH;

		$this->selectSql = join(", ", $colArr);
	}
	
	public function fetchById ($internalRef, $useArchive = false)
	{

		$sqlStmt = "SELECT {$this->selectSql} FROM REQUEST_FOR_QUOTE WHERE RFQ_INTERNAL_REF_NO = :internalRef";
		$rows = $this->db->fetchAll($sqlStmt, array('internalRef' => $internalRef));
		if (!$rows)
		{
			if ($useArchive === true) {
				$sqlStmt = "SELECT {$this->selectSql} FROM REQUEST_FOR_QUOTE_ARC WHERE RFQ_INTERNAL_REF_NO = :internalRef";
				$rows = $this->db->fetchAll($sqlStmt, array('internalRef' => $internalRef));
				if (!$rows) {
					throw new Exception("RFQ does not exist: " . $internalRef);
				}
			} else {
				throw new Exception("RFQ does not exist: " . $internalRef);
			}
			
		}
		
		foreach ($rows[0] as $col => $val)
		{
			if (@$this->dateTimeCols[$col])
			{
				$rows[0][$col] = Shipserv_Oracle_Util_DbTime::parseDbTime($val);
			}
		}
		
		return $rows[0];
	}
	
	private function makeDateTimeCol ($col, array &$colArr)
	{
		$colArr[] = "TO_CHAR($col, 'YYYY/MM/DD HH24:MI:SS') $col";
		$this->dateTimeCols[$col] = 1;
	}	
}
