<?php
/*
* Gateway connecting to reprt service
*/
class Myshipserv_ReportService_Gateway implements Myshipserv_ReportService_GatewayInterface
{
	private static $_instance;
	protected $config;
	protected $memcache;
	protected $serviceUrl;
	protected $success;
	protected $hasMemcache;
	protected $memcacheKey;
	protected $serviceType;
	
	const REPORT_STATS = 0;
	const REPORT_SIR3 = 1;
	const REPORT_SPR = 2;
	const REPORT_SPR_QPD = 3;
	const REPORT_SPR_FUNNEL = 4;
	const REPORT_SPR_QUOTING = 5;
	const REPORT_SPR_ORDERING = 6;
	const REPORT_SPR_CYCLE_TIME = 7;
    const REPORT_SPR_CQS = 8;
    const REPORT_SPR_CONSORTIA = 9;
    const REPORT_PAGES_SERVICE_SEARCH = 10;
	
	const MEMCACHE_TTL = 3600;          //one hour default that may change later
    const MIN5_MEMCACHE_TTL = 300;      // 5 minutes interval
    const HOUR_MEMCACHE_TTL = 3600;     // one hour that will not change
	const DAY_MEMCACHE_TTL = 87400;     //one day, plus something (the time to run the daily cronjob /scripts/spr/warmup.php)
	const INFINITE_MEMCACHE_TTL = 0;    //Infinite, value expres on eviction
	const HTTP_CACHE_TTL = 3600;
	const SPR_DATABASE_CACHE = -1;          //THIS WORKS FOR SPR SHIPSERV AVERAGES ONLY, DO NOT USE FOR ANY OTHER SERVICES, AS KEY IS GENERATED SPECIALLY FOR SPR PARAMETERS
	
    /**
    * Singleton class entry point, create single instance
    * 
    * @param int $serviceType Constant of report type
    *  
    * @return Myshipserv_ReportService_Gateway
    */
    public static function getInstance($serviceType = self::REPORT_STATS)
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        //Here we can play with different services, if we extending the functionality for new reports by passing it into the getinstance
        static::$_instance->setServiceUrl($serviceType);
        
        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return Myshipserv_ReportService_Gateway
	*/
    protected function __construct()
    {
    	$this->config = Zend_Registry::get('config');
    	$this->memcache = Shipserv_Memcache::getMemcache();
    	$this->hasMemcache = ($this->memcache instanceof Memcache);
    	$this->client = new Zend_Http_Client();
	}

	/**
	* Hide clone, protect createing another instance
	* @return Myshipserv_ReportService_Gateway
	*/
    private function __clone()
    {
    }

    /**
     * Normalises supplier webservice parameters so when the same parameters are supplied in a different order they
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
     * Forward a call to report service
     * 
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forward($reportType, $params, $memcacheTtl = null, $rewriteCache = false)
    {
        // DEV-2447 Speed up report(s)
        Myshipserv_CAS_CasRest::getInstance()->sessionWriteClose();

    	$this->reportType = $reportType;
    	$this->params = $params;

    	$data = null;
    	if ($this->hasMemcache === true && $memcacheTtl !== self::SPR_DATABASE_CACHE) {

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
    	
    	//Unconnemt this part to debug if all SPR shipserv global queries are cached, update the file name to your proper testing path in file_put_content
    /*
    	if (!$data) {
        	if (!isset($params['tnid']) && !isset($params['byb'])) {
        	    $debugInfo = "\nKey: $this->memcacheKey \nReport type: $reportType\nTTL: " .var_export($memcacheTtl, true). "\nParams:\n" . print_r($this->params, true);
        	    file_put_contents('/vagrant/xdebug/noncachedlog_' .date('Ymd'). '.txt', $debugInfo, FILE_APPEND | LOCK_EX);
        	}
    	}
    	*/
    	
    	//Uncomment this to test your caches
    	/*
    $debugInfo = "\nKey: $this->memcacheKey \nReport type: $reportType\nTTL: " .var_export($memcacheTtl, true). "\nParams:\n" . print_r($this->params, true);
    file_put_contents('/vagrant/xdebug/keylog_' . $reportType . '_' . microtime(true) . '.txt', $debugInfo, FILE_APPEND | LOCK_EX);
    */
        
