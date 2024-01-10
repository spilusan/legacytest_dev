<?php

/**
 * Collection of Myshipserv_SupplierTransactionStats_LevelResultRow
 */
class Myshipserv_SupplierTransactionStats_LevelResult extends Myshipserv_SupplierTransactionStats_Result
{
	public function __construct ()
	{
		// Prepare 0 rows for level groups
		foreach (array('BASIC', 'PREMIUM') as $v)
		{
			$this->stats[$v] = new Myshipserv_SupplierTransactionStats_LevelResultRow($v, 0, 0, 0, 0);
		}
	}
	
	/**
	 * Ignores item if not relating to level 'BASIC' or 'PREMIUM'
	 */
	public function addStat (Myshipserv_SupplierTransactionStats_LevelResultRow $row)
	{
		$key = $row->getLevel();
		if (array_key_exists($key, $this->stats))
		{
			$this->stats[$key] = $row;
		}
	}
}
