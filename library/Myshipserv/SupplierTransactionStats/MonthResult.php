<?php

/**
 * Collection of Myshipserv_SupplierTransactionStats_MonthResultRow
 */
class Myshipserv_SupplierTransactionStats_MonthResult extends Myshipserv_SupplierTransactionStats_Result
{
	/**
	 * @param Myshipserv_SupplierTransactionStats_Month $startMonth First month in result range
	 * @param Myshipserv_SupplierTransactionStats_Month $endMonth Month after last month in result range
	 */
	public function __construct (Myshipserv_SupplierTransactionStats_Month $startMonth, Myshipserv_SupplierTransactionStats_Month $endMonth)
	{
		// Prepare 0 rows for specified range of months
		$tStart = $startMonth->toStamp();
		$tEnd = $endMonth->toStamp();
		$i = 0;
		while (true)
		{
			$iMonth = new Myshipserv_SupplierTransactionStats_Month(strtotime("+$i months", $tStart));
			
			if ($iMonth->toStamp() >= $tEnd)
				break;
			
			$this->stats[$iMonth->toStr()] = new Myshipserv_SupplierTransactionStats_MonthResultRow($iMonth, 0, 0, 0, 0);
			$i++;
		}
	}
	
	/**
	 * Ignores item if it falls outside instance's month range
	 */
	public function addStat (Myshipserv_SupplierTransactionStats_MonthResultRow $row)
	{
		$key = $row->getMonth();
		if (array_key_exists($key, $this->stats))
		{
			$this->stats[$key] = $row;
		}
	}
}
