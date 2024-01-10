<?php
/**
* Extention of Shipserv_Oracle abstract class to provide cached query function for the singleton classes
*/

class Shipserv_Oracle_Targetcustomers_Dao extends Shipserv_Oracle {

	const
		  CACHE_TTL = 7200
		, DATABASE_NAME = 'ssreport2'
		, DATABASE_NAME_SSERVDBA = 'sservdba'
	;

	public function fetchQuery ($sql, $sqlData, $method )
	{
		$key = $method . '_' . md5($sql . print_r($sqlData, true));
		return $this->fetchCachedQuery ($sql, $sqlData, $key, self::CACHE_TTL, self::DATABASE_NAME);
	}

	public function fetchDbQuery ($sql, $sqlData, $method )
	{
		$key = $method . '_' . md5($sql . print_r($sqlData, true));
		return $this->fetchCachedQuery ($sql, $sqlData, $key, self::CACHE_TTL, self::DATABASE_NAME_SSERVDBA);
	}


	

}