<?php

/**
 * Adapter class for Catalogue Service
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 */
abstract class Shipserv_Adapter
{
	/**
	 * Memcache host
	 *
	 * @var string
	 * @access protected
	 */
	protected $memcacheHost;
	
	/**
	 * Memcache port
	 *
	 * @var string
	 * @access protected
	 */
	protected $memcachePort;
	
	/**
	 * The prefix for memcache keys - used for namespacing
	 *
	 * @access protected
	 * @var string
	 */
	protected $memcacheKeyPrefix;
	
	/**
	 * The suffix for memcache keys - used for expiring keys without restarting memcached
	 *
	 * @access protected
	 * @var string
	 */
	protected $memcacheKeySuffix;
	
	/**
	 * A singleton for a memcache object
	 *
	 * @access protected
	 * @var object
	 */
	protected static $memcacheInstance;
	
	/**
	 * Constructor - should be called by the class extending
	 *
	 * @access protected
	 * @param string $memcacheHost
	 * @param string $memcachePort
	 * @param string $memcacheKeyPrefix
	 * @param string $memcacheKeySuffix
	 */
	protected function __construct ($memcacheHost, $memcachePort, $memcacheKeyPrefix, $memcacheKeySuffix)
	{
		$this->memcacheHost = $memcacheHost;
		$this->memcachePort = $memcachePort;
		$this->memcacheKeyPrefix = $memcacheKeyPrefix;
		$this->memcacheKeySuffix = $memcacheKeySuffix;
	}
	
	/**
	 * Fetches a singleton of the memcache object
	 *
	 * @access protected
	 * @return object A memcache object
	 */
	protected function memcacheInstance ()
	{
		if (!self::$memcacheInstance)
		{
			// set up a memcache connector
			$memcache = new Memcache();
			@$memcache->pconnect($this->memcacheHost, $this->memcachePort);
			
			self::$memcacheInstance = $memcache;
		}
		
		return self::$memcacheInstance;
	}
}