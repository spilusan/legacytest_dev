<?php

/**
 * Adapter class for Analytics Service
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Adapters_Analytics_Api
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	/**
	 * The application version (found in application.ini) - useful to track so
	 * changes can be monitored in the internal analytics database
	 *
	 * @var string
	 * @access protected
	 */
	protected $appVersion;
	
	/**
	 * Browser object to parse the HTTP user agent
	 *
	 * @var object
	 * @access protected
	 */
	protected $browser;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 * @param string $url The URL of the service
	 */
	public function __construct ()
	{
		$config  = Shipserv_Object::getConfig();
		
		$this->client     = new Zend_XmlRpc_Client($config->shipserv->services->analytics->url);
		$this->appVersion = Myshipserv_Config::getApplicationReleaseVersion();
		$this->browser    = new Shipserv_Browser();
		$this->geodata    = new Shipserv_Geodata();
	}
	
	/**
	 * Logs the details of a user's search in the internal analytics service
	 * 
	 * @access public
	 * @param string $ipAddress (required)
	 * @param string $referrer (required)
	 * @param string $source
	 * @param string $username
	 * @param string $query
	 * @param string $searchType
	 * @param int $brandId Set $searchType = 'brand'
	 * @param int $categoryId Set $searchType = 'category'
	 * @param string $fullQuery
	 * @param string $zone
	 * @param string $countryCode
	 * @param string $portCode
	 * @param int $documentsFound get from search results
	 * @param int $catalogueMatchesFound get from search results
	 * @param boolean $searchWidened get from search results
	 * @param int $noOfSuppliers Part of the new Search Position ranking. No of suppliers per page.
	 * @param int $pageNumber Current result set page
	 * @param string[] $supplierTnids Array of tnids in order of appearance in search results
	 * @return string searchRecId
	 */
	public function logSearch ($ipAddress, $referrer, $source, $username, $query,
							   $searchType, $brandId, $categoryId, $fullQuery, $zone,
							   $countryCode, $portCode, $documentsFound,
							   $catalogueMatchesFound, $searchWidened,
							$noOfSuppliers,$pageNumber,$supplierTnids, $searchId = null, $spotlightedTnid = null)
	{
		
		// To prevent an error on the service side due to non-handling of nulls
		if (!$referrer)
		{
			$referrer = '';
		}
		
		// Truncate query string if > 100 bytes: otherwise service balks and fails to log search
		if (strlen($query) > 100) $query = substr($query, 0, 100);
		
		$parameters = array(array('ipAddress'             => Shipserv_Adapters_Analytics::getIpAddress(),
								  'browser'               => $this->browser->fetchName(),
								  'userAgent'             => $this->browser->agent,
								  'referrer'              => $referrer,
								  'source'                => $source,
								  'username'              => $username,
								  'query'                 => $query,
								  'searchType'            => $searchType,
								  'brandId'				  => (int) $brandId,
								  'categoryId'			  => (int) $categoryId,
								  'fullQuery'			  => (string) $fullQuery,
								  'zone'				  => (string) $zone,
								  'countryCode'           => $countryCode,
								  'portCode'              => $portCode,
								  'documentsFound'        => (int) $documentsFound,
								  'catalogueMatchesFound' => (int) $catalogueMatchesFound,
								  'searchWidened'         => (boolean) $searchWidened,
								  'geoData'               => addslashes(serialize($this->geodata)),
								  'appVersion'            => $this->appVersion,
								  'noOfSuppliersPerPage'  => (int) $noOfSuppliers,
								  'pageNumber'			  => (int) $pageNumber,
								  'supplierTnids'		  => $supplierTnids,
								  'spotlightTnid'		  => $spotlightedTnid
								  ),

			);
		
		if(!empty($searchId)){
			$parameters[0]['searchId'] = $searchId;
		}
		
		$result = $this->client->call('Analytics.logSearch', $parameters);
		
		return $result;
	}
	public function logSpotlightedListing($spotlightedTnid, $ipAddress, $referrer, $source, $username, $query,
							   $searchType, $brandId, $categoryId, $fullQuery, $zone,
							   $countryCode, $portCode, $documentsFound,
							   $catalogueMatchesFound, $searchWidened,
							$noOfSuppliers,$pageNumber,$supplierTnids, $searchId = null)
	{
		
		// To prevent an error on the service side due to non-handling of nulls
		if (!$referrer)
		{
			$referrer = '';
		}
		
		// Truncate query string if > 100 bytes: otherwise service balks and fails to log search
		if (strlen($query) > 100) $query = substr($query, 0, 100);
		
		$parameters = array(array('ipAddress'             => Shipserv_Adapters_Analytics::getIpAddress(),
								  'browser'               => $this->browser->fetchName(),
								  'userAgent'             => $this->browser->agent,
								  'referrer'              => $referrer,
								  'source'                => $source,
								  'username'              => $username,
								  'query'                 => $query,
								  'searchType'            => $searchType,
								  'brandId'				  => (int) $brandId,
								  'categoryId'			  => (int) $categoryId,
								  'fullQuery'			  => (string) $fullQuery,
								  'zone'				  => (string) $zone,
								  'countryCode'           => $countryCode,
								  'portCode'              => $portCode,
								  'documentsFound'        => (int) $documentsFound,
								  'catalogueMatchesFound' => (int) $catalogueMatchesFound,
								  'searchWidened'         => (boolean) $searchWidened,
								  'geoData'               => addslashes(serialize($this->geodata)),
								  'appVersion'            => $this->appVersion,
								  'noOfSuppliersPerPage'  => (int) $noOfSuppliers,
								  'pageNumber'			  => (int) $pageNumber,
								  'supplierTnids'		  => $supplierTnids,
								  'spotlightTnid'		  => $spotlightedTnid
								  ),

			);
		
		if(!empty($searchId)){
			$parameters[0]['searchId'] = $searchId;
		}
		
		$result = $this->client->call('Analytics.logSearch', $parameters);
		
		return $result;		
	}
	
	/**
	 * Called when a user views the supplier profile page
	 *
	 * @access public
	 * @param string $ipAddress (required)
	 * @param string $referrer (required)
	 * @param string $username
	 * @param string $tnid
	 * @param string $searchRecId used when coming from a search results page;
	 * 							  this is the value returned by Analytics.logSearch
	 * @param string $source indicates from where profile is viewed (e.g. search result, browser a-z, ...)
	 * @param int $autosuggestId
	 * @param array $recommendedPremiumTnidForBasicListing
	 */
	public function logGetProfile ($ipAddress, $referrer, $username, $tnid, $searchRecId, $source, $autosuggestId = null, $recommendedPremiumTnidForBasicListing = null)
	{
		$parameters = array(array('ipAddress'     => Shipserv_Adapters_Analytics::getIpAddress(),
								  'browser'       => $this->browser->fetchName(),
								  'userAgent'     => $this->browser->agent,
								  'referrer'      => $referrer,
								  'username'      => $username,
								  'tnid'          => (string) $tnid,
								  'searchRecId'   => $searchRecId,
								  'geoData'       => addslashes(serialize($this->geodata)),
								  'appVersion'    => $this->appVersion,
                                  'source'        => $source,
								  'autosuggestId' => $autosuggestId,
								  'recommendedSpb' => $recommendedPremiumTnidForBasicListing));
		
		$result = $this->client->call('Analytics.logGetProfile', $parameters);
		
		return $result;
	}
	
	/**
	 * Called when the user declines to register or sign in after attempting to
	 * view a supplier's contact details.
	 *
	 * @access public
	 * @param string $getProfileRecId this is the value returned by Analytics.logGetProfile
	 * @return void
	 */
	public function logContactInfoDeclined ($getProfileRecId)
	{
		$parameters = array($getProfileRecId);
		
		$result = $this->client->call('Analytics.logContactInfoDeclined', $parameters);
		
		return $result;
	}
        
    /**
     * Called to retrospectively update log entries with given username.
     * 
     * @access public
     * @param string $username
     * @param array $searchRecIds an array of ints
     * @param array $getProfileRecIds an array of ints
     */
	public function updateSearchAndGetProfileRecs ($username, array $searchRecIds, array $getProfileRecIds)
	{		
		$parameters = array(array(
            'username'          => $username,
			'searchRecIds'      => $searchRecIds,
			'getProfileRecIds'  => $getProfileRecIds,
        ));
		
		$result = $this->client->call('Analytics.updateSearchAndGetProfileRecs', $parameters);
		
		return $result;
	}
	
	public function logUpgradeListingClicked ($getProfileRecId)
	{
		$parameters = array((string) $getProfileRecId);
		
		$result = $this->client->call('Analytics.logUpgradeListingClicked', $parameters);
		
		return $result;
	}
	

	/**
	 * Called when the user successfully views a supplier�s contact details.
	 * This implies the user has successfully signed in or registered.
	 *
	 * @access public
	 * @param int $getProfileRecId this is the value returned by Analytics.logGetProfile
	 * @param string $username
	 * @return void
	 */
	public function logContactInfoViewed ($getProfileRecId, $username)
	{
		$parameters = array((int) $getProfileRecId, $username);
	
		$result = $this->client->call('Analytics.logContactInfoViewed', $parameters);
	
		return $result;
	}
	
	/*
		isContactTabClicked (boolean) <- true if 'Contact Supplier' tab is selected.
		isSendRFQClicked (boolean) <-true if 'Send RFQ or enquiry' button is selected.
		isViewTNIDClicked (boolean) <- 'true if View TNID' button is selected.
		isTNIDViewed (boolean) <- true if �View TNID' TNID is displayed to buyer
		isTNIDCopied (boolean) <-true if 'Copy TNID' TNID is pushed to buyer clipboard for use within own system
		isContactDetailsClicked (boolean) <- true if 'View other contact details' is selected
		isContactDetailsViewed (boolean) <- true if Other contact details is actually viewed
		username (string) <- login user name
	*/
	const TAB_TO_VIEW_CONTACT_IS_CLICKED 	= 'isContactTabClicked'
		, BUTTON_TO_SEND_RFQ_IS_CLICKED 	= 'isSendRFQClicked'
		, BUTTON_TO_VIEW_TNID_IS_CLICKED 	= 'isViewTNIDClicked'
		, TNID_IS_VIEWED 	= 'isTNIDViewed'
		, TNID_IS_COPIED 	= 'isTNIDCopied'
		, BUTTON_TO_VIEW_CONTACT_IS_CLICKED = 'isContactDetailsClicked'
		, CONTACT_IS_VIEWED = 'isContactDetailsViewed'
		, CONTACT_EMAIL_IS_VIEWED = 'isContactEmailViewed'
		, BUTTON_TO_WEBSITE_IS_CLICKED = 'isWebsiteClicked'
	;
	
	public function logContactEvent($profileViewId, $eventType = null, $username = '')
	{
		
		$translation = array(
			  'TAB_TO_VIEW_CONTACT_IS_CLICKED' => self::TAB_TO_VIEW_CONTACT_IS_CLICKED
			, 'BUTTON_TO_SEND_RFQ_IS_CLICKED' => self::BUTTON_TO_SEND_RFQ_IS_CLICKED
			, 'BUTTON_TO_VIEW_TNID_IS_CLICKED' => self::BUTTON_TO_VIEW_TNID_IS_CLICKED
			, 'TNID_IS_VIEWED' => self::TNID_IS_VIEWED
			, 'TNID_IS_COPIED' => self::TNID_IS_COPIED
			, 'BUTTON_TO_VIEW_CONTACT_IS_CLICKED' => self::BUTTON_TO_VIEW_CONTACT_IS_CLICKED
			, 'CONTACT_IS_VIEWED' => self::CONTACT_IS_VIEWED
			, 'CONTACT_EMAIL_IS_VIEWED' => self::CONTACT_EMAIL_IS_VIEWED
			, 'BUTTON_TO_WEBSITE_IS_CLICKED' => self::BUTTON_TO_WEBSITE_IS_CLICKED
		);
		
		if( $translation[$eventType] === null )
		{
			throw new Exception ("Invalid action");
		}
		
		$parameters = array(array(
			'profileViewId'          => $profileViewId,
			$translation[$eventType] => (boolean) true,
			'username' 				 => $username
		));
		
		$result = $this->client->call('Analytics.logContactEvent', $parameters);
		return $result;
	}
	
	
	/**
	 * Log Supplier profile catalogue impression event
	 * 
	 * @param unknown $profileViewId
	 * @return ['"OK"'|'"NOT OK"']
	 */
	public function logCatalogueImpressionEvent($profileViewId)
	{
	    $parameters = array((Int) $profileViewId);
	    $result = $this->client->call('Analytics.logCatalogImpression', $parameters);
	    return $result;
	}	
}
