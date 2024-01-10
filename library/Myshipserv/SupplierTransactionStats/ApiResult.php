<?php

/**
 * Statistics passed to application controller layer by
 * methods of Myshipserv_SupplierTransactionStats.
 *
 * Represents a pairing of statistics broken by month and by supplier level.
 */
class Myshipserv_SupplierTransactionStats_ApiResult
{
	private $monthResult;
	private $levelResult;
	private $isTopLevel = false;
	
	public function __construct (Myshipserv_SupplierTransactionStats_MonthResult $mRes, Myshipserv_SupplierTransactionStats_LevelResult $lRes)
	{
		$this->monthResult = $mRes;
		$this->levelResult = $lRes;
	}
	
	/**
	 * @return bool Result is censored to top-level stats
	 */
	public function isTopLevel ()
	{
		return $this->isTopLevel;
	}
	
	public function setIsTopLevel($bool)
	{
		$this->isTopLevel = (bool) $bool;
	}
	
	/**
	 * @return Myshipserv_SupplierTransactionStats_MonthResult
	 */
	public function getMonthResult ()
	{
		return $this->monthResult;
	}
	
	/**
	 * @return Myshipserv_SupplierTransactionStats_LevelResult
	 */
	public function getLevelResult ()
	{
		return $this->levelResult;
	}
	
	/**
	 * Extract by-month statistics into array:
	 * 
	 * array (
	 * 		[0] => array (<month in Y-m format>, <transaction value in $>)
	 * 		[1] => ...
	 * )
	 * 
	 * @return array
	 */
	public function byMonthAsArr ()
	{
		$res = array();
		foreach ($this->monthResult->getStats() as $row)
		{
			$res[] = array($row->getMonth(), $row->getSumValue());
		}
		return $res;
	}
	
	/**
	 * Extract by-month statistics into array:
	 * 
	 * array (
	 * 		[0] => array (<supplier level as string>, <transaction value in $>)
	 * 		[1] => ...
	 * )
	 * 
	 * @return array
	 */
	public function byLevelAsArr ()
	{
		$res = array();
		foreach ($this->levelResult->getStats() as $row)
		{
			$res[] = array($row->getLevel(), $row->getSumValue());
		}
		return $res;
	}
}
