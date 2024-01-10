<?php

/**
 * Represents a calendar-aligned month for the purpose of aggregating statistics
 * over such a period.
 */
class Myshipserv_SupplierTransactionStats_Month
{
	private $tStamp;
	
	/**
	 * Initialize from any Timestamp within month to be represented.
	 *
	 * @oaram int $tStamp Timestamp
	 */
	public function __construct ($tStamp)
	{
		$tStamp = (int) $tStamp;
		if ($tStamp >= 0)
		{
			$this->tStamp = $tStamp;
			return;
		}
		throw new Exception();
	}
	
	/**
	 * Initialize from string representation of month in Y-m format.
	 *
	 * @param string $str
	 */
	public static function fromStr($str)
	{
		$tStamp = strtotime($str);
		if (! $tStamp) throw new Exception();
		
		return new self($tStamp);
	}
	
	/**
	 * @return string Month in Y-m format.
	 */
	public function toStr ()
	{
		return date('Y-m', $this->tStamp);
	}
	
	/**
	 * @return int Timestamp of 00:00:00 on 1st of specified month.
	 */
	public function toStamp ()
	{
		return $this->tStamp;
	}
}
