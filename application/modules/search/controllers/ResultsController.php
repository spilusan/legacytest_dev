<?php

/**
 * Controller for search results
 *
 * @package MyShipServ
 * @copyright Copyright (c) 2009, ShipServ
 */
class Search_ResultsController extends Myshipserv_Controller_Action {

    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     *
     * @access public
     */
    public function init() {
    	parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('page', array('html', 'json'))
	                ->initContext();
    }

	private function getRelatedCategories($refinedQuery)
	{
    	$config  = Shipserv_Object::getConfig();
    	$results = array();
    	if( $refinedQuery['type'] != 'category' ) return $results;

   		$categories = (array) $refinedQuery['id'];
		if( count($categories) > 0 )
		{
			$sql = "
				SELECT
					*
				FROM
					PAGES_SEO_REL_CATEGORIES JOIN PAGES_RELATED_CATEGORIES ON (PSR_RELATED_CATEGORY_ID=PRL_CATEGORY_ID)
					JOIN PRODUCT_CATEGORY ON prl_category_id=id
				WHERE
			";
			foreach( $categories as $category )
			{
				$where[] = 'PSR_PRL_CATEGORY_ID=' . $category;
			}

			$sql .= implode(" OR ", $where);
			$sql .= " ORDER BY PRL_PRIORITY ASC";
			$results = $this->getDb()->fetchAll( $sql );

			return $results;
		}
	}

	private function getRelatedBeacons($refinedQuery)
	{
		$config  = Shipserv_Object::getConfig();
		$results = array();
        $categories = null;

		if(isset($refinedQuery['id']) && $refinedQuery['id'] != "")
		{
			$categories = (array) $refinedQuery['id'];
		}
		else
		{
			if(isset($this->params['categoryId']) && $this->params['categoryId'] != "" )
			{
				$categories = (array) $this->params['categoryId'];
			}
		}

		if( lg_count($categories) > 0 )
		{
			$sql = "
				SELECT * FROM pages_adnetw_beacon_category WHERE
			";
			foreach( $categories as $category )
			{
				$where[] = 'PBC_CATEGORY_ID=' . $category;
			}

			$sql .= implode(" OR ", $where);
			$results = $this->getDb()->fetchAll( $sql );

			return $results;
		}
	}



    private function getAdUnits( $refinedQuery )
    {
    	$config  = Shipserv_Object::getConfig();
    	$results = array();
        $categories = null;

    	if( $config->shipserv->ppc->enable != 1 )
    	{
    		return $results;
    	}

    	if( $refinedQuery['type'] == "category" )
		{
			$categories = (array) $refinedQuery['id'];
		}

		if( lg_count($categories) > 0 )
		{
			$sql = "
				SELECT
					*
				FROM
					pages_ppc_adunit
				WHERE
			";
			foreach( $categories as $category )
			{
				$where[] = 'pad_category_id=' . $category;
			}

			$sql .= implode(" OR ", $where);
	    	$results = $this->getDb()->fetchAll( $sql );
		}

    	return $results;
    }

