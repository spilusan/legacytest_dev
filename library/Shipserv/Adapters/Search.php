<?php
class xmlrpc_client {
	private $url;
	function __construct($url, $autoload=true) {
		$this->url = $url;
		$this->connection = new Myshipserv_Curl;
		$this->methods = array();
		if ($autoload) {
			$resp = $this->call('system.listMethods', null);
			$this->methods = $resp;
		}
	}
	public function call($method, $params = null) {
		$output = array('encoding' => 'UTF-8', 'escaping' => 'markup');
		$post = xmlrpc_encode_request($method, $params, $output);
		return xmlrpc_decode($this->connection->post($this->url, $post), 'UTF-8');
	}
}


/**
 * Adapter class for Search Service
 * 
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Adapters_Search extends Shipserv_Object
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	protected $isWithinZone = false;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 */
	public function __construct ($url = null)
	{
		//$config  = Zend_Registry::get('config');
		$config = parent::getConfig();
		$httpClient = new Zend_Http_Client();
		$httpClient->setConfig(array('timeout' => '60'));
		
		$this->client = new Zend_XmlRpc_Client($config->shipserv->services->supplier->url, $httpClient);

		// fix to service timing out after 10 seconds
		//$this->client->setConfig(array('timeout' => '60'));
	}
	
	/**
	 *	
	 * @param array $ruleset 
	 */
	public function newRFQSearch (array $ruleset){
		if($this->isValidRuleSet()){
		
			
		}		
	}
	
	public function isWithinZone()
	{
		$this->isWithinZone = true;
	}
	
	public function makeSolrProximityQuery($searchStr, $distance = 1){
		return 'description:"' . $searchStr . '"~1 orders:"' . $searchStr . '"~' . $distance . ' catalog:"' . $searchStr . '"~' . $distance; 
	}
	
	public function newSupplierSearch ($query = '', $type = '', $countryCode = '',
									   $portCode = '', $start = 0, $rows = 10,
									   $categoryRows = 500, $membershipRows = 500, $certificationRows = 500, $brandRows = 500,
									   $filters = array())
	{
		// there must be a better way of doing this!
		$query   = (is_null($query)) ? '' : htmlspecialchars_decode($query);
		$type    = (is_null($type)) ? '' : htmlspecialchars_decode($type);
		$country = (is_null($country)) ? '' : htmlspecialchars_decode($country);
		$port    = (is_null($port)) ? '' : htmlspecialchars_decode($port);
		$start   = (is_null($start)) ? 0 : $start;
		$rows    = (is_null($rows)) ? 10 : $rows;
		$categoryRows = (is_null($categoryRows)) ? 500 : $categoryRows;
		$membershipRows = (is_null($membershipRows)) ? 500 : $membershipRows;
		$certificationRows = (is_null($certificationRows)) ? 500 : $certificationRows;
		$brandRows = (is_null($brandRows)) ? 500 : $brandRows;
		$filters = (is_null($filters)) ? array() : $filters;
		
		$parameters = array(array('query'          => (string) $query,									
								  'type'           => (string) $type,
								  'countryCode'    => (string) $countryCode,
								  'portCode'       => (string) $portCode,
								  'start'          => (int) $start,
								  'rows'           => (int) $rows,
								  'categoryRows'   => (int) $categoryRows,
								  'membershipRows' => (int) $membershipRows,
								  'certificationRows' => (int) $certificationRows,
								  'brandRows'	   => (int) $brandRows,
								  'debug'			=> (isset($_GET['debug']) && $_GET['debug'] == "1")?1:0,
								  'filters'        => $filters,
								  'suppressSpotlight' => $this->isWithinZone
				
		));
		
		return $this->client->call('Supplier.search', $parameters);
}
	
	/**
	 * New Request Format:
	 * <struct>
	 * 		query (string)
	 * 		location (string)
	 *		type (string)
	 *		countryCode (string) � note the �Code� suffix added for clarity
	 *		portCode (string) � note the �Code� suffix added for clarity
	 *		start (int)
	 * 		rows (int)
	 *		categoryRows (int) � maximum number of categories to return (default is 0)
	 *		membershipRows (int) � maximum number of memberships to return (default is 0)
	 *		filters (array)
	 * 			<struct>
	 *				field (string) � currently supported: categoryId, membershipId
	 * 				value (string)
	 * 
	 * @access public
	 * @param string $query
	 * @param string $location
	 * @param string $type
	 * @param string $countryCode
	 * @param string $portCode
	 * @param int $start
	 * @param int $rows
	 * @param int $categoryRows
	 * @param int $membershipRows
	 * @param int $brandRows
	 * @param array $filters
	 * @return array
	 */
	public function execute ($query = '', $location = '', $type = '', $countryCode = '',
							 $portCode = '', $start = 0, $rows = 10,
							 $categoryRows = 500, $membershipRows = 500, $certificationRows = 500, $brandRows = 500,
							 $filters = array(), $orFilters=array(), $facets=array())
	{
		// there must be a better way of doing this!
		$query    = (is_null($query)) ? '' : htmlspecialchars_decode($query);
		$location = (is_null($location)) ? '' : htmlspecialchars_decode($location);
		$type     = (is_null($type)) ? '' : htmlspecialchars_decode($type);
		$country  = (is_null($country)) ? '' : htmlspecialchars_decode($country);
		$port     = (is_null($port)) ? '' : htmlspecialchars_decode($port);
		$start    = (is_null($start)) ? 0 : $start;
		$rows     = (is_null($rows)) ? 10 : $rows;
		$categoryRows = (is_null($categoryRows)) ? 500 : $categoryRows;
		$membershipRows = (is_null($membershipRows)) ? 500 : $membershipRows;
		$certificationRows = (is_null($certificationRows)) ? 500 : $certificationRows;
		$brandRows = (is_null($brandRows)) ? 500 : $brandRows;
		$filters = (is_null($filters)) ? array() : $filters;
		$orFilters = (is_null($orFilters)) ? array() : $orFilters;
		$facets = (is_null($facets)) ? array() : $facets;
		$config = parent::getConfig();
		
		$parameters = array(array('query'          => $query,
								  'location'       => $location,
								  'type'           => $type,
								  'countryCode'    => strtoupper($countryCode),
								  'portCode'       => $portCode,
								  'start'          => (int) $start,
								  'rows'           => (int) $rows,
								  'categoryRows'   => (int) $categoryRows,
								  'membershipRows' => (int) $membershipRows,
								  'certificationRows' => (int) $certificationRows,
								  'brandRows'	   => (int) $brandRows,
								  'filters'        => $filters,
								  'orFilters'       => $orFilters,
								  'facets'			=> $facets,
								  'debug'			=> (isset($_GET['debug']) && $_GET['debug'] == "1")?1:0,
								  'suppressSpotlight' => $this->isWithinZone
		));

		// @comment: commented out by EL (too slow)
		//return $this->client->call('Supplier.search', $parameters);
		
		try
		{
			$client = new xmlrpc_client($config->shipserv->services->supplier->url, true);
			$resp = $client->call('Supplier.search', $parameters);
			return $resp;
		}
		catch(Exception $e)
		{
			return $this->client->call('Supplier.search', $parameters);
		}	
	}
	
	/**
	 * Fetches and caches the total number of suppliers returned by Solr in
	 * a blank search
	 * 
	 * @access public
	 * @param int $cacheTTL The number of seconds for which the count should be cached
	 * @return int The total number of suppliers
	 */
	public function fetchSupplierCount ($cacheTTL = 86400)
	{
		//$config = Zend_Registry::get('config');
		$config  = Shipserv_Object::getConfig();
		// check if the cache is alive
		$key = $config->memcache->client->keyPrefix . 'SUPPLIERCOUNT_'.
			   $config->memcache->client->keySuffix;
		
		$memcache = new Memcache();
		@$memcache->connect($config->memcache->server->host,
						   $config->memcache->server->port);
		
		if ($result = $memcache->get($key))
		{
			return $result;
		}

		/*
		$parameters = array(array('query'          => '',
								  'type'           => 'product',
								  'countryCode'    => '',
								  'portCode'       => '',
								  'start'          => 0,
								  'rows'           => 1,
								  'categoryRows'   => 0,
								  'membershipRows' => 0,
								  'certificationRows' => 0,
								  'brandRows'	   => 0,
								  'filters'        => array()));
		*/
		// use the same param for DE3957
		$parameters = array(array(
				'query'          => '',
				'location'       => '',
				'type'           => 'product',
				'countryCode'    => '',
				'portCode'       => '',
				'start'          => 0,
				'rows'           => 15,
				'categoryRows'   => 500,
				'membershipRows' => 500,
				'certificationRows' => 500,
				'brandRows'	     => 500,
				'filters'        => array(),
				'orFilters'      => array(),
				'facets'		 => array()
		));
		
		$this->client->setSkipSystemLookup(true);
		$result = $this->client->call('Supplier.search', $parameters);
		
		$documentsFound = $result['documentsFound'];
		
		$memcache->set($key, $documentsFound, false, $cacheTTL);
		
		return $documentsFound;
	}
	
	private function isValidRuleSet(array $ruleset){
		if(count($ruleset) == 0){
			return false;
		}
		foreach ($ruleset as $rule) {
			if(count($rule) == 0){
				return false;				
			}			
		}
	}
}
