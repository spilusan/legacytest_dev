<?php

/**
 * Controller for handling supplier-related actions (profiles, microprofiles, etc.)
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class SupplierController extends Myshipserv_Controller_Action {

    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     *
     * @access public
     */
    public function init() {
        parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('list', 'html')
                ->addActionContext('catalogue', 'html')
                ->addActionContext('microprofile', 'html')
                ->addActionContext('remove-microprofile', 'json')
                ->addActionContext('catalogue-search', 'html')
                ->addActionContext('list-search', 'html')
                ->addActionContext('log-contact-viewed', 'json')
                ->addActionContext('log-upgrade-listing-clicked', 'json')
                ->addActionContext('trigger-page-impression', 'html')
                ->addActionContext('listing-verified', array('html', 'json'))
                ->addActionContext('invite-brand-owner-to-authorise', 'json')
                ->addActionContext('send-search-feedback', 'json')
                ->addActionContext('get-alerts', 'json')->setAutoJsonSerialization(true)
                ->initContext();
    }

    public function mapDataAction()
    {
        $json = file_get_contents('https://www.shipserv.com/events/fetch-recent-events/fetch/10');
        $data = json_decode($json);

        foreach($data->Locations as $row) {
        	if ($row->eventType == 'search' && $row->lat != "" && $row->lng != "") {
        		$newRows[] = $row;
        	} elseif( $row->eventType != 'search') {
        		$newRows[] = $row;
        	}
        }
	$new = new StdClass;
        $new->Locations = $newRows;
        echo $this->_helper->json((array)$new);
    }

    public function indexAction() {
        // action body
    }

    /**
     * Forwards to Profile Action
     * Flags that 'reviews' tab should be selected as default
     */
    public function reviewsAction() {
        $this->view->defaultTab = 'reviews';
        $this->_forward('profile');
    }

    /**
     * Supplier Profile Controller Action
     *
     * @access public
     */
    public function profileAction()
    {

        $productId = $this->getRequest()->getParam('productId');


        // @todo this part can be removed when the proper IMPA catalogue URL fields provided by Core team
        if ($productId) {
            $pathLookup = new Shipserv_Catalogue_PathLookup();
            $redirectUrlHash = $pathLookup->getCatalogueUrlByItemId($productId);
            $currentUrl = $_SERVER['REQUEST_URI'];
            $strippedUrl = preg_replace('/&?productId=[^&]*/', '', $currentUrl);
            $this->redirect($strippedUrl . $redirectUrlHash , array('code' => 301, 'exit' => true));
        }
        // remove until

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout->setLayout('empty');
        }
        if (empty($this->view->defaultTab)) {
            $this->view->defaultTab = 'profile';
        }

        $this->getResponse()->setHeader('Expires', '', true);
        $this->getResponse()->setHeader('Cache-Control', 'public', true);

        $db = $this->db;
        $user = $this->user;
        $this->view->user = $user;

        $config = $this->config;
        $urlSParam = $this->_getParam('s');
        
        // make sure invalid ULR does not pass through at this point
        if (!preg_match('/(^|-)[0-9]+$/',$urlSParam)) {
            throw new Myshipserv_Exception_MessagedException('Page not found', 404);
        }

        $urlArray = array_reverse(explode('-', $urlSParam));
        $this->view->supplierUrlPart = $this->_getParam('s');
        $tnid = (int)$urlArray[0];

        // create a supplier object
        $supplier = Shipserv_Supplier::fetch($tnid, $db, false, false);
        if (empty($supplier->tnid)) {
            $this->view->canonical = "";
            throw new Myshipserv_Exception_MessagedException("Supplier not found or not published or deleted, please check your url and try again.", 404);
        }

        if (is_object($user)) {
            $ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
            $this->view->userSuppliers = $ucActions->fetchMyCompanies()->getSupplierIds();
            $this->view->userBuyers = $ucActions->fetchMyCompanies()->getBuyerIds();
        } else {
            $this->view->userSuppliers = array();
            $this->view->userBuyers = array();
        }
        $this->view->userHasAdminRights = in_array($tnid, $this->view->userSuppliers);
        $this->view->supplierURL =  $_SERVER['HTTP_HOST'] . $this->getRequest()->getPathInfo();

        // changed by Yuriy Akopov on 2013-07-31 (DE4094)
        // @todo: the same check is performed in supplier and reviews views so it can probably be isolated into a helper (or the whole menu even)
        // $showContactDetails = (is_object($user)) ? true : false;
        $this->view->showContactDetails = (is_object($user)) ? $user->canSeeContactViewDetailEasily() : false;

        $cookieManager = $this->getHelper('Cookie');

        //$form = new Myshipserv_Form_Search();

        if (ctype_digit($tnid) === false) {
            throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        } else {
            if (strlen($tnid) < 5) {
                throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
            }
        }



        //S17860 We have to add publicTind to url if it is not there
        if ($this->getRequest()->getParam('publicTnid') === null) {
            $urlToRedirect = trim($_SERVER['REQUEST_URI'], '?');
            $urlToRedirect .= (strpos($urlToRedirect, '?') === false) ? '?publicTnid=' . $supplier->publicTnid : '&publicTnid=' . $supplier->publicTnid;
            $this->redirect($urlToRedirect, array('code' => 301, 'exit' => true));
        }
       
        // S5820
        if( $this->params['s'] != "" )
        {
        	// perform redirection
        	if( strstr($supplier->getUrl(),$this->params['s']) === false || ctype_digit($this->params['s'] ) )
        	{
        		$url = $supplier->getUrl();

        		if($_SERVER['QUERY_STRING'] != "")
        		{
        			$url .= "?" . $_SERVER['QUERY_STRING'];
        		}
            	$this->redirect($url, array('code' => 301, 'exit' => true));
        	}
        }

        // perform a check to see if we should redirect (for legacy links)
        if (count($urlArray) == 2 && $urlArray[1] == '') {
            $url = $this->view->supplierProfileUrl($supplier);
            $this->redirect($url, array('code' => 301));
        }

        // fetch the supplier competitors if it's a non-Premium Profile
        if (!$supplier->isPremium()) {
            $this->view->competitors = $supplier->fetchCompetitors(3, true);
            $recommendedPremiumTnidForBasicListing = array();
            foreach($this->view->competitors as $c)
            {
                $recommendedPremiumTnidForBasicListing[] = $c->tnid;
            }
        }


        // check on each brand, whether it's a passive or active brand [EL]
        $brandAdditionalInfo = array();

        foreach ($supplier->brands as $brand) {
            $brandHasAdministrators = "N";

            // prepare the adapter to fetch the users' detail
            $ucDom = $userCompanyAdepter = new Myshipserv_UserCompany_Domain($db);

            //fetch list of companies that own brand
            $brandOwnerCompanyId = Shipserv_BrandAuthorisation::getBrandOwners($brand["id"]);

            if (count($brandOwnerCompanyId) == 0) {
                $brandOwnerCompanyId = Shipserv_BrandAuthorisation::getPassiveBrandOwners($brand["id"], true);
            }

            // if one or more companies found
            if (count($brandOwnerCompanyId) > 0) {

                // each company, get the users' detail
                foreach ($brandOwnerCompanyId as $id) {
                    // get the user for this supplier branch type of company with its ID
                    $uColl = $ucDom->fetchUsersForCompany('SPB', $id);

                    // switch the flag to Y
                    if (count($uColl->getAdminUsers())) {
                        $brandHasAdministrators = "Y";
                    }
                }
            }

            $brandAdditionalInfo[$brand["id"]] = $brandHasAdministrators;
        }

        // fix the unicode entities
        $unicodeReplace = new Shipserv_Unicode();
        $supplier->description = $unicodeReplace->UTF8entities($supplier->description);

        // fetch the current basket
        $enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
        $supplierBasket = $enquiryBasket['suppliers'];
        if (!$supplierBasket) {
            $supplierBasket = array();
        }

        // set up the similar suppliers - defined by a search within the category for the country of the supplier
        $similarSuppliers = array();

        if (is_array($supplier->categories)) {
            foreach ($supplier->categories as $category) {
                if ($category['primary']) {
                    $title = $category['name'] . ' in ' . $supplier->countryName;
                    $linkText = $title;
                    $searchText = urlencode('All of ' . $supplier->countryName);
                    $similarSuppliers[] = array('url' => array('searchWhat' => $category['name'], 'searchText' => $searchText, 'searchWhere' => $supplier->countryCode),
                        'title' => $title,
                        'linkText' => $linkText);
                }
            }
        }

        // add to visited suppliers cookie so the app doesn't log subsequent visit
        $tnids = $cookieManager->fetchCookie("visitedTnid");
        $tnid = (int) $tnid;

        if ($tnids != "")
            $tnids = explode(",", $tnids);
        else
            $tnids = array();

        $searchRecId = $cookieManager->fetchCookie('search');

        if (in_array($tnid, $tnids) === false)
        {
            if (count($tnids) == 0)
            {
                $tnids = array($tnid);
            }
            else
            {
                $tnids[] = $tnid;
            }

            $cookieManager->setCookie("visitedTnid", implode(",", $tnids));

            // we pass through a 'nolog' parameter if logging in from a contact tab form
            if ($this->_getParam('nolog') != 1)
            {
                try {

                    $analyticsAdapter = $this->_helper->getHelper('Analytics');

                    $username = (is_object($user)) ? $user->username : '';


                    $ip = Myshipserv_Config::getUserIp();

                    // Fetch profile view source from token
                    $analProfileSrc = $this->_helper->getHelper('ProfileSource')->getPlainKeyFromRequest();

                    $getProfileRecId = $analyticsAdapter->logGetProfile($ip, $_SERVER['HTTP_REFERER'], $username, $tnid, $searchRecId, $analProfileSrc, $this->_getParam('asId'), $recommendedPremiumTnidForBasicListing);

                    $cookieManager->setCookie('profile', $getProfileRecId);

                    $this->view->getProfileRecId = $getProfileRecId;
                    
                    $profileImpression[$tnid] = $getProfileRecId;
                    $cookieManager->setCookie('profileImpression', Shipserv_Encrypt::encrypt(serialize($profileImpression)));
                } catch (Exception $e) {

                }
            }
        }

        if( $this->view->getProfileRecId === null )
        {
            $profileImpression = $cookieManager->fetchCookie('profileImpression');
            $profileImpression = unserialize(Shipserv_Encrypt::decrypt($profileImpression));
        	$this->view->getProfileRecId = $profileImpression[$tnid];
        }

        /***** SEO STUFF **** */

        //if (!preg_match('/\b[shipping|marine|ship|chandlery]\b/i', $supplier->name)) {
            $seoFiller = ' Shipping & Marine Supplier ';
        //} else {
        //    $seoFiller = ' ';
        //}

        $title = trim($supplier->name) . $seoFiller;
		if(strlen($supplier->countryName) > 1){
			$title .=	$supplier->countryName;
			if(strlen($supplier->city) > 1){
				$title .= ",";
			}
		}
		if(strlen($supplier->city) > 1){
			$title .= $supplier->city;
		}

        $title.= ' - ShipServ Pages';

        if( $this->view->defaultTab === "reviews" )
        {
        	$title = "ShipServ Supplier Reviews: " . trim($supplier->name);
        }

        $this->view->headTitle($title, 'SET');

        $primaryCategories = array();
        $secondaryCategories = array();
        foreach ($supplier->categories as $id => $category) {
            if ($category['primary'] == true) {
                $primaryCategories[] = $category['name'];
            } else {
                $secondaryCategories[] = $category['name'];
            }
        }

