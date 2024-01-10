<?php

/**
 * Transaction statistics for given month
 */
class Myshipserv_SupplierTransactionStats_MonthResultRow extends Myshipserv_SupplierTransactionStats_ResultRow
{
	private $monthStart;
	
	/**
	 * @param Myshipserv_SupplierTransactionStats_Month $monthStart Month over which statistics calculated
	 * @param int $nValued Number of transactions having a value (i.e. transactions with value not 0 or null)
	 * @param int $sumValue Total dollar value of transactions
	 * @param int $nDistinctSuppliers Number of suppliers considered
	 * @param int $supplierMaxValue Total transaction value for supplier with highest total transaction value
	 */	
	public function __construct (Myshipserv_SupplierTransactionStats_Month $monthStart, $nValued, $sumValue, $nDistinctSuppliers, $supplierMaxValue)
	{
		parent::__construct($nValued, $sumValue, $nDistinctSuppliers, $supplierMaxValue);
		$this->monthStart = $monthStart;
	}
	
	public function getMonth()
	{
		return $this->monthStart->toStr();
	}
}
