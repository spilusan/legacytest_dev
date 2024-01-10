<?php

/**
* This class returns the list of menu options for a logged in user
*/
class Shipserv_Profile_Menuoptions 
{

	/**
    * @var Singleton The reference to *Singleton* instance of this class
    */
    private static $instance;
    protected 
    	  $db
    	, $user
    	, $shipservUser
    	, $userTradenet 
    	, $byoOrgCode
    	, $config
    	, $activeCompany
    	, $params
    	, $customPages
    ;


    /**
    * Returns the *Singleton* instance of this class.
    *
    * @return Singleton The *Singleton* instance.
    */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    /**
    * Protected what we have to hide
    */
    protected function __construct() {
    	$this->db = Shipserv_Helper_Database::getDb();
    	$this->user = Shipserv_User::isLoggedIn();
	    $this->shipservUser = ($this->user) ? $this->user->isShipservUser() : false;
	    $this->userTradenet = ($this->user) ? $this->user->getTradenetUser() : false;
	    //$this->byoOrgCode = $this->getUserBuyerOrg();
	    $this->config = Zend_Registry::get('config');
	    $this->activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
    }

    private function __clone()  {}

    /**
    * Setter for view params
    */
    public function setParams( $params )
    {
    	$this->params = $params;
    }

    /**
    * Setter for custom pagges
    */
    public function setCustomPages( $customPages )
    {
		$this->customPages = $customPages;
    }

	/**
	 * Returns the navigation content as an array, If selectedMenuUrl is null then it will use the URL of the browser
	 * Refactored by Yuriy Akopov on 2016-07-27 (DE6832 related)
	 *
	 * @param   string  $selectedMenuUrl
	 *
	 * @return  array
	 */
    public function getAnalyseTabOptions($selectedMenuUrl = null)
    {
    	$result = array();
    	$selectedMenuItem = ($selectedMenuUrl === null) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : $selectedMenuUrl;

        if ($this->user && $this->user->canAccessMatchBenchmark()) {
            if ($this->user->canAccessMatchBenchmarkBeta()) {
	            $href = '/reports/match-supplier-report';
	            $displayHref = ($href == $selectedMenuItem) ? '' : $href;
	            $result[] = array(
                    'href'      => $displayHref,
                    'title'     => 'Supplier Recommendations',
                    'selected'  => $displayHref == ''
	            );
            }

			$href = '/reports/match-new';
            $displayHref = ($href == $selectedMenuItem) ? '' : $href;
			$result[] = array(
                'href'      => $displayHref,
                'title'     => 'Sourcing Engine Dashboard',
                'selected'  => $displayHref == ''
            );

			$href = '/reports/match-report';
            $displayHref = ($href == $selectedMenuItem) ? '' : $href;
            $result[] = array(
	            'href'      => $displayHref,
	            'title'     => 'Sourcing Engine Transactions',
	            'selected'  => $displayHref == ''
            );
        }
        
        if ($this->user->canAccessSprKpi()) {
        	$href = '/reports/supplier-performance';
        	$displayHref = (preg_match('/^\/supplier-performance($|\/)/', $selectedMenuItem)) ? '' : $href;
        	$result[] = array(
        			'href'      => $displayHref,
        			'title'     => 'Supplier Performance',
        			'selected'  => $displayHref == ''
        	);
        }
            
        if ($this->user && $this->user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON)) {
			$href = ($this->user) ? '/txnmon/new' : '/txnmon';
        	$displayHref = ($href == $selectedMenuItem) ? '' : $href;
			$result[] = array(
    			  'href' => $displayHref, 
    			  'title' => 'Transaction Monitor', 
    			  'selected' => preg_match('/^\/txnmon($|\/)/', $selectedMenuItem)
			);
		}

