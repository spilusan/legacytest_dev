<?php

/**
 * Manages Pages top/leftnavigation tabs
 *
 */
class Myshipserv_View_Helper_PagesNav extends Zend_View_Helper_Abstract
{
    
    const NAVIGATION_INI_PATH = '/configs/navigation-main.ini';
    const MAX_SHIPMATE_MENU_COUNT = 14;

    protected $user;

	/**
	 * Return true if $tabIdstr is currently selected (check is done through current Zend module and other conditions)
	 * 
	 * @param String $tabIdstr
	 * @return Bool
	 */
	public static function isFirstLevTabSelected($tabIdstr)
	{
	    // check if it is specified for the view explicitly

        $selectedTab = null;

        $request = Zend_Controller_Front::getInstance()->getRequest();
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();


	    $view = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('view'); //Zend_View_Abstract $view
	    if (strlen($view->selectedNav)) {
	        return ($tabIdstr === $view->selectedNav);
	    }
	    
	    //We are forcing tab supplied in bootstrap parameter
	    $bootstrapTabInfo = $request->getParam('forceTab', null);
	    if ($bootstrapTabInfo) {
	        return ($bootstrapTabInfo === $tabIdstr);
	    }

	    // Determine form the current module / controller
	    switch($moduleName) {
	        case 'reports':
	            $selectedTab = 'analyse';
	            break;
	    
	        case 'search':
	            $selectedTab = 'search';
	            break;
	    
	        case 'trade':
	            $selectedTab = 'trade';
	            break;
	    
	        case 'essm':
	            $selectedTab = 'analyse';
	            break;
	    
	        default:
	            // failed to determine from the module, check the controller
	            switch ($controllerName) {
	                case 'shipmate':
	                    $selectedTab = 'shipmate';
	                    break;
	    
	                case 'profile':
	                    $selectedTab = 'profile';
	                    break;
	    
	                case 'supplier':
	                    $selectedTab = 'search';
	                    break;
	    
	                case 'enquiry':
	                    $selectedTab = 'search';
	                    break;
	    
	                case 'trade':
	                    $selectedTab = 'trade';
	                    break;
	    
	                case 'buyer':
	                    $selectedTab = 'buyer';
	                    break;
	    
	                default:
	                    $selectedTab = '';
	            }
	    }

        return ($selectedTab === $tabIdstr);
	}

    /**
     * Getting the navigation configuration array
     *
     * @return array|string|Zend_Config_Ini
     */
	public static function getNavigationConfig()
    {
        //Get navigation from file main-navigation.ini, using cache
        $memcache = Shipserv_Memcache::getMemcache();
        $memcacheKey = 'main-navigation.ini';
        if (!Myshipserv_Config::isInDevelopment() && $memcache && $memcache->get($memcacheKey)) {
            $navigationConfigs = $memcache->get($memcacheKey);
        } else {
            $navigationConfigs = new Zend_Config_Ini(APPLICATION_PATH . self::NAVIGATION_INI_PATH);
            $memcache && $memcache->set($memcacheKey, null, 0, 24*3600);
        }
        $navigationConfigs = $navigationConfigs->get('tabs')->toArray();

        return $navigationConfigs;
    }

