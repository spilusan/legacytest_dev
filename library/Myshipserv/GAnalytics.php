<?php

/**
 * Application's API onto Google Analytics.
 * Uses a disk cache to ensure API calls not repeated as they are SLOW.
 * Methods for cacheing with Memcache are also included - easy to switch if desired.
 */
class Myshipserv_GAnalytics
{
	const CACHE_TTL_SECS = 86400;  // 1 day
	
	const DISK_CACHE_KEY = 'Visits';
	
	private $config;
	
	// Lazily created instance: use getter
	private static $diskCache;
	
	// Lazily created instance: use getter
	private static $memcache;
	
	public function __construct ()
	{
		$this->config = Zend_Registry::get('config');
	}
	
	/**
	 * Fetch number of vists over last week from GA API.
	 *
	 * @return int or null if data not available (rare).
	 */	
	public function getSiteVisits ( $duration = "1 week" )
	{
		return $this->fetchFromCache( $duration );
	}
	
	/**
	 * Attemps to fetch data from disk cache. If cache is expired,
	 * calls API to fetch fresh data. If API call fails, attempts to
	 * revert to old, expired cache data.
	 * 
	 * @return mixed Cached, or fresh, data from API, or null on fail (rare)
	 */
	private function fetchFromCache ( $duration )
	{
		if( $duration == "1 week")
		{
			// Read cache using expiry: false if expired (or not in cache)
			$res = $this->fetchFromDiskCache();
		}
		else 
		{
			$res = false;
		}
		
		// No value from cache
		if ($res === false)
		{
			$this->log("Data not in cache, or expired: calling API");
			
			// Call API
			try
			{
				$res = $this->getSiteVisitsFromApi( $duration );
				
				$this->log("No exception back from API call: assume success, save to cache, return");
				
				// No exception, so write new data to cache
				$this->saveToDiskCache($res);
			}
			catch (Exception $e)
			{
				$this->log("EXCEPTION back from API call: attempt to recover expired data from cache");
				
				// Problem calling API: pull expired data from cache
				$res = $this->fetchFromDiskCache(true);
			}
		}
		
		// Still no data? API call must have failed and there was no expired data in cache
		if ($res === false)
		{
			$this->log("Failed to fetch data from API or from cache: returning null");
			
			$res = null;
		}
		
		return $res;
	}
	
	/**
	 * Calls API.
	 * Throws Exception on failure, or on suspicious looking result.
	 * 
	 * @return int Number of visits over last week
	 */
	private function getSiteVisitsFromApi ( $duration = "1 week")
	{
		// Allow any exceptions to bubble
		
		// Call API
		$ga = new Myshipserv_GAnalytics_GApi($this->config->google->services->analytics->user, $this->config->google->services->analytics->password);		
		$dateArr = $this->getTimeWindow( $duration );
		
		$ga->requestReportData($this->config->google->services->analytics->profile, array(), array('visitors', 'visits', 'pageviews'), null, null, $dateArr['startDate'], $dateArr['endDate']);		
		$res = (int) $ga->getVisits();

		// If results don't look right, force Exception
		if ($res <= 0)
		{
			throw new Exception();
		}
		
		return $res;
	}
	
	/**
	 * Form date window for stats of a week from yesterday.
	 * 
	 * @return array
	 */
	private function getTimeWindow ( $duration = "1 week" )
	{
		$res = array();
		
		$tNow = strtotime('-2 day');
		$res['endDate'] = date('Y-m-d', $tNow);
		if( $duration == "1 week" )
		{
			$tLastWeek = strtotime('-6 days', $tNow);
			$res['startDate'] = date('Y-m-d', $tLastWeek);
		}
		else if( $duration == "3 months" )
		{
			$tLastWeek = strtotime('-3 month', $tNow);
			$res['startDate'] = date('Y-m-d', $tLastWeek);
		}

		return $res;
	}
	
	/**
	 * Debug
	 */
	private function log ($msg)
	{
		// Comment out for live:
		// echo $msg . "<br/>";
	}
	
	// Methods relating to disk cache from here down
	
	/**
	 * @param bool $ignoreExpiry If true, expired item returned as if fresh.
	 * @return mixed or false if item not in cache.
	 */
	private function fetchFromDiskCache ($ignoreExpiry = false)
	{
		$cache = $this->getDiskCache();
		return $cache->load(self::DISK_CACHE_KEY, $ignoreExpiry);		
	}
	
	private function saveToDiskCache ($result)
	{
		$this->getDiskCache()->save($result, self::DISK_CACHE_KEY);
	}
	
	/**
	 * Manage singleton disk cache
	 * 
	 * @return Zend_Cache
	 */
	private function getDiskCache ()
	{
		if (! self::$diskCache)
		{
			$frontendOptions = array(
				'lifetime' => self::CACHE_TTL_SECS,
				'automatic_serialization' => true
			);
			
			$backendOptions = array(
				'cache_dir' => $this->config->shipserv->diskcache->dir
			);
			
			self::$diskCache = Zend_Cache::factory(
				'Core',
				'File',
				$frontendOptions,
				$backendOptions
			);
		}
		
		return self::$diskCache;
	}
	
	// Memcache functionality (from here down) no longer used.
	// Ditched in favour of a disk cache - more reliable
	
	/**
	 * Generate memcache key
	 */
	private function memcacheKey ()
	{
		return $this->config->memcache->client->keyPrefix
			. get_class($this) . '_'
			. 'Visits'
			. $this->memcacheConfig->client->keySuffix;
	}
	
	/**
	 * Save to memcache
	 */
	private function saveToMemcache ($result)
	{
		// Write to cache
		$key = $this->memcacheKey();
		$cacheTTL = self::CACHE_TTL_SECS;
		$this->getMemcache()->set($key, $result, false, $cacheTTL);
	}
	
	/**
	 * Read from cache
	 * 
	 * @return mixed or false if item not in cache.
	 */
	private function fetchFromMemcache ()
	{
		// Read from cache
		$memcache = $this->getMemcache();
		$key = $this->memcacheKey();
		$result = $memcache->get($key);
		return $result;
	}
	
	/**
	 * Manage singleton memcache connection
	 *
	 * @return Memcache
	 */
	private function getMemcache ()
	{
		if (! self::$memcache)
		{
			self::$memcache = new Memcache();
			self::$memcache->connect($this->config->memcache->server->host,
							   $this->config->memcache->server->port);
		}
		return self::$memcache;
	}
}
