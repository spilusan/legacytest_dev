<?php

/**
 * Bootstrap for MyShipServ application
 *
 * @author Dave Starling <dstarling@shipserv.com>
 * @package myshipserv
 * @copyright Copyright (c) 2009, Shipserv
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    
    /**
     * Inherit Zend Bootstrap run method (first method called for running application)  
     * @see Zend_Application_Bootstrap_Bootstrap::run()
     */
    public function run() 
    {
        
        /**
         * This callback is called when the script alrady executer, and will save an SQL log if enabled in application.ini
         */
        function shutdownShipservProcess()
        {
             //In getInstance, true or false, if we want to log all, or just the slowest query. Second parameter could be to log only queries that running longer then X
            Myshipserv_Logger_Sql::getInstance(true)->log();
        }
        register_shutdown_function('shutdownShipservProcess');
        
        //Plugin to catch errors/exceptions and log them down in proper format
        $front = Zend_Controller_Front::getInstance();
        $front->registerPlugin(new Myshipserv_Plugin_Errorlog());
        
        parent::run();
    }

	/**
	 * Set up helpers, etc.
	 *
	 * @access protected
	 */
	protected function _initRequest()
	{
		Zend_Controller_Action_HelperBroker::addPrefix('Shipserv_Helper');
		Zend_Controller_Action_HelperBroker::addPrefix('Myshipserv_Controller_Action_Helper');
	}

	/**
	 * Initialise custom routing for SEO purposes
	 *
	 * @access protected
	 */
	protected function _initRoutes()
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();
		$router = $this->_addRoutesForSearch($router);
		$router = $this->_addRoutesForReport($router);
		$router = $this->_addRoutesForSurvey($router);
		$router = $this->_addRoutesForHelp($router);
		$router = $this->_addRoutesForStyle($router);
		$router = $this->_addRoutesForTrade($router);
		$router = $this->_addRoutesForBuyer($router);
        $router = $this->_addRoutesForSupplier($router);
        $router = $this->_addRestRoutes($router);
        $router = $this->_addWebreporterRoutes($router);
        $router = $this->_addExportRoutes($router);
        $router = $this->_addMiscRoutes($router);
	}

    /**
     * Initialize configuration
     *
     * @return Zend_Config
     */
	protected function _initConfig()
	{
        $options = $this->getOptions();
        Zend_Registry::set('options', $options);
		$config = new Zend_Config($options, true);
		Zend_Registry::set('config', $config);

		if ( $_SERVER['APPLICATION_ENV'] != 'development' ) {
			// overriding $_SERVER['HOST_NAME'] with value stored in application.ini
			$_SERVER['HTTP_HOST'] =  $config->shipserv->application->hostname;
		}

		return $config;

	}

    /**
     * Initialize sessions
     *
     * @throws Zend_Exception
     * @throws Zend_Session_Exception
     */
    protected function _initSession()
    {
        $this->_initGlobalFunctions();

        // session management
        if (!_cookie_loggedin()) {
             return;
        }

        $config = Zend_Registry::get('config');
        $sessionPrefix = 'PHP_SESSION_' . strtoupper($config->shipserv->redis->session->prefix) . ':';
        $sessionMaxLifeTime = $config->shipserv->redis->session->max->lifetime;

        if ($sessionMaxLifeTime) {
            ini_set('session.gc_maxlifetime', (int)$sessionMaxLifeTime);
        }

        $useRedisCluster = $config->shipserv->redis->use->cluster;

        // if redis cluster session flag is on, let's replace the default session handler
        if ((int)$useRedisCluster === 1) {
            $redisPassword = $config->shipserv->redis->cluster->password;
            $redisSeeds = $config->shipserv->redis->cluster->seeds;
            $redisTimeout = $config->shipserv->redis->cluster->timeout;

            // set up redis authentication
            $options  = [
                'cluster' => 'redis',
                'parameters' => [
                    'password' => $redisPassword,
                    'timeout' => $redisTimeout
                ]
            ];

            // initialize redis cluster
            $client = new \Predis\Client('tcp://'.$redisSeeds, $options);

            // replace session handler with new Predis session handler
            $h = new Myshipserv_Predis_PrefixableSession($client, ['prefix' => $sessionPrefix]);
            $h->register();

        }

        // start session
        if (Zend_Session::isStarted() === false) {
            $rememberMe = (array_key_exists('rememberMe', $_COOKIE)) ? (int)$_COOKIE["rememberMe"] : null;

            if ($rememberMe) {
                Zend_Session::setOptions(array(
                        'cookie_lifetime' => $rememberMe
                    )
                );
            }
            Zend_Session::start();
        }
    }

    /**
     * Add routes for buyer
     *
     * @param $router
     * @return mixed
     */
	private function _addRoutesForBuyer($router)
    {
        $routes = array(
            '/buyer' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'index'
            ),
            '/buyer/rfq' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'rfq'
            ),
            '/buyer/gmv' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'gmv',
                'forceTab'  => 'shipmate'
            ),
			'/buyer/gmv-trend' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'gmv-trend'
        	),
			'/buyer/spend-graph' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'spend-graph'
        	),
			'/buyer/gmv/breakdown-by-supplier' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'gmv-by-supplier'
        	),
			'/buyer/gmv/breakdown-by-supplier-interacted-with' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'gmv-by-supplier-interacted-with'
        	),

			'/buyer/gmv/breakdown-by-purchaser' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'gmv-by-purchaser'
        	),
			'/buyer/gmv/breakdown-by-trading-unit' => array(
        		'module'     => 'buyer',
        		'controller' => 'buyer',
        		'action'     => 'gmv-by-trading-unit'
        	),
        	'/buyer/quote' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'quote'
            ),
            '/buyer/po' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'po'
            ),
            // added by Yuri Akopov on 2013-09-23, S7903
            '/buyer/search/results' => array(
                'module'     => 'buyer',
                'controller' => 'match',
                'action'     => 'results'
            ),
            '/buyer/search/terms' => array(
                'module'     => 'buyer',
                'controller' => 'match',
                'action'     => 'terms'
            ),
            // added by Yuri Akopov on 2013-09-23, S7903, for category and brand picker source JSONs
            '/data/source/categories' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'categories'
            ),
            '/data/source/brands' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'brands'
            ),
        		// added by Attila Oct 24, 2014
        	'/reports/data/source/brands' => array(
        				'module'     => 'buyer',
        				'controller' => 'data',
        				'action'     => 'brands'
        		),
            '/data/source/vessels' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'vessels'
            ),
            '/data/source/locations' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'locations'
            ),
        	'/data/source/ports' => array(
        		'module'     => 'buyer',
        		'controller' => 'data',
        		'action'     => 'ports'
        	),
            '/data/source/buyer-branches' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'buyer-branches'
            ),
            '/data/source/buyer-branches-buy' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'buyer-branches-buy'
            ),
        	// added by Elvir
            '/data/source/supplier-trading-partner' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'supplier-trading-partner'
            ),
            '/data/source/buyer-match-purchaser' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'buyer-match-purchaser'
            ),
            '/data/source/buyer-match-vessel' => array(
                'module'     => 'buyer',
                'controller' => 'data',
                'action'     => 'buyer-match-vessel'
            ),
       		'/data/source/buyer-match-segments' => array(
       				'module'     => 'buyer',
       				'controller' => 'data',
       				'action'     => 'buyer-match-segments'
       		),
        	'/data/source/buyer-match-keywords' => array(
        				'module'     => 'buyer',
        				'controller' => 'data',
        				'action'     => 'buyer-match-keywords'
        	),
        		
        	// added by Yuriy Akopov on 2013-10-02, S8459
            '/buyer/search/rfq-suppliers' => array(
                'module'     => 'buyer',
                'controller' => 'match',
                'action'     => 'rfq-suppliers'
            ),
            '/buyer/search/rfq-send' => array(
                'module'     => 'buyer',
                'controller' => 'match',
                'action'     => 'rfq-send'
            ),
            '/buyer/search/rfq-list' => array(
                'module'     => 'buyer',
                'controller' => 'rfq',
                'action'     => 'rfq-list'
            ),
            '/buyer/search/rfq-details' => array(
                'module'     => 'buyer',
                'controller' => 'rfq',
                'action'     => 'rfq-details'
            ),
            '/buyer/search/rfq-savings' => array(
                'module'     => 'buyer',
                'controller' => 'rfq',
                'action'     => 'rfq-savings'
            ),
            '/buyer/quote/list' => array(
                'module'     => 'buyer',
                'controller' => 'quote',
                'action'     => 'quote-list'
            ),
            '/buyer/quote/details' => array(
                'module'     => 'buyer',
                'controller' => 'quote',
                'action'     => 'quote-details'
            ),
            '/buyer/quote/order' => array(
                'module'     => 'buyer',
                'controller' => 'quote',
                'action'     => 'quote-order'
            ),
			// added by Yuriy Akopov on 2014-02-17
            '/buyer/blacklist/get-all' => array(
                'module'     => 'buyer',
                'controller' => 'blacklist',
                'action'     => 'get-all'
            ),
            '/buyer/blacklist/add' => array(
                'module'     => 'buyer',
                'controller' => 'blacklist',
                'action'     => 'add'
            ),
            '/buyer/blacklist/add-and-remove' => array(
                'module'     => 'buyer',
                'controller' => 'blacklist',
                'action'     => 'add-and-remove'
            ),
            '/buyer/blacklist/remove' => array(
                'module'     => 'buyer',
                'controller' => 'blacklist',
                'action'     => 'remove'
            ),
            '/buyer/blacklist/available' => array(
                'module'        => 'buyer',
                'controller'    => 'blacklist',
                'action'        => 'available'
            ),
            '/buyer/blacklist/enabled' => array(
                'module'        => 'buyer',
                'controller'    => 'blacklist',
                'action'        => 'enabled'
            ),
			'/data/source/user/company/list' => array(
        		'module'        => 'buyer',
        		'controller'    => 'data',
        		'action'        => 'user-company-list'
        	),
        	'/buyer/usage/summary' => array(
        		'module'        => 'buyer',
        		'controller'    => 'buyer',
        		'action'        => 'usage-summary'
        	),
        	'/buyer/usage/rfq-list' => array(
        		'module'        => 'buyer',
        		'controller'    => 'data',
        		'action'        => 'usage-rfq-list'
        	),
        	'/buyer/usage/rfq-detail-list' => array(
        		'module'        => 'buyer',
        		'controller'    => 'data',
        		'action'        => 'usage-rfq-detail-list'
        	),

            // added by Yuriy Akopov 2014-07-04
            '/buyer/match/settings/get' => array(
                'module'        => 'buyer',
                'controller'    => 'match',
                'action'        => 'get-settings'
            ),

            '/buyer/match/settings/update' => array(
                'module'        => 'buyer',
                'controller'    => 'match',
                'action'        => 'update-settings'
            ),
            '/buyer/match/settings/delete' => array(
                'module'        => 'buyer',
                'controller'    => 'match',
                'action'        => 'delete-settings'
            ),
            // added by Yuriy Akopov on 2014-10-15, rc-4.8
            '/buyer/quote/stats-exclude' => array(
                'module'     => 'buyer',
                'controller' => 'quote',
                'action'     => 'stats-exclude'
            ),
			'/buyer/match/remind' => array(
                'module'     => 'buyer',
                'controller' => 'match',
                'action'     => 'remind'
            ),
			'/buyer/cancel-rfq' => array(
                'module'     => 'buyer',
                'controller' => 'buyer',
                'action'     => 'cancel-rfq'
            ),           
			'/info/:slug' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'index'
            ),
            '/info/:parentSlug/:slug' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'index'
            ),
            '/info/:parentOfParentSlug/:parentSlug/:slug' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'index'
            ),
            '/info/private-page/:id' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'private-page'
            ),
            '/info/private-post/:id' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'private-post'
            ),                
            '/info/news-feed/:year' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-archive',
                'reqs'       => array('year' => '\d\d\d\d')
            ),
            '/info/news-feed/category/:category' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-category',
            ),              
            '/info/news-feed/search' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-search',
            ),
            '/info/news-feed/:year/:month/:day/:slug' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post',
                'reqs'       => array('year' => '\d\d\d\d', 'month' => '\d\d', 'day' => '\d\d')     
            ),
            '/info/news-feed/category/:category/page/:page' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-category'
            ),
            '/info/news-feed/:year/page/:page' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-archive',
                'reqs'       => array('year' => '\d\d\d\d')                    
            ),
            '/info/news-feed/page/:page' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'post-index'
            ),
            '/info/contact-form-post' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'contact-form-post'
            ),
            '/info/landing-form-post' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'landing-form-post'
            ),
            '/info/sso-form-post' => array(
                'module'     => 'corporate',
                'controller' => 'index',
                'action'     => 'sso-form-post'
            ),
            '/info/sitemap.xml' => array(
                'module'     => 'corporate',
                'controller' => 'sitemap',
                'action'     => 'index'
            ),
            '/info/sitemap/:sitemap-name' => array(
                'module'     => 'corporate',
                'controller' => 'sitemap',
                'action'     => 'child'
            ),                
        );

        foreach ($routes as $url => $routeInfo) {
            $route = new Zend_Controller_Router_Route($url, $routeInfo);

            // it doesn't really matter which name do we use here, but to be consistent...
            $name = str_replace('/', '-', ltrim($url, '/'));
            $router->addRoute($name, $route);
        }

    	return $router;
    }

    
    private function _addRoutesForSupplier($router)
    {

        $routes = array(
                '/data/source/supplier/approvedsupplierlist' => array(
                'module'        => 'buyer',
                'controller'    => 'approved',
                'action'        => 'approved-supplier-list'
                ),
                '/data/source/supplier/approvedsupplierexport' => array(
                'module'        => 'buyer',
                'controller'    => 'approved',
                'action'        => 'approved-supplier-export'
                ),
                '/data/source/supplier/approvedsupplierState' => array(
                'module'        => 'buyer',
                'controller'    => 'approved',
                'action'        => 'approved-supplier-state'
                ),
                '/data/source/supplier/approvedsupplierEmails' => array(
                'module'        => 'buyer',
                'controller'    => 'approved',
                'action'        => 'approved-supplier-emails'
                )
            );

        foreach ($routes as $url => $routeInfo) {
            $route = new Zend_Controller_Router_Route($url, $routeInfo);

            // it doesn't really matter which name do we use here, but to be consistent...
            $name = str_replace('/', '-', ltrim($url, '/'));
            $router->addRoute($name, $route);
        }

        return $router;
    }

    
    private function _addMiscRoutes($router) 
    {
        //Transaction monitor convenience URL
        $route = new Zend_Controller_Router_Route(
            '/txnmon/:action',
            array(  'module'        => 'essm',
                    'controller'    => 'transactionhistory',
                    'action'        => 'index')
        );
        $router->addRoute('txnmon', $route);

        $route = new Zend_Controller_Router_Route(
            '/shipmate/:action',
            array(
                'module'        => 'shipmate',
                'controller'    => 'shipmate',
                'action'        => ':action'
            )
        );
        $router->addRoute('shipmate-action', $route);
        
        //Different route for thransaction monitor, clicked from the shipmate tab        
        $route = new Zend_Controller_Router_Route(
            '/shipmate/txnmon/:action',
            array(  'module'        => 'essm',
                    'controller'    => 'transactionhistory',
                    'action'        => 'index',
                    'forceTab'      => 'shipmate'
                    )
        );
        $router->addRoute('shipmate-txnmon', $route);

        $route = new Zend_Controller_Router_Route(
    		'/shipmate',
    		array(
    			'module'        => 'shipmate',
    			'controller'    => 'shipmate',
    			'action'        => 'index'
    		)
        );
        $router->addRoute('shipmate', $route);

        $route = new Zend_Controller_Router_Route(
            '/shipmate/target-segments',
            array('module'     => 'shipmate',
                'controller' => 'shipmate',
                'action'     => 'target-segments'
            )
        );
        $router->addRoute('target-segments', $route);

        $route = new Zend_Controller_Router_Route(
    		'/shipmate/cron-health-check',
    		array('module'     => 'shipmate',
    				'controller' => 'shipmate',
    				'action'     => 'cron-health-check')
        );
        $router->addRoute('shipmate-cron-health-check', $route);


        $route = new Zend_Controller_Router_Route(
    		'/shipmate/value-event',
    		array(
				'module'        => 'shipmate',
				'controller'    => 'shipmate',
				'action'        => 'value-event'
    		)
        );
        $router->addRoute('shipmate-value-event', $route);

        $route = new Zend_Controller_Router_Route(
    		'/shipmate/vbp-health-check',
    		array(
				'module'        => 'shipmate',
				'controller'    => 'shipmate',
				'action'        => 'vbp-health-check'
    		)
        );
        $router->addRoute('shipmate-vbp-health-check', $route);

        $route = new Zend_Controller_Router_Route(
    		'/shipmate/manage-user',
    		array(
				'module'        => 'shipmate',
				'controller'    => 'shipmate',
				'action'        => 'manage-user'
    		)
        );
        $router->addRoute('shipmate-manage-user', $route);

        $route = new Zend_Controller_Router_Route(
    		'/shipmate/po-rate',
    		array(
				'module'        => 'shipmate',
				'controller'    => 'shipmate',
				'action'        => 'po-rate'
    		)
        );
        $router->addRoute('shipmate-po-rate', $route);

		$route = new Zend_Controller_Router_Route(
    		'/shipmate/zone-helper',
    		array(
				'module'        => 'shipmate',
				'controller'    => 'shipmate',
				'action'        => 'zone-helper'
    		)
        );
        $router->addRoute('shipmate-zone-helper', $route);

        
        $route = new Zend_Controller_Router_Route(
            '/shipmate/erroneous-transactions',
            array(
                'module'        => 'shipmate',
                'controller'    => 'shipmate',
                'action'        => 'erroneous-transactions'
            )
        );
        $router->addRoute('shipmate-erroneous-transactions', $route);

        $route = new Zend_Controller_Router_Route(
            '/shipmate/erroneous-transactions-report',
            array(
                'module'        => 'shipmate',
                'controller'    => 'shipmate',
                'action'        => 'erroneous-transactions-report'
            )
        );
        $router->addRoute('shipmate-erroneous-transactions-report', $route);

        //New login for CAS REST
        $route = new Zend_Controller_Router_Route(
            '/auth/cas/login',
            array(
                'controller'    => 'user',
                'action'        => 'login'
            )
        );
        $router->addRoute('login-action', $route);

        //This route is added for backward compatibility renaming loginAction
        $route = new Zend_Controller_Router_Route(
            '/user/login',
            array(
                'controller'    => 'user',
                'action'        => 'old-login'
            )
        );
        $router->addRoute('old-login-action', $route);

        $route = new Zend_Controller_Router_Route(
            '/auth/cas/logout',
            array(
                'controller'    => 'user',
                'action'        => 'logout-redirector'
            )
        );
        $router->addRoute('logout-redirector-action', $route);

        //Emulate old CAS Task manager
        $route = new Zend_Controller_Router_Route(
            '/auth/cas/passwordManager',
            array(
                'controller'    => 'user',
                'action'        => 'cas-password-manager'
            )
        );
        $router->addRoute('cas-password-manager-action', $route);

        //Non JSON response for Buyer Connnect (file download)
        $route = new Zend_Controller_Router_Route(
            '/reports/data/buyer-connect/transaction/document/:id',
            array(
                'module'        => 'shipmate',
                'controller'    => 'buyer-connect',
                'action'        => 'downlod-doc'
            )
        );
        $router->addRoute('buyer-connect-transaction-document-action', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/catalogue/api/*',
            array(
                'module'        => 'apiservices',
                'controller'    => 'forwarder',
                'action'        => 'index'
            )
        );
        $router->addRoute('apiservices-forwarder', $route);

        return $router;
    }

	private function _addRoutesForSurvey($router)
    {
    	// report routes
		$route = new Zend_Controller_Router_Route(
			'/survey/',
			array('module'     => 'survey',
                'controller' => 'survey',
                'action'     => 'index')
		);
		$router->addRoute('survey-index', $route);

		$route = new Zend_Controller_Router_Route(
			'/survey/completed',
			array('module'     => 'survey',
				  'controller' => 'survey',
				  'action'     => 'completed')
		);
		$router->addRoute('survey-thankyou', $route);

		$route = new Zend_Controller_Router_Route(
			'/survey/invite',
			array('module'     => 'survey',
				  'controller' => 'survey',
				  'action'     => 'invite')
		);
		$router->addRoute('survey-invite', $route);

		return $router;
    }

    
    private function _addRoutesForTrade($router)
    {
        $route = new Zend_Controller_Router_Route(
    		'/trade',
    		array(	'module'     => 'trade',
    				'controller' => 'trade',
    				'action'     => 'index')
        );
    	$router->addRoute('trade-index', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/rfq',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'rfq')
    	);
    	$router->addRoute('trade-rfq', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/view-rfq',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'view-rfq')
    	);
    	$router->addRoute('trade-view-rfq', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/rfq-data',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'rfq-data')
    	);
    	$router->addRoute('trade-rfq-data', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/block-buyer',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'block-buyer')
    	);
    	$router->addRoute('trade-block-buyer', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/po',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'po')
    	);
    	$router->addRoute('trade-po', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/po-sent',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'po-sent')
    	);
    	$router->addRoute('trade-po-sent', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/process-po',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'process-po')
    	);
    	$router->addRoute('trade-process-po', $route);

    	$route = new Zend_Controller_Router_Route(
			'/trade/convert-po-to-pdf',
			array(	'module'     => 'trade',
					'controller' => 'trade',
					'action'     => 'convert-po-to-pdf')
    	);
    	$router->addRoute('trade-convert-po-to-pdf', $route);

        return $router;
    }

    
	private function _addRoutesForReport($router)
    {
    	// report routes
		$route = new Zend_Controller_Router_Route(
			'/reports',
			array('module'     => 'reports',
				  'controller' => 'report',
				  'action'     => 'index',
			      'forceTab'   => 'analyse-supplier')
		);
		$router->addRoute('report-index', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/sir-details',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'sir-details-gateway',
                  'forceTab'   => 'analyse-supplier' 
            )
        );
        $router->addRoute('report-sir-gateway', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/sir-stable',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'sir-stable')
        );
        $router->addRoute('report-sir-stable', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/sir',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'sir-new')
        );
        $router->addRoute('report-sir-new', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/smart-sir',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'smart')
		);
		$router->addRoute('report-smart', $route);

		$route = new Zend_Controller_Router_Route(
    		'/reports/invite',
    		array('module'     => 'reports',
    			  'controller' => 'report',
    			  'action'     => 'invite')
		);
		$router->addRoute('report-invite', $route);

		$route = new Zend_Controller_Router_Route(
    		'/reports/reminder',
    		array('module'     => 'reports',
    			  'controller' => 'report',
    			  'action'     => 'reminder')
		);
		$router->addRoute('report-invite-reminder', $route);

		$route = new Zend_Controller_Router_Route(
    		'/reports/match',
    		array('module'     => 'reports',
    			  'controller' => 'report',
    			  'action'     => 'match')
		);
		$router->addRoute('report-match', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/match-new',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'match-new')
		);
		$router->addRoute('report-match-new', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/match-report',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'match-report')
        );
        $router->addRoute('report-match-report', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/billing',
			array('module'       => 'reports',
					'controller' => 'report',
					'action'     => 'billing',
                    'forceTab'   => 'shipmate'
                    )
		);
		$router->addRoute('report-billing', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/price-benchmark',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'price-benchmark')
		);

		$router->addRoute('price-benchmark', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/price-tracker',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'price-tracker')
        );

        $router->addRoute('price-tracker', $route);

        // Market Sizing tools pages and endpoints
        $route = new Zend_Controller_Router_Route(
            '/reports/market-sizing',
            array('module'     => 'marketsizing',
                    'controller' => 'index',
                    'action'     => 'index',
                    'forceTab'  => 'shipmate'
            )
        );
        $router->addRoute('report-market-sizing', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/market-sizing/create-session-request',
            array('module'   => 'marketsizing',
                'controller' => 'index',
                'action'     => 'create-session-request')
        );
        $router->addRoute('report-market-sizing-create-session-request', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/market-sizing/service/get-vessel-types',
            array('module'   => 'marketsizing',
                'controller' => 'service',
                'action'     => 'get-vessel-types')
        );
        $router->addRoute('report-market-sizing-service-get-vessel-types', $route);

		// SVR's helper/utility page
		$route = new Zend_Controller_Router_Route(
			'reports/api/:action/*',
			array('module'     => 'reports',
				  'controller' => 'report',
				  'action'     => 'action')
		);
		$router->addRoute('reports-api', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/gmv',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'gmv',
                    'forceTab'      => 'shipmate'
                    )
		);
		$router->addRoute('report-gmv', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/gmv-bak',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'gmv-bak')
		);
		$router->addRoute('report-gmv-bak', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/gmv-data',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'gmv-data')
		);
		$router->addRoute('report-gmv-data', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/new-gmv-data',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'gmv-new-data')
        );
        $router->addRoute('report-new-gmv-data', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/engagement',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'engagement',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-engagement', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/pages-dashboard',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'pages-dashboard',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-pages-dashboard', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/sso-dashboard',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'sso-dashboard',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-sso-dashboard', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/sso-installation-dashboard',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'sso-installation-dashboard')
		);
		$router->addRoute('report-sso-installation-dashboard', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/brand-management',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'brandmanagement',
                    'forceTab'  => 'shipmate'
            )
        );
        $router->addRoute('report-brand-management', $route);

        /* Ajax call for brand managament action */
        $route = new Zend_Controller_Router_Route(
            '/reports/brand-report',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'brandreport')
        );
        $router->addRoute('report-brand-report', $route);


        $route = new Zend_Controller_Router_Route(
            '/reports/internal-supplier-kpi',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'internal-supplier-kpi',
                    'forceTab'  => 'shipmate'
            )
        );
        $router->addRoute('report-internal-supplier-kpi', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/internal-supplier-kpi-report',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'internal-supplier-kpi-report')
        );
        $router->addRoute('report-internal-supplier-kpi-report', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/invalid-txn-picker',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'invalid-txn-picker',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-invalid-txn-picker', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/transactions',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'transactions',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-transactions', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/pages',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'pages',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-pages', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/supplier-conversion',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'supplier-conversion',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-supplier-conversion', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/supplier-conversion/rfqs',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'supplier-conversion-list-rfq')
		);
		$router->addRoute('report-supplier-conversion-list-rfq', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/publisher',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'publisher')
		);
		$route = new Zend_Controller_Router_Route(
			'/reports/vessels',
			array('module'     => 'reports',
				'controller' => 'report',
				'action'     => 'vessels')
		);
		$router->addRoute('report-publisher', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/publisher-data',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'publisher-data')
		);
		$router->addRoute('report-publisher-data', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/supplier-stats',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'supplier-stats',
                    'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-supplier-stats', $route);


		// sir3
		$route = new Zend_Controller_Router_Route(
			'/reports/supplier-insight-report',
			array(	'module'     => 'reports',
					'controller' => 'report',
					'action'     => 'supplier-insight-report')
		);
		$router->addRoute('supplier-insight-report', $route);

		$route = new Zend_Controller_Router_Route(
			'reports/supplier-insight-data',
			array('module'     => 'reports',
				  'controller' => 'report',
				  'action'     => 'supplier-insight-data')
		);
		$router->addRoute('reports-supplier-insight-data', $route);

		$route = new Zend_Controller_Router_Route(
			'/reports/supplier/response-rate',
			array('module'     => 'reports',
					'controller' => 'report',
					'action'     => 'supplier-response-rate',
			        'forceTab'  => 'shipmate'
			)
		);
		$router->addRoute('report-supplier-response-rate', $route);


        $route = new Zend_Controller_Router_Route(
            '/reports/match-supplier-report',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'match-supplier-report')
        );
        $router->addRoute('match-supplier-report', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/data/match-supplier-report',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'match-supplier-report-data')
        );
        $router->addRoute('match-supplier-report-data', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/data/match-supplier-report-export',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'match-supplier-report-export')
        );
        $router->addRoute('match-supplier-report-export', $route);
        //Double check, if in merge it will duplicate
        $route = new Zend_Controller_Router_Route(
            '/reports/supplier/supplier-companies-list',
            array('module'     => 'reports',
                    'controller' => 'report',
                    'action'     => 'supplier-companies-list')
        );
        $router->addRoute('report-supplier-companies-list', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/kpi-trend/:status',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'kpi-trend',
                  'status' => 'sir2',
                  'forceTab'   => 'analyse-supplier'
            )
        );
        $router->addRoute('report-kpi-trend', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/kpi-trend-report',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'kpi-trend-report')
        );
        $router->addRoute('report-kpi-trend-report', $route);

        $route = new Zend_Controller_Router_Route (
            '/reports/log-js-event',
            array('module'     => 'reports',
                  'controller' => 'report',
                  'action'     => 'log-js-event')
        );
        $router->addRoute('report-log-js-event', $route);
        
        //SPR Supplier Performance Report
        $route = new Zend_Controller_Router_Route (
        		'/reports/supplier-performance',
        		array('module'     => 'spr',
        				'controller' => 'index',
        				'action'     => 'index',
        		        'forceTab'  => 'analyse'
        		)
        		);
        $router->addRoute('report-supplier-performance', $route);
        
        $route = new Zend_Controller_Router_Route (
        		'/reports/print-supplier-performance',
        		array('module'     => 'spr',
        				'controller' => 'index',
        				'action'     => 'print')
        		);
        $router->addRoute('report-print-supplier-performance', $route);

        // route ID used to be inherited from the route above (and thus breaking it)
        // replaced with a apparently unquer 'report-download' by Yuriy Akopov on 2017-07-05
        $route = new Zend_Controller_Router_Route (
        		'/reports/download',
        		array('module'     => 'reports',
        				'controller' => 'download',
        				'action'     => 'download')
        		);
        $router->addRoute('report-download', $route);

        $route = new Zend_Controller_Router_Route (
            '/reports/transaction-report',
            array('module'     => 'consortia',
                'controller' => 'index',
                'action'     => 'index',
                'forceTab'  => 'analyse-consortia'
            )
        );
        $router->addRoute('transaction-report', $route);
        
        // added by Yuriy Akopov on 2017-11-30 for DEV-1170
        $route = new Zend_Controller_Router_Route(
            '/consortia/pull-supplier-agreements',
            array(
                'module'     => 'consortia',
                'controller' => 'sync',
                'action'     => 'pull-supplier-agreements'
            )
        );
        $router->addRoute('consortia-supplier-pull', $route);

        // added by Yuriy Akopov on 2017-12-29 for DEV-1602
        $route = new Zend_Controller_Router_Route(
            '/consortia/push-consortia',
            array(
                'module'     => 'consortia',
                'controller' => 'sync',
                'action'     => 'push-consortia'
            )
        );
        $router->addRoute('consortia-push', $route);

        // added by Yuriy Akopov on 2018-01-03 for DEV-1602
        $route = new Zend_Controller_Router_Route(
            '/consortia/push-buyer-supplier-relationships',
            array(
                'module'     => 'consortia',
                'controller' => 'sync',
                'action'     => 'push-buyer-supplier-relationships'
            )
        );
        $router->addRoute('consortia-buyer-supplier-push', $route);

        // added by Yuriy Akopov on 2018-02-05 for DEV-1172
        $route = new Zend_Controller_Router_Route(
            '/consortia/get-billed-month-orders',
            array(
                'module'     => 'consortia',
                'controller' => 'sync',
                'action'     => 'get-billed-month-orders'
            )
        );
        $router->addRoute('consortia-billed-month-orders', $route);

        // added by Yuriy Akopov on 2018-02-05 for DEV-1172
        $route = new Zend_Controller_Router_Route(
            '/consortia/get-monthly-bill',
            array(
                'module'     => 'consortia',
                'controller' => 'sync',
                'action'     => 'get-monthly-bill'
            )
        );
        $router->addRoute('consortia-get-monthly-bill', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/specsheet-image',
            array(
                'module'     => 'reports',
                'controller' => 'image-reports',
                'action'     => 'catalogue-specheet-image'
            )
        );
        $router->addRoute('reports-specsheet-image', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/resize-image',
            array(
                'module'     => 'reports',
                'controller' => 'image-reports',
                'action'     => 'resize-image'
            )
        );
        $router->addRoute('reports-resize-image', $route);

        $route = new Zend_Controller_Router_Route(
            '/reports/consortium-transactions',
            array(
                'module'     => 'consortium',
                'controller' => 'index',
                'action'     => 'index'
            )
        );
        $router->addRoute('reports-consortium-transactions', $route);

		return $router;
    }

    
	private function _addRoutesForHelp($router)
	{
		/*Help*/
		$route = new Zend_Controller_Router_Route(
			'help/:action/:subsection',
			array('module'     => '',
				  'controller' => 'help',
				  'action'     => 'buyerfaq',
				  'subsection' => '')
		);
		$router->addRoute('help', $route);

		$route = new Zend_Controller_Router_Route(
			'help/contactform',
			array('module'     => '',
				  'controller' => 'help',
				  'action'     => 'contactform')
		);
		$router->addRoute('contactform', $route);

		return $router;

	}

	private function _addRoutesForStyle($router)
	{
		$route = new Zend_Controller_Router_Route(
			'uicomponents/:action',
			array('module'     => '',
				  'controller' => 'style',
				  'action'     => 'index')
		);

		$router->addRoute('style', $route);

		return $router;
	}

    private function _addRoutesForSearch($router)
    {
    	$sourceHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('SearchSource');

		// Note: I think adding source tracking code here is a bug? If these pages are accessed other than via the browse pages,
		// the hits will be logged as originating from the browse pages.

		// brand routes
		$route = new Zend_Controller_Router_Route(
			'brand/:searchWhat/:brandId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_BRAND'))
		);
		$router->addRoute('brand', $route);

        $route = new Zend_Controller_Router_Route(
			'brand/:searchWhat2/:brandId/zone',
            array('module'     => 'search',
                'controller' => 'results',
                'action'     => 'index',
                Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('ZONE_INVITE_FROM_SEARCH'))
		);
		$router->addRoute('brandZone', $route);

		$route = new Zend_Controller_Router_Route(
			'brand/:searchWhat/:searchText/:searchWhere/:brandId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_BRAND'))
		);
		$router->addRoute('brandCountry', $route);

		// category routes
		$route = new Zend_Controller_Router_Route(
			'category/:searchWhat/:categoryId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_CATEGORY'))
		);
		$router->addRoute('category', $route);


        //Category route with Zone def
        $route = new Zend_Controller_Router_Route(
            'category/:searchWhat/:categoryId/zone',
            array('module'     => 'search',
                'controller' => 'results',
                'action'     => 'index',
            Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('ZONE_INVITE_FROM_SEARCH'))
        );
		$router->addRoute('categoryZone', $route);

		$route = new Zend_Controller_Router_Route(
			'category/:searchWhat/:searchText/:portName/:searchWhere/:categoryId',
			array('module'     => 'search',
					'controller' => 'results',
					'action'     => 'index',
					Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_CATEGORY'))
		);
		$router->addRoute('categoryByPort', $route);


		$route = new Zend_Controller_Router_Route(
			'category/:searchWhat/:searchText/:searchWhere/:categoryId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_CATEGORY'))
		);
		$router->addRoute('categoryCountry', $route);

		// country/port routes
		$route = new Zend_Controller_Router_Route(
			'country/:searchText/:searchWhere',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_COUNTRY'))
		);
		$router->addRoute('country', $route);

        $route = new Zend_Controller_Router_Route(
    		'country/:searchText/:searchWhere/zone',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('ZONE_INVITE_FROM_SEARCH'))
		);
		$router->addRoute('countryZone', $route);

		$route = new Zend_Controller_Router_Route(
			'port/:searchText/:searchWhere',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_PORT'))
		);
		$router->addRoute('port', $route);

        $route = new Zend_Controller_Router_Route(
			'port/:searchText/:searchWhere/zone',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('ZONE_INVITE_FROM_SEARCH'))
		);
		$router->addRoute('portZone', $route);

		// Note: the product & model routes may wish to use a different source tracking code? However, see note above.

		// Product routes
		$route = new Zend_Controller_Router_Route(
			'product/:searchWhat/:productId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_BRAND'))
		);
		$router->addRoute('product', $route);

		// Related search SEO url for suppliers
		$route = new Zend_Controller_Router_Route(
			'/supplier/called/named/:searchWhat/*',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_BRAND'))
		);
		$router->addRoute('productSupplier', $route);

		// Model routes
		$route = new Zend_Controller_Router_Route(
			'model/:searchWhat/:modelId',
			array('module'     => 'search',
				  'controller' => 'results',
				  'action'     => 'index',
				  Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME => $sourceHelper->getObscuredKey('BROWSE_BRAND'))
		);

		$router->addRoute('model', $route);

		$route = new Zend_Controller_Router_Route(
			'supplier/reviews/profile/s/:s/*',
			array('module' => '',
				  'controller' => 'reviews',
				  'action' =>	'supplier')
		);

		$router->addRoute('reviews', $route);

		return $router;

    }

    /**
    * Initalise routes for REST Calls
    */   
    private function _addRestRoutes($router)
    {
        // @todo: why the class below is not autoloaded by Composer?
        require_once('Myshipserv/Controller/Route/Rest.php');

        // defining hierarchical REST routes - please note that the order is important as routes override each other
        // The HTTP Request methods like DELETE, PUT.... are automatically redirected to their proper action
        $restRoutes = array(
            '/reports/data/appusage/' =>  array(
                'module'     => 'reports',
                'controller' => 'rest'
            ),
            '/reports/data/timezones' =>  array(
                'module'     => 'reports',
                'controller' => 'timezone-rest'
            ),
            '/reports/data/countries' =>  array(
                'module'     => 'reports',
                'controller' => 'countries-rest'
            ),
            '/reports/data/appusage/drilldown/:type/:id' =>  array(
                'module'     => 'reports',
                'controller' => 'appusage-drilldown-rest'
            ),
            '/reports/data/supplier-appusage/' =>  array(
                'module'     => 'reports',
                'controller' => 'supplier-usage-rest'
            ),
        	'/reports/data/supplier-appusage/drilldown/:type/:id' =>  array(
        			'module'     => 'reports',
        			'controller' => 'supplier-usage-drilldown-rest'
        	),
        	'/reports/data/supplier-performance/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'report-service'
        	),
        	'/reports/data/supplier-performance-profile/:id' =>  array(
        			'module'     => 'spr',
        			'controller' => 'profile-rest'
        	),
        	'/reports/data/supplier-performance-quote/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'quote-rest'
        	),
        	'/reports/data/supplier-performance-funnel/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'funnel-rest'
        	),
        	'/reports/data/supplier-performance-order/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'order-rest'
        	),
        	'/reports/data/supplier-performance-data/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'rest'
        	),
        	'/reports/data/supplier-performance-cycle/:type' =>  array(
        				'module'     => 'spr',
        				'controller' => 'cycle-rest'
        	),
        	'/reports/data/supplier-performance-quality/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'quality-rest'
        	),
        	'/reports/data/supplier-performance-competitiveness/:type' =>  array(
        			'module'     => 'spr',
        			'controller' => 'competitiveness-rest'
        	),
        	'/reports/data/buyer-connect/transaction/status/:id' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'status'
        	),
        	'/reports/data/buyer-connect/transaction/doctype/:id' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'doctype'
        	),
        	'/reports/data/buyer-connect/transaction/configId/:id' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'configId'
        	),
        	'/reports/data/buyer-connect/transaction/supplier/:id' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'supplier'
        	),
        	'/reports/data/buyer-connect/transaction/buyer/:id' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'buyer'
        	),
        	'/reports/data/buyer-connect/transaction/workFlowStatus/:id/*' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'requestId' => 'workFlowStatus'
        	),
        	'/reports/data/buyer-connect/transaction/:id/extractedData' =>  array(
        			'module'     => 'shipmate',
        			'controller' => 'buyer-connect-rest',
        			'extractedData' => true,
        			'requestId' => null
        	),
            '/reports/data/buyer-connect/transaction' =>  array(
                    'module'     => 'shipmate',
                    'controller' => 'buyer-connect-rest',
                    'extractedData' => false,
                    'requestId' => null
            ),
            '/reports/data/buyer-connect/transaction/:id' =>  array(
                        'module'     => 'shipmate',
                        'controller' => 'buyer-connect-rest',
                        'extractedData' => false,
                        'requestId' => null
            ),
            '/reports/data/consortia/buyers/:byb/post' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'buyers-post'
            ),
            '/reports/data/consortia/buyers/:byb/child-buyers' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'child-buyers'
            ),
            '/reports/data/consortia/buyers/:byb/suppliers' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'suppliers'
            ),
            '/reports/data/consortia/buyers/:byb/suppliers/:spb/pos' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'suppliers-pos'
            ),
            '/reports/data/consortia/buyers' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'buyers'
            ),
            '/reports/data/consortia/suppliers/:spb/post' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'suppliers-post'
            ),
            '/reports/data/consortia/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'child-suppliers'
            ),
            '/reports/data/consortia/suppliers/:spb/buyers' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'buyers'
            ),
            '/reports/data/consortia/suppliers/:spb/buyers/:byb/pos' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'buyers-pos'
            ),
            '/reports/data/consortia/suppliers' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'suppliers'
            ),
            '/reports/data/consortia/buyers/:byb/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'suppliers-child-suppliers'
            ),
            '/reports/data/consortia/suppliers/:spb/buyers/:byb/child-buyers' =>  array(
                'module'     => 'consortia',
                'controller' => 'supplier-rest',
                'type' => 'buyers-child-buyers'
            ),
            '/reports/data/consortia/buyers/:byb/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortia',
                'controller' => 'buyer-rest',
                'type' => 'buyers-child-suppliers'
            ),
            //Api Micro services for new arhitecture
            '/reports/api/menustructure' =>  array(
                'module'     => 'apiservices',
                'controller' => 'menu-service',
            ),
            '/reports/api/getuser/:tgt' =>  array(
                'module'     => 'apiservices',
                'controller' => 'user-service',
                'tgt' => ''
            ),
            '/reports/api/corporatenav/:tgt' =>  array(
                'module'     => 'apiservices',
                'controller' => 'corporate-nav-service',
                'tgt' => ''
            ),
            '/reports/api/getfooter' =>  array(
                'module'     => 'apiservices',
                'controller' => 'footer-service',
            ),
            '/reports/api/getbuyers' =>  array(
                'module'     => 'apiservices',
                'controller' => 'buyers-service',
            ),
            '/reports/api/active-user-company' =>  array(
                'module'     => 'apiservices',
                'controller' => 'active-user-company',
            ),
            '/reports/data/catalogue/:type/:prodId' =>  array(
                'module'     => 'catalogue',
                'controller' => 'api',
                'type' => 'tree',
                'prodId' => '',
            ),
            '/reports/api/token' =>  array(
                'module'     => 'apiservices',
                'controller' => 'oauth-token-service',
            ),
            '/reports/api/refreshtoken' =>  array(
                'module'     => 'apiservices',
                'controller' => 'refresh-token',
            ),
            '/reports/api/resolve-serviceticket' =>  array(
                'module'     => 'apiservices',
                'controller' => 'cas-resolve-service-ticket',
            ),


            '/reports/data/consortium/buyers/:byb/post' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'buyers-post'
            ),
            '/reports/data/consortium/buyers/:byb/child-buyers' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'child-buyers'
            ),
            '/reports/data/consortium/buyers/:byb/suppliers' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'suppliers'
            ),
            '/reports/data/consortium/buyers/:byb/suppliers/:spb/pos' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'suppliers-pos'
            ),
            '/reports/data/consortium/buyers' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'buyers'
            ),
            '/reports/data/consortium/consortia' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'consortia'
            ),
            '/reports/data/consortium/suppliers/:spb/post' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'suppliers-post'
            ),
            '/reports/data/consortium/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'child-suppliers'
            ),
            '/reports/data/consortium/suppliers/:spb/buyers' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'buyers'
            ),
            '/reports/data/consortium/suppliers/:spb/buyers/:byb/pos' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'buyers-pos'
            ),
            '/reports/data/consortium/suppliers' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'suppliers'
            ),
            '/reports/data/consortium/buyers/:byb/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'suppliers-child-suppliers'
            ),
            '/reports/data/consortium/suppliers/:spb/buyers/:byb/child-buyers' =>  array(
                'module'     => 'consortium',
                'controller' => 'supplier-rest',
                'type' => 'buyers-child-buyers'
            ),
            '/reports/data/consortium/buyers/:byb/suppliers/:spb/child-suppliers' =>  array(
                'module'     => 'consortium',
                'controller' => 'buyer-rest',
                'type' => 'buyers-child-suppliers'
            )
        );
       
        $frontController = Zend_Controller_Front::getInstance();

        foreach ($restRoutes as $url => $routeInfo) {
            $frontController->getRouter()->addRoute('rest_' . $url, new Myshipserv_Controller_Route_Rest($frontController, $url, $routeInfo));
        }

        return $router;
    }

    /**
    * Add routes for webreporter
    * @param object $router Zend_Controller_Router
    * @return object Zend_Controller_Router
    */
    private function _addWebreporterRoutes($router)
    {
        $route = new Zend_Controller_Router_Route(
            '/webreporter',
            array('module' => 'webreporter',
                  'controller' => 'index',
                  'action' =>   'index')
        );

        $router->addRoute('webreporter', $route);

        $route = new Zend_Controller_Router_Route(
            'webreporter/index/',
            array('module' => 'webreporter',
                  'controller' => 'index',
                  'action' =>   'index')
        );

        $router->addRoute('webreporter-index', $route);

        $route = new Zend_Controller_Router_Route(
            '/devphpreports/php-webreporter/public/index/cas/',
            array('module' => 'webreporter',
                  'controller' => 'index',
                  'action' =>   'cas')
        );

        $router->addRoute('webreporter-cas', $route);

        $route = new Zend_Controller_Router_Route(
            '/webreporter/report/',
            array('module' => 'webreporter',
                  'controller' => 'report',
                  'action' =>   'index')
        );

        $router->addRoute('webreporter-report', $route);
        
        $route = new Zend_Controller_Router_Route(
            '/webreporter/help/',
            array('module' => 'webreporter',
                  'controller' => 'index',
                  'action' =>   'help')
        );

        $router->addRoute('webreporter-help', $route);

        $route = new Zend_Controller_Router_Route(
            '/webreporter/not-available-for-tradenet',
            array('module' => 'webreporter',
                  'controller' => 'messages',
                  'action' =>   'index')
        );

        $router->addRoute('webreporter-tnet-error', $route);

        return $router;
    }
    
    /*
    * Adding routes for csv exports
    */
    private function _addExportRoutes($router)
    {
        $exportRoutes = array(
            '/reports/export/appusage/' =>  array(
                'module'     => 'reports',
                'controller' => 'appusage-export',
                'action' => 'main'

            ),
            '/reports/export/appusage/drilldown/:type/:id' =>  array(
                'module'     => 'reports',
                'controller' => 'appusage-export',
                'action' => 'index'
            ),
            '/reports/export/supplier-appusage/' =>  array(
                'module'     => 'reports',
                'controller' => 'supplier-usage-export',
                'action' => 'main'
            ),
            '/reports/export/supplier-appusage/drilldown/:type/:id' =>  array(
                'module'     => 'reports',
                'controller' => 'supplier-usage-export',
                'action' => 'index'
            ),
            '/reports/export/supplier-performance-order/' =>  array(
                'module'     => 'spr',
                'controller' => 'export',
                'action' => 'order'
            ),
            '/reports/data/consortia/export-csv' =>  array(
                'module'     => 'consortia',
                'controller' => 'export'
            ),
            '/reports/data/sir-pct/export-csv' =>  array(
                'module'     => 'shipmate',
                'controller' => 'export'
            ),
            '/reports/data/consortium/export-csv' =>  array(
                'module'     => 'consortium',
                'controller' => 'export'
            )
        );

        foreach ($exportRoutes as $url => $routeInfo)
        {
            $router->addRoute('export_' . $url, new Zend_Controller_Router_Route(
               $url, $routeInfo
            ));
        }

        return $router;
    }

	/**
	 * Initialise the default document type
	 *
	 * @access protected
	 */
	protected function _initDoctype()
    {
		$this->bootstrap('view');
        $view = $this->getResource('view');
        $view->doctype('XHTML1_STRICT');
		$view->addHelperPath(APPLICATION_PATH . '/../library/Myshipserv/View/Helper', 'Myshipserv_View_Helper');

		$view->headTitle()->setSeparator(' - ');
        $view->headTitle('ShipServ Pages - Marine Suppliers & Shipping Supplies Directory');

		$view->addScriptPath(APPLICATION_PATH . "/views/scripts/");
		$view->addScriptPath(APPLICATION_PATH . "/views/scripts/partials/");
    }

	/**
	 * Initialise the auto loader
	 *
	 * @access protected
	 */
	protected function _initAutoload()
    {
        $autoloader = new Zend_Application_Module_Autoloader(
            array(
                'namespace' => 'Default_',
                'basePath'  => dirname(__FILE__),
            )
        );

        return $autoloader;
    }

	/**
	 * This layout helper allows us to use different layouts for different models
	 *
	 * @access protected
	 */
	protected function _initLayoutHelper()
	{
		$this->bootstrap('frontController');
		$layout = Zend_Controller_Action_HelperBroker::addHelper(new Myshipserv_Controller_Action_Helper_LayoutLoader());
	}

	protected function _initTranslate()
	{
		$translate = new Zend_Translate('Array', APPLICATION_PATH . '/../languages/en_US.php', 'en_US');
		Zend_Registry::set('Zend_Translate', $translate);
	}

    /**
     * Initialize action helper to track user's browsing history
     */
    protected function _initHistoryHelper()
    {
        Zend_Controller_Action_HelperBroker::addHelper(new Shipserv_Helper_History());
    }

    protected function _initResourceAutoloader()
    {
        $autoloader = new Zend_Loader_Autoloader_Resource(
            array(
                'basePath'  => APPLICATION_PATH,
                'namespace' => 'Application',
            )
        );
        $autoloader->addResourceType('model', 'models', 'Model');
        return $autoloader;
    }
    
    
    /**
     * Init some global util functions that can be used anywhere 
     */
    private function _initGlobalFunctions()
    {
        if (!function_exists('_htmlspecialchars')) {
            //Use this function to be REALLY safe for XSS, because htmlspecialchars IS NOT SAFE for XSS without declaring utf-8 and ENT_QUOTES 
            //See http://stackoverflow.com/questions/19584189/when-used-correctly-is-htmlspecialchars-sufficient-for-protection-against-all-x, http://shiflett.org/blog/2007/may/character-encoding-and-xss, http://stackoverflow.com/questions/4722727/htmlspecialchars-ent-quotes-not-working)
            function _htmlspecialchars($string)
            {
                if (is_string($string)) {
                    $string = str_replace('javascript:', '', $string);
                }
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!function_exists('_safestring')) {
            //Use this function to be REALLY safe for XSS, because htmlspecialchars IS NOT SAFE for XSS without declaring utf-8 and ENT_QUOTES
            //See http://stackoverflow.com/questions/19584189/when-used-correctly-is-htmlspecialchars-sufficient-for-protection-against-all-x, http://shiflett.org/blog/2007/may/character-encoding-and-xss, http://stackoverflow.com/questions/4722727/htmlspecialchars-ent-quotes-not-working)
            function _safestring($string)
            {
                return preg_replace('/[^0-9a-zA-Z]/', '_', (String) $string);

            }
        }

        if (!function_exists('_cookie_loggedin')) {
            function _cookie_loggedin()
            {
                $uriParts = explode('?', $_SERVER['REQUEST_URI']);
                $uri = (count($uriParts) > 0) ? $uriParts[0] : '';
                $allowedUri = (($uri === '/auth/cas/login' && $_SERVER['REQUEST_METHOD'] === 'POST') || (preg_match('/\/txnmon|\/essm/', $uri)));

                return isset($_COOKIE['PAGES_KEEP_SESSION']) || $allowedUri;
            }
        }

        if (!function_exists('_session_start')) {
            function _session_start()
            {
               if (_cookie_loggedin()) {
                    session_start();
               }
            }
        }

    }
    
    /**
     * This callback is called when the script alrady executer, and will save an SQL log if enabled in application.ini
     * @return unknown
     */
    public function shutdownShipservProcess()
    {
        /*
         * in getInstance, true or false, if we want to log all, or just the slowest query
         * Second parameter could be to log only queries that running longer then X
         */
        Myshipserv_Logger_Sql::getInstance(true)->log();
    }

}