    /**
     * Fetches and displays search results. Does some pre-processing using the
     * ontology to ensure a user is placed in the correct zone.
     *
     * @access public
     */
    public function indexAction()
    {

    	// ***************************************************** //
    	// disabled - fix for: DE3294
    	// ***************************************************** //
        /*
    	$this->getResponse()->clearHeaders()
        		->setHeader('Expires', '', true)
                ->setHeader('Cache-Control', 'public', true)
                ->setHeader('Cache-Control', 'max-age=3800')
                ->setHeader('Pragma', '', true);
    	*/
    	// ***************************************************** //



        $countries = new Shipserv_Oracle_Countries();
        $zoneSpotlightSupplier = null;
        $sponsorSupplier = null;
        $forcedZone = null;
        $categoryToMap = null;
        $contentArray = [];

    	// fix for: DE3294 - forcing search result to NOT pulling from cache
        $this->getResponse()->clearHeaders()
	        ->setHeader('Expires', '01-01-2005', true)
	        ->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
	        ->setHeader('Pragma', '', true);

        //$appConfig = Zend_Registry::get('config');
        $config = $this->config;
        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $getVars = array(
            'searchWhat'  => $this->_getParam('searchWhat'),
            'searchWhere' => $this->_getParam('searchWhere')
        );


        $resultsPerPage = $config['shipserv']['search']['results']['resultCountPerPage'];
        $maxSelectedSuppliers = $config['shipserv']['enquiryBasket']['maximum'];

        $this->view->referrer = $this->getRequest()->getHeader('referer');
        $this->view->resultsPerPage = $resultsPerPage;
		$this->view->maxSelectedSuppliers = $maxSelectedSuppliers;

		$this->view->config = $config;

        $this->view->db = $db;
        $this->view->params = $this->params;

        // check if the user is logged in or not
        if (!$user = Shipserv_User::isLoggedIn()) {
            $user = false;
        } else {
            //User is logged in, add activity log events
            $searchTerm = substr($this->_getParam('searchWhat').' ('.$this->_getParam('searchWhere').')',0,255);
            if ($user->isPartOfBuyer()) {
                $user->logActivity(Shipserv_User_Activity::SEARCHES_BUYER_COMPANY, 'PAGES_USER', $user->userId, $searchTerm);
            }

            if ($user->isPartOfSupplier()) {
                $user->logActivity(Shipserv_User_Activity::SEARCHES_SUPPLIER_COMPANY, 'PAGES_USER', $user->userId, $searchTerm);
            }
        }

        $this->view->user = $user;

        $params = $this->params;

        $seoHelper = $this->_helper->getHelper('Seo');

        if(
        	( $params['brandId'] != "" && !ctype_digit( $params['brandId'] ) )
        	|| ( $params['categoryId'] != ""  && !ctype_digit( $params['categoryId'] ) )
        	|| ( $params['productId'] != ""  && !ctype_digit( $params['productId'] ) )
        	|| ( $params['modelId'] != ""  && !ctype_digit( $params['modelId'] ) )
        )
        {
            throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        }
        else
        {
            // check if categoryId is valid
            if( $params['categoryId'] != "" )
            {
                $categoryAdapter = new Shipserv_Oracle_Categories($db);
                $category = $categoryAdapter->fetchCategory($params['categoryId']);
                $this->view->categoryId = $params['categoryId'];
                if( $category == null )
                {
                    throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
                }
            }
            else if( $params['brandId'] != "" )
            {
                $brandAdapter = new Shipserv_Oracle_Brands($db);
                $brand = $brandAdapter->fetchBrand($params['brandId']);
                if( $brand == null )
                {
                    throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
                }
            }
        }

        /**
         * if the user is coming from an SEO optimised page (brand, category, etc.)
         * we need to overide the 'searchWhat' that's sent with the searchable version.
         * The version sent often contains hyphens instead of spaces, etc.
         *
         * We get countries/ports overriden by SOLR, so no need to do the same thing
         */
        $searchOverride = $seoHelper->overrideSearchTerms($params);
        $params = array_merge($params, $searchOverride);

        /**
         * We need to redirect OLD landing pages (e.g. /search/results/index/searchWhat/ABB/brandId/3?ssrc=286)
         *
         * We can do this by checking if $_SERVER['REQUEST_URI'] contains /search/results/ in the string
         */
        $redirect = $seoHelper->redirectOldLandingPage($params);
        if ($redirect != false) {
            $this->redirect($redirect, array('code' => 301, 'exit' => true));
        }

        $cookieManager = $this->getHelper('Cookie');
        $form = new Myshipserv_Form_Search();

        if ($form->isValid($params)) {

            $values = $form->getValues();
            $searchUrlHelper = new Myshipserv_View_Helper_SearchUrl();
            $searchUrlHelper = $searchUrlHelper->searchUrl();

            $values['searchWhat'] = urldecode($searchUrlHelper->decodeForwardSlash($values['searchWhat']));

            // Create search object
            $search = new Myshipserv_Search_Search();
            $search->setRows($config['shipserv']['search']['results']['resultCountPerPage']);

            //Check if the current route is a zone identifier.
            $routeName = Zend_Controller_Front::getInstance()->getRouter()->getCurrentRouteName();

            switch ($routeName) {
                case 'countryZone':
                    $categoryToMap = $values['searchText'];
                    break;
                case 'companyZone':
                case 'brandZone':
                    $categoryToMap = !empty($values['searchWhat']) ? $values['searchWhat'] : $values['searchText'];
                    break;
                case 'portZone':
                     $categoryToMap = !empty($values['searchText']) ? $values['searchText'] : $values['searchWhat'];
                    break;
                case 'categoryZone':
                     $categoryToMap = !empty($values['searchWhat']) ? $values['searchWhat'] : $values['searchText'];
                     break;
                default:
                    break;
            }

            //Zones have predefined search parameters related to their type, eg Brand Names or categories,
            //but we want to override with the search vals entered on previous page to mitigate the problems caused by the route searches.
            if($categoryToMap){
                $params['searchWhat'] = $getVars['searchWhat'];
                $values['searchWhat'] = $getVars['searchWhat'];

                //As this may a category/brand route, which may have spotlight listings, and we are now in a zone we are removing the categoryId as its no longer relevant to the zone (Spotlights for the search shouldnt display in the zone e.g. search
                //for /category/valves/82/zone?ssrc=1429&searchWhat=chandlers+wartsila+valves&searchWhere=&searchText= and without unsetting the categoryid spotlight will follow through for the first part of the search.)
                unset($params['categoryId']);
                unset($params['brandId']);
            }

            // Initialize basic search attributes from request parameters
            $sReqAd = new Myshipserv_Search_SearchRequestAdapter($search);
            $sReqAd->setFromRequestParams($params, $values);

            if (!$this->_getParam('zone') && $categoryToMap) {
                $zone_param = Shipserv_Zone::returnCategoryToZoneMapping(strtolower($categoryToMap));
                $forcedZone = true;
            } else {
                $zone_param = $this->_getParam('zone');
            }

            if($forcedZone){
                $params['searchWhat_override']      = $getVars['searchWhat'];
                $params['searchWhere_override']     = $getVars['searchWhere'];
            }

            // Fetch zone data
            $trimmedKeywords = trim($values['searchWhat'] . ' ' . $values['searchText']);
            $zones = Shipserv_Zone::fetchZoneData($trimmedKeywords, $zone_param);
            
            //DE7297 Directly accessing deactivated zone should raise 404
            if ($zone_param) {
            	if ($zones[$zone_param]['content']['zoneIsActive'] === "0") {
            		throw new Myshipserv_Exception_MessagedException("Zone not found.", 404);
            	}
            }
           
            // If we're not in a zone
            if (!$zone_param || empty($zone_param)) {
                // Apply location parameters to search
                $sLocAd = new Myshipserv_Search_SearchLocationAdapter($db, $search);
                $sLocAd->setLocation(@$values['searchText'], @$values['searchWhere']);

            } else { // If we are in a zone
                $zone = $zones[$zone_param];

				if( $zone['content']['canonical'] != "" )
				{
					$this->view->canonical = $zone['content']['canonical'];
				}

                //If there is a sponsor, add the sponsor supplier data to the view.
                if ($params['searchWhat'] == '' || empty($params['searchWhat'])) {
                    if (!empty($zone['content']['sponsorship']['tradeNetId'])) {
                        $sponsorTNID = $zone['content']['sponsorship']['tradeNetId'];
                        $sponsorSupplierAdapter = new Shipserv_Supplier(array());

                        $sponsorSupplier = $sponsorSupplierAdapter->fetch($sponsorTNID, $db);
                    }
                }

                // Apply zone specific parameters to search
                $sZoneAd = new Myshipserv_Search_SearchZoneAdapter($db, $search);
                $sZoneAd->setLocation(@$values['searchText'], @$values['searchWhere']);
                $sZoneAd->addZoneFilterDef(@$zone['content']['search']['filters']);
                $sZoneAd->addZoneOrFilterDef(@$zone['content']['search']['orFilters']);
                $sZoneAd->addZoneFacetDef(@$zone['content']['search']['facets']);

                // Save for later
                $zoneFilters = $sZoneAd->getFilters();

                // Get all optiional search parameters for zone and store them in nice searchable way
                // ajwp todo - encapsulate this if not already done in Ulad's reworking
                $options = $filters = $zone['content']['search']['options'];

                $zoneOptions = array();
                if (is_array($options)) {
                    foreach ($options as $optionType => $option) {
                        if (is_array($option)) {
                            foreach ($option as $optionValue) {
                                $zoneOptions[$optionType][$optionValue] = true;
                            }
                        } else {
                            $zoneOptions[$optionType][$option] = true;
                        }
                    }
                }
            }
            
            // S9570
            $zones = Shipserv_Zone::performCheckOnPorts($zones, $result['refinedQuery']);

            // SPC-613 Redirect to Zone if we have zone match, add the original URL to last zone redirect session
            if (!$zone && $zones &&  $this->getRequest()->getParam('full') === null) {
                $zoneRedirectCount = 0;

                foreach ($zones as $isRedirectZone) {
                    $zoneRedirectCount += (int)($isRedirectZone['autoRedirect'] === 1 && $isRedirectZone['fullMatch'] === true);
                }
                
                // SPC-2565 Add new check if redirect is enabled
                foreach ($zones as $redirectZoneId => $isRedirectZone) {
                    if (($isRedirectZone['autoRedirect'] === 1 && count($zones) === 1) || ($isRedirectZone['fullMatch'] === true && $isRedirectZone['autoRedirect'] === 1 && $zoneRedirectCount === 1)) {
                        $requestUri = $this->getRequest()->getRequestUri();
                        // $lastZoneRedirect = Myshipserv_Helper_Session::getNamespaceSafely('Shipserv_Last_Zone_Redirect');
                        $zoneUrl = Shipserv_Zone::returnZoneToCategoryURL($redirectZoneId);

                        //make sure no matching url's so no forever redirection loop
                        if (trim(explode('?', $zoneUrl)[0]) !== trim(explode('?', $requestUri)[0])) {
                            // $lastZoneRedirect->url = $requestUri;
                            Myshipserv_Helper_Cookie::set('last_zone', $requestUri);
                            $this->redirect($zoneUrl, array('code' => 301, 'exit' => true));
                        }
                    }
                }
            }

            // Execute search
            $searcher = new Shipserv_Adapters_Search();
            $result = call_user_func_array(array($searcher, 'execute'), array_values($search->exportSearchServiceParamArr()));

            $data = array();

            /**
             * Part of S9570;
             * If user search for Engine Spare in Singatoka, Fiji without using a suggested dropdown,
             * they need to see the same result as when user use suggested dropdown
             * feedback from Mark Slinger
             */
            if( strstr($_SERVER['REQUEST_URI'], 'search/results') !== false
            	&& $this->_getParam('searchWhere') == ""
            	&& (trim($result['refinedQuery']['countryCode']) != "" || trim($result['refinedQuery']['portCode']) != "" )
            	&& ( $this->_getParam('searchStart') == null ) )
            {
            	if( trim($result['refinedQuery']['countryCode']) != "" )
            	{
            		$data['searchWhere'] = trim($result['refinedQuery']['countryCode']);
            	}

            	if( trim($result['refinedQuery']['portCode']) != "" )
            	{
            		$data['searchWhere'] = trim($result['refinedQuery']['portCode']);
            	}

            	$data['searchWhat'] = $this->params['searchWhat'] ?? null;
            	$data['zone'] = $this->params['zone'] ?? null;
            	$data['searchText'] = $this->params['searchText'] ?? null;

            	$u = '/search/results?' . http_build_query($data);
            	//echo "url: " . $u;
            	$this->redirect($u, array('code'=>301));

            }


            /**
             * now format the categories and memberships so we only show the
             * maximum (as defined in the config), plus any selected
             */
            $searchBoxOptionsAdapter = $this->_helper->getHelper('SearchBoxOptions');
            $searchBoxOptionsAdapter->initEnv($result, $zoneFilters, $zoneOptions, $params);
            $result['categoriesCount'] = count($result['categories']);
            $result['membershipsCount'] = count($result['memberships']);
            $result['certificationsCount'] = count($result['certifications']);
            $result['brandsCount'] = count($result['brands']);
            $result['categories'] = $searchBoxOptionsAdapter->getTypeOptions(array('singular' => 'category', 'plural' => 'categories'));
            $result['memberships'] = $searchBoxOptionsAdapter->getTypeOptions(array('singular' => 'membership', 'plural' => 'memberships'));
            $result['brands'] = $searchBoxOptionsAdapter->getTypeOptions(array('singular' => 'brand', 'plural' => 'brands'));
            $result['certifications'] = $searchBoxOptionsAdapter->getTypeOptions(array('singular' => 'certification', 'plural' => 'certifications'));

            //cache search results for 'More' autocomplete
            $optionsCacheId = $searchBoxOptionsAdapter->cacheOptions();

            // If we are in a zone and search returned few results ...
            //if ($zone_param and !isset($search->filters["authBrandOnly"]["true"]))
            if ($zone_param) {
                if ($result['documentsFound'] < $config['shipserv']['search']['results']['resultCountPerPage']) {
                    // Un-zone search and perform a second search
                    $sUnzoner = new Myshipserv_Search_SearchUnzoner($zone);
                    $alternativeSearch = $sUnzoner->cloneUnzonedSearch($search);
                    $searcher = new Shipserv_Adapters_Search();
                    $alternativeResult = call_user_func_array(array($searcher, 'execute'), array_values($alternativeSearch->exportSearchServiceParamArr()));

                    // Let's store number of results of alternative search in original result
                    $result['generalZoneResultCount'] = $alternativeResult['documentsFound'];

                    // Generate link to extra-zone search
                    $result['generalZoneResultUrl'] = $this->view->searchUrl()->fromSearchObj($alternativeSearch)->sourceKey('ZONE_EXIT_INVITE');
                }
            }

            // if it's a new search then clear the appropriate cookies:
            if ($params['newSearch']) {
                $cookieManager->clearCookie('enquiryBasket');
                $cookieManager->clearCookie('microprofile');
                $cookieManager->clearCookie('search');
            }

            // Fetch the microprofile cookie, and populate an array with TNIDs
            $microprofiles = unserialize(stripslashes($cookieManager->fetchCookie('microprofile')));
            if (!$microprofiles || $params['newSearch']) {
                $microprofiles = array();
            }

            // check if there are any company matches:
            $showCompanyMatches = false;
            if ($result['nameMatchFound']) {
                $showCompanyMatches = true;
            }

            $unicode = $this->getHelper('Unicode');
            $result['documents'] = $unicode->walkThroughResults($result['documents']);

            // Apply post-search query refinement
            $refinedSearch = clone $search;
            $sRefinedAd = new Myshipserv_Search_SearchRefinedQueryAdapter($db, $refinedSearch);
            $sRefinedAd->applyRefinedQuery(@$result['refinedQuery']);

			$getPlainKey = $this->_helper->getHelper('SearchSource')->getPlainKeyFromRequest();
			$isDirectRequest = Myshipserv_Controller_Action_Helper_AnalyticsSource::isRequestDirect();

			$logSearchId =  !empty($params['logSearchId']) ? $params['logSearchId'] : null;

			$arrTnids = array();
			foreach ((Array) $result['documents'] as $key => $doc) {
				$arrTnids[] = (int) $doc['tnid'];
				$resultCountryCode = $doc['countryCode'];
				// add if country restricted
                $result['documents'][$key]['isRestricted'] = false;

                if ($resultCountryCode && $resultCountryCode !== '') {

                    $checkCountry = $countries->fetchCountryByCode($resultCountryCode);
                    if (count($checkCountry) > 0) {
                        $isRestrictedCountry = (int)$checkCountry[0]['IS_RESTRICTED'];
                        $result['documents'][$key]['isRestricted'] = ($isRestrictedCountry === 1);
                    }
                }
			}

            // Log search if the tracking parameter is present, or if the referrer is a 3rd party.
			$curPage = (($params['searchStart'] + $resultsPerPage) / $resultsPerPage) ? (($params['searchStart'] + $resultsPerPage) / $resultsPerPage) : 1;

			$analyticsAdapter = $this->_helper->getHelper('Analytics');

            if ($this->_helper->getHelper('SearchSource')->getPlainKeyFromRequest() != '' || Myshipserv_Controller_Action_Helper_AnalyticsSource::isRequestDirect()) {
                try
                {
                    $username = (is_object($user)) ? $user->username : '';

                    $ip = Myshipserv_Config::getUserIp();
                    $spotlightedTnid = null;

                    // storing the impression of the spotlighted tnid to the db [xxx]
                    foreach( (array) $result['documents'] as $d )
                    {
                        if( $d['spotlight'] == 1 )
                        $spotlightedTnid = $d['tnid'];
                    }

                    $keyData[] = $this->_helper->getHelper('SearchSource')->getPlainKeyFromRequest();
                    $keyData[] = $username;
                    $keyData[] = $refinedSearch->what;
                    $keyData[] = $refinedSearch->type;
                    $keyData[] = $refinedSearch->where;
                    $keyData[] = $_SERVER['REQUEST_URI'];
                    $keyData[] = $zone_param;
                    $keyData[] = 'country' . $refinedSearch->country;
                    $keyData[] = 'port' . $refinedSearch->port;

                    $sp = $cookieManager->fetchCookie('searchParameter');

                    if( ($sp == null) || ($sp != md5(implode($keyData))) ){
                        $cookieManager->setCookie('searchParameter', md5(implode($keyData)));

    			        $searchRecId = $analyticsAdapter->logSearch(
                            $ip,
                            $_SERVER['HTTP_REFERER'],
                            $this->_helper->getHelper('SearchSource')->getPlainKeyFromRequest(),
                            $username,
                            $refinedSearch->what,
                            $refinedSearch->type,
                            @$result['refinedQuery']['id'], // brandId (when type = brand)
                            @$result['refinedQuery']['id'], // categoryId (when type = category)
                            $_SERVER['REQUEST_URI'], // fullQuery - path + params
                            $zone_param,
                            $refinedSearch->country,
                            $refinedSearch->port,
                            $result['documentsFound'],
                            $result['catalogueMatchesFound'],
                            $result['widenedSearch'],
                            null,
                            null,
                            null,
                            null,
                            null,
                            null
                        );
                        $cookieManager->setCookie('search', $searchRecId);
                    }
                } catch (Exception $e) { }
            }

			if(empty($logSearchId) && !empty($searchRecId))
			{
				$logSearchId = $searchRecId;
			}

			/**
			 * Create Session handler and store/check for page and search id combos to prevent duplicate submissions to the DB.
			 */
			$supplierPositionNS = Myshipserv_Helper_Session::getNamespaceSafely('SSSupplierPosition');

			$alreadyLogged = false;
			if(is_array($supplierPositionNS->logHistory)){
				foreach($supplierPositionNS->logHistory as $item){
					if($item['id'] == $logSearchId && $item['page'] == $curPage){
						$alreadyLogged = true;
					}
				}
			}


			if(!$alreadyLogged){
				//TODO: Add this to session instead of
				$supplierPositionNS->logHistory[] = array('id' => $logSearchId,'page' => $curPage);

                /*
				$secondId = $analyticsAdapter->logSearch(null, null, null, null, null, null, null, // brandId (when type = brand)
					null, // categoryId (when type = category)
					null, // fullQuery - path + params
					null, null, null, null, null, null,
					$resultsPerPage, $curPage, $arrTnids, (int) $logSearchId, $spotlightedTnid
					);
                */
			}

            // fetch the current basket
            $enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
            $supplierBasket = ($enquiryBasket['suppliers'] && !$params['newSearch']) ? $enquiryBasket['suppliers'] : array();

            // create the related searches
            $relatedSearches = array();

            // check if terms has 6 digits of IMPA number
            $impaSearch = $this->isImpaSearch($values["searchWhat"]);
            if (is_null($params['impa']) && $impaSearch !== false && $impaSearch["otherKeyword"] != "") {
                $impaCodeSearch = clone $search;
                $impaCodeSearchAd = new Myshipserv_Search_SearchRefinedQueryAdapter($db, $impaCodeSearch);
                $impaCodeResult = $result;
                $impaCodeResult['refinedQuery']["query"] = $impaSearch["impaCode"];
                $impaCodeSearchAd->applyRefinedQuery(@$impaCodeResult['refinedQuery']);

                $resultForImpaCodeSuggestion = call_user_func_array(array($searcher, 'execute'), array_values($impaCodeSearch->exportSearchServiceParamArr()));

                // check if the suggested related impa code search will return result, if so, show it, otherwise hide it
                if (count($resultForImpaCodeSuggestion["documents"]) > 0 ||
                        count($resultForImpaCodeSuggestion["brands"]) > 0 ||
                        count($resultForImpaCodeSuggestion["categories"]) > 0 ||
                        count($resultForImpaCodeSuggestion["memberships"]) > 0) {
                    $relatedSearches["Suppliers of IMPA item code " . $impaSearch["impaCode"] . ""] = $this->view->searchUrl()
                            ->searchWhat($impaSearch["impaCode"])
                            ->zone($zone_param)
                            ->searchText($refinedSearch->country)
                            ->searchWhere($refinedSearch->country)
                            ->sourceKey('RELATED_FROM_SEARCH');
                }

                $impaCodeSearch = clone $search;
                $impaCodeSearchAd = new Myshipserv_Search_SearchRefinedQueryAdapter($db, $impaCodeSearch);
                $impaCodeResult = $result;
                $impaCodeResult['refinedQuery']["query"] = $impaSearch["otherKeyword"];
                $impaCodeSearchAd->applyRefinedQuery(@$impaCodeResult['refinedQuery']);

                $resultForImpaCodeSuggestion = call_user_func_array(array($searcher, 'execute'), array_values($impaCodeSearch->exportSearchServiceParamArr()));

                // check if the suggested other related keyword will return result, if so, show it, otherwise hide it
                if (count($resultForImpaCodeSuggestion["documents"]) > 0 ||
                        count($resultForImpaCodeSuggestion["brands"]) > 0 ||
                        count($resultForImpaCodeSuggestion["categories"]) > 0 ||
                        count($resultForImpaCodeSuggestion["memberships"]) > 0
                ) {
                    $relatedSearches["Suppliers of " . $impaSearch["otherKeyword"] . ""] = $this->view->searchUrl()
                    	->searchWhat($impaSearch["otherKeyword"])
                        ->zone($zone_param)
                        ->searchText($refinedSearch->country)
                        ->searchWhere($refinedSearch->country)
                        ->sourceKey('RELATED_FROM_SEARCH');

                    $relatedSearches["Suppliers of " . $impaSearch["otherKeyword"] . ""] .= '&impa=0';
                }
            }


            if ($refinedSearch->country != '' || $refinedSearch->port != '') {

                //SEO context to tailor metadata based on whether result is a country or a port
                if($refinedSearch->port != ''){
                    $seoContext = "port";
                }else{
                    $seoContext = "country";
                }

                // create a chandlery related search if chandlery is not the selected zone
                if (!array_key_exists('http://rdf.myshipserv.com/ontology.rdf#chandlers', (array)$zones)) {
                    $name = 'Chandlery in ' . $refinedSearch->text;
                    // Removed
                    //$relatedSearches[$name] = $this->view->searchUrl()->searchWhat('Chandlery')->searchText($refinedSearch->text)->searchWhere($refinedSearch->port ? $refinedSearch->port : $refinedSearch->country);
                    // Removed
                    //$relatedSearches['Chandlery Zone'] = $this->view->searchUrl()->zone('chandlers')->searchWhat('Chandlery');
                }

                // if a port is selected, do a [SearchWhat] in [Country]
                if ($refinedSearch->what != '' && $refinedSearch->port != '')
                {
                	// fix for DE1962
                	$addRelatedSearch = true;
                	if( !empty($zone) && count($zone['content']['search']['filters']['searchWhere']) > 0 )
                	{
                		$addRelatedSearch = false;
                	}

                	if( $addRelatedSearch )
                	{
	                	$name = $refinedSearch->what . ' in ' . $refinedSearch->country;
	                    $relatedSearches[$name] = $this->view->searchUrl()
	                    	->zone($zone_param)
	                        ->searchWhat($refinedSearch->what)
	                        ->searchText($refinedSearch->country)
	                        ->searchWhere($refinedSearch->country)
	                        ->sourceKey('RELATED_FROM_SEARCH');
                	}
                }
            }

            $searchAdapter = new Shipserv_Adapters_Search();
            $totalSuppliers = $searchAdapter->fetchSupplierCount();

            $seoData = array('brandId' => $params['brandId'],
                'categoryId' => $params['categoryId'],
                'productId' => @$params['productId'],
                'modelId' => @$params['modelId'],
                'location' => $refinedSearch->text,
                'what' => $refinedSearch->what);

            // swap the view with the landing page of mooring ropes
            if( $params['categoryId'] == 49 && $refinedSearch->country == '' && $refinedSearch->port == '' )
            {
            	$this->_helper->viewRenderer('landing-page-category');
            	$a = new Shipserv_Oracle_Categories;
            	$x = $a->fetchNestedCategories();

            	foreach($x as $row)
            	{
            		$cats = explode("/", $row['PATH_ID']);
            		if( $cats[0] == 49 )
            		$subCategories[] = Shipserv_Category::getInstanceById($row['ID']);

            	}
            	$this->view->subCategories = $subCategories;
            }

            if($seoContext){
                $seoData['context'] = $seoContext;
            }

            if ($zone_param) {
                $seoData['zone'] = $zone['content']['title'];
                if (isset($zone['content']['meta']['canonical'])) {
                    $seoData['canonical'] = $zone['content']['meta']['canonical'];
                }
                if (isset($zone['content']['meta']['description'])) {
                    $seoData['description'] = $zone['content']['meta']['description'];
                }
            }

            $seoContent = $seoHelper->generateSeoContent($seoData, $breadcrumbs, $this->params);
            $this->view->breadcrumbs = $breadcrumbs;
            $this->view->headTitle($seoContent['title'], 'SET');

            // Google Ad Manager code:
            $gam['country'] = ($refinedSearch->country) ? $refinedSearch->country : 'ALL';
            $gam['port'] = ($refinedSearch->port) ? $refinedSearch->port : 'ALL';
            $gam['supplier'] = 'ALL';

            // A bit ugly
            $viewSearchValues = [
                'searchStart' => 0,
            ];

            $currentPage = (($viewSearchValues['searchStart'] + $resultsPerPage) / $resultsPerPage) ? (($viewSearchValues['searchStart'] + $resultsPerPage) / $resultsPerPage) : 1;
            
            foreach ($refinedSearch->exportArr() as $k => $v) {

                if ($k != 'filters' and $k != 'orFilters' and $k != 'facets') {
                	//$v = str_replace("%2F", "/", $v);
            	    //$viewSearchValues[$k] = ($v);
            	    $viewSearchValues[$k] = _htmlspecialchars($v);
                }
            }
            
            //Slightly hacky approach to fixing the issue with coming to a category with zone definition via friendly URL and search parameters being overridden
            if($forcedZone){
                $viewSearchValues['searchWhat'] = $getVars['searchWhat'];
                $viewSearchValues['searchWhere'] = $getVars['searchWhere'];
            }

            //Does this zone have a specified spotlight listing to display? If so we add the supplier to the view and inject it into the results via the view.
            if(!empty($zone['content']['spotlightId'])){

                // If there are conditions to be met before spotlighting this TNID
                if( !empty($zone['content']['spotlightConditions']) )
                {
                	if( $zone['content']['spotlightConditions']['tnid'] == $zone['content']['spotlightId'] )
                	{
                		$showSpotlight = false;

						foreach( $zone['content']['spotlightConditions']['searchConditions']['condition'] as $condition )
						{
							if( isset( $condition['searchWhat'] ) )
							{
								if( ($this->_getParam('searchWhat') == "" && is_null($condition['searchWhat']) ) || ($this->_getParam('searchWhat') == $condition['searchWhat']) )
								{
									if( ($viewSearchValues['searchWhere'] == "" && is_null($condition['searchWhere'] )) || ($viewSearchValues['searchWhere'] == $condition['searchWhere']) )
									{
											$showSpotlight = true;
									}
								}
							}
						}
                	}
                }
                else
                {
                	$showSpotlight = true;
                }

                if( $showSpotlight == true )
                {
                	$zoneSpotlightAdapter = new Shipserv_Supplier(array());
	                $zoneSpotlightSupplier = $zoneSpotlightAdapter->fetch($zone['content']['spotlightId'], $db);
                }
         	}

            // check special condition of when a certain supplier need to be spotlighted when it's outside zone
            if (!$zone_param || empty($zone_param)) {
                $keywordsForWWM = array('valve', 'valves', 'actuator', 'actuators');
                if( ( $viewSearchValues['searchWhere'] == "US" || $viewSearchValues['searchWhere'] == "" ) && in_array(strtolower(trim($this->_getParam('searchWhat'))), $keywordsForWWM) )
                {
                	$zoneSpotlightAdapter = new Shipserv_Supplier(array());
	                $zoneSpotlightSupplier = $zoneSpotlightAdapter->fetch(52418, $db);
                }
            }

            /*
            //We need to suppress spotlighting in results if we are in a zone.
            if($zone_param && $currentPage == 1){
                foreach($result['documents'] as $key => $value){
                       //We want to suppress all other spotlight listings.
                       $result['documents'][$key]['spotlight'] = 0;
                }
            }
            */

            //Merge the Sposor/ZoneSpotlight records into the results and update the result count accordingly.
            if($sponsorSupplier){
                $sponsorSupplierarr = get_object_vars($sponsorSupplier);
                $sponsorSupplierarr['spotlight'] = 1;

                foreach ($result['documents'] as $key => $value) {
                   if($sponsorSupplierarr['publicTnid'] == $value['tnid'])
                   {
                       unset($result['documents'][$key]);
                       $result['documentsFound']--;
                   }
               }

                if($currentPage == 1){
                    $result['documents'] = array_merge(array($sponsorSupplierarr), $result['documents']);
                }

                $result['documentsFound']++;
            }

            if($zoneSpotlightSupplier){
                $zoneSpotlightSupplierarr = get_object_vars($zoneSpotlightSupplier);
                $zoneSpotlightSupplierarr['spotlight'] = 1;

                foreach ($result['documents'] as $key => $value) {
                   if($zoneSpotlightSupplierarr['publicTnid'] == $value['tnid'])
                   {
                       unset($result['documents'][$key]);
                       $result['documentsFound']--;

                   }
               }

                if($currentPage == 1){
                   $result['documents'] = array_merge(array($zoneSpotlightSupplierarr), $result['documents']);
                }

                $result['documentsFound']++;
            }

            // Stick everything into the view object
            $this->view->hasResults = ($result['documentsFound'] > 0) ? true : false;
            $this->view->metaDescription = $seoContent['metaDescription'];
            $this->view->metaKeywords = $seoContent['metaKeywords'];

            if (isset($seoContent['canonical']) ) {
                $this->view->canonical = $seoContent['canonical'];
            }

            // for supplier/called/name remove the canonical
            if(strstr($_SERVER['REQUEST_URI'], "supplier/called/named") !== false)
            {
            	$this->view->canonical = false;
            }

            // lets randomize zone order
            $randomOrderedZones = array();
            $zoneKeys = array_keys($zones);
            $zoneKeyCount = count($zoneKeys);

            while ($zoneKeyCount > 0) {
                $randomZoneKeyId = mt_rand(0, count($zoneKeys) - 1);
                $randomZoneKey = $zoneKeys[$randomZoneKeyId];
                $randomOrderedZones[$randomZoneKey] = $zones[$randomZoneKey];
                unset($zoneKeys[$randomZoneKeyId]);
                $zoneKeys = array_values($zoneKeys);
                $zoneKeyCount--;
            }

            $zones = $randomOrderedZones;

            //die($this->view->canonical);
            $this->view->viewSearchValues = $viewSearchValues;
            $this->view->serpsTitle = $seoContent['serpsTitle'];
            $this->view->seoBlock = $seoContent['seoBlock'];
            $this->view->gam = $gam;
            $this->view->basketCookie = $config['shipserv']['enquiryBasket']['cookie'];
            $this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
            $this->view->supplierCount = number_format($totalSuppliers, 0, '.', ',');
            $this->view->showCompanyMatches = $showCompanyMatches;
            $this->view->microprofiles = $microprofiles;
            $this->view->relatedSearches = $relatedSearches;
            $this->view->searchRecId = $searchRecId;
			$this->view->logSearchId = isset($logSearchId) ? $logSearchId : $searchRecId;
            $this->view->optionsCacheId = $optionsCacheId;
            $this->view->supplierBasket = $supplierBasket;
            $this->view->zones = $zones;
            $this->view->content = $contentArray;
            $this->view->appliedFilters = $search->filters;
            $this->view->adUnits = $this->getAdUnits($result['refinedQuery']);
			$this->view->seoRelatedCategories = $this->getRelatedCategories($result['refinedQuery']);
			$this->view->beacons = $this->getRelatedBeacons($result['refinedQuery']);
			$this->view->refinedQuery = $result['refinedQuery'];
            $this->view->showRelatedCategory = ( count($this->view->seoRelatedCategories) > 0 );
            $this->view->searchValues = $viewSearchValues;
            $this->view->searchText = $refinedSearch->text;
            $this->view->searchWhere = $refinedSearch->where;
            $this->view->result = $result;
            $this->view->currentZoneIdent = $zone_param;
            $this->view->searchOriginalText = $search->text;

            // zone sponsorship
            $this->view->zoneSponsorshipIsEnabled = ($result['refinedQuery']['type'] == 'category' && Shipserv_Zone::showZoneSponsorshipBanner($result['refinedQuery']['id']));
			$this->view->zoneSponsorshipBanner = ($this->view->zoneSponsorshipIsEnabled===true) ? Shipserv_Zone::getZoneSponsorshipBanner($result['refinedQuery']):null;

			//S11348 Add Zone Banner if in singapore, and zone is sinwa
            //EMS Zone, requested by KIM
			$this->view->addActiveZoneBanner =  (array_key_exists('sinwa', $zones) && array_key_exists('singapore', $zones) || array_key_exists('ems', $zones));

            if ($result['unknownLocation']) {
                $this->view->unknownLocation = $this->_getParam('searchText');
            }

            if (isset($result["refinedQuery"]["type"]) and $result["refinedQuery"]["type"] == "brand")
                $this->view->refinedBrandId = $result["refinedQuery"]["id"];
            else
                $this->view->refinedBrandId = null;


            // No result page stats for this release
            //$rpStats = new Myshipserv_SupplierTransactionStats($db);
            //$this->view->statsResults = $rpStats->resultPageStats(Myshipserv_SupplierTransactionStats::extractTnids($result));
        }
        else {
            $this->view->errors = $form->getErrors();
        }

        //Pass through the sponsor for the zone if it exists and set the zponsoredZone flag.
        if ($sponsorSupplier) {
            $this->view->sponsorSupplier = $sponsorSupplier;
            $this->view->sponsoredZone = true;
        } else {
            $this->view->sponsoredZone = false;
        }

        if($zoneSpotlightSupplier){
            $this->view->zoneSpotlightSupplier = $zoneSpotlightSupplier;
            $this->view->showZoneSpotlightSupplier = true;
        }else{
            $this->view->showZoneSpotlightSupplier = false;
        }

        // Pass search source helper through to view to aid formation of tracking links
        $this->view->searchSourceHelper = $this->_helper->getHelper('SearchSource');

	    // Add current URL to stack to enable 'back to search' functionality
	    $this->rememberSearchUri();

        // S7566: storing variable needed by the search box on the supplier profile page
        $memcache = new Memcache;
        $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);
        $key = session_id()  . "-" . $this->view->logSearchId;