    	return ($data) ? json_decode($data, true) : $this->getUrl($memcacheTtl);
    }
    
    /**
     * *** This HACK function is implemented, as after refactoring the report-service, the URL endpoints are not consequent anymore :-( 
     * *** and as this class is a singleton, I cannot use two instances at the same time, so I have to be able to call the forward with an individual URL setting 
     * 
     * @param integer $serviceType   The service type constant definied above
     * @param string $reportType 	The report type we would like to fetch
     * @param array $params      	The URL params
     * @param boolean $memcacheTtl	If null, default 1 hour, else the value set
     * @param boolean $rewriteCache	force to rewrite cache, always read from database
     * @return array
     */
    public function forwardAs($serviceType, $reportType, $params, $memcacheTtl = null, $rewriteCache = false)
    {
        $currentServiceType = $this->getServiceType();
        $this->setServiceUrl($serviceType);
        $result = $this->forward($reportType, $params, $memcacheTtl, $rewriteCache);
        $this->setServiceUrl($currentServiceType);
        return $result;
        
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
     * @param int $serviceType Constant of report type
     * Set the service URL by implemented types
     * 
     * @return unknown
     */
    public function setServiceUrl($serviceType)
    {

        $this->serviceType = $serviceType;

        switch ($serviceType) {
            case self::REPORT_STATS:
                $this->serviceUrl = $this->config->shipserv->services->report->url . '/json/stats';
                break;
            case self::REPORT_SIR3:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/sir';
                break;
            case self::REPORT_SPR:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr';
                break;
            case self::REPORT_SPR_QPD:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/quality-payment-delivery';
                break;
            case self::REPORT_SPR_FUNNEL:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/funnel';
                break;
            case self::REPORT_SPR_QUOTING:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/quoting';
                break;
            case self::REPORT_SPR_ORDERING:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/ordering';
                break;
            case self::REPORT_SPR_CYCLE_TIME:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/cycle-time';
                break;
            case self::REPORT_SPR_CQS:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/spr/competitiveness';
                break;
            case self::REPORT_SPR_CONSORTIA:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/consortia';
                break;
            case self::REPORT_PAGES_SERVICE_SEARCH:
                $this->serviceUrl = $this->config->shipserv->services->catalogue->search->url;
                break;
            default:
                $this->serviceUrl = $this->config->shipserv->services->report->url. '/json/stats';
                break;
        }
    }
    
    /**
     * Returns the service type constant
     * @return integer
     */
    protected function getServiceType()
    {
        return $this->serviceType;
    }
    
    /**
     * Tries to connect to report service, and fetch the data, otherwise creates an error description array
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
    	$this->client->setUri(rtrim($this->serviceUrl, '/') . '/' . $this->reportType);
	   	$this->client->setHeaders("Accept-Language", "en");
	   	$this->client->setMethod(Zend_Http_Client::GET);
	   	$this->client->resetParameters();
	   	$this->client->setConfig(array('timeout' => self::HTTP_CACHE_TTL));
	   	$this->client->setParameterGet($this->params);
	   	
	   	//Send the request
	   	$response = $this->client->request();
	   	
	   	if ($response->getStatus() === 200) {
	   		//In case of success, return as an array and memcache the result
	   		//$elapsedTime = microtime(true) - $startProfile;
	   		//file_put_contents('/var/www/test/spr-time-log.csv', '"' . $this->reportType . '",' . $elapsedTime . "\n", FILE_APPEND);
	   		try {
	   			$result = $response->getBody();
	   		} catch (Exception $e) {
	   			//In case of the result is not JSON create a response array with the parameters
	   			return array(
	   					'status' => 'error',
	   					'exception' => array(
	   							'code' => $response->getStatus(),
	   							'type' => 'Remote server error',
	   					        'message' => 'The server does not return a valid JSON file in ' . $this->serviceUrl . '/' . $this->reportType,
	   					        'parameters' => http_build_query($this->params),
	   							'serverMessage' => $e->getMessage(),
	   							'response' => strip_tags($response->getBody(), '<br>')
	   					)
	   			);
	   		}
	   		
	   		//In case of we use database cache TTL then just skip this, as the caching will be done outside this class, storing to a database
	   		if ($this->hasMemcache === true && $memcacheTtl !== self::SPR_DATABASE_CACHE) {
	   			$ttl = ($memcacheTtl !== null) ? $memcacheTtl : self::MEMCACHE_TTL;
	   			$this->memcache->set($this->memcacheKey, $result, null, $ttl);
	   		}
	   		$this->success = true;
	   		return json_decode($result, true);
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
