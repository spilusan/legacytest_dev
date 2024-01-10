<?php

/**
 * Adapter class for Browse Service (a-z listings)
 * 
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Adapters_Browse extends Shipserv_Adapter
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 */
	public function __construct ()
	{
		$config  = Zend_Registry::get('config');
		
		$this->client = new Zend_XmlRpc_Client($config->shipserv->services->browse->url);
		
		parent::__construct($config->memcache->server->host,
							$config->memcache->server->port,
							$config->memcache->client->keyPrefix,
							$config->memcache->client->keySuffix);
	}
	
	/**
	 * Adapter for fetching a supplier profile. Will optionally cache the result
	 * if a memcache adapter is passed
	 * 
	 * REQUEST FORMAT:
	 * 
     * startsWith (string) � case insensitive
     * start (int) � 0 means start from the beginning (0-based)
     * rows (int) � 0 means display everything
	 * 
	 * 	methodName
	 * 		SupplierSearch.getProfiles
	 * 	params
	 *		tnid (string)
	 * 
	 * @access public
	 * @param string $startsWith The letter for browse
	 * @param int $start The offset to start browsing with
	 * @param int $rows The number of rows to return (0 returns all)
	 * @param boolean $premiumListing Set to true to only return Premium Profiles
	 * @param boolean $startsWithNonAlpha Set to true to return
	 * @param boolean $useMemcache Optional memcache object used to cache the result
	 * @param int $cacheTTL Memcache key timeout (default 12 hours)
	 * @return array An array of supplier data
	 */
	public function fetch ($startsWith, $start, $rows, $premiumListing, $startsWithNonAlpha = false, $useMemcache = false, $cacheTTL = 43200)
	{
		$parameters = array(array('startsWith' => $startsWith,
								  'start'      => (int) $start,
								  'rows'       => (int) $rows,
								  'premiumListing' => (boolean) $premiumListing,
								  'startsWithNonAlpha' => (boolean) $startsWithNonAlpha));
		
		$profiles = false;
		
		if ($useMemcache)
		{
			$memcacheInstance = $this->memcacheInstance();
			
			$key = $this->memcacheKeyPrefix . 'BROWSE_'.$startsWith.'-'.$start
										    .'-'.$rows.$this->memcacheKeySuffix;
			
			$cacheAlive = true;
			if (!$profiles = $memcacheInstance->get($key))
			{
				$cacheAlive = false;
			}
		}
		
		if (!$profiles)
		{
			$profiles = $this->client->call('Supplier.getProfiles', $parameters);
			
			if ($useMemcache)
			{
				$memcacheInstance->set($key, $profiles, false, $cacheTTL);
			}
		}
		
		return $profiles;
	}
}