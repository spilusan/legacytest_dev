<?php
/*
* Gateway connecting to buyer connect app
*/

class Myshipserv_BuyerConnect_Gateway
{
	private static $_instance;
	protected $config;
	protected $memcache;
	protected $serviceUrl;
	protected $success;
	protected $hasMemcache;
	protected $memcacheKey;
	protected $serviceType;
	protected $httpRequestType;
	protected $postData;

	const MEMCACHE_TTL = 3600;          //one hour default that may change later
    const MIN5_MEMCACHE_TTL = 300;      // 5 minutes interval
    const HOUR_MEMCACHE_TTL = 3600;     // one hour that will not change
	const DAY_MEMCACHE_TTL = 87400;     //one day, plus something (the time to run the daily cronjob /scripts/spr/warmup.php)
	const INFINITE_MEMCACHE_TTL = 0;    //Infinite, value expres on eviction

	const HTTP_CACHE_TTL = 3600;

    /**
    * Singleton class entry point, create single instance
    *  
    * @return Myshipserv_BuyerConnect_Gateway
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
                
        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return Myshipserv_BuyerConnect_Gateway
	*/
    protected function __construct()
    {
    	$this->config = Zend_Registry::get('config');
    	$this->memcache = Shipserv_Memcache::getMemcache();
    	$this->hasMemcache = ($this->memcache instanceof Memcache);
    	$this->client = new Zend_Http_Client();
    	$this->serviceUrl = $this->config->shipserv->services->buyerconnect->url;
    	$this->httpRequestType = Zend_Http_Client::GET;
	}

	/**
	* Hide clone, protect createing another instance
	* @return Myshipserv_ReportService_Gateway
	*/
    private function __clone()
    {
    }

    /**
     * Normalises  webservice parameters so when the same parameters are supplied in a different order they
     * are considered the same
     *
     * @author  Yuriy Akopov
     * @story   BUY-392
     * @date    2017-08-08
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function normaliseParams(array $params)
    {
        foreach ($params as $key => $value) {
            // normalise NULLs and numbers so print_r() used in cache key generation is always the same
            $params[$key] = (string) $value;
        }

        ksort($params);

        return $params;
    }

    /**
     * Set sending method to HTTP post
     * @param string $data The http raw body
     * @return Myshipserv_BuyerConnect_Gateway
     */
    public function post($data = null)
    {
        $this->postData = $data;
        $this->httpRequestType = Zend_Http_Client::POST;
        	return $this;
    }
    
    /**
     * Set sending method to HTTP delete
     * @return Myshipserv_BuyerConnect_Gateway
     */
    public function delete()
    {
        	$this->httpRequestType = Zend_Http_Client::DELETE;
        	return $this;
    }
    
    /**
     * Forward a call Buyer Connect service
     * 
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forward($reportType, $params, $memcacheTtl = null, $rewriteCache = false)
    {
    	$this->reportType = $reportType;
    	$this->params = $params;

    	$data = null;
    	if ($this->hasMemcache === true) {

            $this->memcacheKey = 'REPS_' . md5(
    	        implode(
                    '_',
                    array(
                        'REPS',
                        $this->serviceType,
                        $this->reportType,
                        '=',
                        print_r($this->normaliseParams($this->params), true)
                    )
                )
            );

    		if ($rewriteCache === false) {
    			$data = $this->memcache->get($this->memcacheKey);
    			$this->success = true;
    		}
    	}

    	return ($data) ? $data : $this->getUrl($memcacheTtl);
    }
    
     /**
      * Getter for the status of the last call
      * 
      * @return boolean
      */
    public function getStatus()
    {
    	return $this->success;	
    }
    

    
    /**
     * Tries to connect to Buyer Connect service, and fetch the data, otherwise creates an error description array
     * In case of success, memcache the result
     * @param boolean $memcacheTtl If null, default 1 hour, else the value set
     * 
     * @return array
     */
    protected function getUrl($memcacheTtl = null)
    {
        	//$startProfile = microtime(true);
        	
        	$this->success = false;
        	
        	//Set parameters for sending the request
        	$this->client->setUri($this->serviceUrl . '/' . $this->reportType);
	   	$this->client->setHeaders("Accept-Language", "en");
	   	$this->client->setMethod($this->httpRequestType);
	   	

	   	$this->client->resetParameters();
	   	$this->client->setConfig(array('timeout' => self::HTTP_CACHE_TTL));
	   	$this->client->setParameterGet($this->params);
	   	
	   	if ($this->httpRequestType === Zend_Http_Client::POST) {
	   	    $this->client->setRawData($this->postData, 'application/json');
	   	}
	   	
	   	//Re setting to default GET method after the header already set
	   	$this->httpRequestType = Zend_Http_Client::GET;
	   	$this->postData = null;
	   	
	   	//Send the request
	   	$response = $this->client->request();
	   	
	   	if ($response->getStatus() === 200) {
	   		//In case of success, return as an array and memcache the result
	   		//$elapsedTime = microtime(true) - $startProfile;
	   		//file_put_contents('/var/www/test/spr-time-log.csv', '"' . $this->reportType . '",' . $elapsedTime . "\n", FILE_APPEND);
	   		try {
	   			$result = json_decode($response->getBody(), true);
	   		} catch (Exception $e) {
	   			//In case of the result is not JSON create a response array with the parameters
	   			return array(
	   					'status' => 'error',
	   					'exception' => array(
	   							'code' => $response->getStatus(),
	   							'type' => 'Remote server error',
	   							'message' => 'The server does not return a valid JSON file',
	   							'serverMessage' => $e->getMessage(),
	   							'response' => strip_tags($response->getBody(), '<br>')
	   					)
	   			);
	   		}
	   		
	   		if ($this->hasMemcache === true) {
	   			$ttl = ($memcacheTtl !== null) ? $memcacheTtl : self::MEMCACHE_TTL;
	   			$this->memcache->set($this->memcacheKey, $result, null, $ttl);
	   		}
	   		$this->success = true;
	   		return $result;
	   	} else {
	   		//In case of error create a response array with the parameters
	   		return array(
                'status' => 'error',
                'exception' => array(
                    'code' => $response->getStatus(),
                    'type' => 'Remote server error',
                    'message' => strip_tags($response->getBody(), '<br>')
                )
            );
	   	}
    }
}
