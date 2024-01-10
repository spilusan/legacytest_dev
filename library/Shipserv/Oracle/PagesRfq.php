<?php

/**
 * Load/Save RFQs to PAGES_RFQ and associated tables. Note - PAGES_RFQ is used
 * exclusively by Pages; this class does *not* act on 'REQUEST_FOR_QUOTE'.
 */
class Shipserv_Oracle_PagesRfq extends Shipserv_Oracle
{
	const ATTACHMENT_STS_ACTIVE = 'A';
	const ATTACHMENT_STS_INACTIVE = 'I';
		
	private $rfqTableName = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_TABLE_NAME;
	private $rfqColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_COL_PREFIX;
	private $liTableName = Shipserv_Oracle_Util_PagesRfqTableDef::LI_TABLE_NAME;
	private $liColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::LI_COL_PREFIX;
	private $recipTableName = Shipserv_Oracle_Util_PagesRfqTableDef::RECIPIENTS_TABLE_NAME;
	private $recipColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RECIPIENTS_COL_PREFIX;
	//private $pagedQueryRunner;
	private $rfqSelecter;
	private $rfqUpserter;
	
	public function __construct ($db)
	{
		parent::__construct($db);
		//$this->pagedQueryRunner = new Shipserv_Oracle_Util_PagedQueryRunner($this->db);
		$this->rfqSelecter = new Shipserv_Oracle_PagesRfq_Select($this->db);
		$this->rfqUpserter = new Shipserv_Oracle_PagesRfq_Upsert($this->db, $this);
	}
	
	/**
	 * Upsert RFQ.
	 *
	 * @return int Pages RFQ Control Reference (of new RFQ, or of updated RFQ).
	 */
	public function save (Myshipserv_Purchasing_Rfq_Saveable $rfq)
	{
		return $this->rfqUpserter->save($rfq);
	}
	
	/**
	 * Convenience wrapper around loadRfqs() to load just one RFQ.
	 *
	 * @return array
	 * @throw Exception if not found.
	 */
	public function loadRfq ($pagesControlRef)
	{
		$rfqArr = $this->loadRfqs(array($pagesControlRef));
		if ($rfqArr)
		{
			return $rfqArr[0];
		}
		else
		{
			throw new Exception("RFQ not found");
		}
	}
	
	/**
	 * Load RFQs from array of Pages Control References.
	 * 
	 * @return array
	 */
	public function loadRfqs (array $pagesControlRefs)
	{
		$res = $this->rfqSelecter->loadRfqs($pagesControlRefs);
		return $res;
	}
	
	/**
	 * Convenience wrapper around loadLineItemsForRfqs to load line items for just one RFQ.
	 *
	 * @return array
	 */
	public function loadLineItemsForRfq ($pagesControlRef)
	{
		$liArr = $this->loadLineItemsForRfqs(array($pagesControlRef));
		return $liArr[$pagesControlRef];
	}
	
	/**
	 * Load RFQ line items from array of Pages Control References.
	 * 
	 * @return array
	 */
	public function loadLineItemsForRfqs (array $pagesControlRefs)
	{
		// Load line items ordered by line item number so that items are added to RFQ in the correct order
		$cfSql = Shipserv_Oracle_Util::makeSqlList($this->db, $pagesControlRefs);
		$sql = "SELECT * FROM {$this->liTableName} WHERE {$this->liColPrefix}RFQ_PAGES_CONTROL_REF IN ($cfSql) ORDER BY {$this->liColPrefix}LINE_ITEM_NO";
		
		// Prepare results array with key => array for each control ref requested (important - clients depend on finding these entries)
		$liArr = array();
		foreach ($pagesControlRefs as $pcr)
		{
			$liArr[$pcr] = array();
		}
		
		foreach ($this->db->fetchAll($sql) as $r)
		{
			// Retrieve parent RFQ by pages control reference
			$cr = $r[$this->liColPrefix . 'RFQ_PAGES_CONTROL_REF'];
			$liArr[$cr][] = $r;
		}
		return $liArr;
	}
	
	/**
	 * Load paged RFQs by BYB code.
	 *
	 * @return array
	 */
	public function loadRfqsByBuyerBranch ($buyerBranchCode, $firstOffset = 1, $pageSize = 20)
	{
		return $this->rfqSelecter->loadRfqsByBuyerBranch($buyerBranchCode, $firstOffset, $pageSize);
	}
	
	public function loadRfqsByBuyerBranchCount ($buyerBranchCode)
	{
		return $this->rfqSelecter->loadRfqsByBuyerBranchCount($buyerBranchCode);
	}
	
