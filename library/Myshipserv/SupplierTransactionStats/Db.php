<?php

/**
 * Fetches transaction stats from DB cut by month, or by supplier listing level.
 */
class Myshipserv_SupplierTransactionStats_Db
{
	private $db;
	private $tNow;
	
	/**
	 * @param Zend_Db_Adapter_Oracle $db
	 */
	public function __construct (Zend_Db_Adapter_Oracle $db)
	{
		$this->db = $db;
		$this->tNow = time();
	}
	
	/**
	 * Fetch transaction stats cut by month.
	 *
	 * @param Myshipserv_SupplierTransactionStats_SupplierTnidSql $supplierTnidSql
	 * @return Myshipserv_SupplierTransactionStats_MonthResult
	 */
	public function byMonth (Myshipserv_SupplierTransactionStats_SupplierTnidSql $supplierTnidSql)
	{
		// Set time window for stats query
		$calTimeArr = $this->makeCalTime();
		$fromTime = $calTimeArr[0];
		$toTime = end($calTimeArr);
		
		// Build SQL
		$sql = "";
		
		// Outer query performs roll-up by month
		$sql .= "SELECT MONTH_START, SUM(N_VALUED) N_VALUED, SUM(SUM_VALUE) SUM_VALUE, COUNT(DISTINCT PT_SUPPLIER_TNID) N_DISTINCT_SUPPLIERS, MAX(SUM_VALUE) SUPPLIER_MAX_VALUE FROM (";
		
		// Inner query performs first-pass roll-up by month and by supplier id
		$sql .= 	"SELECT TO_CHAR(PT_ORD_SUBMITTED_DATE, 'yyyy-mm') MONTH_START, PT_SUPPLIER_TNID, COUNT(NULLIF(PT_ORD_ADJ_TOTAL_COST_USD, 0)) N_VALUED, NVL(SUM(PT_ORD_ADJ_TOTAL_COST_USD), 0) SUM_VALUE";
		$sql .= 		" FROM PAGES_TRANSACTION";
		$sql .= 		" WHERE PT_ORD_SUBMITTED_DATE >= DATE '$fromTime' AND PT_ORD_SUBMITTED_DATE < DATE '$toTime'";
		
		// Constrain by supplier id if present
		if ($supplierTnidSql != '')
			$sql .= 		" AND PT_SUPPLIER_TNID IN ($supplierTnidSql)";
		
		// Add inner query's group by
		$sql .= 		" GROUP BY TO_CHAR(PT_ORD_SUBMITTED_DATE, 'yyyy-mm'), PT_SUPPLIER_TNID";
		
		// Add outer query's group by
		$sql .= ") GROUP BY MONTH_START";
		
		$returnRes = new Myshipserv_SupplierTransactionStats_MonthResult(
			Myshipserv_SupplierTransactionStats_Month::fromStr($fromTime),
			Myshipserv_SupplierTransactionStats_Month::fromStr($toTime)
		);
		
		$returnRes->setSql($sql);
		
		$res = $this->db->fetchAll($sql);
		foreach ($res as $row)
		{
			$returnRes->addStat(
				new Myshipserv_SupplierTransactionStats_MonthResultRow(
					Myshipserv_SupplierTransactionStats_Month::fromStr($row['MONTH_START']),
					$row['N_VALUED'],
					$row['SUM_VALUE'],
					$row['N_DISTINCT_SUPPLIERS'],
					$row['SUPPLIER_MAX_VALUE']
				)
			);
		}
		
		return $returnRes;
	}
	
	/**
	 * Fetch transaction stats cut by supplier level (BASIC/PREMIUM).
	 *
	 * @param Myshipserv_SupplierTransactionStats_SupplierTnidSql $supplierTnidSql
	 * @return Myshipserv_SupplierTransactionStats_LevelResultRow
	 */
	public function bySupplierLevel (Myshipserv_SupplierTransactionStats_SupplierTnidSql $supplierTnidSql)
	{
		// Set time window for stats query
		$calTimeArr = $this->makeCalTime();
		$fromTime = $calTimeArr[0];
		$toTime = end($calTimeArr);
		
		// Build SQL
		$sql = "";
		
		// Outer query rolls up by simplified listing level
		$sql .= "SELECT DECODE(PT_LISTING_LEVEL, 4, 'PREMIUM', 'BASIC') LISTING_LEVEL, SUM(N_VALUED) N_VALUED, SUM(SUM_VALUE) SUM_VALUE, COUNT(DISTINCT PT_SUPPLIER_TNID) N_DISTINCT_SUPPLIERS, MAX(SUM_VALUE) SUPPLIER_MAX_VALUE FROM (";
		
		// Inner query performs first-pass roll-up by listing level and by supplier id
		$sql .= 	"SELECT PT_LISTING_LEVEL, PT_SUPPLIER_TNID, COUNT(NULLIF(PT_ORD_ADJ_TOTAL_COST_USD, 0)) N_VALUED, NVL(SUM(PT_ORD_ADJ_TOTAL_COST_USD), 0) SUM_VALUE";
		$sql .= 		" FROM PAGES_TRANSACTION";
		$sql .= 		" WHERE PT_ORD_SUBMITTED_DATE >= DATE '$fromTime' AND PT_ORD_SUBMITTED_DATE < DATE '$toTime'";
		
		if ($supplierTnidSql != '')
			$sql .= 	" AND PT_SUPPLIER_TNID IN ($supplierTnidSql)";
			
		// Add inner query's group by
		$sql .=			" GROUP BY PT_LISTING_LEVEL, PT_SUPPLIER_TNID";
		
		// Add outer query's group by
		$sql .= ") GROUP BY DECODE(PT_LISTING_LEVEL, 4, 'PREMIUM', 'BASIC')";
		
		$returnRes = new Myshipserv_SupplierTransactionStats_LevelResult();
		
		$returnRes->setSql($sql);
		
		// Execute query and push results into an array
		$res = $this->db->fetchAll($sql);
		foreach ($res as $row)
		{
			$returnRes->addStat(
				new Myshipserv_SupplierTransactionStats_LevelResultRow(
					$row['LISTING_LEVEL'],
					$row['N_VALUED'],
					$row['SUM_VALUE'],
					$row['N_DISTINCT_SUPPLIERS'],
					$row['SUPPLIER_MAX_VALUE']
				)
			);
		}
		
		return $returnRes;
	}

	/**
	 * Calculates date of start of month for last 3 calendar months
	 * and (incomplete) current month.
	 */
	private function makeCalTime ()
	{
		$arr = array();
		for ($i = 3; $i >= 0; $i--)
		{
			$arr[] = date('Y-m-01', strtotime("$i months ago", $this->tNow));
		}
		
		return $arr;
	}
}
