<?php

/**
 * Transaction statistics relating to a supplier level (e.g. Basic / Premium)
 */
class Myshipserv_SupplierTransactionStats_LevelResultRow extends Myshipserv_SupplierTransactionStats_ResultRow
{
	private $listingLevel;
	
	/**
	 * @param string $listingLevel Listing level
	 * @param int $nValued Number of transactions having a value (i.e. transactions with value not 0 or null)
	 * @param int $sumValue Total dollar value of transactions
	 * @param int $nDistinctSuppliers Number of suppliers considered
	 * @param int $supplierMaxValue Total transaction value for supplier with highest total transaction value
	 */
	public function __construct ($listingLevel, $nValued, $sumValue, $nDistinctSuppliers, $supplierMaxValue)
	{
		parent::__construct($nValued, $sumValue, $nDistinctSuppliers, $supplierMaxValue);
		$this->listingLevel = (string) $listingLevel;
	}
	
	public function getLevel()
	{
		return $this->listingLevel;
	}
}
