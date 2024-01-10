<?php

/**
 * Adapter class for Report Service
 * 
 * @package myshipserv
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Adapters_Report extends Shipserv_Object 
{
	const HTTP_CACHE_TTL = 3600;
	const MEMCACHE_TTL = 3600;
	
	protected $supportedReports = 
		array( 
			"impression", 
			"batchimpressions", 
			"svr", 
			"get-unactioned-rfq",
			"get-ucv-urfq",
			"get-gmv-p1si",
			"get-gmv-details",
			"get-data-for-sf"
		);
	
	/**
	 * Storing error happens on the adapter
	 * @var array
	 * @access protected
	 */
	protected $errors = array();
	 
	/**
	 * The Curl Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	/**
	 * Set up the Curl interface
	 * 
	 * @access public
	 */
	public function __construct ( )
	{		
		parent::__construct();
	}
	
	public function __destruct()
	{
		if( count($this->errors) > 0 )
		{
			throw new Exception("There are issues with the backend: " . implode("<hr />", $this->errors));
		}
	}
	
	/**
	 * Create adapter to access the service
	 * @param unknown_type $module
	 */
	private function getHttpClient( $module, $noTimeout = false )
	{
		// check if module supplied is being supported by the backend
		if( !in_array( $module, $this->supportedReports ) )
		{
			throw Myshipserv_Exception_MessagedException("Reporting service doesn't support " . $module);
		}
		
		// pull uri from the configuration file (depending on the stage)
		$config  = $this->getConfig();
		$baseUrl = $config->shipserv->services->report->url . '/json/stats';
		$selectUrl = $baseUrl . "/" . $module;
		
		// create HTTP client
		$client = new Zend_Http_Client();
		$client->setUri($selectUrl);
		
		if( $noTimeout === false )
		{
			$client->setConfig(array('timeout' => self::HTTP_CACHE_TTL));
		}

		// reseting the parameters to avoid clash from the previous call
		$client->resetParameters();
		
		// return the client to be excuted on the function level
		return $client;
	}
	
	private function reportError( $response )
	{
		$this->error[] = $response->getBody();
	}
	
	public function getSupplierUnactionedRFQ($tnid, $startDate = '', $endDate = '')
	{
		// memcache the raw data from the backend
		$memcache = $this::getMemcache();
		if ($memcache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SVR_SUPPLIER_UNACTION_RFQ_' . $tnid . '-' . $startDate . '-' . $endDate . $this->memcacheConfig->client->keySuffix;
			// check the cache and see if it's a hit
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}
		
		
		$client = $this->getHttpClient("get-unactioned-rfq");
		$client->setHeaders("Accept-Language", "en");
		if( $startDate != "" )
		{
			$client->setParameterGet(
					array(
							'tnid'	=> $tnid,
							'lowerdate' => $startDate,
							'upperdate' => $endDate
					)
			);
		}
		else
		{
			$client->setParameterGet(
					array(
							'tnid'	=> $tnid
					)
			);
		}
		// make request to the backend
		$client->setMethod(Zend_Http_Client::GET);
		$response = $client->request();
	
		if( $response->getStatus() == 500 )
		{
			$this->reportError( $response );
			//throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
		}
	
		// encode this to json
		$data = json_decode($response->getBody(),TRUE);
		
		if ($memcache) $memcache->set($key, $data, false, self::MEMCACHE_TTL);
		
		// return the data
		return $data;
	
	
	}

	
	public function getGmvPageOneSearchImpression($tnid, $startDate = '', $endDate = '')
	{
		if( $startDate != "" && $endDate != "" )
		{
			$client = $this->getHttpClient("get-gmv-p1si");
			$client->setParameterGet(
				array(
					'tnid'	=> $tnid,
					'lowerdate' => $startDate,
					'upperdate' => $endDate
				)
			);
			
			// make request to the backend
			$client->setMethod(Zend_Http_Client::GET);
			$response = $client->request();

			// check if its successful
			if( $response->getStatus() == 500 )
			{
				$this->reportError( $response );
				//throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
			}
			
			// return the data
			return json_decode($response->getBody(),TRUE);
		}
		else
		{
			// throw exception
			throw new Exception("Invalid date.", 500);
		}
	}

	
	public function getUniqueContactViewAndUnactionedRfq($tnid, $startDate = '', $endDate = '')
	{
		if( $startDate != "" && $endDate != "" )
		{
			$client = $this->getHttpClient("get-ucv-urfq");
			$client->setParameterGet(
				array(
					'tnid'	=> $tnid,
					'lowerdate' => $startDate,
					'upperdate' => $endDate
				)
			);
				
			// make request to the backend
			$client->setMethod(Zend_Http_Client::GET);
			$response = $client->request();
	
			// check if its successful
			if( $response->getStatus() == 500 )
			{
				$this->reportError( $response );
				//throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
			}
				
			// return the data
			return json_decode($response->getBody(),TRUE);
		}
		else
		{
			// throw exception
			throw new Exception("Invalid date.", 500);
		}
	}
	
	public function getGmvDetail($tnid, $startDate = '', $endDate = '')
	{
		if( $startDate != "" && $endDate != "" )
		{
			$client = $this->getHttpClient("get-gmv-details");
			$client->setParameterGet(
				array(
					'tnid'	=> $tnid,
					'lowerdate' => $startDate,
					'upperdate' => $endDate
				)
			);
				
			// make request to the backend
			$client->setMethod(Zend_Http_Client::GET);
			$response = $client->request();

			// check if its successful
			if( $response->getStatus() == 500 )
			{
				$this->reportError( $response );
				//throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
			}
				
			// return the data
			return json_decode($response->getBody(),TRUE);
		}
		else
		{
			// throw exception
			throw new Exception("Invalid date.", 500);
		}
	}
	
	/**
	 * Pulling data from Telesales API for supplier impression.
	 * 
	 * @param int $tnid
	 * @param string $startDate 
	 * @param string $endDate
	 */
	public function getSupplierImpression ($tnid = '', $startDate = '', $endDate = "" )
	{
		// memcache the raw data from the backend
		$memcache = $this::getMemcache();
		if ($memcache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SVR_SUPPLIER_IMPRESSION_' . $tnid . '-' . $startDate . '-' . $endDate . $this->memcacheConfig->client->keySuffix;
			// check the cache and see if it's a hit
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}		
		
		// if not then make a call to the backend
		$client = $this->getHttpClient("svr");
		$client->setParameterPost(
			array(
				'lowerdate'	=> $startDate,
				'upperdate'	=> $endDate,
				'tnid'	=> $tnid
			)
		);
		
		// make request to the backend
		$client->setMethod(Zend_Http_Client::POST);
		$response = $client->request();
		
		if( $response->getStatus() == 500 )
		{
			throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
		}
		
		
		// encode this to json
		$data = json_decode($response->getBody(),TRUE);

		// store the period
		$data["period"]["start"] = $startDate;
		$data["period"]["end"] = $endDate;

		// cache the data
		if ($memcache) $memcache->set($key, $data, false, self::MEMCACHE_TTL);
		
		// return the data
		return $data;
	}
	
	/**
	 * Pulling data for batch-impression or global supplier value report
	 * see getSupplierImpression for detailed documentation
	 * 
	 * @param string $startDate
	 * @param string $endDate
	 * @param array $ports
	 * @param array $categories
	 * @param array $brands
	 * @param array $products
	 * @param array $countries
	 * @return json
	 */
	public function getGeneralImpression ($startDate = '', $endDate = "", $ports = array(), $categories = array(), $brands = array(), $products = array(), $countries = array() )
	{
		$memcache = $this::getMemcache();
		if ($memcache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SVR_GENERAL_SUPPLIER_IMPRESSION_' . '-' . $startDate . '-' . $endDate . '-';
			if( $ports != "" && $ports != null ) 			$key .= "_PORT_" . implode(".", $ports);
			if( $categories != "" && $categories != null ) 	$key .= "_CATEGORIES_" . implode(".", $categories);
			if( $brands != "" && $brands != null ) 			$key .= "_BRANDS_" . implode(".", $brands);
			if( $products != "" && $products != null) 		$key .= "_PRODUCTS_" . implode(".", $products);
			if( $countries != "" && $countries!= null) 		$key .= "_COUNTRIES_" . implode(".", $countries);
			$key .= $this->memcacheConfig->client->keySuffix;

			// md5 the key
			$key = md5( $key );
			
			$result = $memcache->get($key);
			if ($result !== false){
				return $result;
			}
		}	
		
		$client = $this->getHttpClient("batchimpressions");

		/*
		 * ********************************************************************************************************************************************
		 * FRONT END FIX FOR: DE3266 -- but it's better if it's handled gracefully on the service side (report-service) - commented for now
		 * ********************************************************************************************************************************************
		$ports 		= array_splice($ports, 		0, 1000);
		$categories = array_splice($categories, 0, 1000);
		$products 	= array_splice($products, 	0, 1000);
		$countries 	= array_splice($countries, 	0, 1000);
		*/
		
		$client->setParameterPost(
			array(
				'lowerdate'	=> $startDate,
				'upperdate'	=> $endDate,
				'countries' => implode(",", $countries),
				'ports' => implode(",", $ports),
				'categories' => implode(",", $categories),
				'products' => implode(",", $products),
				'brands' => implode(",", $brands),
			)
		);
		
		$client->setMethod(Zend_Http_Client::POST);
		$response = $client->request();
		
		if( $response->getStatus() == 500 )
		{
			throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
		}
		
		$array = json_decode($response->getBody(), TRUE);
		$array["period"]["start"] = $startDate;
		$array["period"]["end"] = $endDate;
		
		if ($memcache) $memcache->set($key, $array, false, self::MEMCACHE_TTL);
		
		return $array;
	}
	
	/**
	 * Convert XML attributes to node using xsl
	 * @param string $xml
	 */
	public function convertAttributesToNode( $xml )
	{
$string = <<<XSL
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <xsl:output method="xml" indent="yes"/>

  <xsl:template match="node()">
    <xsl:copy>
      <xsl:apply-templates select="@*|node()"/>
    </xsl:copy>
  </xsl:template>

  <xsl:template match="@*">
    <xsl:element name="{name()}"><xsl:value-of select="."/></xsl:element>
  </xsl:template>

</xsl:stylesheet>
XSL;
		
		// initiate the processor
	  	$xslt = new XSLTProcessor();
	  	
	  	// prepare the xls
	  	$xslt->importStylesheet(new SimpleXMLElement($string));
	  	
	  	// convert it 
	  	$xml = $xslt->transformToXml(new SimpleXMLElement($xml));
	 	return new SimpleXMLElement( $xml );	
	}
}
