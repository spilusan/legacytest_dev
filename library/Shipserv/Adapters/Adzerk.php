<?php

/**
 * Adzerk API thin client
 * Jul 2012 - @hubail
 *
 * Usage Tips, Advertisers as example, consistent behaviour for all endpoints:
 *
 * List Advertisers
 * // no arguments
 * $adzerk->advertiser();
 *
 * Create Advertiser
 * // arg1: null, arg2: array of data (plain-text associative PHP array)
 * $adzerk->advertiser(null, array());
 *
 * Update Advertiser
 * // arg1: (int) Advertiser ID, arg2: array of data (plain-text associative PHP array)
 * $adzerk->advertiser($advertiserId, array());
 *
 * All responses are returned by default, as an associative PHP array
 *
 */

class Shipserv_Adapters_Adzerk extends Shipserv_Object
{
    private $apiKey;
    private $apiBase = 'http://api.adzerk.net/v1/';

    public function __construct()
    {
        $this->apiKey = '4B975158A2497A446BA98D4AB233907300D4';
        $this->logger = new Myshipserv_Logger_File('adzerk-publisher');
    }
    
    public function getActivePublisherInPages()
    {
    	$sql = "SELECT * FROM pages_company_publisher";
    	return $this->getDb()->fetchAll($sql);
    }
    
    /**
     * Getting list of publisher in Adzerk - if company id isn't specified, then pull all from AdZerk, otherwise just pull it from DB
     * @param string $companyId
     * @return unknown
     */
 public function getListOfPublisher( $companyId = null)
    {
    	if( $companyId == null )
    	{
    		$data = $this->requestPublisherFromServer();
    		
    		foreach( $data->items as $row)
    		{
    			$new[$row->Id] = $row->CompanyName;
    		}
    	}
    	else
    	{
    		$sql = "
    			SELECT 
    				pcp_adzerk_publisher_id ID
    				, PCP_ADZERK_PUBLISHER
    			FROM
    				pages_company_publisher
    			WHERE
    				pcp_company_tnid=:companyId
    		";
    		foreach( $this->getDb()->fetchAll($sql, array('companyId' => $companyId)) as $row)
    		{

				$new[$row['ID']] = $row['PCP_ADZERK_PUBLISHER'];
				
    		}    		
    	}
    	return $new;
    }
    
    /**
     * Getting list of site
     * @param string $companyId
     * @return unknown
     */
    public function getListOfSite( $companyId = null)
    {
    	if( $companyId == null )
    	{
    		return $this->requestSiteFromServer();
    	}
    	else
    	{
    		$sql = "
    			SELECT
    				pcp_adzerk_site_id ID
    			FROM
    				pages_company_publisher
    			WHERE
    				pcp_company_tnid=:companyId
    		";
    		foreach( $this->getDb()->fetchAll($sql, array('companyId' => $companyId)) as $row)
    		{
    
    			$new[$row['ID']] = $row['PCP_ADZERK_PUBLISHER'];
    
    		}
    	}
    	
    	return $new;
    } 
    
    public function requestPublisherFromServer()
    {
    	$url = $this->apiBase . 'publisher' ;
    	
    	$ch = curl_init();
    	
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Adzerk-ApiKey: ' . $this->apiKey));
    	if ($is_put) {
    		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    	}
    	
    	curl_setopt($ch, CURLOPT_POST, false);    	
    	
    	
    	$result = curl_exec($ch);
    	if ($result === false) {
    		throw new Exception(curl_error($ch), curl_errno($ch));
    	}
    	curl_close($ch);
    	
    	return json_decode($result);
    	 
    }
    
    public function requestSiteFromServer( $publisherId = null ){
    	$url = $this->apiBase . 'site' ;
    	 
    	$ch = curl_init();
    	 
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Adzerk-ApiKey: ' . $this->apiKey));
    	if ($is_put) {
    		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    	}
    	 
    	curl_setopt($ch, CURLOPT_POST, false);
    	 
    	$result = curl_exec($ch);
    	if ($result === false) {
    		throw new Exception(curl_error($ch), curl_errno($ch));
    	}
    	curl_close($ch);
    	$sites = json_decode($result);
    	foreach( $sites->items as $row )
    	{
    		if( $publisherId != null && $row->PublisherAccountId == $publisherId )
    		{
    			$site[] = $row->Id;
    		}
    		else if( $publisherId == null )
    		{
    			$site[$row->Id] = $row->Title;	
    		}
    	}
    	
    	return $site;
    }
    
    public function requestReport($startDate, $endDate, $siteId)
    {
    	//$this->requestSiteFromServer();
    	
    	$memcache = $this::getMemcache();
    	
    	if ($memcache)
    	{
    		$key = $this->memcacheConfig->client->keyPrefix . 'Shipserv_Adapters_Adzerk' . $startDate . $endDate . $siteId. $this->memcacheConfig->client->keySuffix;
    		$result = $memcache->get($key);
    	}    	 
    	$result = false;
    	
    	// if miss cache
    	if( $result === false )
    	{
    		$siteIds = array($siteId);
    		$siteIdsAsString = ( count($siteIds) > 1 ? '[':'') . implode(",", $siteIds) . ( count($siteIds) > 1 ? ']':'');
    		$params = "{'StartDate': '" . $startDate . "', 'EndDate': '" . $endDate . "', 'GroupBy': ['month'], 'Parameters': [{'siteId':" . $siteIdsAsString . "}]}";
    		
    		$x = $this->requestReportFromServer('queue', $params);
    		
    		$queueId = $x->Id;
    		do {
    			$result = $this->requestReportFromServer('queue', $params, $queueId);
    			 
    			sleep(2);
    		} while( $result->Status==1 );
    		
    		// store lineitem ID
    		if ($memcache) $memcache->set($key, $result, false, 86400);
    	}
    	
    	
    	 
    	return $result;
    }
    
    public function requestReportFromServer( $action, $params, $queueId = null )
    {
        $url = $this->apiBase . 'report/' . $action;

        if( $queueId !== null )
        {
        	$data = '';
        	$url .= '/' . $queueId . '';
        }
        else 
        {
        	$data = 'criteria=' . urlencode($params);
        }
        $this->logger->log($params);
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Adzerk-ApiKey: ' . $this->apiKey));
        if ($is_put) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
                
        if( $queueId === null ){
        	curl_setopt($ch, CURLOPT_POST, true);
        	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }else{
        	curl_setopt($ch, CURLOPT_POST, false);
        	//curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        	 
        }
        

        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }
        curl_close($ch);
        
        $this->logger->log(print_r(json_decode($result), true));
        return json_decode($result);
    }
}
