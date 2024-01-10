<?php

/**
 * Adapter class for Catalogue Service
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Adapters_Catalogue extends Shipserv_Adapter
{
	/**
	 * Uri for browse
	 *
	 * @access protected
	 * @var string
	 */
	protected $browseUri;
	
	/**
	 * Uri for search
	 *
	 * @access protected
	 * @var string
	 */
	protected $searchUri;
	
	/**
	 * Set up the URI for the JSON service, as well as Memcache server details
	 * 
	 * @access public
	 */
	public function __construct ()
	{
		$config  = Zend_Registry::get('config');
		
		$this->browseUri = $config->shipserv->services->catalogue->browse->url;
		$this->searchUri = $config->shipserv->services->catalogue->search->url;
		
		parent::__construct($config->memcache->server->host, $config->memcache->server->port,
							$config->memcache->client->keyPrefix, $config->memcache->client->keySuffix);
	}
	
	/**
	 * Adapter for fetching a supplier catalogue. Will optionally cache the result
	 * if a memcache adapter is passed
	 * 
	 * @access public
	 * @param int $id TradeNet ID or Catalogue ID of the catalogue profile to fetch
	 * @param int $folderStart
	 * @param int $folderRows
	 * @param int $itemStart
	 * @param int $itemRows
	 * @param boolean $useMemcache Use memcache to cache the result
	 * @param int $timeout Memcache key timeout (default 12 hours)
	 * @return array An array of supplier data
	 */
	public function fetch ($id, $folderStart = 0, $folderRows = 10, $itemStart = 0,
						   $itemRows = 10, $useMemcache = false, $keyPrefix, $keySuffix,
						   $timeout = 43200)
	{
		$catalogue = false;
		$useMemcache = false;
		if ($useMemcache)
		{
			// set up a memcache connector
			$memcacheInstance = $this->memcacheInstance();;
			
			$key = $keyPrefix . 'SCAT_'.$id.'-'.$start.'-'.$rows.'-'.$keySuffix;
			
			$cacheAlive = true;
			if (!$catalogue = $memcacheInstance->get($key))
			{
				$cacheAlive = false;
			}
		}
		
		if (!$catalogue)
		{

			$catUri = $this->browseUri.$id.'?folderStart='.$folderStart.'&folderRows='.$folderRows
										  .'&itemStart='.$itemStart.'&itemRows='.$itemRows;
			
			$catalogueJson = file_get_contents($catUri);
			
			$catalogue = json_decode($catalogueJson, true);
			
			if ($useMemcache)
			{
				$memcacheInstance->set($key, $catalogue, false, $timeout);
			}
		}
		
		return $catalogue;
	}
	
	/**
	 * Adapter for searching a supplier catalogue.
	 * 
	 * @access public
	 * @param int $id TradeNet ID or Catalogue ID of the catalogue profile to fetch
	 * @param int $folderStart
	 * @param int $folderRows
	 * @param int $itemStart
	 * @param int $itemRows
	 * @param string $query
	 * @return array An array of supplier data
	 */
	public function search ($id, $folderStart, $folderRows, $itemStart, $itemRows, $query)
	{
		$catUri = $this->searchUri.$id.'?folderStart='.$folderStart.'&folderRows='.$folderRows
										  .'&itemStart='.$itemStart.'&itemRows='.$itemRows
										  .'&query='.urlencode($query);
										  
		$catalogue = "";
		
		if( $id != "" )
		{
			$catalogueJson = file_get_contents($catUri);
			$catalogue = json_decode($catalogueJson, true);
		}
		
		return $catalogue;
	}
}