<?php

/**
 * API to application for transaction statistics on results page
 * and profile page.
 *
 * Stats are parameterized by supplier tnid, or a set of supplier tnids.
 * Moderation is applied to ensure that sensitive data are not revealed.
 * In the event of moderation, generalized, safe stats are returned.
 *
 * Generalized stats are cached in Memcache.
 */
class Myshipserv_SupplierTransactionStats
{
	private $db;
	private $statGen;
	
	// Memcache variables
	private $useCache;
	private $memcacheConfig;
	
	/**
	 * @param Zend_Db_Adapter_Oracle $db
	 */
	public function __construct (Zend_Db_Adapter_Oracle $db)
	{
		$this->db = $db;
		$this->statGen = new Myshipserv_SupplierTransactionStats_Db($this->db);
		
		// Init Memcache variables
		$this->useCache = (APPLICATION_ENV == 'live') ? true : false;
		if ($this->useCache)
		{
			$config = Zend_Registry::get('config');
			
			$this->memcacheConfig = $config->memcache;
		}
	}
	
	/**
	 * Utility function: extract list of supplier ids from search service result array
	 *
	 * @param array $result
	 * @return array of supplier tnids
	 */
	public static function extractTnids (array $result)
	{
		$tnidArr = array();
		foreach ($result['documents'] as $d)
		{
			$tnidArr[] = $d['tnid'];
		}
		return $tnidArr;
	}
	
	/**
	 * Build statistics for display on supplier profile page.
	 *
	 * @param int $supplierTnid
	 * @return Myshipserv_SupplierTransactionStats_ApiResult
	 */
	public function supplierProfileStats ($supplierTnid)
	{
		$tnidSql = Myshipserv_SupplierTransactionStats_SupplierTnidSql::fromTnidArrViaCategories($this->db, array($supplierTnid));
		return $this->executeAndModerate($tnidSql);
	}
	
	/**
	 * Build statistics for display on search results page.
	 *
	 * @param array of int $supplierTnids
	 * @return Myshipserv_SupplierTransactionStats_ApiResult
	 */
	public function resultPageStats (array $supplierTnids)
	{
		$tnidSql = Myshipserv_SupplierTransactionStats_SupplierTnidSql::fromTnidArr($this->db, $supplierTnids);
		return $this->executeAndModerate($tnidSql);
	}
	
	/**
	 * Cache layer for generic stats (which change very slowly)
	 */
	private function getGeneralStatsFromCache ()
	{
		if ($this->useCache)
		{
			// Attempt to read from cache
			$memcache = new Memcache();
			$memcache->connect($this->memcacheConfig->server->host,
							   $this->memcacheConfig->server->port);
			
			$key = $this->memcacheConfig->client->keyPrefix . get_class($this) . '_GenericStats_' . $this->memcacheConfig->client->keySuffix;				
			$stats = $memcache->get($key);
			
			// If read failed, get stats from db and cache result
			if ($stats === false)
			{
				$stats = $this->getGeneralStats();
				$cacheTTL = 60 * 60 * 3; // 3 hours
				$memcache->set($key, $stats, false, $cacheTTL);
			}
		}
		else
		{
			// If we're not using the cache, just generate them from db
			$stats = $this->getGeneralStats();
		}
		
		return $stats;
	}
	
	/**
	 * Query for generic stats.
	 */
	private function getGeneralStats ()
	{
		$tnidSql = Myshipserv_SupplierTransactionStats_SupplierTnidSql::allSuppliers();
		$o = new Myshipserv_SupplierTransactionStats_ApiResult(
			$this->statGen->byMonth($tnidSql),
			$this->statGen->bySupplierLevel($tnidSql)
		);
		$o->setIsTopLevel(true);
		return $o;
	}
	
	/**
	 * Executes query and checks results conform with what we can display.
	 */	
	private function executeAndModerate (Myshipserv_SupplierTransactionStats_SupplierTnidSql $tnidSql)
	{
		$resArr = array();
		$res = null;
		
		foreach (
			array('byMonth', 'bySupplierLevel')
			as
			$methodName
		)
		{
			// Generate stats
			$resArr[$methodName] = $this->statGen->$methodName($tnidSql);
			
			// Check stats are suitable for display
			if (! $this->boolSafeToReveal($resArr[$methodName]))
			{
				$res = $this->getGeneralStatsFromCache();
				break;
			}
		}
		
		if (! $res)
		{
			$res = new Myshipserv_SupplierTransactionStats_ApiResult($resArr['byMonth'], $resArr['bySupplierLevel']);
		}
		
		return $res;
	}
	
	/**
	 * Checks statistics to ensure they are safe for display
	 *
	 * @return bool True if ok to display, or false.
	 */
	private function boolSafeToReveal (Myshipserv_SupplierTransactionStats_Result $result)
	{
		// For each line item (e.g. month, or supplier level)
		foreach ($result->getStats() as $row)
		{
			// Ensure not revealing information relating to a sole supplier
			if ($row->getNumSuppliers() < 2)
			{
				//echo "Failed min number of suppliers test";
				return false;
			}
			
			// Ensure $ value big enough
			if ($row->getSumValue() < 5000)
			{
				//echo "Failed min value test";
				return false;
			}
		}
		
		return true;
	}
}
