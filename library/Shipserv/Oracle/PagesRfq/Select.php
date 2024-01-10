<?php

/**
 * Provides SELECT functionality on PAGES_RFQ.
 * Post-processes return rows, converting datetime fields into
 * Shipserv_Oracle_Util_DbTime objects.
 */
class Shipserv_Oracle_PagesRfq_Select
{
	private $rfqTableName = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_TABLE_NAME;
	private $rfqColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_COL_PREFIX;
	
	private $db;
	private $pagedQueryRunner;
	
	// List of columns to select from PAGES_RFQ as "c1, c2, ...".
	private $selectColSql;
	
	// Names of datetime columns to be post-processed from strings into datetime objects.
	private $dateTimeCols = array();
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->pagedQueryRunner = new Shipserv_Oracle_Util_PagedQueryRunner($this->db);
		$this->initSql();
	}
	
	/**
	 * Initialise $this->selectColSql and $this->dateTimeCols.
	 *
	 * @return void
	 */
	private function initSql ()
	{
		$cols = array();
		
		$cols[] = $this->rfqColPrefix . 'PAGES_CONTROL_REF';
		$cols[] = $this->rfqColPrefix . 'BYB_BRANCH_CODE';
		$cols[] = $this->rfqColPrefix . 'BYB_BYO_ORG_CODE';
		$cols[] = $this->rfqColPrefix . 'STS';
		$cols[] = $this->rfqColPrefix . 'LINE_ITEM_COUNT';
		$this->makeDateTimeCol($this->rfqColPrefix . 'ESTIMATED_ARRIVAL_TIME', $cols);
		$this->makeDateTimeCol($this->rfqColPrefix . 'ESTIMATED_DEPARTURE_TIME', $cols);
		$cols[] = $this->rfqColPrefix . 'TERMS_OF_DELIVERY';
		$cols[] = $this->rfqColPrefix . 'TRANSPORTATION_MODE';
		$cols[] = $this->rfqColPrefix . 'TAX_STS';
		$this->makeDateTimeCol($this->rfqColPrefix . 'DATE_TIME', $cols);
		$this->makeDateTimeCol($this->rfqColPrefix . 'ADVICE_BEFORE_DATE', $cols);
		$cols[] = $this->rfqColPrefix . 'REF_NO';
		$cols[] = $this->rfqColPrefix . 'SUBJECT';
		$cols[] = $this->rfqColPrefix . 'COMMENTS';
		$cols[] = $this->rfqColPrefix . 'GENERAL_TERMS_CONDITIONS';
		$cols[] = $this->rfqColPrefix . 'PACKAGING_INSTRUCTIONS';
		$cols[] = $this->rfqColPrefix . 'CURRENCY_INSTRUCTIONS';
		$cols[] = $this->rfqColPrefix . 'TERMS_OF_PAYMENT';
		$cols[] = $this->rfqColPrefix . 'VESSEL_NAME';
		$cols[] = $this->rfqColPrefix . 'VESSEL_CLASSIFICATION';
		$cols[] = $this->rfqColPrefix . 'IMO_NO';
		$cols[] = $this->rfqColPrefix . 'DELIVERY_PORT';
		$cols[] = $this->rfqColPrefix . 'ADDRESS';
		$cols[] = $this->rfqColPrefix . 'SUGGESTED_SHIPPER';
		$cols[] = $this->rfqColPrefix . 'CONTACT';
		$cols[] = $this->rfqColPrefix . 'PHONE_NO';
		$cols[] = $this->rfqColPrefix . 'EMAIL_ADDRESS';
		$cols[] = $this->rfqColPrefix . 'ACCOUNT_REF_NO';
		$cols[] = $this->rfqColPrefix . 'BILLING_ADDRESS_1';
		$cols[] = $this->rfqColPrefix . 'BILLING_ADDRESS_2';
		$cols[] = $this->rfqColPrefix . 'BILLING_STATE_PROVINCE';
		$cols[] = $this->rfqColPrefix . 'BILLING_POSTAL_ZIP_CODE';
		$cols[] = $this->rfqColPrefix . 'BILLING_CITY';
		$cols[] = $this->rfqColPrefix . 'BILLING_COUNTRY';
		$this->makeDateTimeCol($this->rfqColPrefix . 'UPDATED_DATE', $cols);		
		
		// Initialise columns to be SELECTed
		$this->selectColSql = join(", ", $cols);
	}
	
	/**
	 * Load RFQs from array of Pages Control References.
	 * 
	 * @return array
	 */
	public function loadRfqs (array $pagesControlRefs)
	{
		// No IDs: return empty array
		$rfqArr = array();
		if (!$pagesControlRefs)
		{
			return $rfqArr;
		}
		
		// Load RFQs
		$cfSql = Shipserv_Oracle_Util::makeSqlList($this->db, $pagesControlRefs);
		$sql = "SELECT {$this->selectColSql} FROM {$this->rfqTableName} WHERE {$this->rfqColPrefix}PAGES_CONTROL_REF IN ($cfSql)";
		$rows = $this->db->fetchAll($sql);
		
		// Post-process rows
		$this->postProcessRows($rows);
		return $rows;
	}
	
	/**
	 * Load RFQs by BYB code. Returns paginated array.
	 * 
	 * @return array
	 */
	public function loadRfqsByBuyerBranch ($buyerBranchCode, $firstOffset, $pageSize)
	{
		$pagedRows = $this->pagedQueryRunner->execute(
			$this->makeRfqsByBuyerBranchSql($this->selectColSql, "{$this->rfqColPrefix}UPDATED_DATE DESC NULLS LAST"),
			$firstOffset,
			$pageSize,
			array('bybCode' => $buyerBranchCode)
		);
		$this->postProcessRows($pagedRows['rows']);
		return $pagedRows;
	}
	
	public function loadRfqsByBuyerBranchCount ($buyerBranchCode)
	{
		$rows = $this->db->fetchAll($this->makeRfqsByBuyerBranchSql("COUNT(*) CNT"), array('bybCode' => $buyerBranchCode));
		return $rows[0]['CNT'];
	}
	
	/**
	 * Make SQL statement used by loadRfqsByBuyerOrg methods.
	 */
	private function makeRfqsByBuyerBranchSql ($select, $orderBy = null)
	{
		$sql = "SELECT $select FROM {$this->rfqTableName} WHERE {$this->rfqColPrefix}BYB_BRANCH_CODE = :bybCode";
		
		if ($orderBy != '')
		{
			$sql .= " ORDER BY $orderBy";
		}
		
		return $sql;
	}
	
	/**
	 * Post-processes array supplied, converting columns specified in $this->dateTimeCols
	 * from datetime strings into Shipserv_Oracle_Util_DbTime objects.
	 * 
	 * @return void
	 */
	private function postProcessRows (array &$rows)
	{
		foreach ($rows as $i => &$r)
		{
			foreach ($r as $col => $val)
			{
				if (@$this->dateTimeCols[$col])
				{
					$r[$col] = Shipserv_Oracle_Util_DbTime::parseDbTime($val);
				}
			}
		}
	}
	
	/**
	 * Wraps column provided in SQL as below and adds it to array supplied:
	 *  "TO_CHAR($col, 'YYYY/MM/DD HH24:MI:SS') $col"
	 *
	 * Also adds column to list of columns to be post-processed.
	 *
	 * @return void
	 */
	private function makeDateTimeCol ($col, array &$colArr)
	{
		$colArr[] = "TO_CHAR($col, 'YYYY/MM/DD HH24:MI:SS') $col";
		$this->dateTimeCols[$col] = 1;
	}
}
