<?php

/**
 * Collection of Myshipserv_SupplierTransactionStats_ResultRow
 */
abstract class Myshipserv_SupplierTransactionStats_Result
{
	private $sql = '';
	protected $stats = array();
	
	/**
	 * Debug
	 */
	public function setSql ($sql)
	{
		$this->sql = (string) $sql;
	}
	
	/**
	 * Debug
	 */
	public function getSql ()
	{
		return $this->sql;
	}
	
	/**
	 * @return array of Myshipserv_SupplierTransactionStats_ResultRow
	 */
	public function getStats ()
	{
		return $this->stats;
	}
	
	/**
	 * Calculate sum of transaction values over items
	 */
	public function getTotal ()
	{
		$t = 0;
		foreach ($this->stats as $row)
		{
			$t += $row->getSumValue();
		}
		return $t;
	}
	
	/**
	 * Fetch maximum transaction value over items
	 */
	public function getMax ()
	{
		$m = null;
		foreach ($this->stats as $row)
		{
			$v = $row->getSumValue();
			if ($v > $m) $m = $v;
		}
		return $m;
	}
}