	/**
	 * Convenience wrapper around loadRecipientsForRfqs() to load just one recipient.
	 */
	public function loadRecipientsForRfq ($pagesControlRef)
	{
		$recipArr = $this->loadRecipientsForRfqs(array($pagesControlRef));
		return $recipArr[$pagesControlRef];
	}
	
	/**
	 * Loads rows from $this->recipTableName inner join supplier_branch for
	 * given control refs.
	 * 
	 * @return array
	 */
	public function loadRecipientsForRfqs (array $pagesControlRefs)
	{
		$cfSql = Shipserv_Oracle_Util::makeSqlList($this->db, $pagesControlRefs);
		
		$sql = "SELECT a.PRR_PGS_CTRL_REF, a.PRR_RECIPIENT_ID, a.PRR_TN_CTRL_REF, b.* ";
		$sql .= "FROM {$this->recipTableName} a INNER JOIN SUPPLIER_BRANCH b ON b.SPB_BRANCH_CODE = a.{$this->recipColPrefix}RECIPIENT_ID ";
		$sql .= "WHERE a.{$this->recipColPrefix}PGS_CTRL_REF IN ($cfSql)";
		
		$rows = $this->db->fetchAll($sql);
		
		// Prepare return array with indexes & empty arrays for each requested control ref
		$rowsByRfq = array();
		foreach ($pagesControlRefs as $pcr)
		{
			$rowsByRfq[$pcr] = array();
		}
		
		// Loop on result rows, populating result array
		foreach ($rows as $r)
		{
			$cr = $r[$this->recipColPrefix . 'PGS_CTRL_REF'];
			$rowsByRfq[$cr][] = $r;
		}
		
		return $rowsByRfq;
	}
	
	/**
	 * Returns ACTIVE rows from ATTACHMENT_FILE.
	 * 
	 * @return array
	 */
	public function loadAttachmentsForRfq ($pagesControlRef)
	{
		$sql = 
			"SELECT * FROM ATTACHMENT_FILE a WHERE ATF_ID IN
			(
				SELECT PRA_ATF_ID FROM PAGES_RFQ_ATTACHMENT WHERE PRA_PRR_PGS_CTRL_REF = :pcr AND PRA_STS = :sts
			)";
		
		return $this->db->fetchAll($sql, array('pcr' => $pagesControlRef, 'sts' => self::ATTACHMENT_STS_ACTIVE));
	}
	
	/**
	 * Sets RFQ to submitted status and updates all recipients in
	 * PAGES_RFQ_RECIPIENT with TN Control Reference for submitted RFQ.
	 */
	public function markSubmitted ($pagesControlRef, $tnControlRef)
	{
		$this->markRfqSubmitted($pagesControlRef); // Exception if RFQ is not in draft status
		$this->markRfqRecipientsWithTnControlRef($pagesControlRef, $tnControlRef);
	}
	
	/**
	 * @throws Exception if RFQ does not exist, or is not in draft status.
	 */
	private function markRfqSubmitted ($pagesControlRef)
	{
		$stmtSql = "UPDATE {$this->rfqTableName} SET ";
		$stmtSql .= "{$this->rfqColPrefix}STS = 'SUB' ";
		
		// Only RFQ with this control ref
		$stmtSql .= "WHERE {$this->rfqColPrefix}PAGES_CONTROL_REF = :pagesControlRef ";
		
		// Only if current status is draft
		$stmtSql .= "AND {$this->rfqColPrefix}STS = 'DFT'";
		
		// Do query
		$params = array();
		$params['pagesControlRef'] = $pagesControlRef;
		$stmt = $this->db->query($stmtSql, $params);
		
		if ($stmt->rowCount() == 0)
		{
			throw new Exception("Attempting to mark RFQ submitted: RFQ does not exist, or is not in draft status");
		}
	}
	
	private function markRfqRecipientsWithTnControlRef ($pagesControlRef, $tnControlRef)
	{
		$stmtSql = "UPDATE {$this->recipTableName} SET ";
		$stmtSql .= "{$this->recipColPrefix}TN_CTRL_REF = :tnControlRef ";
		
		// All recipients for this RFQ
		$stmtSql .= "WHERE {$this->recipColPrefix}PGS_CTRL_REF = :pagesControlRef";
		
		// Do query
		$params = array();
		$params['pagesControlRef'] = $pagesControlRef;
		$params['tnControlRef'] = $tnControlRef;
		$stmt = $this->db->query($stmtSql, $params);
	}
}