//		if ($supplier->description) { // if there's a description, use that
//			$metaDescription = $this->view->string()->shortenToLastWord(strip_tags(str_replace("\n", "", $supplier->description)), 150);
//		} elseif (count($primaryCategories) > 0) { // if there are primary categories, use those
//			$metaDescription = 'Marine supplier of ' . implode(', ', $primaryCategories);
//		} elseif (count($secondaryCategories) > 0) { // if there are secondary categories, use those
//			$metaDescription = 'Marine supplier of ' . implode(', ', $secondaryCategories);
//		} else {
//			$metaDescription = 'Marine Supplier in ' . $supplier->city . ', ' . $supplier->countryName;
//		}
//
        $metaDescription = trim($supplier->name);
        //if (!preg_match('/\b[shipping|marine|ship|chandlery]b/i', $supplier->name)) {
            $metaDescription .= ' Shipping & Marine Supplier ';
        //} else {
        //    $metaDescription .= ' ';
        //}
        $metaDescription .= $supplier->countryName . ', ' . $supplier->city . ' Up to date Marine Supply information for the Shipping Industry - ShipServ Pages';


        // meta keywords
        $metaKeywords = 'Marine, Maritime, Shipping, Suppliers, Supply, Companies, Directory, Listings, Search, ShipServ, Parts, Equipment, Spares, Services, ';
        $metaKeywords.= $supplier->name . ', ' . $supplier->city . ', ' . $supplier->countryName;
        if (count($primaryCategories) > 0) {
            $metaKeywords.= ', ' . implode(', ', $primaryCategories);
        }

        if (count($secondaryCategories) > 0) {
            $metaKeywords.= ', ' . implode(', ', $secondaryCategories);
        }

        if (count($supplier->brands) > 0) {
            foreach ($supplier->brands as $brand) {
                $metaKeywords.= ', ' . $brand['name'];
            }
        }

        // see if there has been a catalogue query passed through.
        // If so, trigger the catalogue search
        if ($this->params['q']) {
            $this->view->catQuery = $this->params['q'];
        }

        //Get supplier reviews count. This will initially only be used for SEO purposes
        $supplierReviewCount = Shipserv_Review::getReviewsCounts(array($tnid));
        $endorsementsDao = new Shipserv_Oracle_Endorsements($this->getInvokeArg('bootstrap')->getResource('db'));

        $searchAdapter = new Shipserv_Adapters_Search();
        $totalSuppliers = $searchAdapter->fetchSupplierCount();

        $this->view->supplierCount = number_format($totalSuppliers, 0, '.', ',');

        $getVars = array('searchWhat' => strip_tags($_GET['q']), 'searchWhere' => strip_tags($_GET['searchWhere']));
        $searchValues['searchWhat'] = $getVars['searchWhat'];
        $searchValues['searchWhere'] = $getVars['searchWhere'];
        
        //Split ports S16839, This logic was moved from the view to here
        $ports = array(
        		'presence' => array(),
        		'served' => array()
        );
        
        if ($supplier->ports) {
        	if ($config['shipserv']['supplier']['wss']['tnid'] != $supplier->tnid) {
        		foreach ($supplier->ports as $port) {
        			$key = ($port['primary'] == 1) ? 'presence' : 'served';
       				$ports[$key][] = array(
       						'id' => $port['code'],
       						'name' => $port['name'] .', '.$port['countryCode'],
                            'restricted' => $port['isRestricted']
       				);
				}
				
				if ($supplier->globalDelivery == 1 && $supplier->premiumListing) {
					$ports['served'][] = array(
							'id' => null,
							'name' => 'Worldwide'
					);
				} 
        	} else {
        		$ports['served'][] = array(
        				'id' => null,
        				'name' => 'Worldwide'
        		);
        	}
        }

        // DEV-1610 Dynamically add tnid to the parameter was set in supplier profile
        Zend_Registry::set('sirNavigateTnid', $tnid);

        $this->view->ports = $ports;
        $this->view->config = $config;
        $this->view->endorsements = $endorsementsDao->fetchEndorsementsByEndorsee($supplier->tnid);
        // give the view the supplier data
        $this->view->basketCookie = $config['shipserv']['enquiryBasket']['cookie'];
        $this->view->metaDescription = $metaDescription;
        $this->view->metaKeywords = $metaKeywords;
        $this->view->supplierBasket = $supplierBasket;
        $this->view->similarSuppliers = $similarSuppliers;
        $this->view->supplier = $supplier;
        $this->view->supplierReviewCount = $supplierReviewCount[$tnid];
        $this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
        $this->view->mixpanelApiKey = $config['mixpanel']['services']['apiKey'];
        $this->view->searchWhat = $this->_getParam('searchWhat');
        $this->view->searchWhere = $this->_getParam('searchWhere');
        $this->view->searchStart = $this->_getParam('searchStart');
        $this->view->page = $this->_getParam('page');
        $this->view->brandAdditionalInfo = $brandAdditionalInfo;
        $this->view->tnid = $tnid;
        $this->view->userCompanyHelper = $this->_helper->companyUser;
        $this->view->referrer = $this->getRequest()->getHeader('referer');

        // Stats only required for non-Premium Profiles
        if (!$supplier->premiumListing) {

			if( $_SERVER['APPLICATION_ENV'] == "development")
			{
				$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
				$db = $resource->getDb('standbydblocal');
			}

            $rpStats = new Myshipserv_SupplierTransactionStats($db);
            $this->view->statsProfile = $rpStats->supplierProfileStats($supplier->tnid);
        }

        // S7566: storing variable needed by the search box on the supplier profile page
        $memcache = new Memcache;
        $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);
        $key = session_id()  . "-" . $searchRecId;
        $data = unserialize($memcache->get($key));


        $this->view->searchValues = $data->searchValues ;
        $this->view->searchText = $data->searchText;
        $this->view->supplierCount = $data->supplierCount ;
        $this->view->currentZoneIdent = $data->currentZoneIdent;


    }

    private function weightedRandom($values, $weights) {
        $count = count($values);
        $i = 0;
        $n = 0;
        $num = mt_rand(0, array_sum($weights));
        while ($i < $count) {
            $n += $weights[$i];
            if ($n >= $num) {
                break;
            }
            $i++;
        }
        return $values[$i];
    }

    /**
     * Fetches the profile for a supplier for use in expandable search results
     *
     * @access public
     */
    public function microprofileAction() {
        $tnid = $this->_getParam('s');
        $currentPage = $this->_getParam('p');
        $config = Zend_Registry::get('options');
        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $cookieManager = $this->getHelper('Cookie');

        $user = Shipserv_User::isLoggedIn();


        // fetch the supplier profile
        $supplierObj = Shipserv_Supplier::fetch($tnid, $db);


        try {
            $analyticsAdapter = $this->_helper->getHelper('Analytics');
            $username = (is_object($user)) ? $user->username : '';

            $searchRecId = $cookieManager->fetchCookie('search');

            $ip = Myshipserv_Config::getUserIp();

            $getProfileRecId = $analyticsAdapter->logGetProfile($ip, $_SERVER['HTTP_REFERER'], $username, $tnid, $searchRecId, 'MICRO');

            $this->view->getProfileRecId = $getProfileRecId;
        } catch (Exception $e) {

        }

        // fetch the current basket
        $supplierBasket = unserialize(stripslashes($_COOKIE[$config['shipserv']['enquiryBasket']['cookie']['name']]));
        if (!$supplierBasket) {
            $supplierBasket = array();
        }

        // add the microprofile to the microprofile basket so we know to expand it later
        $cookie = $config['shipserv']['microprofile']['cookie'];
        $microprofiles = $_COOKIE[$cookie['name']];

        $tnids = unserialize(stripslashes($microprofiles));
        if (!$tnids) {
            $tnids = array();
        }

        $tnids[$currentPage][$tnid] = true;

        $expiryTime = ($cookie['expiry'] == 0) ? 0 : time() + $cookie['expiry'];
        setcookie($cookie['name'], serialize($tnids), $expiryTime, $cookie['path'], $cookie['domain']);

        // done
        $this->view->supplier = $supplierObj;
        $this->view->supplierBasket = $supplierBasket;
    }

    public function removeMicroprofileAction() {
        $tnid = $this->_getParam('s');
        $currentPage = $this->_getParam('p');
        $config = Zend_Registry::get('options');

        $cookie = $config['shipserv']['microprofile']['cookie'];
        $microprofiles = $_COOKIE[$cookie['name']];

        $tnids = unserialize(stripslashes($microprofiles));
        if (!$tnids) {
            $tnids = array();
        }

        unset($tnids[$currentPage][$tnid]);
        $expiryTime = ($cookie['expiry'] == 0) ? 0 : time() + $cookie['expiry'];
        setcookie($cookie['name'], serialize($tnids), $expiryTime, $cookie['path'], $cookie['domain']);

        $this->_helper->json((array)[]);
    }

    /**
     * Action to trigger a page impression
     *
     * @access public
     */
    public function triggerPageImpressionAction() {
        try {
            $tnid = str_replace('tnid-doc-', '', $this->_getParam('s'));
            $source = $this->_getParam('source') ? $this->_getParam('source') : 'DOCMATCH';
            $user = Shipserv_User::isLoggedIn();
            $username = (is_object($user)) ? $user->username : '';
            $searchRecId = $this->getHelper('Cookie')->fetchCookie('search');
            $ip = Myshipserv_Config::getUserIp();

            $analyticsAdapter = $this->_helper->getHelper('Analytics');

            $getProfileRecId = $analyticsAdapter->logGetProfile($ip, $_SERVER['HTTP_REFERER'], $username, $tnid, $searchRecId, $source);
        } catch (Exception $e) {

        }
        exit;
    }

    /**
     * List action - provides the file tree structure of a catalogue
     *
     * @access public
     */
    public function listAction() {
        // create a catalogue adapter
        $catAdapter = new Shipserv_Adapters_Catalogue();

        $config = $this->config; //Zend_Registry::get('options');

        $id = ($this->_getParam('dir') != '') ? str_replace('/', '', $this->_getParam('dir')) : $this->_getParam('tnid');
        $folderStart = $this->_getParam('folderStart') ? $this->_getParam('folderStart') : 0;
        $folderRows = $this->_getParam('folderRows') ? $this->_getParam('folderRows') : 600;
        $itemStart = $this->_getParam('itemStart') ? $this->_getParam('itemStart') : 0;
        $itemRows = $this->_getParam('itemRows') ? $this->_getParam('itemRows') : 600;

        // fetch the supplier profile
        $catalogue = $catAdapter->fetch($id, $folderStart, $folderRows, $itemStart, $itemRows, false, $config['memcache']['client']['keyPrefix'], $config['memcache']['client']['keySuffix']);

        //if( ctype_digit( $this->_getParam('catId') ) == false || ctype_digit( $this->_getParam('tnid') ) == false  )
        //{
        //  throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        //}
        // give the view the supplier data
        $this->view->catalogueImageUrlPrefix = $config['shipserv']['services']['catalogue']['images']['urlPrefix'];
        $this->view->catalogue = $catalogue;
        $this->view->catId = $this->_getParam('catId');
        $this->view->tnid = $this->_getParam('tnid');
    }

    /**
     * Catalogue action - provides the products for a specific catalogue ID
     *
     * @access public
     */
    public function catalogueAction() {
        // create a catalogue adapter
        $catAdapter = new Shipserv_Adapters_Catalogue();

        $config = $this->config; //Zend_Registry::get('options');

        $folderStart = $this->_getParam('folderStart') ? $this->_getParam('folderStart') : 0;
        $folderRows = $this->_getParam('folderRows') ? $this->_getParam('folderRows') : 50;
        $itemStart = $this->_getParam('itemStart') ? $this->_getParam('itemStart') : 0;
        $itemRows = $this->_getParam('itemRows') ? $this->_getParam('itemRows') : 50;

        // fetch the supplier profile
        $id = str_replace('/', '', $this->_getParam('catId')); // Call edited so the catalgueAction and listAction used same rel attr data
        $catalogue = $catAdapter->fetch($id, $folderStart, $folderRows, $itemStart, $itemRows, false, $config['memcache']['client']['keyPrefix'], $config['memcache']['client']['keySuffix']);

        // give the view the supplier data
        $this->view->catalogueImageUrlPrefix = $config['shipserv']['services']['catalogue']['images']['urlPrefix'];
        $this->view->catalogue = $catalogue;
        $this->view->itemStart = $itemStart;
        $this->view->catId = $id;
        $this->view->folderId = $this->_getParam('folderId');
        $this->view->tnid = $this->_getParam('tnid');
    }

    /**
     * Catalogue search action
     *
     * @access public
     */
    public function catalogueSearchAction() {
        // create a catalogue adapter
        $catAdapter = new Shipserv_Adapters_Catalogue();

        $config = $this->config; //Zend_Registry::get('options');

        $folderStart = $this->_getParam('folderStart') ? $this->_getParam('folderStart') : 0;
        $folderRows = $this->_getParam('folderRows') ? $this->_getParam('folderRows') : 50;
        $itemStart = $this->_getParam('itemStart') ? $this->_getParam('itemStart') : 0;
        $itemRows = $this->_getParam('itemRows') ? $this->_getParam('itemRows') : 50;
        $query = (trim($this->_getParam('query')) == '') ? 'browse' : $this->_getParam('query');
        $id = str_replace('/', '', $this->_getParam('catId'));

        $searchResults = $catAdapter->search($id, $folderStart, $folderRows, $itemStart, $itemRows, $query);

        $this->view->catalogueImageUrlPrefix = $config['shipserv']['services']['catalogue']['images']['urlPrefix'];
        $this->view->tnid = str_replace('/', '', $this->_getParam('tnid'));
        $this->view->catId = $id;
        $this->view->itemStart = $itemStart;
        $this->view->query = $query;
        $this->view->searchResults = $searchResults;
        $this->view->folderId = $this->_getParam('folderId');
        $this->view->tnid = $this->_getParam('tnid');
    }

    /**
     * List search action - provides the file tree structure of a catalogue search
     *
     * @access public
     */
    public function listSearchAction() {
        // create a catalogue adapter
        $catAdapter = new Shipserv_Adapters_Catalogue();

        $config = $this->config; //Zend_Registry::get('options');

        $folderStart = $this->_getParam('folderStart') ? $this->_getParam('folderStart') : 0;
        $folderRows = $this->_getParam('folderRows') ? $this->_getParam('folderRows') : 50;
        $itemStart = $this->_getParam('itemStart') ? $this->_getParam('itemStart') : 0;
        $itemRows = $this->_getParam('itemRows') ? $this->_getParam('itemRows') : 50;
        $query = (trim($this->_getParam('query')) == '') ? 'browse' : $this->_getParam('query');
        $id = ($this->_getParam('dir') != '') ? str_replace('/', '', $this->_getParam('dir')) : $this->_getParam('tnid');

        $searchResults = $catAdapter->search($id, $folderStart, $folderRows, $itemStart, $itemRows, $query);

        //$this->view->catId         = $id;
        $this->view->catalogueImageUrlPrefix = $config['shipserv']['services']['catalogue']['images']['urlPrefix'];
        $this->view->folderStart = $folderStart;
        $this->view->query = $query;
        $this->view->searchResults = $searchResults;
        $this->view->catId = $this->_getParam('catId');
        $this->view->tnid = $this->_getParam('tnid');
    }

    /**
     * Browse action - Creates A to Z listing from the browse service
     *
     * @access public
     */
    public function browseAction() {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
        $searchAdapter = new Shipserv_Adapters_Search();
        $this->view->supplierCount = number_format($searchAdapter->fetchSupplierCount(), 0, '.', ',');

        $form = new Myshipserv_Form_Search();

        $browseAdapter = new Shipserv_Adapters_Browse();

        $startsWithNonAlpha = false;
        $searchStart = $this->_getParam('searchStart') ? $this->_getParam('searchStart') : 0;
        $searchRows = $this->_getParam('searchRows') ? $this->_getParam('searchRows') : 0;

        $premiumListing = ($this->_getParam('view') == 'all') ? false : true;
        $profiles = array();

        if ($this->_getParam('l')) {
            $letter = str_replace('marine-suppliers-beginning-with-', '', $this->_getParam('l'));

            if (!in_array($letter, range('a', 'z'))) {
                $startsWithNonAlpha = true;
                $letter = "&#35;";
            }

            $searchRows = 0;
        } else {
            $letter = 'a';
            $premiumListing = true;
            $startsWithNonAlpha = false;
        }

        $result = $browseAdapter->fetch($letter, $searchStart, $searchRows, $premiumListing, $startsWithNonAlpha);

        $profiles[$letter] = $result['profiles'];

        $this->view->onlyPremiums = $premiumListing;
        $this->view->letter = $letter;
        $this->view->profiles = $profiles;

        // Add current URL to stack to enable 'back to search' functionality
	    $this->rememberSearchUri();
    }

    
    /*
     * Commented by Claudio for security reason... OMG!
    public function xyzAction()
    {
    	print_r($_SERVER);
    	die();
    	$parts = parse_url($_SERVER['HTTP_REFERER']);
    	print_r($parts);
    	//phpinfo();
    	die();
    }
    */
    

    /**
     * Category action - Creates list of Categories
     *
     * @access public
     */
    public function categoryAction() {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
        $this->view->params = $this->params;
        $searchAdapter = new Shipserv_Adapters_Search();
        $this->view->supplierCount = number_format($searchAdapter->fetchSupplierCount(), 0, '.', ',');

        $categoriesAdapter = new Shipserv_Oracle_Categories($this->getInvokeArg('bootstrap')->getResource('db'));

        $letter = null;
        $display = 'topCategories';
        if ($this->_getParam('view') == 'all') {
            $display = 'allCategories';
            $categories = $categoriesAdapter->fetchNestedCategories();

            $mainTitle = 'All Supply Categories';
        } elseif ($this->_getParam('browse-by-country') && $this->_getParam('id')) {
        	$display = 'countries';
            $continents = $categoriesAdapter->fetchCountriesForCategory($this->_getParam('id'));
            $this->view->continents = $continents;
            $mainTitle = 'Marine suppliers by category';
            $categories = array();
        } elseif ($this->_getParam('browse-by-port') && $this->_getParam('cntcode') && $this->_getParam('id')) {
            $display = 'ports';
            $mainTitle = 'Marine suppliers by category';
            $categories = $categoriesAdapter->fetchPortsForCategory($this->_getParam('id'), $this->_getParam('cntcode'));
        } else {
            // fetch popular categories
            $categories = $categoriesAdapter->fetchPopularCategories();
            $mainTitle = 'Popular Marine Supply Categories';
        }

        if ($this->_getParam('id')) {
            // fetch the brand if an id is specified
            $category = $categoriesAdapter->fetchCategory($this->_getParam('id'));
            $mainTitle = 'Marine suppliers of ' . $category['DISPLAYNAME'];

            if( $display == "ports" )
            {
            	$countryAdapter = new Shipserv_Oracle_Countries;
            	$countryDetail = $countryAdapter->fetchCountryByCode($this->params['cntcode']);
            	$mainTitle = 'Marine suppliers of ' . $category['DISPLAYNAME'] . " in " . $countryDetail[0]['CNT_NAME'] ;
            }
            $this->view->category = $category;
        }

        if( $_SERVER['REQUEST_URI'] == '/supplier/category/' || $_SERVER['REQUEST_URI'] == '/supplier/category')
        {
        	$this->view->canonical = 'https://www.shipserv.com/supplier/category/view/all';
        }
        $this->view->mainTitle = $mainTitle;
        $this->view->display = $display;
        $this->view->letter = $letter;
        $this->view->categories = $categories;
    }

    /**
     * Brands action - Creates list of Brands
     *
     * @access public
     */
    public function brandAction() {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
        $searchAdapter = new Shipserv_Adapters_Search();
        $this->view->supplierCount = number_format($searchAdapter->fetchSupplierCount(), 0, '.', ',');

        $brandAdapter = new Shipserv_Oracle_Brands($this->getInvokeArg('bootstrap')->getResource('db'));

        if ($this->_getParam('id')) {
            // fetch the brand if an id is specified
            $brand = $brandAdapter->fetchBrand($this->_getParam('id'));
            if ($brand !== null) {
                $mainTitle = 'Marine Suppliers of ';
                $mainTitle.= ($brand['BROWSE_PAGE_NAME']) ? $brand['BROWSE_PAGE_NAME'] : $brand['NAME'];
                $this->view->brand = $brand;
            }
        }

        $letter = null;
        $display = 'brands';
        if ($this->_getParam('l')) {
            $letter = str_replace('marine-suppliers-of-brands-beginning-with-', '', $this->_getParam('l'));

            if (!in_array($letter, range('a', 'z'))) {
                $startsWithNonAlpha = true;
                $letter = "&#35;";
            }

            $brands = $brandAdapter->fetchBrands($letter, $startsWithNonAlpha);
            $mainTitle = 'Marine Brands A-Z';
            $this->view->brands = $brands;
        } elseif ($this->_getParam('browse-by-country') && $this->_getParam('id')) {
            $display = 'countries';
            $continents = $brandAdapter->fetchCountriesForBrand($this->_getParam('id'));
            $mainTitle = 'Marine Brands A-Z';
            $this->view->continents = $continents;
        } elseif ($this->_getParam('browse-by-port') && $this->_getParam('cntcode') && $this->_getParam('id')) {
            $display = 'ports';
            $port = $brandAdapter->fetchPortsForBrand($this->_getParam('id'), $this->_getParam('cntcode'));
            $mainTitle = 'Marine Brands A-Z';
            $this->view->ports = $ports;
        } elseif ($this->_getParam('browse-by-product') !== null && $brand !== null) {
            $display = 'products';
            $mainTitle = 'Marine Products made by ' . (($brand['BROWSE_PAGE_NAME'] != '') ? $brand['BROWSE_PAGE_NAME'] : $brand['NAME']);
            $productDao = new Shipserv_Oracle_Product($this->getInvokeArg('bootstrap')->getResource('db'));
            $this->view->products = $productDao->fetchByBrandId($brand['ID']);
        } elseif ($this->_getParam('browse-by-model') !== null) {
            $display = 'models';

            $productDao = new Shipserv_Oracle_Product($this->getInvokeArg('bootstrap')->getResource('db'));
            $product = $productDao->fetchById($this->_getParam('pid'));

            if ($product === null) {
                throw new Exception("No product found for passed id");
            }

            $mainTitle = "Models of {$product['NAME']}"; // todo

            $modelDao = new Shipserv_Oracle_Model($this->getInvokeArg('bootstrap')->getResource('db'));
            $this->view->models = $modelDao->fetchByProductId($product['ID']);
        } else {
            // fetch popular brands
            $brands = $brandAdapter->fetchPopularBrands();
            $mainTitle = 'Marine Brands A-Z';
            $this->view->brands = $brands;
        }

        $this->view->mainTitle = $mainTitle;
        $this->view->display = $display;
        $this->view->letter = $letter;
    }

    /**
     * Countries action - Creates list of Countries
     *
     * @access public
     */
    public function countryAction() {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
        $searchAdapter = new Shipserv_Adapters_Search();
        $this->view->supplierCount = number_format($searchAdapter->fetchSupplierCount(), 0, '.', ',');

        $countryAdapter = new Shipserv_Oracle_Countries($this->getInvokeArg('bootstrap')->getResource('db'));
        $continents = $countryAdapter->fetchCountriesByContinent();
        $this->view->continents = $continents;

        $mainTitle = 'Countries A-Z';
        if ($this->_getParam('con')) {
            $this->view->display = 'continents';
            $this->view->continent = $continents[$this->_getParam('con')]['name'];
            $this->view->countries = $continents[$this->_getParam('con')]['countries'];
        } else {
            $countries = $countryAdapter->fetchPopularCountries();
            $this->view->display = 'countries';
            $this->view->countries = $countries;
        }

        $this->view->mainTitle = $mainTitle;
    }

    /**
     * Fetches ports for a specific country
     *
     * @access public
     *
     */
    public function browseByPortAction() {
        $user = Shipserv_User::isLoggedIn();
        $this->view->user = $user;
        $searchAdapter = new Shipserv_Adapters_Search();
        $this->view->supplierCount = number_format($searchAdapter->fetchSupplierCount(), 0, '.', ',');

        // fetch the ports
        $portAdapter = new Shipserv_Oracle_Ports($this->getInvokeArg('bootstrap')->getResource('db'));
        $ports = $portAdapter->fetchSupplierPorts($this->_getParam('cnt'));

        // fetch the country
        $countryAdapter = new Shipserv_Oracle_Countries($this->getInvokeArg('bootstrap')->getResource('db'));
        $country = $countryAdapter->fetchCountryByCode($this->_getParam('cnt'));

        $this->view->country = $country[0]['CNT_NAME'];
        $this->view->ports = $ports;
    }

    /**
     * AJAX call to log a contact details viewed event with the internal analytics
     *
     * @access public
     *
     */
    public function logContactViewedAction() {
        $analyticsAdapter = $this->_helper->getHelper('Analytics');
        $getProfileRecId = $this->_getParam('getprofilerecid');

        /**
         * if the user is already logged in, log as logContactInfoViewed,
         * otherwise log as declined until a login/register event occurs
         */
        $user = Shipserv_User::isLoggedIn();

        if (is_object($user)) {
            $result = $analyticsAdapter->logContactInfoViewed($getProfileRecId, $user->username);
        } else {
            // check the ABTest cookie
            if (isset($_COOKIE['ABRoute']) && $_COOKIE['ABRoute'] == "contact-viewable-by-public") {
                $result = $analyticsAdapter->logContactInfoViewed($getProfileRecId, $user->username);
            } else {
                $result = $analyticsAdapter->logContactInfoDeclined($getProfileRecId);
            }

            // now push this onto the log stack so it can be updated if a login/registration occurs later in the session
        }

        $this->_helper->json((array)[]);
    }

    public function logValueEventAction()
    {
    	$username = "";
    	$response = null;

    	if (trim($this->params['getprofilerecid']) == "" || $this->params['getprofilerecid'] === null || ctype_digit($this->params['getprofilerecid']) == false || $this->params['a'] == "") {
    		throw new Myshipserv_Exception_MessagedException("Invalid parameter, make sure that you include: getprofilerecid and action", 404);
    	}

    	$api = new Shipserv_Adapters_Analytics_Api();

    	try {
    		$response = $api->logContactEvent($this->params['getprofilerecid'], $this->params['a'], $username);
    		$response = "OK";
    	} catch (Exception $e) {
    		$response = "NOT OK - " . $e->getMessage();
    	}
    	$this->_helper->json((array)$response);
    }

    /**
     * End point called by js (public/js/jquery.supplierdetail.js) triggered by (ex) /supplier/profile/s/fastenal-company-north-america-80347?publicTnid=80347#catalogue
     */
    public function logCatalogueImpressionEventAction()
    {
        $username = "";
        $response = null;
    
        if (trim($this->params['getprofilerecid']) == "" || $this->params['getprofilerecid'] === null || ctype_digit($this->params['getprofilerecid']) == false) {
            throw new Myshipserv_Exception_MessagedException("Invalid parameter, make sure that you include: getprofilerecid and action", 404);
        }
    
        $api = new Shipserv_Adapters_Analytics_Api();    
        try {
            $response = $api->logCatalogueImpressionEvent($this->params['getprofilerecid'], $this->params['a'], $username);
            $response = "OK";
        } catch (Exception $e) {
            $response = "NOT OK - " . $e->getMessage();
        }
        $this->_helper->json((array)$response);
    }
    
    
    public function logUpgradeListingClickedAction() {
        $analyticsAdapter = $this->_helper->getHelper('Analytics');
        $result = $analyticsAdapter->logUpgradeListingClicked($this->_getParam('getprofilerecid'));

        $this->_helper->json((array)[]);
    }

    public function postDispatch() {
        // Pass search source helper through to view to aid formation of tracking links
        $this->view->searchSourceHelper = $this->_helper->getHelper('SearchSource');
    }

    public function traderankTooltipAction() {
        $this->_helper->layout->setLayout('empty');

        $db = $this->db;

        if (intval($this->_getParam('tnid')) > 0) {
            $this->view->supplier = Shipserv_Supplier::fetch(intval($this->_getParam('tnid')), $db);
        } else {
            $this->view->supplier = null;
        }

        $this->view->activeTab = $this->_getParam('tab');
    }

    public function einvoiceTooltipAction() {
        $this->_helper->layout->setLayout('empty');
    }

    public function membershipTooltipAction() {
        $this->_helper->layout->setLayout('empty');
    }

    public function brandverificationTooltipAction() {
        $this->_helper->layout->setLayout('empty');
    }

    public function verifiedTooltipAction() {
        $this->_helper->layout->setLayout('empty');
    }

    public function maxsuppliersTooltipAction() {
        $this->_helper->layout->setLayout('empty');
    }

    /**
     * Handle the ajax response when inviting brand owner to authorise a supplier using their brand
     *
     * @author Elvir <eleonard@shipserv.com>
     */
    public function inviteBrandOwnerToAuthoriseAction() {

        // initialise response
        $response = array();
        $response['ok'] = false;
        $response['msg'] = 'Undefined error';

        // get database instance
        $db = $this->db;

        // prepare notfication manager to deliver the email
        $notificationManager = new Myshipserv_NotificationManager($db);

        // get all parameters
        $params = $this->_getAllParams();
        // check if all parameters exists
        if (!isset($params['brandId']) || !isset($params['supplierId']) || !isset($params['companyId']) || !isset($params['message']) || !isset($params['authLevel']) || !isset($params['name'])) {
            $response['ok'] = false;
            $response['msg'] = 'Problem with parameters';

            throw new Myshipserv_Exception_MessagedException("Problem with parameters");
        }

        // if all ok
        else {
            // check the data integrity
            if (intval($params['brandId']) > 0) {
                // preparing the notification manager
                $notificationManager = new Myshipserv_NotificationManager($db);
                $authLevels = explode(",", $params["authLevel"]);
                foreach ($authLevels as $authLevel) {
                    $authLevel = trim($authLevel);
                    // check if request to the brandowner is already been sent
                    if ($auths = Shipserv_BrandAuthorisation::search(array(
                                "PCB_COMPANY_ID" => $params["companyId"],
                                "PCB_BRAND_ID" => $params["brandId"],
                                "PCB_AUTH_LEVEL" => $authLevel
                            ))) {

                        // send the invitation for each auth request
                        foreach ($auths as $a) {

                            // prepare multiple emails
                            if (strstr($params["email"], ",") !== false)
                                $emails = explode(",", $params["email"]);
                            else
                                $emails[0] = $params["email"];

                            // sending email to brand owner
                            $notificationManager->inviteBrandOwnerToAuthoriseSupplier($a, $emails, $params["message"]);
                        }

                        $response['ok'] = true;
                        $response['msg'] = 'Request has been sent';
                    }
                }
            }
            // throws exception
            else {
                $response['ok'] = false;
                $response['msg'] = 'Problem with parameters';

                throw new Myshipserv_Exception_MessagedException("Problem with parameters");
            }
        }

        //$this->view->assign($response);
        $this->_helper->json((array)$response);
    }

    /**
     * Controller made for Kevin to test email compability accross different readers
     *
     * @author Elvir <eleonard@shipserv.com>
     */
    /*
      public function sendTestEmailAction() {
      $this->_helper->layout->setLayout('empty');

      // initialise response
      $response = array();
      $response['ok'] = false;
      $response['msg'] = 'Undefined error';

      // get database instance
      $db = $this->db;

      // prepare notfication manager to deliver the email
      $notificationManager = new Myshipserv_NotificationManager($db);

      // get all parameters
      $params = $this->_getAllParams();
      $params["authLevel"] = "REP";
      $params["brand"] = "xxxxx";
      $params["brandId"] = "1073";
      $params["companyId"] = "68818";
      $params["supplierId"] = "68818";
      $params["email"] = "soconnor@shipserv.com";
      $params["message"] = "Hello
      line 1
      line 2
      line 3";
      $params["name"] = "Fawaz Yousef";
      $params["supplier"] = "Gulf shipchandler";

      // check if all parameters exists
      if (!isset($params['brandId']) || !isset($params['supplierId']) || !isset($params['companyId']) || !isset($params['message']) || !isset($params['authLevel']) || !isset($params['name'])) {
      $response['ok'] = false;
      $response['msg'] = 'Problem with parameters';

      throw new Myshipserv_Exception_MessagedException("Problem with parameters");
      }

      // if all ok
      else {
      // check the data integrity
      if (intval($params['brandId']) > 0) {
      // preparing the notification manager
      $notificationManager = new Myshipserv_NotificationManager($db);

      // check if request to the brandowner is already been sent
      if ($auths = Shipserv_BrandAuthorisation::search(array(
      "PCB_COMPANY_ID" => $params["companyId"],
      "PCB_BRAND_ID" => $params["brandId"],
      "PCB_AUTH_LEVEL" => $params["authLevel"]
      ))) {
      // send the invitation for each auth request
      foreach ($auths as $a) {
      // prepare multiple emails
      if (strstr($params["email"], ",") !== false)
      $emails = explode(",", $params["email"]);
      else
      $emails[0] = $params["email"];
      $notificationManager->returnAsHtml();
      // sending email to brand owner
      $result = $notificationManager->inviteBrandOwnerToClaim($a, $emails, $params["message"]);
      }
      $this->view->html = str_replace("dev.myshipserv.com", "test.myshipserv.com", $result[0]);
      $this->view->html = str_replace("dev-cert.myshipserv.com", "test.myshipserv.com", $result[0]);

      $response['ok'] = true;
      $response['msg'] = 'Request has been sent';
      }
      }
      // throws exception
      else {
      $response['ok'] = false;
      $response['msg'] = 'Problem with parameters';

      throw new Myshipserv_Exception_MessagedException("Problem with parameters");
      }
      }
      $this->view->assign($response);
      $this->_helper->json((array)$response);
      }
     */

    /**
     * Controller for UAT to check the functionality that happens on cron
     * This particular one is sending email to unverified suppliers explaining
     * why they should join ShipServ
     *
     * @throws Exception
     * @author Elvir <eleonard@shipserv.com>
     */
    public function sendEmailReminderToUnverifiedSuppliersAction() {
        $remoteIp = Myshipserv_Config::getUserIp();

        $config = Zend_Registry::get('config');

        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
        	throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        $generator = new Myshipserv_UnverifiedSupplierReminder_Generator();
        $generator->debug = true;

        // beautify the output
        ob_start();
        $generator->generate();
        $output = ob_get_contents();
        ob_end_clean();

        echo str_replace("\n", "<br />", $output);
    }

    public function sendEmailReminderToPassiveBrandOwnerAction() {
        $remoteIp = Myshipserv_Config::getUserIp();

        $config = Zend_Registry::get('config');

        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
        	throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        $generator = new Myshipserv_PassiveBrandOwnerReminder_Generator();
        $generator->debug = true;

        // beautify the output
        ob_start();
        $generator->generate();
        $output = ob_get_contents();
        ob_end_clean();

        echo str_replace("\n", "<br />", $output);
    }

    /**
     * Handle the AJAX request to get the form to remind brand owner to approve
     * particular supplier to become their own authorised supplier
     *
     * @author Elvir <eleonard@shipserv.com>
     */
    public function inviteBrandOwnerFormAction() {

        // since this is an Ajax response, we need to use blank layout
        $this->_helper->layout->setLayout('empty');

        $db = $this->db;

        if (intval($this->_getParam('brandId')) > 0) {
            // get brandId from get parameter
            $brandId = $this->_getParam('brandId');

            //
            $brandAdapter = new Shipserv_Oracle_Brands($db);

            // fetch the brand if an id is specified
            $brand = $brandAdapter->fetchBrand($brandId);

            if ($brand !== null) {
                $this->view->brand = $brand;
            } else {
                throw new Exception("Brand not found");
            }

            // fetch email address of all administrator of all brands that this supplier is belong to
            $res = array();

            // prepare the adapter to fetch the users' detail
            $ucDom = new Myshipserv_UserCompany_Domain($db);

            //fetch list of companies that own brand
            $brandOwnerCompanyId = Shipserv_BrandAuthorisation::getBrandOwners($brandId);

            if (count($brandOwnerCompanyId) == 0) {
                $brandOwnerCompanyId = Shipserv_BrandAuthorisation::getPassiveBrandOwners($brandId, true);
            }

            // if one or more companies found
            if (count($brandOwnerCompanyId) > 0) {
                // each company, get the users' detail
                foreach ($brandOwnerCompanyId as $id) {
                    // get the user for this supplier branch type of company with its ID
                    $uColl = $ucDom->fetchUsersForCompany('SPB', $id);

                    // get the users detail
                    foreach ($uColl->getAdminUsers() as $u) {
                        $row = array(
                            'email' => $u->email,
                            'name' => $u->firstName . ' ' . $u->lastName,
                            'companyId' => $brandOwnerCompanyId
                        );
                        $users[] = $row;
                    }
                }
            } else {
                $this->view->supplier = null;
            }
        } else {
            $this->view->supplier = null;
        }
        $user = Shipserv_User::isLoggedIn();

        $this->view->loggedUser = $user;
        $this->view->users = $users;
        $this->view->brandname = $brand["NAME"];
        $this->view->brand = $brand;
        $this->view->brandId = $brandId;
        $this->view->companyId = implode(",", $brandOwnerCompanyId);
    }

    /**
     * Action to handle url that is get sent on the verify supplier email. This page will then update supplier's directory
     * listing date to the current time/date
     *
     * @see Myshipserv_NotificationManager_Email_InviteUnverifiedSupplier::getBody(); for more info
     * @author Elvir <eleonard@shipserv.com>
     */
    public function listingVerifiedAction() {

        $db = $this->db;

        if (intval($this->_getParam('tnid')) > 0) {
            $supplier = Shipserv_Supplier::fetch($this->_getParam('tnid'), $db);
            $supplier->updateDirectoryListingDate();

            //  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
            Myshipserv_AlertManager::removeCompanyActionFromCache($this->_getParam('tnid'), Myshipserv_AlertManager_Alert::ALERT_COMPANY_UNVERIFIED, $this->_getParam('tnid'));

            $config = Zend_Registry::get('options');

            $memcache = new Memcache;
            $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);

            $key = $config['memcache']['client']['keyPrefix'] . 'PROFILEFOR_' . $this->_getParam('tnid') .
                    $config['memcache']['client']['keySuffix'];


            $memcache->delete($key, 0);

            $memcache = new Memcache;
            $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);

            $key = $config['memcache']['client']['keyPrefix'] . 'PROFILETNID_' . $this->_getParam('tnid') .
                    $config['memcache']['client']['keySuffix'];

            $memcache->delete($key, 0);

            // send url to the view for timed redirection
            if ($this->_getParam('format') == 'json') {
                $this->_helper->json((array)true);
            } else {
                // send url to the view for timed redirection --- r=1 will instruct action to ignore cache
                $this->view->redirectTo = $supplier->getUrl() . "?r=1";
            }
        } else {
            $this->view->supplier = null;
        }
        $this->view->error = false;
        $user = Shipserv_User::isLoggedIn();

        $this->view->user = $user;
    }

    public function sendSearchFeedbackAction() {
       
        $config = $this->config;
        $db = $this->db;
        $remoteIp = Myshipserv_Config::getUserIp();
        $email = $config['shipserv']['notifications']['searchFeedback']['email'];
        $params = $this->_getAllParams();
        $message = "";

        $zm = new Zend_Mail('UTF-8');
        $zm->setFrom('support@shipserv.com', 'ShipServ Pages');
        $zm->setSubject('Search feedback on pages');
        $zm->addTo($email, 'ShipServ Pages');

        if (isset($params['importExisting']) && $params['importExisting'] == '1') {
            $adapter = new Shipserv_Oracle_Survey($db);
            $adapter->normaliseAllAnswerForSearchResultFeedback();
            //insertNormalisedAnswerForSearchResultFeedback($remoteIp, strtoupper($params["mood"]), $params['uri'], $message, $params["email"], $raw, $userId);
            die();
        }

        if ($params["mood"] == 'positive') {
            $raw = 'We just receved a positive feedback on the search result page.';
        } else {
            $raw = "We just received a negative feedback on search result page.\n\n";
            if (!empty($params["uri"]))
                $raw .= "URL: " . $params["uri"] . "\n";

            if (!empty($params["reason"])) {
                $raw .= "Reason: " . $params["reason"] . "\n";
                $message .= "Reason: " . $params["reason"] . "\n";
            }
            if (!empty($params["message"])) {
                $raw .= "Comments: " . $params["message"] . "\n";
                $message .= "Comments: " . $params["message"] . "\n";
            }

            if (!empty($params["name"]))
                $raw .= "Name: " . $params["name"] . "\n";
            if (!empty($params["email"]))
                $raw .= "Email: " . $params["email"] . "\n";
        }

        $raw .= "\n\n\n\nMORE INFO\n";

        $u = Shipserv_User::isLoggedIn();
        $raw .= "User information\n";
        $raw .= $u ? print_r($u, true) : "user not logged in";
        $raw .= "\n\nRequest information\n";
        $request = array();
        foreach ($_REQUEST as $k => $v) {
            $request[$k] = _htmlspecialchars($v);
        }
        $raw .= print_r($request, true);

        $userId = $u ? $u->userId : null;

        // store survey to the database (survey table)
        $survey = Shipserv_Survey::getInstanceById(Shipserv_Survey::QID_SEARCH_RESULT_FEEDBACK);
        //$result = $survey->storeAnswersSearchResultFeedback($raw);

        $adapter = new Shipserv_Oracle_Survey($db);
        $adapter->insertNormalisedAnswerForSearchResultFeedback($remoteIp, strtoupper($params["mood"]), $params['uri'], $message, $params["email"], $raw, $userId);

        $zm->setBodyText($raw);
        $zm->send();

        if ($params['negform-displayed'] == "Y") {
            $response = new Myshipserv_Response("200", "OK", array("done" => 1));
            $this->_helper->json((array)$response->toArray());
        }

        $this->_helper->json((array)[]);
    }

    public function sendEmailCampaignAction() {
        $this->_helper->layout->setLayout('empty');

        $remoteIp = Myshipserv_Config::getUserIp();
        $config = Zend_Registry::get('config');

        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
        	throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        $generator = new Myshipserv_EmailCampaign_Notification_Generator();
        $generator->debug = true;

        // beautify the output
        ob_start();
        $generator->generate(7);
        $output = ob_get_contents();
        ob_end_clean();

        echo str_replace("\n", "<br />", $output);
        die();
    }

    public function getScoreAction() {
        $params = $this->_getAllParams();
        $profileChecker = new Myshipserv_SupplierListing($params['tnid']);
        echo $profileChecker->getToDoAsHtmlBlock();
        echo print_r($profileChecker->getCompletedTaskForPartial(), true);
        $response = new Myshipserv_Response("200", "OK", array());
        die();
        $this->_helper->json((array)$response->toArray());
    }

    public function storeProfileCompletionScoreAction() {
        Myshipserv_SupplierListing::storeAllTnidToDb();
        die();
    }

    public function openquoteQuerybuilderAction() {
        $opb = new Shipserv_OpenquoteQuerybuilder();

        $this->view->operators = $opb->operator_array;
        $this->view->cols = $opb->column_array;
    }

    public function updateAccountManagerAction() {
        $results = Myshipserv_Salesforce_Supplier::updateProfileRecordsWithAccountOwner();
    }

    public function outputSupplierrfqAction() {
        $this->_helper->layout->setLayout('empty');

        $this->getResponse()->setHeader('Content-type', 'application/vnd.ms-excel', true);

        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $rfqs = array(7931790, 7930336);


        $this->view->rfqs = $rfqs;
        $this->view->db = $db;
    }


    public function getsfemailsAction() {
        $this->_helper->layout->setLayout('blank');
        //$this->getResponse()->setHeader('Content-type', 'application/vnd.ms-excel', true);
        $this->view->emails = Myshipserv_Salesforce_Email::getEmailOfAllAccountsFromSalesforce();
    }

    private static function getSsreport2Db() {
        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        return $resource->getDb('ssreport2');
    }

    public function sendQuoteToBuyerAction() {
        $this->_helper->layout->setLayout('empty');
        $db = $this->db;
        $params = $this->params;

        $nm = new Myshipserv_NotificationManager($db);
        $nm->stopSending();

        $htmlMode = ($this->_getParam('mode') == 'html');
        $sendMode = ($this->_getParam('send') == 1);

        if ($htmlMode) {
            $nm->returnAsHtml();
        }

        if ($sendMode) {
            $nm->startSending();
        }

        try {
            $result = $nm->sendMatchQuoteToBuyer($params['quoteId']);

            if ($htmlMode) {
                echo $result[0];
                die();
            } else {
                $response = new Myshipserv_Response("200", "OK");
                $this->_helper->json((array)$response);
            }

        } catch (Myshipserv_NotificationManager_Email_Exception $e) {
            // added by Yuriy Akopov on 2014-04-07
            if ($htmlMode) {
                echo implode(PHP_EOL, array(
                    "<h2>Match quote notification was not sent</h2>",
                    get_class($e) . ': ' . $e->getMessage()
                ));

                die();

            } else {
                $response = new Myshipserv_Response("200", "Match quote notification email skipped");
                $this->_helper->json((array)$response);
            }
        }
    }

    public function sendQuoteToBuyerPagesAction() {
        $this->_helper->layout->setLayout('empty');
        $db = $this->db;
        $params = $this->params;

        $nm = new Myshipserv_NotificationManager($db);

        $nm->stopSending();

        if (isset($_GET['mode']) && $_GET['mode'] == 'html')
            $nm->returnAsHtml();

        if (isset($_GET['send']) && $_GET['send'] == 1) {
            $nm->startSending();
        }

        $result = $nm->sendPagesQuoteToBuyer($params['quoteId']);

        if (isset($_GET['mode']) && $_GET['mode'] == 'html') {
            echo $result[0];
            die();
        } else {
            $response = new Myshipserv_Response("200", "OK");
            $this->_helper->json((array)$response);
        }
    }

    public function ppcViewMetricsAction() {
        $remoteIp = Myshipserv_Config::getUserIp();

        $config = Zend_Registry::get('config');
        $config = $this->config;

        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
        	throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('standbydb');
        //$db = $this->getInvokeArg('bootstrap')->getResource('db');
        //$db = $this->getInvokeArg('bootstrap')->getResource('db');
        $params = $this->_getAllParams();
        $totalDays = ( empty($params['days']) ) ? 30 : $params['days'];

        $sql = "
			SELECT
			  AD_UNIT_ID,
			  AD_UNIT_TNID,
			  CATEGORY_ID,
			  (SELECT NAME FROM PRODUCT_CATEGORY WHERE ID=CATEGORY_ID) CATEGORY_NAME,
			  AD_UNIT,

			  ppc_impression,
			  ppc_click,
			  ppc_ctr,
			  ppc_contact_view,
			  ppc_rfq_sent,

			  search_impression,
			  search_click,
			  search_ctr,

			  top_5_search_click,
		      top_5_search_click_detailed,
			  top_5_search_impression,
			  top_5_search_ctr,

			  ROUND(ppc_ctr / search_ctr * 100, 2) AS CANNIBALISATION_RATE,

			  rfq_sent,
			  contact_view

			FROM
			(
			  SELECT
			    a.PAD_ID AS AD_UNIT_ID,
			    a.PAD_TNID AS AD_UNIT_TNID,
			    a.PAD_CATEGORY_ID AS CATEGORY_ID,
			    a.PAD_title AS AD_UNIT,

			    ppc_impression.total    ppc_impression,
			    ppc_click.total         ppc_click,
			    ROUND(ppc_click.total / ppc_impression.total * 100, 2) AS PPC_CTR,
			    ppc_contact.total		ppc_contact_view,
			    ppc_rfq.total           ppc_rfq_sent,

			    search_impression.total search_impression,
			    search_click.total      search_click,
			    ROUND(search_click.total / search_impression.total * 100, 2) AS SEARCH_CTR,

			    top_5_search_impression.total top_5_search_impression,
			    top_5_search_click.total      top_5_search_click,
			    top_5_detailed_search_click.detailed top_5_search_click_detailed,
          		ROUND(top_5_search_click.total / top_5_search_impression.total * 100, 2) AS TOP_5_SEARCH_CTR,

			    rfq.total rfq_sent,
			    contact.total contact_view

			  FROM
			    PAGES_PPC_ADUNIT a
			    ,
			    -- INFORMATION ABOUT THE IMPRESSION
			    (
			      SELECT
			        PAI_PAD_ID ADUNIT_ID,
			        COUNT(PAI_PAD_ID) total
			      FROM
			        PAGES_PPC_ADUNIT JOIN PAGES_PPC_IMPRESSION
			          ON (PAI_PAD_ID=PAD_ID)
			      WHERE
			      	PAI_DATE_TIME between SYSDATE-:numOfDays and SYSDATE
			      GROUP BY
			        PAI_PAD_ID
			    ) ppc_impression
			    ,
			    -- INFORMATION ABOUT THE CLICK
			    (
			      SELECT
			        PAD_ID ADUNIT_ID,
			        COUNT( PAD_ID ) total
			      FROM
			        PAGES_PPC_ADUNIT,
			        PAGES_PPC_IMPRESSION JOIN PAGES_PPC_CLICK
			          ON (PAC_PAI_ID=PAI_ID)
			      WHERE
			        PAD_ID=PAI_PAD_ID
			        AND PAI_DATE_TIME between SYSDATE-:numOfDays and SYSDATE

			      GROUP BY
			        PAD_ID
			    ) ppc_click
			    ,
			    -- INFORMATION ABOUT RELATED SEARCH IMPRESSION
			    (
			      SELECT
			        a.PAD_ID ADUNIT_ID,
			        tmp.*
			      FROM
			        pages_ppc_adunit a
			        ,
			        (

						SELECT
						  PAD_CATEGORY_ID,
						  SUM(TOTAL) TOTAL
						FROM
						(
						  SELECT
						    *
						  FROM
						    (
						      SELECT
						        psp_pst_id,
						        COUNT(*) AS TOTAL
						      FROM
						        pages_supplier_position
						      WHERE
						        psp_pst_id IN(
						          SELECT pai_pst_id FROM pages_ppc_impression
						        )
			        			AND psp_created_date between SYSDATE-:numOfDays and SYSDATE
						      GROUP BY
						        psp_pst_id
						    ) TOTAL_PER_SEARCH
						    ,
						    (
						      SELECT
						        DISTINCT pad_category_id, pai_pst_id
						      FROM
						        PAGES_PPC_IMPRESSION
						          JOIN PAGES_PPC_ADUNIT
						            ON ( pad_id=pai_pad_id )
						    ) CATEGORY
						  WHERE
						    CATEGORY.PAI_PST_ID = TOTAL_PER_SEARCH.psp_pst_id
						)

						GROUP BY
						  PAD_CATEGORY_ID
			        ) tmp
			      WHERE
			        a.pad_category_id=tmp.pad_category_id
			    ) search_impression
			    ,
			    (
			      SELECT
			        a.PAD_ID ADUNIT_ID,
			        tmp.*
			      FROM
			        pages_ppc_adunit a
			        ,
			        (

						SELECT
						  PAD_CATEGORY_ID,
						  SUM(TOTAL) TOTAL
						FROM
						(
						  SELECT
						    *
						  FROM
						    (
						      SELECT
						        psp_pst_id,
						        COUNT(*) AS TOTAL
						      FROM
						        pages_supplier_position
						      WHERE
						        psp_pst_id IN(
						          SELECT pai_pst_id FROM pages_ppc_impression
						        )
			                    AND psp_position <= 15
			        			AND psp_created_date between SYSDATE-:numOfDays and SYSDATE
						      GROUP BY
						        psp_pst_id
						    ) TOTAL_PER_SEARCH
						    ,
						    (
						      SELECT
						        DISTINCT pad_category_id, pai_pst_id
						      FROM
						        PAGES_PPC_IMPRESSION
						          JOIN PAGES_PPC_ADUNIT
						            ON ( pad_id=pai_pad_id )
						    ) CATEGORY
						  WHERE
						    CATEGORY.PAI_PST_ID = TOTAL_PER_SEARCH.psp_pst_id
						)

						GROUP BY
						  PAD_CATEGORY_ID
			        ) tmp
			      WHERE
			        a.pad_category_id=tmp.pad_category_id

			    ) top_5_search_impression
			    ,
			    -- INFORMATION ABOUT RELATED SEARCH CLICK
			    (
			      select
			        PAD_ID ADUNIT_ID,
			        COUNT(*) total
			      from
			        pages_statistics_supplier
			          JOIN pages_ppc_impression
			            ON pss_pst_id = pai_pst_id
			          JOIN pages_ppc_adunit
			            ON pad_id=pai_pad_id
			      where
			        pss_view_date  between SYSDATE-:numOfDays and SYSDATE
			        and pss_browser <> 'crawler'
			        " . ( ($_SERVER['APPLICATION_ENV'] == 'production') ? "and PSS_VIEWER_IP_ADDRESS NOT IN (SELECT PSI_IP_ADDRESS FROM PAGES_STATISTICS_IP)" : "" ) . "
			      GROUP BY
			        pad_id

			    ) search_click
			    ,
			    (
			      SELECT
			        PAD_ID ADUNIT_ID,
			        COUNT(*) total
			      FROM
			        pages_statistics_supplier
			          JOIN pages_ppc_impression
			            ON pss_pst_id = pai_pst_id
			          JOIN pages_ppc_adunit
			            ON pad_id=pai_pad_id
			          JOIN pages_statistics ON (pss_pst_id = pst_id AND pss_browser <> 'crawler' AND pss_view_date  between SYSDATE-:numOfDays and SYSDATE)
	                  JOIN
	                    (
	                      SELECT * FROM pages_supplier_position
	                        WHERE
	                          psp_position <=5
	                    ) pages_supplier_position ON (psp_pst_id=pst_id and psp_spb_branch_code=PSS_SPB_BRANCH_CODE)
			      GROUP BY
			        pad_id
			    ) top_5_search_click
			    ,
			    (
		            SELECT
		              ADUNIT_ID,
		              RTRIM( xmlagg( xmlelement( c, lower( psp_position || '=' || total ) || ',' ) order by  lower( psp_position || '=' || total ) ).extract ( '//text()' ), ',' )
		                DETAILED

		            FROM
		            (
		              SELECT
		                PAD_ID ADUNIT_ID,
		                psp_position,
		                COUNT(*) total
		              FROM
		                pages_statistics_supplier
		                  JOIN pages_ppc_impression
		                    ON pss_pst_id = pai_pst_id
		                  JOIN pages_ppc_adunit
		                    ON pad_id=pai_pad_id
		                  JOIN pages_statistics ON (pss_pst_id = pst_id AND pss_browser <> 'crawler' AND pss_view_date  between SYSDATE-:numOfDays and SYSDATE)
		                      JOIN
		                        (
		                          SELECT * FROM pages_supplier_position
		                            WHERE
		                              psp_position <=5
		                        ) pages_supplier_position ON (psp_pst_id=pst_id and psp_spb_branch_code=PSS_SPB_BRANCH_CODE)
		              GROUP BY
		                pad_id, psp_position
		              ORDER BY
		                pad_id, psp_position
		            )
		            GROUP BY
						ADUNIT_ID
		        ) top_5_detailed_search_click
			    ,
			    (
					SELECT
					  PAD_ID ADUNIT_ID,
					  total
					FROM
					  PAGES_PPC_ADUNIT,
					  (
					    SELECT
					      pst_category_id category_id,
					      COUNT(*) total
					    FROM
					      pages_inquiry,
					      pages_statistics
					    WHERE
					      pin_creation_date  between SYSDATE-:numOfDays and SYSDATE
					      and pin_pst_id = pst_id
					      and pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
					      and pin_pst_id IN (SELECT DISTINCT pai_pst_id FROM pages_ppc_impression)

					    GROUP BY
					      pst_category_id
					  ) a
					WHERE
					  PAD_CATEGORY_ID=CATEGORY_ID
			    ) rfq
			    ,
			    (
					SELECT
					  PAD_ID ADUNIT_ID,
					  total
					FROM
					  PAGES_PPC_ADUNIT,
					  (
						SELECT
						  pst_category_id category_id,
						  COUNT(*) total
						FROM
						  pages_statistics_supplier,
						  pages_statistics
						WHERE
						  pss_view_date  between SYSDATE-:numOfDays and SYSDATE
						  and pss_browser <> 'crawler'
						  and pss_pst_id = pst_id
						  and pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
						  and pss_pst_id IN (SELECT DISTINCT pai_pst_id FROM pages_ppc_impression)
						  and PSS_IS_SIGNIN_DECLINED = 'N'
						  and pss_contact_details_viewed=1
						  and pss_usr_user_code IS NOT NULL
						GROUP BY
						  pst_category_id
  					  ) a
					WHERE
					  PAD_CATEGORY_ID=CATEGORY_ID
			    ) contact
			    ,
			    (
					SELECT
					  (SELECT pad_id FROM pages_ppc_adunit WHERE pad_tnid=visited_adunit.PAD_TNID)
					  AS ADUNIT_ID,
					  COUNT(*) AS TOTAL
					FROM
					  pages_statistics_supplier,
					  (select * from (SELECT PAI_PST_ID,
        PAD_ID,
        PAD_TNID
      FROM pages_ppc_impression
      JOIN pages_ppc_click
      ON pac_pai_id=pai_id
      JOIN pages_ppc_adunit
      ON pai_pad_id=pad_id
      WHERE pai_date_created BETWEEN (SYSDATE-:numOfDays) AND SYSDATE
      ) where rownum = 1) visited_adunit
					WHERE
					  pss_view_date  between SYSDATE-:numOfDays and SYSDATE
					  and pss_browser <> 'crawler'


					  and PSS_IS_SIGNIN_DECLINED = 'N'
					  and pss_contact_details_viewed=1
					  and pss_usr_user_code IS NOT NULL

					  and pss_pst_id=visited_adunit.pai_pst_id
					  and pss_spb_branch_code=visited_adunit.pad_tnid
					GROUP BY
					  visited_adunit.PAD_TNID
    			) ppc_contact
	          	,
	          	(
	             SELECT
	                PAD_ID AS ADUNIT_ID,
	                COUNT(*) AS TOTAL
	              FROM
	                pages_inquiry JOIN
	                (
	                  SELECT
	                    DISTINCT PAI_PST_ID,
	                    PAD_ID,
	                    PAD_TNID
	                  FROM
	                    pages_ppc_impression JOIN pages_ppc_click ON pac_pai_id=pai_id
	                    JOIN pages_ppc_adunit ON pai_pad_id=pad_id
	                  WHERE
	                    pai_date_created  between SYSDATE-:numOfDays and SYSDATE
	                ) visited_ad_unit
	                ON pai_pst_id=pin_pst_id
	              GROUP BY
	          		PAD_ID
	          	) ppc_rfq

			    WHERE
			        a.PAD_ID = ppc_impression.ADUNIT_ID (+)
			    AND a.PAD_ID = ppc_click.ADUNIT_ID (+)
			    AND a.PAD_ID = ppc_contact.ADUNIT_ID (+)
			    AND a.PAD_ID = ppc_rfq.ADUNIT_ID (+)

			    AND a.PAD_ID = search_click.ADUNIT_ID (+)
			    AND a.PAD_ID = search_impression.ADUNIT_ID (+)

			    AND a.PAD_ID = top_5_search_click.ADUNIT_ID (+)
	            AND a.PAD_ID = top_5_detailed_search_click.ADUNIT_ID (+)
           		AND a.PAD_ID = top_5_search_impression.ADUNIT_ID (+)

			    AND a.PAD_ID = rfq.ADUNIT_ID (+)
			    AND a.PAD_ID = contact.ADUNIT_ID (+)

			)
			ORDER BY CATEGORY_ID ASC
		";
        $ppcAndSearchData = $db->fetchAll($sql, array('numOfDays' => $totalDays));
        /*
          $sql = "
          SELECT
          (SELECT NAME FROM PRODUCT_CATEGORY WHERE ID=CATEGORY_ID) CATEGORY_NAME,
          a.*
          FROM
          (
          SELECT
          search_impression.category_id,
          search_impression.total search_impression,
          search_click.total search_click,
          rfq.total rfq_sent,
          contact.total contact_view,
          ROUND(search_click.total / search_impression.total * 100,2) AS SEARCH_CTR
          FROM
          -- INFORMATION ABOUT RELATED SEARCH IMPRESSION
          (
          SELECT
          pst_category_id category_id,
          COUNT(*) as TOTAL
          FROM
          pages_statistics,
          pages_supplier_position
          WHERE
          pst_id=psp_pst_id
          AND pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
          AND pst_search_date_time between SYSDATE-:numOfDays and SYSDATE
          GROUP BY
          pst_category_id
          ) search_impression
          ,
          -- INFORMATION ABOUT RELATED SEARCH CLICK
          (

          SELECT
          pst_category_id category_id,
          COUNT(*) total
          FROM
          pages_statistics_supplier,
          pages_statistics
          WHERE
          pss_view_date  between SYSDATE-:numOfDays and SYSDATE
          and pss_browser <> 'crawler'
          and pss_pst_id = pst_id
          and pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
          " . ( ($_SERVER['APPLICATION_ENV'] == 'production')?"and PSS_VIEWER_IP_ADDRESS NOT IN (SELECT PSI_IP_ADDRESS FROM PAGES_STATISTICS_IP)":"" ) . "
          GROUP BY
          pst_category_id
          ) search_click
          ,
          (
          SELECT
          pst_category_id category_id,
          COUNT(*) total
          FROM
          pages_inquiry,
          pages_statistics
          WHERE
          pin_creation_date  between SYSDATE-:numOfDays and SYSDATE
          and pin_pst_id = pst_id
          and pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
          GROUP BY
          pst_category_id
          ) rfq
          ,
          (
          SELECT
          pst_category_id category_id,
          COUNT(*) total
          FROM
          pages_statistics_supplier,
          pages_statistics
          WHERE
          pss_view_date  between SYSDATE-:numOfDays and SYSDATE
          and pss_browser <> 'crawler'
          and pss_pst_id = pst_id
          and pst_category_id IN (SELECT DISTINCT pad_category_id FROM pages_ppc_adunit)
          and PSS_IS_SIGNIN_DECLINED = 'N'
          and pss_contact_details_viewed=1
          and pss_usr_user_code IS NOT NULL
          GROUP BY
          pst_category_id
          ) contact
          WHERE
          search_impression.category_id = search_click.category_id
          AND rfq.category_id(+) = search_click.category_id
          AND contact.category_id(+) = search_click.category_id
          ) a
          ORDER BY CATEGORY_NAME ASC
          ";
          $searchData = $db->fetchAll($sql, array('numOfDays' => $totalDays));
         */
        $this->view->ppcAndSearchData = $ppcAndSearchData;
        //$this->view->searchData = $searchData;
        $this->view->config = $config;
        $this->view->days = $totalDays;
    }

    public function ppcTrackAction() {
        $params = $this->_getAllParams();
        $supplier = Shipserv_Supplier::fetch(51606);
        // check if hash ok
        if ($supplier->getAdUnitHash($params['i'], $params['sig'])) {
            $clickId = $supplier->registerClick($params['i']);
        }

        $this->redirect($params['u']);
    }

    /**
     * API to handle supplier related data. Caller's IP
     * address has to be authorised on SUPER IP list on conf file
     * @throws Exception
     */
    public function apiAction() {
        $params = $this->_getAllParams();
        $tnid = $params['tnid'];
        $remoteIp = Myshipserv_Config::getUserIp();
        $config = Zend_Registry::get('config');
        $config = $this->config;
        $remoteIp = Myshipserv_Config::getUserIp();
        if (!$this->isIpInternal($remoteIp) && !$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
            throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        if (empty($params['call'])) {
            throw new Exception("Please enter call (call type)", 404);
        }

        if (empty($tnid) || ctype_digit($tnid) === false) {
            throw new Exception("Please enter TNID", 404);
        }

        $supplier = Shipserv_Supplier::fetch($tnid);

        if ($params['call'] == 'rfq-statistic') {
            $response = new Myshipserv_Response(200, "OK", array('statistic' => $supplier->getEnquiriesStatistic()));
        } else if ($params['call'] == 'profile-complete-score') {
            $response = new Myshipserv_Response(200, "OK", array('score' => $supplier->getProfileCompletionScore()));
        } else if ($params['call'] == 'purge-memcache') {
            try
            {
            	$supplier->purgeMemcache();
            	$response = new Myshipserv_Response(200, "OK");
            }
            catch(Exception $e)
            {
            	$response = new Myshipserv_Response(500, "NOT OK", $e->getMessage());
            }
        }

        $this->_helper->json((array)$response->toArray());
    }

    public function danishToolsAction()
    {
        //shipserv.supplier.dt.tnid
        $this->view->tnid = $this->config['shipserv']['supplier']['dt']['tnid'];
    }

    public function pullVbpTransitionDateAction()
    {
        $remoteIp = Myshipserv_Config::getUserIp();

        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
            throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }

        // beautify the output
        ob_start();
        $app = new Myshipserv_Salesforce_Supplier();
        $result = $app->pullVBPTransitionDate();
        echo print_r($result, true);
        $output = ob_get_contents();
        ob_end_clean();

        echo str_replace("\n", "<br />", $output);
		die();
    }

    public function valueEventAction()
    {
    	if( $this->params['sf_account_id'] != "" )
    	{
	    	$application = new Myshipserv_Salesforce_Report_Billing_Supplier();

	    	$application->setPeriodStart($this->params['start_month'], $this->params['start_year']);
	    	$application->setPeriodEnd($this->params['end_month'], $this->params['end_year']);
	    	$application->setSFAccountId($this->params['sf_account_id']);

	    	$this->view->app = $application;
	    	$this->view->rateSet = $application->getRateSetFromSalesforce();
	    	$this->view->valueEvent = $application->getCurrentValueEventInSalesforce();
	    	$this->view->valueEventToBeUploaded = $application->getValueEventDataFromReportService();
	    	$this->view->supplier = Shipserv_Supplier::getInstanceById($application->sf['tnid'], '', true);
    	}
    }

    public function trackAction()
    {
    	// record click through
    	if( $this->params['w'] == "h" )
    	{
    		if( $this->params['id'] == "" || $this->params['id'] == null )
    		{
    			throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
    		}

    		// check hash ensuring authenticity impression
    		$hb = Shipserv_Zone_HighlightBanner::getInstanceById($this->params['id']);

    		$hb->logClick( $this->params['i'] );

    		$uri = new Myshipserv_View_Helper_Uri();
    		$url = $uri->deobfuscate($this->params['u']);
    		$this->_redirector = $this->_helper->getHelper('Redirector');


    		$this->_redirector->goToUrl($url);
    		return; // never reached since default is to goto and exit
    	}
    }

    /**
     * This will redirect browser to Pages's profile page
     * https://www.shipserv.com/supplier/tnid?id=51606
     */
    public function tnidAction()
    {
        // make sure id is supplied and valid
        if( $this->params['id'] == "" || ctype_digit($this->params['id']) == false ){
            throw new Myshipserv_Exception_MessagedException("Missing TNID.", 404);
        }

        // getting the supplier based on the db
    	$supplier = Shipserv_Supplier::getInstanceById($this->params['id'], '', true);

        // making sure the object is NOT empty
        if( $supplier->tnid == "" ){
            throw new Myshipserv_Exception_MessagedException("Supplier is not listed within ShipServ Pages or check your url.", 404);
        }

        // making sure the URL exists
        if( $supplier->getUrl() != "" && $supplier->directoryStatus == 'PUBLISHED' ){
            $this->resetSearchCache($supplier->name);
            $this->redirect($supplier->getUrl(), array('code' => 301, 'exit' => true));
        }else{
            throw new Myshipserv_Exception_MessagedException("Supplier is not listed within ShipServ Pages or check your url.", 404);
        }

    }

    public function gmvTrendAction(){

      $this->_helper->layout->setLayout('simple');
  		if($this->params['tnid'] != ""){

  			$report = new Shipserv_Report_Supplier_Statistic;
  			$supplier = Shipserv_Supplier::getInstanceById( $this->params['tnid'] );
  			$this->view->data = $report->getTrends($this->params['tnid']);
            $this->view->periodForTrends = $report->periodForTrends;
  			$this->view->supplier = $supplier;
  		}else{
  			throw new Myshipserv_Exception_MessagedException('No TNID specified');
  		}

    }

    public function erroneousReplyAction()
    {
        $this->_helper->layout->setLayout('simple');
        $supplierResponse = new Shipserv_Erroneous_SupplierResponse($this->params);
        $response = $supplierResponse->saveResponse();

        $this->_helper->viewRenderer($response['renderPage']);
        //$this->_helper->viewRenderer('erroneous/accept-order');
        
        $this->view->response = $response;

    }

    /**
     * UI responsible for declining RFQ or ORD
     * @author: Elvir
     *
     * @refactored and fixed by Yuriy Akopov on 2015-12-10
     */
    public function declineAction() {
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        $this->_helper->layout->setLayout('simple');
        $this->view->docType = $this->params['doc'];

        // check authentication cache // @todo: Is this really secure enough?! (Yuriy)
        switch ($this->params['doc']) {
            case 'rfq':
                $hashIdParam = 'rfqInternalRefNo';
                break;

            case 'ord':
                $hashIdParam = 'ordInternalRefNo';
                break;

            default:
                throw new Myshipserv_Exception_MessagedException("Unknown document type", 200);
        }

        // check the hash unless in development environment
        if (Myshipserv_Config::getEnv() !== Myshipserv_Config::ENV_DEV) {
            if (strtolower($this->params['h']) !== md5($hashIdParam . '=' . $this->params['id'] . '&spbBranchCode=' . $this->params['s'])) {
                throw new Myshipserv_Exception_MessagedException ("You are not authorised to access this page", 401);
            }
        }

        // authentication passed, instantiate the document
        try {
            switch ($this->params['doc']) {
                case 'rfq':
                    $rfq = Shipserv_Rfq::getInstanceById($this->params['id']);
                    if (!is_object($rfq) or (strlen($rfq->rfqInternalRefNo) === 0)) {
                        throw new Exception("RFQ " . $this->params['id'] . " cannot be found");
                    }

                    // if we are here, a valid RFQ has been found
                    // added by Yuriy Akopov on 2015-12-03, DE6134
                    if ($rfq->rfqBybBranchCode == Myshipserv_Config::getProxyMatchBuyer()) {
                        $sourceRfq = $rfq->resolveMatchForward();
                        $buyer = $sourceRfq->getBuyerBranch();
                    } else {
                        $buyer = $rfq->getBuyerBranch();
                    }
                    // changes by Yuriy Akopov end

                    $rfqSupplierIds = $rfq->getDirectSupplierIds();
                    if (!in_array($this->params['s'], $rfqSupplierIds)) {
                        throw new Myshipserv_Exception_MessagedException ("Invalid transaction parameters supplied", 200);
                    }

                    $supplier = Shipserv_Supplier::getInstanceById($this->params['s'], '', true);

                    $docLabel    = "RFQ";
                    $docRefNo    = $rfq->rfqRefNo;
                    $docUrl      = $rfq->getUrl('supplier', $supplier->tnid);
                    $docSubject  = $rfq->rfqSubject;
                    $docPort     = $rfq->rfqDeliveryPort;
                    $docDeliveryDate = $rfq->rfqDateTime;
                    $docDate     = $rfq->rfqCreatedDate;
                    $docVessel   = $rfq->rfqVesselName;
                    $docDeclineUrl = $rfq->getDeclineUrl($supplier->tnid);

                    $doc = $rfq;
                    break;

                case 'ord':
                    $ord = Shipserv_PurchaseOrder::getInstanceById($this->params['id']);
                    if (!is_object($ord) or (strlen($ord->ordInternalRefNo) === 0)) {
                        throw new Exception("Order " . $this->params['id'] . " cannot be found");
                    }

                    // if we are here, a valid order has been found
                    $buyer      = $ord->getBuyerBranch();
                    $supplier   = $ord->getSupplier();

                    $docLabel   = "Purchase Order";
                    $docRefNo   = $ord->ordRefNo;
                    $docUrl     = $ord->getUrl();
                    $docSubject = $ord->ordSubject;
                    $docPort    = $ord->ordDeliveryPort;
                    $docDeliveryDate = $ord->ordDateTime;
                    $docDate    = $ord->ordCreatedDate;
                    $docVessel  = $ord->ordVesselName;
                    $docDeclineUrl = $ord->getDeclineUrl();

                    $doc = $ord;
                    break;

                default:
                    // should not really happen as we have checked the type above already
                    throw new Exception("Unknown document type");
            }
        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException("Your Purchase Order or RFQ cannot be found", 200);
        }

        $this->view->docType     = $this->params['doc'];

        $this->view->buyer       = $buyer;
        $this->view->supplier    = $supplier;

        $this->view->docLabel    = $docLabel;
        $this->view->docRefNo    = $docRefNo;
        $this->view->docUrl      = $docUrl;
        $this->view->docSubject  = $docSubject;
        $this->view->docPort     = $docPort;

        $normaliseDate = function ($dateStr) {
            if (strlen($dateStr) === 0) {
                return null;
            }

            try {
                $dateTime = new DateTime($dateStr);
                return $dateTime->format('d M Y');
            } catch (Exception $e) {
                return $dateStr;
            }
        };

        $this->view->docDeliveryDate = $normaliseDate($docDeliveryDate);
        $this->view->docDate     	 = $normaliseDate($docDate);

        $this->view->docVessel   = $docVessel;
        $this->view->docDeclineUrl = $docDeclineUrl;

        $this->view->isProduction = (Myshipserv_Config::getEnv() === Myshipserv_Config::ENV_LIVE);

        $portAdapter = new Shipserv_Oracle_Ports(Shipserv_Helper_Database::getDb());
        $this->view->docPortLabel = $portAdapter->getPortLabelByCode($docPort);

        // when a form is submitted and user is declining a document
        if (
            (
                (($this->params['doc'] === 'rfq') and (count($this->params['answers']) > 0) ) or
                ($this->params['doc'] === 'ord')
            )
            and (strlen($this->params['a']) > 0)
        ) {

            // check if decline request is valid
            if (md5($this->params['h']) !== $this->params['a']) {
                throw new Myshipserv_Exception_MessagedException("You are not authorised to access this page", 401);
            }

            // only process reason for RFQ; for orders, we don't allow reasons
            if ($this->params['doc'] === 'rfq') {
                $reason = implode("\n", $this->params['answers']);
                $reason .= $this->params['otherDeclineReasonAttractive'];
                $reason .= $this->params['otherDeclineReasonUnattractive'];
            } else {
                $reason = "";
            }

            if ($doc instanceof Shipserv_Rfq) {
                $this->view->success = $doc->decline($reason, $this->params['s']);
            } else if ($doc instanceof Shipserv_PurchaseOrder) {
                $this->view->success = $doc->decline($reason);
            }
        }

        if ($this->params['doc'] === 'ord') {
            // if document is already been declined before, we need to show this information
            // to the user
            $this->view->declinedInformation = $ord->getDeclineInformation();

        } else if ($this->params['doc'] === 'rfq') {
            $attractiveSurvey = Shipserv_Survey::getInstanceById(Shipserv_Survey::SID_ATTRACTIVE_RFQ);
            $unattractiveSurvey = Shipserv_Survey::getInstanceById(Shipserv_Survey::SID_UNATTRACTIVE_RFQ);

            $this->view->documentIsAttractive = $attractiveSurvey->getQuestions();
            $this->view->documentIsUnattractive = $unattractiveSurvey->getQuestions();
        }
    }

    /**
    * We have to reset the search cookie / memcache before page redirected
    */
    protected function resetSearchCache( $supplierName )
    {
        $memcache = new Memcache;
        $searchRecId = $this->getHelper('Cookie')->fetchCookie('search');
        $memcache->connect($this->config['memcache']['server']['host'], $this->config['memcache']['server']['port']);
        $key = session_id()  . "-" . $searchRecId;
        $data = new stdClass();
        $data->searchValues = array(
                'searchWhat' => $supplierName
            );
        $data->searchText = null;
        $data->supplierCount = null;
        $data->currentZoneIdent = null;
        $memcache->set($key, serialize($data));
        
    }

}
