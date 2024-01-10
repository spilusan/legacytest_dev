<?php
/**
 * Base class to communicating with report service for the new SIR3
 * 
 * @author Elvir
 *
 */
class Shipserv_Report_Supplier_Insight extends Shipserv_Memcache 
{
	
	protected $tnid = null;
	protected $startDate = null;
	protected $endDate = null;

	//for date ranges
	protected $startDate1 = null;
	protected $endDate1 = null;
	protected $startDate2 = null;
	protected $endDate2 = null;
	
	const MEMCACHE_TTL = 3600;
	const HTTP_CACHE_TTL = 3600;
	
	protected $supportedCallType = array(
		Shipserv_Report_Supplier_Insight_Brand::ADAPTER_NAME
	);
	
	public function setTnid( $tnid )
	{
		if( ctype_digit($tnid) === false )
		{
			throw new Exception("SIR3: Invalid TNID");
		}
		
		$this->tnid = $tnid;
	}	
	
	public function setDatePeriod( DateTime $startDate, DateTime $endDate )
	{
		$this->startDate = $startDate;
		$this->endDate = $endDate;
	}
	
	public function setDateRangePeriod( DateTime $startDate1, DateTime $endDate1, DateTime $startDate2, DateTime $endDate2 )
	{
		$this->startDate1 = $startDate1;
		$this->endDate1 = $endDate1;
		$this->startDate2 = $startDate2;
		$this->endDate2 = $endDate2;
	}
	
	protected function getDataFromReportService()
	{
		$startElapserTime = microtime(true);
		$config = $this->getConfig();

		// setting up params, can be different according to some services
		switch (static::ADAPTER_NAME ) {
			case 'po-value-by-buyer':
				$params = array(
					'tnid'	=> $this->tnid,
					'lowerdate1' => $this->startDate1->format('Ymd'),
					'upperdate1' => $this->endDate1->format('Ymd'),
					'lowerdate2' => $this->startDate2->format('Ymd'),
					'upperdate2' => $this->endDate2->format('Ymd'),
					);
				break;
			
			default:
				$params = array(
					'tnid'	=> $this->tnid,
					'lowerdate' => $this->startDate->format('Ymd'),
					'upperdate' => $this->endDate->format('Ymd'),
					);
				break;
		}

		//add if the user is shipmate	
		if (static::ADAPTER_NAME == 'get-gmv-breakdown' && $this::getUser() && $this::getUser()->isShipservUser()) {
				$params['shipmate'] = 1;
			}
			
		$this->setMemcacheTTL(self::MEMCACHE_TTL);	
		$memcacheKey = $this->getMemcacheKey($params);
		$cachedResponse = $this->memcacheGet(__CLASS__, static::ADAPTER_NAME, $memcacheKey);
        if ($cachedResponse !== false) {
            	$data = json_decode($cachedResponse,TRUE); 
            	$data['debug']['cached'] = true;
            } else {
            	$client = new Zend_Http_Client();
            	$serviceUrl = (static::ADAPTER_NAME == 'get-gmv-breakdown') ? $config->shipserv->services->report->url . "/json/stats/" . static::ADAPTER_NAME : $config->shipserv->services->report->url . "/json/sir/" . static::ADAPTER_NAME;
				$client->setUri($serviceUrl);
				$client->setHeaders("Accept-Language", "en");
				$client->setMethod(Zend_Http_Client::GET);
				$client->resetParameters();
				$client->setConfig(array('timeout' => self::HTTP_CACHE_TTL));
            	$client->setParameterGet($params);
				$response = $client->request();
				if( $response->getStatus() == 500 )
				{
					throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
					//throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.".$serviceUrl.'?'.http_build_query($params).$response->getBody(), 500);
				} else {
					$this->memcacheSet(__CLASS__, static::ADAPTER_NAME, $memcacheKey, $response->getBody());
				}
				$data = json_decode($response->getBody(),TRUE);
				$data['debug']['cached'] = false;
				//$data['debug']['url']  = $serviceUrl.'?'.http_build_query($params);
            }
           $data['debug']['elapsed'] = microtime(true) - $startElapserTime;
           //for testing errors
           // if (mt_rand(0,5) == 3)  throw new Exception("We're sorry, there is a problem with our backend server. Please try again later.", 500);
		return $data;
	}
	
	public function getUser()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			return false;
		}
		
		return $user;
	}

	protected function getMemcacheKey($param = array())
	{
		return md5(implode($param));
	}
}