	/**
	 * Returns top level navigation tabs to display on the current page
	 *
	 * @param   Shipserv_user|null $user   no runtime type because it might also be false
	 * @param   Zend_Session_Namespace $currentCompany  this (crappy) object contains has the following props: id, type ('v' or 'b'), company (a Shipserv_Buyer or a Shipserv_Supplier). @see Shipserv_User::initialiseUserCompany()     
	 *
	 * @return Array 
	 */
    public function pagesNav($user = null, $currentCompany = null)
    {
        $this->user = $user;

        $isLoggedIn = ($user instanceof Shipserv_User);
        $isShipservUser = ($isLoggedIn && $user->isShipservUser());
        $isSupplier = ($currentCompany && $currentCompany->type === 'v');
        $isBuyer = ($currentCompany && $currentCompany->type === 'b');
        $isConsortia = ($currentCompany && $currentCompany->type === 'c');

        $request = Zend_Controller_Front::getInstance()->getRequest();
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();

        $navigationConfigs = self::getNavigationConfig();

        // if we are calling menu-service the we have to retrieve the selected menu by the referrer
        if ($moduleName === 'apiservices' && $controllerName === 'menu-service') {
            self::_setSelectedRecursive($navigationConfigs);
        }

        //Build navigation array to be returned
        $navigation = array();
        
        //Search to always be present
        $navigation['search'] = $navigationConfigs['search'];
        
        //Trade or Buyer+analyze depending on whether current user is a supplier or a buyer
        if ($isSupplier && ($isShipservUser || ($isLoggedIn && $user->isPartOfSupplier($currentCompany->id)))) {
            $navigation['trade'] = $navigationConfigs['trade'];
            $navigation['analyse-supplier'] = self::_getAnalyseSupplierSubNav($user, $navigationConfigs['analyse-supplier'], $currentCompany->id);
        } elseif ($isBuyer && ($isShipservUser || ($isLoggedIn && $user->isPartOfBuyer($currentCompany->id)))) {
            if ($user->canAccessFeature($user::BRANCH_FILTER_BUY)) {
                $navigation['buyer'] = $navigationConfigs['buyer'];
            }
            $navigation['analyse'] = self::_getAnalyseSubNav($user, $navigationConfigs['analyse']);
           
        } elseif ($isConsortia) {
            $navigation['analyse-consortia']  = $navigationConfigs['analyse-consortia'];
        }
        
        //Account (profile)
        if ($isLoggedIn) {
            if ($isConsortia) {
                $navigation['profile'] = $navigationConfigs['profile-consortia'];
            } else {
                $navigation['profile'] = $navigationConfigs['profile'];
                $navigation = self::_getProfileSubNav($user, $currentCompany, $navigation);
            }
        }
        
        //ShipMate
        if ($isShipservUser) {
            $navigation['shipmate'] = $navigationConfigs['shipmate'];
            $navigation = self::_getShipmateSubNav($user, $navigation, $isSupplier);
        }

        if (isset($navigation['buyer'])) {
            $navigation = self::_getBuySubNav($isShipservUser, $navigation);
        }

        if (isset($navigation['trade'])) {
            $navigation = self::_getTradeSubNav($navigation);
        }

        if (!($moduleName === 'apiservices' && $controllerName === 'menu-service')) {
            $navigation = self::_setSelected($navigation);
        }

    	return $navigation;
    }

    /**
     * Set user rights for buy tab
     * 
     * @param bool $isShipservUser
     * @param array $navigation
     * @return array
     */
    private static function _getBuySubNav($isShipservUser, array $navigation)
    {
        $config = Zend_Registry::get('config');
        $invoiceEnabled = (int)$config->shipserv->menu->invoicing;

        if (!$isShipservUser) {
            unset($navigation['buyer']['children']['buy-quotes']);
        }

        if ($invoiceEnabled !== 1) {
            unset($navigation['buyer']['children']['invoices']);
        }
    
        return $navigation;
        
    }

     /**
      * Set user rights for sell tab
      * 
      * @param array $navigation The navigaton array 
      *
      * @return array
     */
    private static function _getTradeSubNav(array $navigation)
    {
        $config = Zend_Registry::get('config');
        $invoiceEnabled = (int)$config->shipserv->menu->invoicing;



        if ($invoiceEnabled !== 1) {
            unset($navigation['trade']['children']['invoices']);
        }
    
        return $navigation;
        
    }
    
    /**
     * Private method to help self::pagesNav() get the buyer analyse navigation
     *
     * @param Shipserv_User $user
     * @param Array  $navigation
     *
     * @return Array  $navigation
     */
    private static function _getAnalyseSubNav($user, array $navigation)
    {

    	$config = Zend_Registry::get('config');
    	$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
    	$showTrendGraph = true;
    	if ($user && $user->isShipservUser() == false) {
   			if (false === in_array($activeCompany->id, explode(",", $config->shipserv->buyerSpendTrendGraph->participant->buyerId))) {
   				$showTrendGraph = false;
   			}
   		}
    	
        $allowedReports = array();
        
        if (Shipserv_Oracle_Consortia_BuyerSupplier::getSupplierCountForConsortiaBuyer($activeCompany->id) > 0) {
            array_push($allowedReports, 'consortium-transactions');
        }
    	
    	if ($user && $user->canAccessMatchBenchmark()) {
    		//TODO beta access may be removed when we go live with this!!!!!
    		if ($user->canAccessMatchBenchmarkBeta()) {
    			array_push($allowedReports, 'supplier-recommendations');
    		}

    		array_push($allowedReports, 'sourcing-engine-dashboard');
    		array_push($allowedReports, 'sourcing-engine-transactions');
    	}
    	
    	if ($user && $user->canAccessSprKpi()) {
    		array_push($allowedReports, 'supplier-performance-report');
    	}
    	
    	if ($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON)) {
    		array_push($allowedReports, 'transaction-monitor');
    	}

