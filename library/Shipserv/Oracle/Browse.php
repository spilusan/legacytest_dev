<?php

/**
 * Class for reading the browse data from Oracle
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Browse extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	public function fetchBrowseLinks ()
	{
		
		
		$result = $this->db->fetchAll($sql);
	}
	
	/**
	 * Fetches the top brands from Oracle (as defined in the PAGES_TOP_BRAND table)
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchTopBrands ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PTB.PTB_RANK, PTB.PTB_DISPLAY_TITLE,';
		$sql.= '       B.NAME, B.ID';
		$sql.= '  FROM PAGES_TOP_BRAND PTB, BRAND B';
		$sql.= ' WHERE PTB.PTB_BRAND_ID = B.ID';
		$sql.= ' ORDER BY PTB.PTB_RANK ASC';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'TOPBRANDS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
}