<?php

/**
 * Transaction statistics
 */
abstract class Myshipserv_SupplierTransactionStats_ResultRow
{
	private $nValued;
	private $sumValue;
	private $nDistinctSuppliers;
	private $supplierMaxValue;

	/**
	 * @param int $nValued Number of transactions having a value (i.e. transactions with value not 0 or null)
	 * @param int $sumValue Total dollar value of transactions
	 * @param int $nDistinctSuppliers Number of suppliers considered
	 * @param int $supplierMaxValue Total transaction value for supplier with highest total transaction value
	 */	
	public function __construct ($nValued, $sumValue, $nDistinctSuppliers, $supplierMaxValue)
	{
		$this->nValued = (int) $nValued;
		$this->sumValue = (float) $sumValue;
		$this->nDistinctSuppliers = (int) $nDistinctSuppliers;
		$this->supplierMaxValue = (float) $supplierMaxValue;
	}
	
	public function getNumValued ()
	{
		return $this->nValued;
	}
	
	public function getSumValue ()
	{
		return $this->sumValue;
	}
	
	public function getNumSuppliers ()
	{
		return $this->nDistinctSuppliers;
	}
	
	public function getSupplierMaxValue ()
	{
		return $this->supplierMaxValue;
	}
}
