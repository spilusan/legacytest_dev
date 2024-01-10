<?php

class Shipserv_Oracle_Util_DbTime
{
	/**
	 * @param $dbTime string 'yyyy/mm/dd hh24:mi:ss', or null.
	 * @return object or null (if $dbTime === null).
	 */
	public static function parseDbTime ($dbTime, $format = "yyyy/mm/dd hh24:mi:ss")
	{
		if ($dbTime === null)
		{
			return null;
		}

		$dbTime = (string) $dbTime;

		if( $format == "yyyy/mm/dd hh24:mi:ss" )
		{
			$tParts['yyyy'] = substr($dbTime, 0, 4);
			$tParts['mm'] = substr($dbTime, 5, 2);
			$tParts['dd'] = substr($dbTime, 8, 2);
			$tParts['hh24'] = substr($dbTime, 11, 2);
			$tParts['mi'] = substr($dbTime, 14, 2);
			
			$res = gmmktime($tParts['hh24'], $tParts['mi'], 0, $tParts['mm'], $tParts['dd'], $tParts['yyyy']);
		} 
		else if( $format == "dd/mmm/yyyy" )
		{
			$tParts['yyyy'] = substr($dbTime, 7, 4);
			$tParts['mm'] = substr($dbTime, 3, 3);
			$tParts['dd'] = substr($dbTime, 0, 2);
			$tParts['hh24'] = 0;
			$tParts['mi'] = 0;
				print_r( $tParts );
			$res = gmmktime($tParts['hh24'], $tParts['mi'], 0, $tParts['mm'], $tParts['dd'], $tParts['yyyy']);			
		}
		return new self($res);
	}
	
	private $tStamp;
	
	public function __construct ($timestamp)
	{
		$this->tStamp = (int) $timestamp;
	}
	
	public function getTimestamp ()
	{
		return $this->tStamp;
	}
	
	public function format($format = 'd M Y')
	{
		$date = new DateTime("@" . $this->tStamp);
		return $date->format( $format );
	}
	
}
