<?php

/**
 * Class for abstracting Memcache
 *
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Memcache
{
	/**
	 * 
	 * 
	 * @access protected
	 * @var object
	 */
	static $memcache;
	
	static $keyPrefix;
	
	static $keySuffix;
	
	/**
	 * Config detail for the website
	 * 
	 * @access protected
	 * @var object
	 */
	protected static $config = false;

	protected $memcacheConfig = null;
	
	/**
	 * Recommended cache TTL
	 */
	const MEMCACHE_TTL = 86400;
	
	/**
	 * Flag indicating that Memcache connection has been attempted already
	 */
	private static $mcConnectTriedFlag = false;

	public ?int $ttl = null;

	public function __construct()
	{
		$this->memcacheConfig = self::getConfig()->memcache;
	}

	public static function getConfig()
	{
		if( self::$config === false )
		{
			$config = Zend_Registry::get('config');
			
			self::$config = $config;
		}
		return self::$config;	
	}
	
	
	/**
	 * Singleton method
	 * 
	 * @return Memcache|null
	 */
	public static function getMemcache()
	{
		$config = self::getConfig();
		
		// Attempt to connect if memcache conn not initialised
		// and if this is first attempt to connect
		if (!self::$memcache && !self::$mcConnectTriedFlag)
		{
			// Set flag to ensure no attempt to re-connect
			self::$mcConnectTriedFlag = true;
			
			$memcache = new Memcache();
			$cOk = @$memcache->pconnect($config->memcache->server->host, $config->memcache->server->port);
			
			// Save conn if successful
			if ($cOk) self::$memcache = $memcache;
		}
		// Return conn if present
		if (self::$memcache) return self::$memcache;
	}
	
	
	protected function setMemcacheTTL( $ttl )
	{
		$this->ttl = $ttl;
	}
	
	protected function getMemcacheTTL()
	{
		if( $this->ttl != "" )
			return $this->ttl;
		else 
			self::MEMCACHE_TTL;
	}

    /**
     * Decorates the given key in the same way as memcacheGet/Set do and purges the cached value
     *
     * @author  Yuriy Akopov
     * @date    2013-08-07
     *
     * @param   string  $className
     * @param   string  $methodName
     * @param   string  $key
     *
     * @return  bool
     */
    protected function memcachePurge($className, $methodName, $key)
	{
        $mckey = $this->makeKey($className, $methodName, $key);

		return $this->purgeCache($mckey);
	}
	
	protected function purgeCache($key)
	{
        $memcache = $this::getMemcache();
        if (!$memcache) return false;

        if ($memcache->get($key) !== false)
        {
            return $memcache->delete($key, 0);
        }

		return false;
	}
	
	/**
	 * Read key from Memcache.
	 * (Key is constructed from class & method names)
	 * 
	 * @return mixed or false on failure
	 */
	protected function memcacheGet($className, $methodName, $key)
	{
		$memcache = $this::getMemcache();
		if (!$memcache) return false;
		
        $mcKey = $this->makeKey($className, $methodName, $key);

		$mcVal = $memcache->get($mcKey);
		return $mcVal;
	}
	
	/**
	 * Set key in Memcache
	 * (Key is constructed from class & method names)
	 * 
	 * @return bool
	 */
	protected function memcacheSet($className, $methodName, $key, $val)
	{
		$memcache = $this::getMemcache();
		if (!$memcache) return false;
		
        $mcKey = $this->makeKey($className, $methodName, $key);

		return $memcache->set($mcKey, $val, false, $this->getMemcacheTTL() );
	}
	
	/**
	 * Utility method for making a unique Memcache key based on class name,
	 * method name & method-specific data ($key).
     *
     * Refactored by Yuriy Akopov on 2013-08-07
     * WARNING: behaviour changed - previously $key with no class and method name supplied was returned unwrapped
     *
     * @param   string  $className
     * @param   string  $methodName
     * @param   string  $key
     *
     * @return  string
     */
	protected function makeKey($className, $methodName, $key = null)
	{
        $keyParts = array();

        if (strlen($className . $methodName)) {
            $keyParts[] = $className;
            $keyParts[] = $methodName;
        }

        if (strlen($key)) {
            $keyParts[] = $key;
        }

        $key = implode('_', $keyParts);

		return $this->wrapKey($key);
	}

	/**
	 * Wraps Memcache key with prefix & suffix dictated by app env.
	 */
	protected function wrapKey ($rawKey)
	{
        $config = self::getConfig();

		return $config->memcache->client->keyPrefix . $rawKey . $config->memcache->client->keySuffix;
	}

	public static function generateKey($key)
	{
		return self::$keyPrefix . $key . self::$keySuffix;
	}
	
	/**
	 * Pull all available data on memcache
	 * 
	 * @param string $prefix prefix for memcache keys
	 * @return object containing information of everything that is cached
	 */
	public static function peekMemcache( $prefix = "" )
	{
	  	$list = array();

	  	// memcache connectivity
		$memcache = new Memcache;
		
	  	$config = Zend_Registry::get('config');
		$memcache->connect($config->memcache->server->host,
				   		   $config->memcache->server->port);
							   		   
		
		if( !$memcache )
		throw new Exception("Memcached isn't running.");
		
	  	$allSlabs = $memcache->getExtendedStats('slabs');
	  	
	    $items = $memcache->getExtendedStats('items');
	    
	    foreach($allSlabs as $server => $slabs) 
	    {
    	    foreach($slabs AS $slabId => $slabMeta) 
    	    {
    	        $cdump = $memcache->getExtendedStats('cachedump',(int)$slabId);
    	        foreach($cdump AS $server => $entries) 
    	        {
    	            if($entries) 
    	            {
        	            foreach($entries AS $eName => $eData) 
        	            {
        	                if( $prefix != "" )
        	                {
        	                	if( strstr( $eName, $prefix ) !== false )
        	                	{
        	                	   	$list[$eName] = array(
	        	                    	'key' => $eName,
	        	                    	'server' => $server,
	        	                     	'slabId' => $slabId,
	        	                     	'detail' => $eData,
	        	                     	'age' => $items[$server]['items'][$slabId]['age'],
	        	                    );
        	                	}
        	            	}
        	            	else 
        	            	{	
	        	            	$list[$eName] = array(
	        	                     'key' => $eName,
	        	                     'server' => $server,
	        	                     'slabId' => $slabId,
	        	                     'detail' => $eData,
	        	                     'age' => $items[$server]['items'][$slabId]['age'],
	        	                );
        	            	}
        	            }
    	            }
    	        }
    	    }
	    }
	    
	    ksort($list);
		return $list;	
	}
}