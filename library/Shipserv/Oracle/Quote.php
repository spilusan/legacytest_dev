<?php

class Shipserv_Oracle_Quote extends Shipserv_Oracle
{
	private $select;
	
	public function __construct ($db)
	{
		parent::__construct($db);
		$this->select = new Shipserv_Oracle_Quote_Select($this->db);
	}
	
	public function fetchById ($internalRefNo, $useArchive = false)
	{
		return $this->select->fetchById($internalRefNo, $useArchive);
	}
	
	public function fetchLineItems ($rfqInternalRefNo)
	{
		$sql = "SELECT * FROM QUOTE_LINE_ITEM WHERE QLI_QOT_INTERNAL_REF_NO = :rfqInternalRefNo ORDER BY QLI_LINE_ITEM_NUMBER";
		return $this->db->fetchAll($sql, array('rfqInternalRefNo' => $rfqInternalRefNo));
	}
	
	public function fetchLineItemChanges ($quoteId, $rfqId)
	{
		$sql = "SELECT * FROM RFQ_QUOTE_LINE_ITEM_CHANGE WHERE RQLC_RFQ_INTERNAL_REF_NO = :rfqId AND RQLC_QOT_INTERNAL_REF_NO=:quoteId ORDER BY RQLC_LINE_ITEM_NO";
		return $this->db->fetchAll($sql, array('quoteId' => $quoteId, 'rfqId' => $rfqId));
	}
	
	public function fetchByBuyerBranch ($buyerBranchCode, $firstOffset = 1, $pageSize = 20)
	{
		return $this->select->fetchByBuyerBranch($buyerBranchCode, $firstOffset, $pageSize);
	}
	
	public function fetchByBuyerBranchCount ($buyerBranchCode)
	{
		return $this->select->fetchByBuyerBranchCount($buyerBranchCode);
	}
}

