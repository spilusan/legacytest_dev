<?php

/**
 * Class for reading the continents data from Oracle
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Continents extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	/**
	 * Fetches all continents and places them in an associative array
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchAllContinents ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT CON_CODE, CON_NAME';
		$sql.= '  FROM CONTINENT';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ALLCONTINENTS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	/**
	 * Fetches the continent for a specific code
	 *
	 * @access public
	 * @param string $conCode The 2-character code for the continent to be fetched
	 * @param boolean $cache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchContinentByCode ($conCode, $useCache = true,
										  $cacheTTL = 86400)
	{
		$sql = 'SELECT CON_CODE, CON_NAME';
		$sql.= '  FROM CONTINENT';
		$sql.= ' WHERE CON_CODE = :conCode';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTCODE_'.
			       $countryCode . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('conCode' => $conCode),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('conCode' => $conCode));
		}
		
		return $result;
	}
	
}