        $data = new stdClass();
        $data->searchValues = $this->view->searchValues;
        $data->searchText = $this->view->searchText;
        $data->supplierCount = $this->view->supplierCount;
        $data->currentZoneIdent = $this->view->currentZoneIdent;

        $memcache->set($key, serialize($data));

        if( $this->_getParam('j') == 1 )
        {
            $this->_helper->layout()->disableLayout();
            $this->renderScript('json/json.phtml');;
            return;
        }
    }

    private function isImpaSearch($keyword) {
        //$result = preg_match("/([\d]{6})|([\d][\.|\-|\s]{0,}[\d][\.|\-|\s]{0,}[\d][\.|\-|\s]{0,}[\d][\.|\-|\s]{0,}[\d][\.|\-|\s]{0,}[\d][\.|\-|\s]{0,})|([\d]{2}\-[\d]{2}\-[\d]{2})/", $keyword, $matches);
        //$result = preg_match("/(?!.*[\d]{6})$/", $keyword, $matches);
        $result = preg_match("/.*([\d]{6})$/", $keyword, $matches);

        if ($result == 1) {
            $output["impaCode"] = $matches[count($matches) - 1];
            $output["otherKeyword"] = trim(str_replace($output["impaCode"], "", $keyword));
            return $output;
        }
        else
            return false;
    }

	private function getDb() {
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}

	public function dataAction()
	{
    	$response = new Myshipserv_Response("200", "OK", array("done" => 1));
    	$this->_helper->json((array)$response->toArray());
	}
}