        if ($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON)) {
            array_push($allowedReports, 'transaction-monitor');
        }

        if ($user && $user->canAccessTransactionReport()) {
            array_push($allowedReports, 'transaction-report');
        }

    	if ($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER)) {
    		array_push($allowedReports, 'webreporter');
    	} else {
    		if (!$user) {
    			array_push($allowedReports, 'webreporter');
    		}
    	}
    	
    	if ($user && $user->canAccessPriceBenchmark()) {
    		array_push($allowedReports, 'impa-price-benchmark');
    		array_push($allowedReports, 'impa-spend-tracker');
    	}
    	
    	if ($user && $showTrendGraph === true) {
    		array_push($allowedReports, 'total-spend');
    	}
    	
    	//Invert applied selectins
    	foreach (array_keys($navigation['children']) as $key) {
    		if (!in_array($key, $allowedReports)) {
    			unset($navigation['children'][$key]);
    		}
    	}
	
    	return $navigation;
    	
    }
    
    /**
     * Private method to help self::pagesNav() get the supplier analyse navigation
     *
     * @param Shipserv_User $user
     * @param Array  $navigation
     * @param Array  $currentCompanyId
     * @return Array  $navigation
     */
    private static function _getAnalyseSupplierSubNav($user, array $navigation, $currentCompanyId)
    {

    	if ($user) {
    		if (!($user->canAccessAdNetworkPublisher($currentCompanyId))) {
	     		unset($navigation['children']['ad-network-publisher']);
	     	}
	
	     	if (!($user->canAccessKpiTrendReport())) {
	     		unset($navigation['children']['kpi-trend-report']);
	     	}
    	}

    	if ($user && $user->isShipservUser() && !$user->canPerform('PSG_ACCESS_SIR')) {
            unset($navigation['children']['sales-funnel']);
            unset($navigation['children']['billable-transactions']);
            unset($navigation['children']['detailed-breakdown']);
            unset($navigation['children']['customers']);
        }

    	// DEV-1610 Dynamically add tnid to the parameter was set in supplier profile
        $replacement = (Zend_Registry::isRegistered('sirNavigateTnid')) ? '?tnid=' . Zend_Registry::get('sirNavigateTnid') : '';
        foreach (array_keys($navigation['children']) as $key) {
            $navigation['children'][$key]['url'] = str_replace('{tnid}', $replacement, $navigation['children'][$key]['url']);
    	}

    	return $navigation;
    }
    
    
    /**
     * Private method to help self::pagesNav() get the shipmate sub navigation
     *
     * @param Shipserv_USer  $user
     * @param Array  $navigation
     * @param bool $isSupplier
     *
     * @return Array  $navigation
     */
    private static function _getShipmateSubNav($user, array $navigation, $isSupplier = null)
    {

        self::_addCustomPages($navigation);
        	
        if (count($navigation['shipmate']['children']) >= self::MAX_SHIPMATE_MENU_COUNT) {
            throw new Myshipserv_Exception_MessagedException("No more then " . self::MAX_SHIPMATE_MENU_COUNT . " ShipMate root menu item can be implemented, as it will not fit on a 13' monitor!");
        }

        	//User rights for the second level menu options
        if (!($user && $user->canPerform('PSG_ACCESS_SCORECARD'))) {
            unset($navigation['shipmate']['children']['scorecard']);
        }
        	
        if (!($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER))) {
            unset($navigation['shipmate']['children']['webreporter']);
        }

        if (!($user && ($user->canPerform('PSG_PAGES_ADMIN') || $user->canPerform('PSG_PAGES_ADMIN_ADV')))) {
            unset($navigation['shipmate']['children']['pagesadmin']);
        }

        if (!($user && $user->canPerform('PSG_ADMIN_GATEWAY'))) {
            unset($navigation['shipmate']['children']['gateway']);
        }

        if (!($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON))) {
            unset($navigation['shipmate']['children']['txn-monitor']);
        }

        if (!($user && $user->canPerform('PSG_MANAGE_USER_AND_COMPANY'))) {
            unset($navigation['shipmate']['children']['user-company']['children']['manage-user']);
        }

        if (!($user && $user->canPerform('PSG_ACCESS_MANAGE_GROUP'))) {
            unset($navigation['shipmate']['children']['user-company']['children']['manage-group']);
        }

        if (count($navigation['shipmate']['children']['user-company']['children']) === 0) {
            unset($navigation['shipmate']['children']['user-company']);
        }

        if (!($user && $user->canPerform('PSG_ACCESS_MANAGE_PUBLISHER'))) {
            unset($navigation['shipmate']['children']['ad-network-publisher']);
        }

        if (!($user && $user->canPerform('PSG_ACCESS_BC_ADMIN'))) {
            unset($navigation['shipmate']['children']['buyer-connect-admin']);
        } else {
            // SPC-2531 Make this URL dynamic
            $config  = Zend_Registry::get('config');
            $navigation['shipmate']['children']['buyer-connect-admin']['url'] = $config->shipserv->bcadmin->menu->url;
        }

        self::_filterReportMenus($user, $navigation);
        self::_filterMatchMenus($user, $navigation);
        self::_filterFinanceMenus($user, $navigation);
        self::_filterDashboardMenus($user, $navigation, $isSupplier);

        //unset empty menus
        if (count($navigation['shipmate']['children']['gmv-report']['children']) === 0) {
            unset($navigation['shipmate']['children']['gmv-report']);
        }

        if (count($navigation['shipmate']['children']['match']['children']) === 0) {
            unset($navigation['shipmate']['children']['match']);
        }

        if (count($navigation['shipmate']['children']['finance']['children']) === 0) {
            unset($navigation['shipmate']['children']['finance']);
        }

        if (count($navigation['shipmate']['children']['dashboards']['children']) === 0) {
            unset($navigation['shipmate']['children']['dashboards']);
        }

        return $navigation;
    }
    
    
    /**
     * Adding custom pages to the navigation
     * @param array $navigation
     */
    private static function _addCustomPages(array &$navigation)
    {
    	//Generate custom pages, General news temporarliy removed
    	$config = Zend_Registry::get('options');
    	
    	$pages = array();
    	foreach ($config['shipserv']['shipmate'] as $x) {
    		foreach ($x as $p) {
    			$tmp = explode(",", $p);
    			$pages[$tmp[0]] = "/help/proxy?u=" . self::_strToHex($tmp[1]);
    		}
    	}
    	
    	if (is_array($pages)) {
    		foreach ($pages as $name => $url) {
    			if ($name !== 'General news') {
    				array_unshift(
    					$navigation['shipmate']['children'],
    					array(
    						'title' => $name,
    						'url' => '/shipmate?custompage=' . urlencode($url)
    					)
    				);
    			}
    		}
    	}
    }
    
    
    /**
     * Filter out the menu options, where the user has no permission to see in shipmate / reports
     * @param object $user
     * @param array $navigation
     */
    private static function _filterReportMenus($user, array &$navigation)
    {
    	//Apply rules of reports
    	$allowedGmvReports = array();
    	if ($user && ($user->canPerform('PSG_GMV_SUPPLIER') || $user->canPerform('PSG_CONVERSION_SUPPLIER') || $user->canPerform('PSG_GMV_BUYER'))) {
    		if ($user->canPerform('PSG_GMV_SUPPLIER')) {
    			array_push($allowedGmvReports, 'gmv');
    		} else if ($user->canPerform('PSG_GMV_BUYER')) {
    			array_push($allowedGmvReports, 'buyer-gmv-report');
    		}
    		
    		if ($user->canPerform('PSG_GMV_SUPPLIER')) {
    			array_push($allowedGmvReports, 'supplier');
    		}
    		
    		if ($user->canPerform('PSG_GMV_SUPPLIER')) {
    			array_push($allowedGmvReports, 'supplier-stats');
    		}
    		
    		if ($user->canPerform('PSG_CONVERSION_SUPPLIER')) {
    			array_push($allowedGmvReports, 'supplier-conversion');
    		}
    		
    		if ($user->canPerform('PSG_GMV_BUYER')) {
    			array_push($allowedGmvReports, 'buyer');
    		}
    		
    		if ($user->canPerform('PSG_MARKET_SIZING')) {
    		    array_push($allowedGmvReports, 'market-sizing-tool');
    		}
    		
    	}
    	
    	//Invert applied selectins
    	foreach (array_keys($navigation['shipmate']['children']['gmv-report']['children']) as $key) {
    		if (!in_array($key, $allowedGmvReports)) {
    			unset($navigation['shipmate']['children']['gmv-report']['children'][$key]);
    		}
    	}
    }
    
    /**
     * Filter out the menu options, where the user has no permission to see in shipmate / match
     * @param object $user
     * @param array $navigation
     */
    private static function _filterMatchMenus($user, array &$navigation)
    {
    	//Applying Match report rules
    	$allowedMatchReports = array();
    	if ($user && $user->canPerform('PSG_ACCESS_MATCH')) {
    		array_push($allowedMatchReports, 'usage');
    		array_push($allowedMatchReports, 'efficiency');
    		array_push($allowedMatchReports, 'adoption');
    		array_push($allowedMatchReports, 'conversion');
    	}
    	
    	if ($user && $user->canPerform('PSG_ACCESS_MATCH_INBOX')) {
    		array_push($allowedMatchReports, 'inbox');
    		array_push($allowedMatchReports, 'sent-box');
    		array_push($allowedMatchReports, 'algo');
    		array_push($allowedMatchReports, 'stopwords');
    		array_push($allowedMatchReports, 'automatch');
    	}
    	
    	array_push($allowedMatchReports, 'target-segments');
    	array_push($allowedMatchReports, 'rate-synch');
    	
    	//Invert applied selectins
    	foreach (array_keys($navigation['shipmate']['children']['match']['children']) as $key) {
    		if (!in_array($key, $allowedMatchReports)) {
    			unset($navigation['shipmate']['children']['match']['children'][$key]);
    		}
    	}
    }
    
    
    /**
     * Filter out the menu options, where the user has no permission to see in shipmate / finance
     * @param object $user
     * @param array $navigation
     */
    private static function _filterFinanceMenus($user, array &$navigation)
    {
        $allowedFinanceMenus = array();
        if ($user && $user->canPerform('PSG_ACCESS_SALESFORCE')) {
            array_push($allowedFinanceMenus, 'value-event');
            array_push($allowedFinanceMenus, 'health');
            array_push($allowedFinanceMenus, 'invalid-txn-tools');
            array_push($allowedFinanceMenus, 'billing');
            array_push($allowedFinanceMenus, 'po-rate');
            array_push($allowedFinanceMenus, 'erroneous-transactions');
        }

        if (($user && $user->canPerform('PSG_ACCESS_PO_PACK'))) {
            array_push($allowedFinanceMenus, 'po-pack');
        }
        
        //Invert applied selections
        foreach (array_keys($navigation['shipmate']['children']['finance']['children']) as $key) {
            if (!in_array($key, $allowedFinanceMenus)) {
                unset($navigation['shipmate']['children']['finance']['children'][$key]);
            }
        }
    }

    /**
     * Remove menus where the user has no right to access
     * from ShipMage dashboard tab
     *
     * @param object $user
     * @param array $navigation
     * @param bool $isSupplier
     */
    private static function _filterDashboardMenus($user, array &$navigation, $isSupplier)
    {
        $dashboardAccess = false;

        if ($user && $user->canPerform('PSG_ACCESS_DASHBOARD')) {
            $dashboardAccess = true;
        }

        foreach (array_keys($navigation['shipmate']['children']['dashboards']['children']) as $key) {
            if ($dashboardAccess === false) {
                if ($key === 'supplier-insight-report' || $key === 'supplier-insight-report-pct') {
                    if (!($user && $isSupplier && $user->canPerform('PSG_ACCESS_SIR'))) {
                        unset($navigation['shipmate']['children']['dashboards']['children'][$key]);
                    }
                } else {
                    unset($navigation['shipmate']['children']['dashboards']['children'][$key]);
                }
            } else {
                if (($key === 'supplier-insight-report' || $key === 'supplier-insight-report-pct') && !($user && $isSupplier && $user->canPerform('PSG_ACCESS_SIR'))) {
                    unset($navigation['shipmate']['children']['dashboards']['children'][$key]);
                }
            }
        }
    }
    
    /**
     * Private method to help self::pagesNav() get the profile sub navigation
     *  
     * @param Shipserv_user $user
     * @param Zend_Session_Namespace $currentCompany  @see self::pagesNav()
     * @param Array  $navigation
     * 
     * @return Array  $navigation
     */
    private static function _getProfileSubNav($user, $currentCompany, array $navigation)
    {
        //Local vars 
        $isAdminOfCompany = $user->isAdminOf($currentCompany->id);
        $isShipservUser = $user->isShipservUser();
        
        
        //ACCOUNT
        $navigation['profile']['children']['account']['title'] = (trim($user->getDisplayName()) === '') ? 'User Account' : $user->getDisplayName();;
        
        //Pendinf review requests
        $pendingReviewRequestsCount = Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countPendingReviewActions();   //@TODO cache it!
        if ($pendingReviewRequestsCount > 0) {
            $navigation['profile']['children']['account']['children']['reviews-requests']['title'] .= " ($pendingReviewRequestsCount)";
        } else {
            unset($navigation['profile']['children']['account']['children']['reviews-requests']);
        }
        
        //Managed categories
        $managedCategories = Shipserv_CategoryAuthorisation::getManagedCategories($user->userId);
        if (count($managedCategories) > 0) {
            $pendingCategories = Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countPendingCategoriesActions();
            $pendingCategoriesTitleConcat = ($pendingCategories? " ($pendingCategories)" : ''); 
            $navigation['profile']['children']['account']['children']['categories']['title'] .= " ($pendingCategoriesTitleConcat)";
        } else {
            unset($navigation['profile']['children']['account']['children']['categories']);
        }       
        
        
        //COMPANY
        if (!$currentCompany->id) {
            unset($navigation['profile']['children']['company']);
            return $navigation; 
        }
        $navigation['profile']['children']['company']['title'] = $currentCompany->company->name;

        //adjust urls
        foreach (array_keys($navigation['profile']['children']['company']['children']) as $tabIdstr) {
            if (isset($navigation['profile']['children']['company']['children'][$tabIdstr]['company-params-concat'])) {
                $navigation['profile']['children']['company']['children'][$tabIdstr]['url'] .= '/type/' . $currentCompany->type . '/id/' . $currentCompany->id;
                unset($navigation['profile']['children']['company']['children'][$tabIdstr]['company-params-concat']);
            }
        }

        //Company people
        $uaTypeMap = array(
        		'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
        		'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
                'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON

        );

        $companyUsers = Zend_Controller_Action_HelperBroker::getStaticHelper('companyUser')->fetchUsersByType($uaTypeMap[$currentCompany->type], $currentCompany->id);
        $pendingUsersTitleConcat = (isset($companyUsers['pending']) && count((array) $companyUsers['pending'])? ' (' . count($companyUsers['pending']) . ')' : '');
        $navigation['profile']['children']['company']['children']['company-people']['title'] .= $pendingUsersTitleConcat;

        //Available only for admins, not even shipmates
        if ($isAdminOfCompany) {
            //Memberships
            if (count(Shipserv_MembershipAuthorisation::getOwnedMemberships($currentCompany->id)) > 0) {
                $pendingMemberships = Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countCompanyMembershipActions($currentCompany->id);
                $pendingMembershipsTitleConcat = ($pendingMemberships? " ($pendingMemberships)" : ''); 
                $navigation['profile']['children']['company']['children']['memberships']['title'] .= $pendingMembershipsTitleConcat;
            } else {
                unset($navigation['profile']['children']['company']['children']['memberships']);
            }
        } else {
            unset($navigation['profile']['children']['company']['children']['privacy']);
            unset($navigation['profile']['children']['company']['children']['memberships']);
        }
        
        //Brands
        if (($isAdminOfCompany || $isShipservUser) && count(Shipserv_BrandAuthorisation::getManagedBrands($currentCompany->id)) > 0) {
            $pendingBrands = Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countCompanyBrandActions($currentCompany->id);
            $pendingBrandsTitleConcat = ($pendingBrands? " ($pendingBrands)" : '');
            $navigation['profile']['children']['company']['children']['brands']['title'] .= $pendingBrandsTitleConcat;
        } else {
            unset($navigation['profile']['children']['company']['children']['brands']);
        }
        
        //Company settings
        if (!($isAdminOfCompany || ( $isShipservUser &&  $user->canPerform('PSG_TURN_TN_INTEGRATION') == true))) {
            unset($navigation['profile']['children']['company']['children']['settings']);
        }
        
        //Buyer navigation tabs
        if ($currentCompany->type === 'b') {
            //Do not display all suppliers-only tabs
            unset($navigation['profile']['children']['company']['children']['company-profile']);
            unset($navigation['profile']['children']['company']['children']['target-customers']);
            
            //Autoreminder       
            if (!$isShipservUser && !$user->canAccessFeature($user::BRANCH_FILTER_AUTOREMINDER)) {
                unset($navigation['profile']['children']['company']['children']['automatic-reminders']);
            }

            //Tabs available only for admins or shipmates (and buyer tnid)
            if (!($isAdminOfCompany || $isShipservUser)) {
                unset($navigation['profile']['children']['company']['children']['approved-suppliers']);
                unset($navigation['profile']['children']['company']['children']['match-settings']);                
            }

        //Supplier navigation tabs            
        } elseif ($currentCompany->type === 'v') {
            //Do not display all buyers-only nav tabs
            unset($navigation['profile']['children']['company']['children']['approved-suppliers']);
            unset($navigation['profile']['children']['company']['children']['reviews']);
            unset($navigation['profile']['children']['company']['children']['automatic-reminders']);
            unset($navigation['profile']['children']['company']['children']['match-settings']);
            
            //Active promotions
            if (!$user->canAccessActivePromotion()) {
                unset($navigation['profile']['children']['company']['children']['target-customers']);
            }
            	
        }
            
        return $navigation;
    }

    /**
     * Iterate on first navigation level and set the isSelected attribute
     *  
     * @param Array $navigation 
     * @return Array $navigation
     */
    private static function _setSelected(array $navigation)
    {
        foreach (array_keys($navigation) as $tabIdstr) {
            $navigation[$tabIdstr]['isSelected'] = self::isFirstLevTabSelected($tabIdstr);
        }
        return $navigation;
    }

    /**
     * Set the selected flag on the entire menu tree 
     * 
     * @param array $navigation The navigation items
     * 
     * @return array
     */
    private static function _setSelectedRecursive(array &$navigation)
    {
        $referrer = Zend_Controller_Front::getInstance()->getRequest()->getHeader('referer');

        if (!$referrer) {
            return;
        }

        $parsedUrl = parse_url($referrer);

        // for these few special cases when the URL have to match with a TNID injected in
        $parsedUrlMatch = preg_replace('/\/\d*$/', '/{tnid}', preg_replace('/\/\d*\//', '/{tnid}/', $parsedUrl['path'], -1), -1);

        $referrerMatchExpression = '/^' . preg_quote($parsedUrlMatch, '/') . '.*$/i';

        // a recursive lambda function to get all tree URL elements to match
        $fetchMenuTree = function (array &$navigation, $referrer, $parentNode = null) use (&$fetchMenuTree) {
            foreach ($navigation as &$navigationItem) {
                if (isset($navigationItem['children'])) {
                    $parentNodes = [];
                    $node = array(
                        'parent' => $parentNode,
                        'node' => &$navigationItem
                    );
                    $parentNodes[] = &$navigationItem;
                    $fetchMenuTree($navigationItem['children'], $referrer, $node);
                } else {
                    if (isset($navigationItem['url']) && preg_match($referrer, $navigationItem['url'])) {
                        $navigationItem['isSelected'] = true;
                        $thisNode = $parentNode;
                        while ($thisNode !== null) {
                            $thisNode['node']['isSelected'] = true;
                            $thisNode = $thisNode['parent'];
                        }
                    }
                }
            }
        };

        $fetchMenuTree($navigation, $referrerMatchExpression);
    }
    
    
    /**
     * For custom reports, we have to convert the url to hex
     * @param string $string
     * @return string
     */
    private static function _strToHex($string)
    {
    	$hex='';
    	for ($i=0; $i < strlen($string); $i++) {
    		$hex .= dechex(ord($string[$i]));
    	}
    	return $hex;
    }

}