class Shipserv_Oracle_Quote_Select
{
	private $db;
	private $pagedQueryRunner;
	private $selectSql;
	private $dateTimeCols = array();
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->pagedQueryRunner = new Shipserv_Oracle_Util_PagedQueryRunner($this->db);
		$this->initSql();
	}
	
	private function initSql ()
	{
		$colArr = array();
		
		$colArr[] = 'QOT_INTERNAL_REF_NO';
		$colArr[] = 'QOT_RFQ_INTERNAL_REF_NO';
		$colArr[] = 'QOT_SBU_USR_USER_CODE';
		$colArr[] = 'QOT_SPB_SUP_ORG_CODE';
		$colArr[] = 'QOT_SPB_BRANCH_CODE';
		$colArr[] = 'QOT_SS_TRACKING_NO';
		$colArr[] = 'QOT_TERMS_OF_DELIVERY';
		$colArr[] = 'QOT_LINE_ITEM_COUNT';
		$colArr[] = 'QOT_TOTAL_COST';
		$colArr[] = 'QOT_CURRENCY';
		$colArr[] = 'QOT_QUOTE_STS';
		$colArr[] = 'QOT_REF_NO';
		$colArr[] = 'QOT_SUBJECT';
		$colArr[] = 'QOT_COMMENTS';
		$colArr[] = 'QOT_SHIPPER';
		$this->makeDateTimeCol('QOT_SUBMITTED_DATE', $colArr);
		$colArr[] = 'QOT_CONTACT';
		$colArr[] = 'QOT_MTML_CATEGORY';
		$colArr[] = 'QOT_PHONE_NO';
		$colArr[] = 'QOT_EMAIL_ADDRESS';
		$colArr[] = 'QOT_ACCOUNT_REF';
		$colArr[] = 'QOT_PACKAGING_INSTRUCTIONS';
		$colArr[] = 'QOT_SUGGESTED_SHIPPER';
		$colArr[] = 'QOT_TRANSPORTATION_MODE';
		$colArr[] = 'QOT_CURRENCY_INSTRUCTIONS';
		$colArr[] = 'QOT_GENERAL_TERMS_CONDITIONS';
		$colArr[] = 'QOT_TAX_STS';
		$colArr[] = 'QOT_DISCOUNT_PERCENTAGE';
		$colArr[] = 'QOT_SHIPPING_COST';
		$colArr[] = 'QOT_ADDITIONAL_COST_DESC1';
		$colArr[] = 'QOT_ADDITIONAL_COST_DESC2';
		$colArr[] = 'QOT_ADDITIONAL_COST_AMOUNT1';
		$colArr[] = 'QOT_ADDITIONAL_COST_AMOUNT2';
		$colArr[] = 'QOT_NOTES';
		$colArr[] = 'QOT_DELIVERY_STS';
		$colArr[] = 'QOT_PRIORITY';
		$this->makeDateTimeCol('QOT_EXPIRY_DATE', $colArr);
		$colArr[] = 'QOT_ARCHIVAL_STS';
		$colArr[] = 'QOT_TERMS_OF_PAYMENT';
		$this->makeDateTimeCol('QOT_ON_TIME_DELIVERY_TILL', $colArr);
		$colArr[] = 'QOT_SUBTOTAL';
		$colArr[] = 'QOT_SCANNED_IMAGE';
		$colArr[] = 'QOT_MTML_EXPORTED';
		$colArr[] = 'QOT_TXN_TRANSACTION_ID';
		$colArr[] = 'QOT_CREATED_BY';
		$this->makeDateTimeCol('QOT_CREATED_DATE', $colArr);
		$colArr[] = 'QOT_UPDATED_BY';
		$this->makeDateTimeCol('QOT_UPDATED_DATE', $colArr);
		$colArr[] = 'QOT_RESPONSE_COMMENTS';
		$colArr[] = 'QOT_TYPE';
		$colArr[] = 'QOT_INTEGRATION_TYPE';
		$colArr[] = 'QOT_DELIVERY_LEAD_TIME';
		$colArr[] = 'QOT_RFQQOT_LINEITEM_CHANGE';
		$colArr[] = 'CREATED_FROM_SUBSUPPLIER_QOTS';
		$colArr[] = 'QOT_SOURCERFQ_INTERNAL_NO';
		$this->makeDateTimeCol('QOT_MTML_EXPORTED_DATE', $colArr);
		$colArr[] = 'QOT_MTML_ACKNOWLEDGED';
		$colArr[] = 'QOT_IS_FOR_APPROVAL';
        // added by Yuriy Akopov on 2013-12-05, S8971
        $colArr[] = 'QOT_BYB_BRANCH_CODE';
        // added by Yuriy Akopov on 2015-03-11, S12888
        $colArr[] = Shipserv_Quote::COL_GENUINE;

		$this->selectSql = join(", ", $colArr);
	}
	
	public function fetchById ($internalRef, $useArchive = false)
	{
		$sqlStmt = "SELECT {$this->selectSql} FROM QUOTE WHERE QOT_INTERNAL_REF_NO = :internalRef";
		$rows = $this->db->fetchAll($sqlStmt, array('internalRef' => $internalRef));
		
		if (!$rows)	{
			if ($useArchive === true) {
				$sqlStmt = "SELECT {$this->selectSql} FROM QUOTE_ARC WHERE QOT_INTERNAL_REF_NO = :internalRef";
				$rows = $this->db->fetchAll($sqlStmt, array('internalRef' => $internalRef));
				
				if (!$rows) {
					throw new Exception("Quote does not exist");
				}
			} else {
				throw new Exception("Quote does not exist");
			}
			
		}
		
		$this->postProcessRows($rows);
		return $rows[0];
	}
	
	/**
	 * NB - Returns quote inner join spb
	 */
	public function fetchByBuyerBranch ($buyerBranchCode, $firstOffset, $pageSize)
	{
		$sqlStmt = $this->makeByBuyerBranchSql("{$this->selectSql}, c.*", "b.QOT_SUBMITTED_DATE DESC");
		$pagedRows = $this->pagedQueryRunner->execute($sqlStmt, $firstOffset, $pageSize, array('bybCode' => $buyerBranchCode));
		$this->postProcessRows($pagedRows['rows']);
		return $pagedRows;
	}
	
	public function fetchByBuyerBranchCount ($buyerBranchCode)
	{
		$sqlStmt = $this->makeByBuyerBranchSql("COUNT(*) CNT");
		$rows = $this->db->fetchAll($sqlStmt, array('bybCode' => $buyerBranchCode));
		return $rows[0]['CNT'];
	}
	
	/**
	 * Make SELECT statment for fetchByBuyerBranch queries.
	 * 
	 * @param string $select e.g. 'col1, F(col2) anAlias, ...'
	 * @param string $orderBy e.g. 'col1 ASC, col2 DESC'
	 */
	private function makeByBuyerBranchSql ($select, $orderBy = null)
	{		
		$sql = 
			"SELECT $select
			FROM REQUEST_FOR_QUOTE a
			INNER JOIN QUOTE b ON b.QOT_RFQ_INTERNAL_REF_NO = a.RFQ_INTERNAL_REF_NO
			INNER JOIN SUPPLIER_BRANCH c ON c.SPB_BRANCH_CODE = b.QOT_SPB_BRANCH_CODE
			WHERE
				a.RFQ_BYB_BRANCH_CODE = :bybCode
				AND b.QOT_QUOTE_STS = 'SUB'";
		
		if ($orderBy != '')
		{
			$sql .= " ORDER BY $orderBy";
		}
		
		return $sql;
	}
	
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
	
	private function makeDateTimeCol ($col, array &$colArr)
	{
		$colArr[] = "TO_CHAR($col, 'YYYY/MM/DD HH24:MI:SS') $col";
		$this->dateTimeCols[$col] = 1;
	}
	
	/**
	 * A temporary method to get demo-friendly Quotes (all belonging to BYB 10390)
	 */
	private function getDemoQuoteRefSql ()
	{
		$idArr = array();
		$idArr[] = 569737;
		$idArr[] = 564174;
		$idArr[] = 564484;
		$idArr[] = 576777;
		$idArr[] = 565916;
		$idArr[] = 571977;
		$idArr[] = 571978;
		$idArr[] = 576487;
		$idArr[] = 570292;
		$idArr[] = 569690;
		$idArr[] = 571639;
		$idArr[] = 572062;
		$idArr[] = 572088;
		$idArr[] = 574469;
		$idArr[] = 570586;
		$idArr[] = 570730;
		$idArr[] = 574319;
		$idArr[] = 574324;
		$idArr[] = 588797;
		$idArr[] = 576669;
		$idArr[] = 578383;
		$idArr[] = 578322;
		$idArr[] = 586127;
		$idArr[] = 586133;
		$idArr[] = 586109;
		$idArr[] = 587682;
		$idArr[] = 587684;
		$idArr[] = 592799;
		$idArr[] = 591492;
		$idArr[] = 631559;
		$idArr[] = 600444;
		$idArr[] = 592851;
		$idArr[] = 595328;
		$idArr[] = 598334;
		$idArr[] = 643034;
		$idArr[] = 599584;
		$idArr[] = 611541;
		$idArr[] = 613599;
		$idArr[] = 613604;
		$idArr[] = 626369;
		$idArr[] = 613608;
		$idArr[] = 626374;
		$idArr[] = 614854;
		$idArr[] = 614832;
		$idArr[] = 616314;
		$idArr[] = 643600;
		$idArr[] = 617971;
		$idArr[] = 634257;
		$idArr[] = 618131;
		$idArr[] = 618162;
		$idArr[] = 618156;
		$idArr[] = 631570;
		$idArr[] = 625541;
		$idArr[] = 620185;
		$idArr[] = 649564;
		$idArr[] = 634335;
		$idArr[] = 624399;
		$idArr[] = 627461;
		$idArr[] = 631420;
		$idArr[] = 626606;
		$idArr[] = 631439;
		$idArr[] = 627804;
		$idArr[] = 627816;
		$idArr[] = 629130;
		$idArr[] = 659271;
		$idArr[] = 630273;
		$idArr[] = 631464;
		$idArr[] = 635777;
		$idArr[] = 630340;
		$idArr[] = 630305;
		$idArr[] = 642347;
		$idArr[] = 637243;
		$idArr[] = 640976;
		$idArr[] = 641145;
		$idArr[] = 641076;
		$idArr[] = 642066;
		$idArr[] = 641090;
		$idArr[] = 650965;
		$idArr[] = 643104;
		$idArr[] = 655306;
		$idArr[] = 648093;
		$idArr[] = 641064;
		$idArr[] = 640991;
		$idArr[] = 646980;
		$idArr[] = 642454;
		$idArr[] = 643142;
		$idArr[] = 644943;
		$idArr[] = 646097;
		$idArr[] = 651821;
		$idArr[] = 645872;
		$idArr[] = 646156;
		$idArr[] = 648804;
		$idArr[] = 650563;
		$idArr[] = 649739;
		$idArr[] = 653147;
		$idArr[] = 652073;
		$idArr[] = 650006;
		$idArr[] = 649993;
		$idArr[] = 653050;
		$idArr[] = 654573;
		return join(',', $idArr);
	}
}
