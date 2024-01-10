<?php

/**
 * Adapter class for Search Service
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Adapters_Profile extends Shipserv_Adapter
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
		
		$this->client = new Zend_XmlRpc_Client($config->shipserv->services->supplier->url);
		
		parent::__construct($config->memcache->server->host, $config->memcache->server->port,
							$config->memcache->client->keyPrefix, $config->memcache->client->keySuffix);
	}
	
	/**
	 * Adapter for fetching a supplier profile. Will optionally cache the result
	 * if a memcache adapter is passed
	 * 
	 * REQUEST FORMAT:
	 * 
	 * 	methodName
	 * 		SupplierSearch.getProfile
	 * 	params
	 *		tnid (string)
	 * 
	 * @access public
	 * @param int $tnid TradeNet ID of the supplier profile to fetch
	 * @param boolean $useMemcache Optional memcache object used to cache the result
	 * @param int $timeout Memcache key timeout (default 12 hours)
	 * @return array An array of supplier data
	 */
	public function fetch ($tnid, $useMemcache = true, $timeout = 43200)
	{
		$parameters = array($tnid);
		$supplierProfile = false;
		
		if ($useMemcache)
		{
			$memcacheInstance = $this->memcacheInstance();
			
			$key = $this->memcacheKeyPrefix . 'TNID_'.$tnid.$this->memcacheKeySuffix;
			
			$cacheAlive = true;
			if (!$supplierProfile = $memcacheInstance->get($key))
			{
				$cacheAlive = false;
			}
		}
		
		if (!$supplierProfile)
		{
			$supplierProfile = $this->client->call('Supplier.getProfile', $parameters);
			
			if ($useMemcache)
			{
				$memcacheInstance->set($key, $supplierProfile, false, $timeout);
			}
		}
		
		return $supplierProfile;
	}
}