	    if ($this->user && $this->user->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER)) {
			$href = '/webreporter/';
			$displayHref = (preg_match('/^\/webreporter($|\/)/', $selectedMenuItem)) ? '' : $href;
            $result[] = array(
                'href'      => $displayHref,
                'title'     => 'WebReporter',
                'selected'  => $displayHref == ''
            );
        } else {
		    if (!$this->user) {
			    $href = '/webreporter/';
			    $displayHref = (preg_match('/^\/webreporter($|\/)/', $selectedMenuItem)) ? '' : $href;
			    $result[] = array(
				    'href' => $displayHref,
				    'title' => 'WebReporter',
				    'selected' => $displayHref == ''
			    );
		    }
	    }

        if ($this->user && $this->user->canAccessPriceBenchmark()) {
	        $href = '/reports/price-benchmark';
	        $displayHref = ($href == $selectedMenuItem) ? '' : $href;
	        $result[] = array(
	            'href'      => $displayHref,
	            'title'     => 'IMPA Price Benchmark',
	            'selected'  => $displayHref == ''
            );

			$href = '/reports/price-tracker';
            $displayHref = ($href == $selectedMenuItem) ? '' : $href;
			$result[] = array(
	            'href'      => $displayHref,
	            'title'     => 'IMPA Spend Tracker',
	            'selected'  => $displayHref == ''
            );
		}

        if ($this->user && $this->showTrendGraph() === true) {
			$href = '/buyer/spend-graph';
            $displayHref = ($href == $selectedMenuItem) ? '' : $href;
			$result[] = array(
                'href'      => $displayHref,
                'title'     => 'Total Spend',
                'selected'  => $displayHref == ''
            );
        }

    	return $result;
    }

    /**
    * Rerurn the shipmate menu options
    */
    public function getShipmateTabOptions($selectedMenuUrl = null)
    {
    	$result = array();

    	$currentYear = date('Y');

    	$selectedMenuItem = ($selectedMenuUrl === null) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : $selectedMenuUrl;
    	if (!is_array($this->params)) {
    		throw new Myshipserv_Exception_MessagedException("Params is not set for Shipserv_Profile_Menuoptions", 500);

    	}

		$action = $this->params['controller'] . "-" . $this->params['action'];
		$userForManagingGroup = array('eleonard@shipserv.com', 'jgo@shipserv.com', 'smay@shipserv.com');
		
		$matchActions = array(
	        'match-breakdown-per-buyer',
	        'match-breakdown-per-rfq',
	        'match-inbox',
	        'match-update-algo-values',
	        'match-update-match-stopwords',
	        'match-sent-box',
	        'match-adoption-kpi',
	        'match-conversion-kpi',
	        'match-usage-report',
	        'match-order-detail',
			'match-automatch-stats',
			'match-efficiency',
			'shipmate-rate-synch'
    	);

		$i = 0;
		if (is_array($this->customPages)) {
			foreach($this->customPages as $name => $url) {
				if ($name !== 'General news') {
					if ($action=='shipmate-index') {
						$result[] = array(
		        			  'href' => $url
		        			, 'title' => $name
		        			, 'selected' => ($i++==0)
		        			, 'class' => ''
		        			, 'aClass' => 'iFrameLink'
		        			, 'target' => 'shipmateIFrame'
		        			, 'aStyle' => ''
	        			);	
					} else {
						$result[] = array(
		        			  'href' => '/shipmate'
		        			, 'title' => $name
		        			, 'selected' => false
		        			, 'class' => ''
		        			, 'aClass' => 'iFrameLink'
		        			, 'target' => ''
		        			, 'aStyle' => ''
	        			);
					}
				}
			}
		}

		if ($this->user && $this->user->canPerform('PSG_ACCESS_SCORECARD')) {
			$result[] = array(
			//	  'href' => 'https://intra.shipserv.com/sc_standby/login.php3'
				  'href' => '/shipmate/scorecard'
				, 'title' => 'ScoreCard'
				, 'selected' => ($this->params['module'] == 'shipmate' && $action=='shipmate-scorecard') 
				, 'class' => ''
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);
		}
		
		if ($this->user && $this->user->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER)) {
			$style =  ($this->params['module'] == 'webreporter' && $action=='index-index') ? 'padding: 0;' : '';
			$href = '/webreporter?tab=shipmate&h='.md5('wrhash_'.$this->user->username);		
			$result[] = array(
				  'href' => $href
				, 'title' => 'WebReporter'
				, 'selected' => ($this->params['module'] == 'webreporter' && $action=='index-index')
				, 'class' => ''
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => $style
			);
		}
	if ($this->user && ($this->user->canPerform('PSG_PAGES_ADMIN') || $this->user->canPerform('PSG_PAGES_ADMIN_ADV'))) {
		$result[] = array(
			  'href' => '/user/login-to-pages-adm'
			, 'title' => 'Pages admin'
			, 'selected' => false
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => '_blank'
			, 'aStyle' => ''
		);
	}

	if ($this->user && $this->user->canPerform('PSG_MARKET_SIZING')) {
		$style = ($this->params['module'] == 'marketsizing' && $action=='index-index') ? 'padding: 0': '' ;
		$result[] = array(
			  'href' => '/reports/market-sizing'
			, 'title' => 'Market Sizing Tool'
			, 'selected' => ($this->params['module'] == 'marketsizing' && $action=='index-index') 
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => $style
		);
	}
		
	if ($this->user && $this->user->canPerform('PSG_ACCESS_PO_PACK')) {
		$result[] = array(
			  'href' => '/ShipServ/JSP/Txn_Pack_Details.jsp'
			, 'title' => 'PO Pack'
			, 'selected' => false 
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => '_blank'
			, 'aStyle' => ''
		);
	}

	if ($this->user && $this->user->canPerform('PSG_ADMIN_GATEWAY')) {
	    $result[] = array(
	            'href' => '/user/login-to-admin-gw'
	            , 'title' => 'Gateway'
	            , 'selected' => false
	            , 'class' => ''
	            , 'aClass' => ''
	            , 'target' => '_blank'
	            , 'aStyle' => ''
	    );
	}
	
	$style =  ($this->params['module'] == 'essm' && ( $action ==  'transactionhistory-nsearch' || $action = 'transactionhistory-new')) ? 'padding: 0;' : '';
	if ($this->user && $this->user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON)) {
		$result[] = array(
			  'href' => '/shipmate/txnmon/new'
			, 'title' => 'Txn monitor'
			, 'selected' => ($this->params['module'] == 'essm' && ( $action ==  'transactionhistory-nsearch' || $action = 'transactionhistory-new')) 
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => $style
		);
	}

	$result[] = array(
		  'href' => '/shipmate/target-segments'
		, 'title' => 'Target segments'
		, 'selected' => (in_array($action, array('shipmate-target-segments')))
		, 'class' => ''
		, 'aClass' => ''
		, 'target' => ''
		, 'aStyle' => ''
	);

	$result[] = array(
		  'href' => '/kpi/web/login'
		, 'title' => 'KPI Report'
		, 'selected' => false
		, 'class' => ''
		, 'aClass' => ''
		, 'target' => '_blank'
		, 'aStyle' => ''
	);



	if( $this->user && ($this->user->canPerform('PSG_GMV_SUPPLIER') || $this->user->canPerform('PSG_CONVERSION_SUPPLIER') || $this->user->canPerform('PSG_GMV_BUYER')) )
	{
		if( $this->user->canPerform('PSG_GMV_SUPPLIER') )
		{

			$result[] = array(
				  'href' => '/reports/gmv'
				, 'title' => 'GMV Report'
				, 'selected' => (in_array($action, array('report-supplier-conversion-list-rfq', 'report-supplier-stats', 'report-gmv', 'report-supplier-conversion', 'buyer-gmv-by-supplier')))
				, 'class' => ''
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

		} else if( $this->user->canPerform('PSG_GMV_BUYER') )
		{
			$result[] = array(
				  'href' => '/buyer/gmv'
				, 'title' => 'GMV Report'
				, 'selected' => (in_array($action, array('report-supplier-conversion-list-rfq', 'report-supplier-stats', 'report-gmv', 'report-supplier-conversion', 'buyer-gmv-by-supplier')))
				, 'class' => ''
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);
	    }

		if( in_array($action, array('report-gmv', 'buyer-gmv', 'report-supplier-conversion-list-rfq', 'report-supplier-conversion', 'buyer-gmv-by-supplier', 'report-supplier-stats')) )
		{
			if( $this->user->canPerform('PSG_GMV_SUPPLIER') )
			{
				$result[] = array(
					  'href' => '/reports/gmv'
					, 'title' => 'Supplier'
					, 'selected' => ($action == 'report-gmv')
					, 'class' => 'secondary'
					, 'aClass' => ''
					, 'target' => ''
					, 'aStyle' => ''
				);
			}

			if( $this->user->canPerform('PSG_GMV_SUPPLIER') ) {
				$result[] = array(
					  'href' => '/reports/supplier-stats'
					, 'title' => 'Supplier statistics'
					, 'selected' => ($action == 'report-supplier-stats')
					, 'class' => 'secondary'
					, 'aClass' => ''
					, 'target' => ''
					, 'aStyle' => ''
				);
			}

			if( $this->user->canPerform('PSG_CONVERSION_SUPPLIER') ) 
			{
				$result[] = array(
					  'href' => '/reports/supplier-conversion'
					, 'title' => 'Supplier conversion'
					, 'selected' => (in_array($action, array('report-supplier-conversion', 'report-supplier-conversion-list-rfq')))
					, 'class' => 'secondary'
					, 'aClass' => ''
					, 'target' => ''
					, 'aStyle' => ''
				);
			}

			if( $this->user->canPerform('PSG_GMV_BUYER') )
			{
				$result[] = array(
					  'href' => '/buyer/gmv'
					, 'title' => 'Buyer'
					, 'selected' => ($action == 'buyer-gmv' || $action == 'buyer-gmv-by-supplier')
					, 'class' => 'secondary'
					, 'aClass' => ''
					, 'target' => ''
					, 'aStyle' => ''
				);
			}

		}
	}

	$result[] = array(
		  'href' => '/match/usage-report?doc=&yyyy='.$currentYear
		, 'title' => 'Match'
		, 'selected' => (in_array($action, $matchActions))
		, 'class' => ''
		, 'aClass' => ''
		, 'target' => ''
		, 'aStyle' => ''
	);

	if( in_array($action, $matchActions) )
	{ 
		if($this->user && $this->user->canPerform('PSG_ACCESS_MATCH') )
		{
			$result[] = array(
				  'href' => '/match/usage-report?doc=&yyyy='.$currentYear
				, 'title' => 'Usage Report'
				, 'selected' => (in_array($action, array('match-usage-report')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/efficiency?doc=&yyyy='.$currentYear
				, 'title' => 'Efficiency'
				, 'selected' => (in_array($action, array('match-efficiency')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/adoption-kpi'
				, 'title' => 'Adoption Report'
				, 'selected' => ((in_array($action, array('match-adoption-kpi', 'match-breakdown-per-buyer', 'match-breakdown-per-rfq'))))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/conversion-kpi'
				, 'title' => 'Conversion Report'
				, 'selected' => (in_array($action, array('match-conversion-kpi', 'match-order-detail')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

		}
		if( $this->user && $this->user->canPerform('PSG_ACCESS_MATCH_INBOX') )
		{
			$result[] = array(
				  'href' => '/match/inbox'
				, 'title' => 'Inbox'
				, 'selected' => (in_array($action, array('match-inbox')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/sent-box'
				, 'title' => 'Forwarded items'
				, 'selected' => (in_array($action, array('match-sent-box')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/update-algo-values'
				, 'title' => 'Engine'
				, 'selected' => (in_array($action, array('match-update-algo-values')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/update-match-stopwords'
				, 'title' => 'Stopwords'
				, 'selected' => (in_array($action, array('match-update-match-stopwords')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/match/automatch-stats'
				, 'title' => 'Automatch stats'
				, 'selected' => (in_array($action, array('match-automatch-stats')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);
 
		}
		//TODO S20551 recomment this when we will go live with this feature
		/*
		$result[] = array(
				'href' => '/shipmate/rate-synch'
				, 'title' => 'SalesForce rate synch '
				, 'selected' => (in_array($action, array('shipmate-rate-synch')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
		);
		*/
	 }

	if( $this->user && $this->user->canPerform('PSG_ACCESS_SALESFORCE') )
	{
		$result[] = array(
			  'href' => '/shipmate/value-event'
			, 'title' => 'Finance'
			, 'selected' => (in_array($action, array('shipmate-po-rate', 'shipmate-value-event', 'report-billing', 'shipmate-vbp-health-check', 'report-invalid-txn-picker', 'shipmate-erroneous-transactions')))
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => ''
		);
	
		if( in_array($action, array('shipmate-po-rate','shipmate-value-event', 'report-billing', 'shipmate-vbp-health-check', 'report-invalid-txn-picker', 'shipmate-erroneous-transactions')) ) 
		{
			$result[] = array(
				  'href' => '/shipmate/value-event'
				, 'title' => 'Value Event Upload'
				, 'selected' => (in_array($action, array('shipmate-value-event')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/shipmate/vbp-health-check'
				, 'title' => 'VBP Health check'
				, 'selected' => (in_array($action, array('shipmate-vbp-health-check')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/invalid-txn-picker'
				, 'title' => 'Invalid Txn Tools'
				, 'selected' => (in_array($action, array('report-invalid-txn-picker')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/billing'
				, 'title' => 'Billing Report'
				, 'selected' => (in_array($action, array('report-billing')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/shipmate/po-rate'
				, 'title' => 'PO Rate'
				, 'selected' => (in_array($action, array('shipmate-po-rate')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/shipmate/erroneous-transactions'
				, 'title' => 'Erroneous Transactions'
				, 'selected' => ((in_array($action, array('shipmate-erroneous-transactions'))))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

		}
	 }
	if( $this->user && $this->user->canPerform('PSG_ACCESS_DASHBOARD') )
	{
		$result[] = array(
			  'href' => '/reports/internal-supplier-kpi'
			, 'title' => 'Dashboards'
			, 'selected' => false
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => ''
		);

		if( in_array($action, array('report-supplier-response-rate', 'report-transactions', 'report-pages', 'report-engagement', 'report-pages-dashboard', 'report-sso-dashboard', 'report-sso-installation-dashboard', 'report-brandmanagement', 'report-internal-supplier-kpi', 'shipmate-buyer-usage-dashboard', 'shipmate-supplier-usage-dashboard')) )
		{

			$result[] = array(
					'href' => '/reports/internal-supplier-kpi'
					, 'title' => 'Internal Supplier KPI'
					, 'selected' => (in_array($action, array('report-internal-supplier-kpi')))
					, 'class' => 'secondary'
					, 'aClass' => ''
					, 'target' => ''
					, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/engagement'
				, 'title' => 'Engagement'
				, 'selected' => (in_array($action, array('report-engagement')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);


			$result[] = array(
				  'href' => '/reports/supplier/response-rate'
				, 'title' => 'Paying Supplier Response'
				, 'selected' => (in_array($action, array('report-supplier-response-rate')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/pages-dashboard'
				, 'title' => 'Pages'
				, 'selected' => (in_array($action, array('report-pages-dashboard')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/sso-dashboard?paying=1'
				, 'title' => 'SSO'
				, 'selected' => (in_array($action, array('report-sso-dashboard', 'report-sso-installation-dashboard')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/transactions'
				, 'title' => 'Tradenet usage'
				, 'selected' => (in_array($action, array('report-transactions')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);
			
			$result[] = array(
				  'href' => '/reports/pages'
				, 'title' => 'Pages Activity'
				, 'selected' => (in_array($action, array('report-pages')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/reports/brand-management'
				, 'title' => 'Brand management'
				, 'selected' => (in_array($action, array('report-brandmanagement')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/shipmate/buyer-usage-dashboard'
				, 'title' => 'Buyer Application Usage'
				, 'selected' => (in_array($action, array('shipmate-buyer-usage-dashboard')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			$result[] = array(
				  'href' => '/shipmate/supplier-usage-dashboard'
				, 'title' => 'Supplier Application Usage'
				, 'selected' => (in_array($action, array('shipmate-supplier-usage-dashboard')))
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);

			

			

		}
	}

	if ($this->user && $this->user->canPerform('PSG_MANAGE_USER_AND_COMPANY')) {
	    $result[] = array(
	            'href' => '/shipmate/manage-user'
	            , 'title' => 'User &amp; company'
	            , 'selected' => ($action === 'shipmate-manage-user')
	            , 'class' => ''
	            , 'aClass' => ''
	            , 'target' => ''
	            , 'aStyle' => ''
	    );
	}
	
	if (
	        in_array($action, array('profile-manage-group', 'shipmate-manage-user'))
	        && $this->user
	        && (
	                in_array($this->user->email, $userForManagingGroup) !== false 
	                || $this->user->canPerform('PSG_ACCESS_MANAGE_GROUP')
                )
        ) {
			$result[] = array(
				  'href' => '/profile/manage-group'
				, 'title' => 'User access group'
				, 'selected' => ($action === 'profile-manage-group')
				, 'class' => 'secondary'
				, 'aClass' => ''
				, 'target' => ''
				, 'aStyle' => ''
			);
	}

	if ($this->user && $this->user->canPerform('PSG_ACCESS_MANAGE_PUBLISHER')) {
		$result[] = array(
			  'href' => '/reports/publisher'
			, 'title' => 'Ad Network Publisher'
			, 'selected' => (in_array($action, array('report-publisher')))
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => ''
		);
	}

	if ($this->user && $this->user->canPerform('PSG_ACCESS_MANAGE_PUBLISHER')) {
		$result[] = array(
			  'href' => '/shipmate/buyer-connect-admin'
			, 'title' => 'Buyer Connect Admin'
			, 'selected' => (in_array($action, array('buyer-connect-admin')))
			, 'class' => ''
			, 'aClass' => ''
			, 'target' => ''
			, 'aStyle' => ''
		);
	}

    	return $result;
    }
    
    public function isLoggedIn()
    {
    	if ($this->user) {
    		return true;
    	} 
    	return false;
    }

    public function isShipmate()
    {
    	return $this->shipservUser;
    }

    public function hasTradenetUser()
    {
    	return (!($this->userTradenet === false || $this->userTradenet === null));
    }

    protected function showTrendGraph()
    {
		$showTrendGraph = true;

	    if( $this->user && $this->user->isShipservUser() == false){
	        if( false === in_array($this->activeCompany->id, explode(",", $this->config->shipserv->buyerSpendTrendGraph->participant->buyerId)))
	        {
	            $showTrendGraph = false;
	        }
	    }

	   return $showTrendGraph;
    }

    /**
	 * Returns buyer organisation current user belongs to
	 *
	 * @return  Shipserv_Buyer
	 */
	protected function getUserBuyerOrg() {
		$testingEnv = in_array($_SERVER['APPLICATION_ENV'], array('development', 'testing'));

		if ($this->user === false) {
			if (!$testingEnv) {
				return null;
			}
		} 

		$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		if (strlen($activeCompany->id) === 0){
		    if (in_array($_SERVER['APPLICATION_ENV'], array('testing', 'ukdev'))) {
				$buyerOrgId = 23404;    //@todo: backdoor for load testing
			} else {
				return null;
			}
		} else {
			$buyerOrgId = $activeCompany->id;
		}

		try {
			$buyerOrgCompany = Shipserv_Buyer::getInstanceById($buyerOrgId);
		} catch (Exception $e) {
			return null;
		}

		return $buyerOrgCompany;
	}


}