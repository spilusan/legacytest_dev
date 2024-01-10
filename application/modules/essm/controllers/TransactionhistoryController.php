<?php

class Essm_TransactionhistoryController extends Myshipserv_Controller_Action
{
    const
        PARAM_TIMEZONE = 'timezone',
        TIMEZONE_DEFAULT = 'UTC'
    ;

    /**
     * Current user-set or default timezone
     *
     * @var string
     */
    protected $timezone = self::TIMEZONE_DEFAULT;


    protected $dateFieldsToCorrect = array(
                'CH_SUBMITTED_DATE'
            /*
            , 'RFQ_DEADLINE_MGR_UNLOCKED_DATE'
            , 'RFQ_ADVICE_BEFORE_DATE'
            */

        );

    /**
     * Supported timezones
     *
     * @var array
     */
    static protected $timezones = array(
        'Pacific/Midway'            => '(GMT-11:00) Midway Island, Samoa',

        'America/Adak'              => '(GMT-10:00) Hawaii-Aleutian',
        'Etc/GMT-10'                => '(GMT-10:00) Hawaii',

        'Pacific/Marquesas'         => '(GMT-09:30) Marquesas Islands',

        'Pacific/Gambier'           => '(GMT-09:00) Gambier Islands',
        'America/Anchorage'         => '(GMT-09:00) Alaska',

        'America/Ensenada'          => '(GMT-08:00) Tijuana, Baja California',
        'Etc/GMT-8'                 => '(GMT-08:00) Pitcairn Islands',
        'America/Los_Angeles'       => '(GMT-08:00) Pacific Time (US & Canada)',

        'America/Denver'            => '(GMT-07:00) Mountain Time (US & Canada)',
        'America/Chihuahua'         => '(GMT-07:00) Chihuahua, La Paz, Mazatlan',
        'America/Dawson_Creek'      => '(GMT-07:00) Arizona',

        'America/Belize'            => '(GMT-06:00) Saskatchewan, Central America',
        'America/Cancun'            => '(GMT-06:00) Guadalajara, Mexico City, Monterrey',
        'Chile/EasterIsland'        => '(GMT-06:00) Easter Island',
        'America/Chicago'           => '(GMT-06:00) Central Time (US & Canada)',

        'America/New_York'          => '(GMT-05:00) Eastern Time (US & Canada)',
        'America/Havana'            => '(GMT-05:00) Cuba',
        'America/Bogota'            => '(GMT-05:00) Bogota, Lima, Quito, Rio Branco',

        'America/Caracas'           => '(GMT-04:30) Caracas',

        'America/Santiago'          => '(GMT-04:00) Santiago',
        'America/La_Paz'            => '(GMT-04:00) La Paz',
        'Atlantic/Stanley'          => '(GMT-04:00) Faukland Islands',
        'America/Campo_Grande'      => '(GMT-04:00) Brazil',
        'America/Goose_Bay'         => '(GMT-04:00) Atlantic Time (Goose Bay)',
        'America/Glace_Bay'         => '(GMT-04:00) Atlantic Time (Canada)',

        'America/St_Johns'          => '(GMT-03:30) Newfoundland',

        'America/Araguaina'         => '(GMT-03:00) UTC-3',
        'America/Montevideo'        => '(GMT-03:00) Montevideo',
        'America/Miquelon'          => '(GMT-03:00) Miquelon, St. Pierre',
        'America/Godthab'           => '(GMT-03:00) Greenland',
        'America/Argentina/Buenos_Aires' => '(GMT-03:00) Buenos Aires',
        'America/Sao_Paulo'         => '(GMT-03:00) Brasilia',

        'America/Noronha'           => '(GMT-02:00) Mid-Atlantic',

        'Atlantic/Cape_Verde'       => '(GMT-01:00) Cape Verde Is.',
        'Atlantic/Azores'           => '(GMT-01:00) Azores',

        self::TIMEZONE_DEFAULT      => '(GMT+0) Universal Coordinated Time',
        'Europe/Belfast'            => '(GMT+0) Greenwich Mean Time : Belfast',
        'Europe/Dublin'             => '(GMT+0) Greenwich Mean Time : Dublin',
        'Europe/Lisbon'             => '(GMT+0) Greenwich Mean Time : Lisbon',
        'Europe/London'             => '(GMT+0) Greenwich Mean Time : London',
        'Africa/Abidjan'            => '(GMT+0) Monrovia, Reykjavik',

        'Europe/Amsterdam'          => '(GMT+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
        'Europe/Belgrade'           => '(GMT+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague',
        'Europe/Brussels'           => '(GMT+01:00) Brussels, Copenhagen, Madrid, Paris',
        'Africa/Algiers'            => '(GMT+01:00) West Central Africa',
        'Africa/Windhoek'           => '(GMT+01:00) Windhoek',

        'Asia/Beirut'               => '(GMT+02:00) Beirut',
        'Africa/Cairo'              => '(GMT+02:00) Cairo',
        'Asia/Gaza'                 => '(GMT+02:00) Gaza',
        'Africa/Blantyre'           => '(GMT+02:00) Harare, Pretoria',
        'Asia/Jerusalem'            => '(GMT+02:00) Jerusalem',
        'Europe/Minsk'              => '(GMT+02:00) Minsk',
        'Asia/Damascus'             => '(GMT+02:00) Syria',

        'Europe/Moscow'             => '(GMT+03:00) Moscow, St. Petersburg, Volgograd',
        'Africa/Addis_Ababa'        => '(GMT+03:00) Nairobi',

        'Asia/Tehran'               => '(GMT+03:30) Tehran',

        'Asia/Dubai'                => '(GMT+04:00) Abu Dhabi, Muscat',
        'Asia/Yerevan'              => '(GMT+04:00) Yerevan',

        'Asia/Kabul'                => '(GMT+04:30) Kabul',

        'Asia/Yekaterinburg'        => '(GMT+05:00) Ekaterinburg',
        'Asia/Tashkent'             => '(GMT+05:00) Tashkent',

        'Asia/Kolkata'              => '(GMT+05:30) Chennai, Kolkata, Mumbai, New Delhi',

        'Asia/Katmandu'             => '(GMT+05:45) Kathmandu',

        'Asia/Dhaka'                => '(GMT+06:00) Astana, Dhaka',
        'Asia/Novosibirsk'          => '(GMT+06:00) Novosibirsk',
        'Asia/Rangoon'              => '(GMT+06:30) Yangon (Rangoon)',

        'Asia/Bangkok'              => '(GMT+07:00) Bangkok, Hanoi, Jakarta, Manila',   // @todo: isn't Manila at GMT+8 ?
        'Asia/Krasnoyarsk'          => '(GMT+07:00) Krasnoyarsk',

        'Asia/Hong_Kong'            => '(GMT+08:00) Beijing, Chongqing, Hong Kong, Urumqi',
        'Asia/Irkutsk'              => '(GMT+08:00) Irkutsk, Ulaan Bataar',
        'Australia/Perth'           => '(GMT+08:00) Perth',

        'Australia/Eucla'           => '(GMT+08:45) Eucla',

        'Asia/Tokyo'                => '(GMT+09:00) Osaka, Sapporo, Tokyo',
        'Asia/Seoul'                => '(GMT+09:00) Seoul',
        'Asia/Yakutsk'              => '(GMT+09:00) Yakutsk',

        'Australia/Adelaide'        => '(GMT+09:30) Adelaide',
        'Australia/Darwin'          => '(GMT+09:30) Darwin',

        'Australia/Brisbane'        => '(GMT+10:00) Brisbane',
        'Australia/Hobart'          => '(GMT+10:00) Hobart',
        'Asia/Vladivostok'          => '(GMT+10:00) Vladivostok',

        'Australia/Lord_Howe'       => '(GMT+10:30) Lord Howe Island',

        'Etc/GMT+11'                => '(GMT+11:00) Solomon Is., New Caledonia',
        'Asia/Magadan'              => '(GMT+11:00) Magadan',

        'Pacific/Norfolk'           => '(GMT+11:30) Norfolk Island',

        'Asia/Anadyr'               => '(GMT+12:00) Anadyr, Kamchatka',
        'Pacific/Auckland'          => '(GMT+12:00) Auckland, Wellington',
        'Etc/GMT+12'                => '(GMT+12:00) Fiji, Kamchatka, Marshall Is.',

        'Pacific/Chatham'           => '(GMT+12:45) Chatham Islands',

        'Pacific/Tongatapu'         => '(GMT+13:00) Nuku Alofa',

        'Pacific/Kiritimati'        => '(GMT+14:00) Kiritimati'
    );

    protected $vesselModel;
    protected $supplierModel;
    protected $pageSize = 50;
    protected $buyerbranch;
    protected $parentbranch;
    protected $includechildren = false;

    /**
     * @var Application_Model_Transaction
     */
    protected $transactionModel = null;

    public function init()
    {
        parent::init();
        $config = Zend_Registry::get('config');

        /*
         * Commented by Claudio because 
         *  - security reasons: with a GET param we are allowing a user to flush memcache completely?
         *  - flush the whole memcache is craaaaaaazy! just flush the key you need, not everything!
        if ($_REQUEST["nocache"] == "1") {
            $config = Zend_Registry::get('config');
    		$memcache = new Memcache();
    		$memcache->connect(	$config->memcache->server->host, $config->memcache->server->port);
            //We'll just flush memcache, rather than 'disable' it
            $memcache->flush();
        }
        */

        //Force auth for all actions
        $forceShipmate = (array_key_exists('forceTab',$this->params )) ? ($this->params['forceTab'] == 'shipmate') ? true : false : false;
        $hasNewLayout = ($this->params['action'] === 'new');
        $casRest = Myshipserv_CAS_CasRest::getInstance();

        if ($hasNewLayout === true) {
            //If this is the new layout, the priority login is pages
            if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_PAGES);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_PAGES);
            } else if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_TRADENET);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_TRADENET);
            } else {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_PAGES);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_PAGES);
            }
        } else {
            //If this is the old layout the priority login is tradenet
            if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_TRADENET);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_TRADENET);
            } else  if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_PAGES);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_PAGES);
            } else {
                $this->forceAuthentication(true,Myshipserv_CAS_CasRest::LOGIN_TRADENET);
                $this->initialiseUserFromCAS(Myshipserv_CAS_CasRest::LOGIN_TRADENET); 
            }
        }

        if ($this->user && $hasNewLayout === true && $this->user->canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON) === false) {
            throw new Myshipserv_Exception_MessagedException("Insufficient user rights", 403);
        }

        /*Use ESSM layout for now*/

        //S17710 Control layout per logged in as TradeNet or Pages
        if ($this->userTradenet->isPagesUser === true) {
            //If we are logged in as Pages User and we are on essm layout, redirect
            if ($this->params['action'] == 'index') {
                $redirect = ($forceShipmate === true) ? '/shipmate/txnmon/new' : '/txnmon/new';
                $this->redirect($redirect);
            }

        } else {
            //If we are loged in as Tradenet user but the layout is new, redirect to essm layout
            if ($this->params['action'] == 'new') {
                $redirect = ($forceShipmate === true) ? '/shipmate/txnmon' : '/txnmon';
                $this->redirect($redirect);
            }
        }

        $this->_helper->layout->setLayout('essm');

        $this->vesselModel = new Application_Model_Vessel();
        $this->buyerModel = new Application_Model_Buyer();
        $this->supplierModel = new Application_Model_Supplier();
        $this->transactionModel = new Application_Model_Transaction();
        $this->view->cas = new Myshipserv_View_Helper_Cas();

        //Insert default values into request
        $today = new DateTime();
        $todayFormatted = $today->format('d-M-Y');

        $_REQUEST = $_REQUEST + array(
            'supplierbranch'    => '0',
            'vessel'            => 'all',
            'daterange'         => '0',
            'datefrom'          => $todayFormatted,
            'dateto'            => $todayFormatted,
            'suppliertype'      => 'all',
            'doctype'           => 'all',
            'buyerreference'    => '',
            'searchtype'        => '1',
            'page'              => '1',
            'sortfield'         => 'submitted_date',
            'buyercontact'      => 'ALL',
            'timezone'          => 'UTC',
            'buyerbranch'       => ''
        );

        $isRFQDeadlineMgr = ($this->userTradenet && $this->userTradenet->isRFQDeadlineMgr) || ($this->user && (bool)$this->user->userRow['PSU_RFQ_DEADLINE_MGR']);
        $isRFQDeadlineAllowed = ($this->userTradenet && $this->userTradenet->isRFQDeadlineAllowed);

        if ($this->userTradenet->isPagesUser === true) {
           $isRFQDeadlineMgr = $this->userTradenet->canAccessTransactionMonitorAdm();
        }
        /*
        if ($isRFQDeadlineAllowed) {
            if (!array_key_exists('qotdeadline', $_REQUEST)) {
                $_REQUEST['qotdeadline'] = $todayFormatted;
            }
        }
        */

        //If date range includes today, update model's memcache expiration to 5 minutes, to provide freshness for today
        if ($_REQUEST['dateto'] == $todayFormatted || $_REQUEST['datefrom'] == $todayFormatted) {
            Application_Model_Transaction::setMemcacheExpiration(300);
            Application_Model_Vessel::setMemcacheExpiration(300);
            Application_Model_Supplier::setMemcacheExpiration(300);
        }

        if ($forceShipmate && $this->userTradenet->isPagesUser === true) {
                $relatedBranchCodes = array();
                foreach ($this->buyerModel->fetchAllBuyerBranchNames() as $branch) {
                    $relatedBranchCodes[] = array('BYB_NAME' => $branch['BYB_NAME'] . ' (' . $branch['BYB_BRANCH_CODE'] . ')', 'BYB_BRANCH_CODE' => $branch['BYB_BRANCH_CODE']);
                }
                $this->view->buyercompanies = $relatedBranchCodes;
        } else {            
            if ($this->userTradenet->isAdmin || ($this->user && $this->user->isShipservUser())) {
                if (!$forceShipmate && $this->userTradenet->isPagesUser === true) {
                    if ($this->getActiveCompany()->type == 'b') {
                        $relatedBranchCodes = array();
                        foreach (Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_TXNMON) as $branch) {
                            $relatedBranchCodes[] = array(
                                'BYB_BRANCH_CODE' => $branch['id'],
                                'BYB_NAME' => $branch['name'],
                                'PARENT_BRANCH_CODE' => null, //apparnetly this is not needed in pages-user mode
                                'PUC_IS_DEFAULT' => (Int) $branch['default']
                            );
                        }
                        $this->view->buyercompanies = $relatedBranchCodes;
                    } else {
                    	if ($this->params['action'] !== 'resendemail' && $this->params['action'] !== 'getresendemail') {
	                        if ($this->user && $this->user->isShipservUser()) {
	                            // DE6740 We should redirect to a buyer org instead of throwing this message
	                            $redirect = ($this->params['action'] === 'new') ? '/shipmate/txnmon/new' : '/shipmate/txnmon';
	                            //$this->redirect('/user/switch-company?tnid=23404&redirect='.urlencode($redirect));
	                            $this->redirect($redirect);
	                            die();
	                        } else  {
	                            throw new Myshipserv_Exception_MessagedException("Sorry, the selected compamy is not Buyer Org", 200);
	                        }
                    	}
                    }
                } else {
                    $relatedBranchCodes = array();
                    foreach ($this->buyerModel->fetchAllBuyerBranchNames() as $branch) {
                        $relatedBranchCodes[] = array('BYB_NAME' => $branch['BYB_NAME'] . ' (' . $branch['BYB_BRANCH_CODE'] . ')', 'BYB_BRANCH_CODE' => $branch['BYB_BRANCH_CODE']);
                    }
                    $this->view->buyercompanies = $relatedBranchCodes;
                }
            } else {
                $relatedBranchCodes = array();
                if (!$this->userTradenet->isPagesUser) {
                    //If not admin check to see if the branch has related branches (parent or child) and create a list so they can select one
                    // check if user is viewundercontract != 0 [DE4298]
                    if ($this->userTradenet->__get('viewundercontract')) {
                        $relatedBranchCodes = $this->buyerModel->fetchAllRelatedBranches($this->userTradenet->branchcode);
                    }
                } else {
                    foreach (Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_TXNMON) as $branch) {
                        $relatedBranchCodes[] = array(
                            'BYB_BRANCH_CODE' => $branch['id'],
                            'BYB_NAME' => $branch['name'],
                            'PARENT_BRANCH_CODE' => null, //apparnetly this is not needed in pages-user mode
                            'PUC_IS_DEFAULT' => (Int) $branch['default']
                        );
                    }
                }
                $this->view->buyercompanies = $relatedBranchCodes;
            }

            $this->parentbranch = ($_REQUEST['buyerbranch'] == 'ALL') ? $relatedBranchCodes[0]["PARENT_BRANCH_CODE"] : $relatedBranchCodes[0]["BYB_BRANCH_CODE"];
		}

       /* } */

        $selectedBranch = ($_REQUEST['buyerbranch'] == 'ALL')? $this->parentbranch : (int) $_REQUEST['buyerbranch'];

        $tnUser = new Shipserv_Oracle_User_Tradenet();
        $this->view->otherUser = ($_REQUEST['childbranch']=="1" || $this->userTradenet->isPagesUser==true)?$tnUser->fetchOneUserByBybBranchCode($selectedBranch):false;
        $this->view->childBranch = ($_REQUEST['childbranch']=="1" || $this->userTradenet->isPagesUser==true);

       /* if($_REQUEST['buyerbranch'] == 'ALL' || $_REQUEST['childbranch']!="1") { */
        if ($_REQUEST['buyerbranch'] == 'ALL') { 
            $this->buyerbranch = $this->parentbranch;
            $this->includechildren = ($_REQUEST['childbranch']=="1");
        } else {
            $this->buyerbranch = _htmlspecialchars($_REQUEST['buyerbranch']);
        }

        //Validate the specified buyer branch to ensure the user is allowed to view it
        if (!$this->userTradenet->isAdmin && $this->buyerbranch != $this->userTradenet->branchcode) {
            $allowableBranch = false;
            foreach ((array)$relatedBranchCodes as $relBranch) {
                if ($relBranch["BYB_BRANCH_CODE"] == $this->buyerbranch) {
                    $allowableBranch = true;
                    break;
                }
            }
            //If the given code is not associated with the user, just set it to their default code and move on
            if (!$allowableBranch) {
                if( $this->buyerbranch == "" )
                $this->buyerbranch = $this->userTradenet->branchcode;
            }
        }



        //Sanitise sortdirection param as we'll be injecting it directly into SQL, not as a query parameter
        if ($_REQUEST['sortdirection'] != 'ASC') { 
            $_REQUEST['sortdirection'] = 'DESC'; 
        }

        if ($forceShipmate === true && ($this->user && $this->user->isShipservUser()) === false) {
            $redirect = ($this->params['action'] === 'new') ? '/txnmon/new' : '/txnmon';
            $this->redirect($redirect);
        }

        // refactored by Yuriy Akopov on 2014-08-27
        $this->timezone = $this->_getParam(self::PARAM_TIMEZONE, self::TIMEZONE_DEFAULT);


        $this->view->timezone  = $this->timezone;
        $this->view->timezones = self::$timezones;
        // changes by Yuriy Akopov end
        
        $this->view->isRFQDeadlineAllowed = $isRFQDeadlineAllowed;
        $this->view->isRFQDeadlineMgr = $isRFQDeadlineMgr;
        
        $this->view->config = $this->config;
        $this->view->forceShipmate =  $forceShipmate;
        $this->view->isPagesUser = $this->userTradenet->isPagesUser;

    }

    
    public function indexAction()
    {
    	//var_dump($this->userTradenet);
    	//Inital page with no results - don't need to load or show suppliers and vessels yet
        
        if ($this->user) {
            $this->user->logActivity(Shipserv_User_Activity::TRANSACTION_MONITOR_LAUNCH, 'PAGES_USER', $this->user->userId, $this->user->email);
        }
        
        $isShipMate = ($this->user) ? $this->user->isShipservUser() : false;

    	if ($this->userTradenet->canAccessTransactionMonitor() === false  && $isShipMate == false)
    	{
    		$this->_helper->layout->setLayout('essm');

            $options =  Shipserv_Profile_Menuoptions::getInstance()->getAnalyseTabOptions();
             if (count($options) > 0) {
                $this->redirect($options[0]['href']);
                die();
             } else {
                $this->redirect('/search');
                die();
             }
    		//throw new Myshipserv_Exception_MessagedException("Sorry, you don't have the necessary user privileges to access this page.", 200, array('user' => $this->userTradenet));
    	}

    	$this->view->user = $this->userTradenet;
    }


    public function newAction()
    {
        //Inital page with no results - don't need to load or show suppliers and vessels yet        
        if ($this->user) {
             $this->user->logActivity(Shipserv_User_Activity::TRANSACTION_MONITOR_LAUNCH, 'PAGES_USER', $this->user->userId, $this->user->email);
        }

        //$this->view->user = Shipserv_User::isLoggedIn();
        /* For this URL we have to change to an another laout */

        $isShipMate = ($this->user) ? $this->user->isShipservUser() : false;

        $this->view->fromPages = true;
        $this->view->user = $this->user;
        $this->view->userTradenet = $this->userTradenet;
        /*
            It was requisted to hide the selector, but much later it was reported as a defect, it does not show, so I remove the hide now
            $this->view->hideCompanyDetail = !$isShipMate;
        */
        $this->_helper->viewRenderer('index');
        $this->_helper->layout->setLayout('default');

        if ($this->userTradenet->canAccessTransactionMonitor() === false && $isShipMate == false) {
            $this->_helper->layout->setLayout('default');

            $options =  Shipserv_Profile_Menuoptions::getInstance()->getAnalyseTabOptions();
            if (count($options) > 0) {
                $this->redirect($options[0]['href']);
                die();
            } else {
                $this->redirect('/search');
                die();
            }

            //throw new Myshipserv_Exception_MessagedException("Sorry, you don't have the necessary user privileges to access this page.", 200, array('user' => $this->user));
        }
    }

    
    /**
    * Default search action
    */
    public function searchAction()
    {
        $this->getSearchResult();
    }

    /**
    * Search action with the new layout
    */
    public function nsearchAction()
    {
        $this->getSearchResult(true);
    }

    /*
    * Called by searchAction or Nsearch action, do the search, select layout
    * @param bool $newLayout, if true, new pages look and feel layout is used
    */
    protected function getSearchResult($newLayout = false)
    {

        $isShipMate = ($this->user) ? $this->user->isShipservUser() : false;

    	if ($this->userTradenet->canAccessTransactionMonitor() === false && $isShipMate == false) {
			$this->_helper->layout->setLayout('essm');
    		throw new Myshipserv_Exception_MessagedException("Sorry, you don't have the necessary user privileges to access this page.", 403);
    	}

        //Adding new access right to Pages User for Deadline Control, if  it is set in TradingAccount tab
        $isRFQDeadlineAllowed = ($this->userTradenet && $this->userTradenet->isRFQDeadlineAllowed);
        $isRFQDeadlineMgr = ($this->userTradenet && $this->userTradenet->isRFQDeadlineMgr) || ($this->user && (bool)$this->user->userRow['PSU_RFQ_DEADLINE_MGR']);
        if ($this->userTradenet->isPagesUser === true) {
           $isRFQDeadlineMgr = $this->userTradenet->canAccessTransactionMonitorAdm();
        }
        $this->view->isRFQDeadlineAllowed = $isRFQDeadlineAllowed;
        $this->view->isRFQDeadlineMgr = $isRFQDeadlineMgr;

        $this->view->hideTimeZone = false;


        //Redirect GET requests to index, as a search is not being performed (maybe they just copied / bookmarked the URL)
        //if ($this->_request->isGet()) { $this->redirect('/txnmon'); }

        /**
         * @params
         * daterange (1 day, 1 week, etc) OR datefrom & dateto (yyyy-mm-dd)
         * suppliertype (all, startsupplier, trandenetsupplier)
         * doctype (PO, RFQ, etc)
         * status (all, ACC, CON, DEC, NREP, OPN, SENT, NEW, QUO)
         * buyerreference (free text)
         * searchtype (startwith or anywhere)
         * pageno (int or not present = 1)
         */

        //Add in some parameter checking for e.g. status must exist in group of all, ACC, CON etc. later

        //Format date range
        if ($_REQUEST["daterange"] != '0') {
            $fromdate = new DateTime();
            $fromdate->modify('-'.$_REQUEST["daterange"]);
            $todate = new DateTime();
        } else {
            $fromdate = new DateTime($_REQUEST['datefrom']);
            $todate = new DateTime($_REQUEST['dateto']);
        }

        $fromdate = new DateTime($_REQUEST['datefrom']);
        $todate = new DateTime($_REQUEST['dateto']);


        if ($this->view->isRFQDeadlineAllowed) {
            $fromdate->sub(new DateInterval('PT8H'));
            $todate->sub(new DateInterval('PT8H'));
        }
        
        //Transform timestamps if needed

        // fixed and improved by Yuriy Akopov on 2014-08-27
        $utc = new DateTimeZone(self::TIMEZONE_DEFAULT);
        if ($this->timezone !== self::TIMEZONE_DEFAULT) {
            $selectedTimezone = new DateTimeZone($this->timezone);
        }

        //Also we need timezome switch for Keppel
        if ($this->view->isRFQDeadlineAllowed) {
            $bb = $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->buyerbranch);
            $timeZoneDiff = (int)$bb->bybTimeDifferenceFromGmt;
            $this->timezone = self::PARAM_TIMEZONE;
            $timeZoneName = $this->getTimeZoneNameByOffset($timeZoneDiff);
            if ($timeZoneName !== false) {
                $selectedTimezone = new DateTimeZone($timeZoneName);
                $this->view->timeZoneName = $timeZoneName;
            }
        } else {
            $timeZoneDiff = 0;
        }

        $qotdeadline = (array_key_exists('qotdeadline', $_REQUEST) && $_REQUEST['qotdeadline'] != '') ? new DateTime($_REQUEST['qotdeadline']) : null;

        //Get the total number of results and calculate some page numbers
        $numResults = $this->view->numResults = $this->transactionModel->getSearchCount($this->buyerbranch, $fromdate, $todate, $qotdeadline, $_REQUEST, $this->userTradenet, $this->includechildren);
        $numPages = $this->view->numPages = ceil($numResults / $this->pageSize);
        $currentPage = $this->view->currentPage = intval($_REQUEST['page']);


        $currPageStart = $this->pageSize * ($currentPage - 1) + 1; //1,51,101 etc (with pagesize of 50)
        $currPageEnd = $this->pageSize * $currentPage;//50,100,150 etc (with pagesize of 50)
        $this->view->timeZoneDiff = $timeZoneDiff;

        //Do search and assign to view
        $this->view->results = $this->transactionModel->search($this->buyerbranch, $fromdate, $todate, $qotdeadline, $currPageStart, $currPageEnd, $_REQUEST, $this->userTradenet, $this->includechildren, $this->view->isRFQDeadlineAllowed, $timeZoneDiff);



        foreach($this->view->results as &$result) {
            foreach ($this->dateFieldsToCorrect as  $field)
            {
                if (array_key_exists($field, $result))
                {
                    if ($result[$field])
                    {
                        $datetime = new DateTime($result[$field], $utc);
                        if ($this->timezone !== self::TIMEZONE_DEFAULT) {
                            $datetime->setTimezone($selectedTimezone);
                        }
                        $result[$field] = strtoupper($datetime->format('d-M-Y H:i:s'));
                    }
                }
            }
        }
        unset($result);
        // changes by Yuriy Akopov end

        //Get vessels dropdown data
        $this->view->vessels = $this->vesselModel->fetchAllVesselNames($this->userTradenet,  $_REQUEST['documenttype'], $this->buyerbranch, $fromdate, $todate, $this->includechildren);
        //Get buyer contact names dropdown data
        $this->view->buyerContacts = $this->buyerModel->fetchBuyerNamesForSearch($_REQUEST['documenttype'], $this->buyerbranch, $fromdate, $todate, $_REQUEST['supplierbranch'], $this->includechildren);
        //Get supplier names dropdown data
        //DE6679 Included the document type so that the cache is reset when it changes
        $this->view->supplierNames = $this->supplierModel->fetchSupplierNamesForFilter($this->userTradenet, $_REQUEST['documenttype'], $this->buyerbranch, $fromdate, $todate, $this->includechildren);

        //Using the index view renderer for both / and /search

       //If we come from pages, render a different layout
        $this->view->user = $this->user;
        $this->view->userTradenet = $this->userTradenet;

        if ( $newLayout ) {
            $this->view->fromPages = true;
            //$this->view->hideCompanyDetail = true;

            $this->_helper->layout->setLayout('default');
        } else {

            $this->_helper->layout->setLayout('essm');
        }
        $this->_helper->viewRenderer('index');
    }

    public function resendemailAction() {
        // if parameters are not sent, thow 404
        if (isset($_REQUEST['internal_ref_no']) &&
            isset($_REQUEST['internal_ref_no']) && 
            isset($_REQUEST['spb_branch_code']) && 
            isset($_REQUEST['atttype']) && 
            (
                isset($_REQUEST['email1']) || 
                isset($_REQUEST['email2'])
            )
        ) {
            $this->transactionModel->resendEmail($_REQUEST['internal_ref_no'], $_REQUEST['spb_branch_code'], $_REQUEST['atttype'], false, $_REQUEST['email1'], $_REQUEST['email2']);
            $this->_helper->json((array)array('status' => 'ok'));
        } else {
            $this->_helper->json((array)array(
                'status' => 'error',
                'message' => 'parameters are missing'
            ));
        }
    }

    public function getresendemailAction() {
        $email = $this->transactionModel->getResendEmailAddress($_REQUEST["doc_type"], $_REQUEST['internal_ref_no'], $_REQUEST['spb_branch_code'], $_REQUEST['byb_branch_code']);
    	if( $email == false ) $email = "";
        $this->_helper->json((array)array('email' => $email));
    }


    private function getActiveCompany()
    {
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        return $sessionActiveCompany;
    }

    public function cancelrfqemailAction() {
        $this->transactionModel->cancelEmail($_REQUEST['internal_ref_no'], $_REQUEST['spb_branch_code'], false, $_REQUEST['email1'], $_REQUEST['email2']);
        $this->_helper->json((array)array('status' => 'ok'));
    }

    public function getcancelrfqemailAction() {
        $email = $this->transactionModel->getCancelRfqAddress($_REQUEST['internal_ref_no'], $_REQUEST['spb_branch_code'], $_REQUEST['byb_branch_code']);
        if( $email == false ) $email = "";
        $this->_helper->json((array)array('email' => $email));
    }


    /**
    * Unlock the RFQ by RFQ Internal Ref no , or doctype 'QOT', and Quot internal ref no
    */
    public function unlockDocumentAction()
    {
        $id = (array_key_exists('id', $this->params)) ? (int)$this->params['id'] : null;
        if ($id) {
            $isQot = (array_key_exists('doctype', $this->params)) ? ($this->params['doctype'] == 'QOT') : false;

            $timeZoneDiff = 0;
            $bb = $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->buyerbranch);
            $timeZoneDiff = (int)$bb->bybTimeDifferenceFromGmt;
           
            $result = $this->transactionModel->unlockTransaction($id, $isQot, false, $timeZoneDiff);
                if ($result)
                {
                    $this->_helper->json((array)array(
                    'status' => 'ok'
                    ,'createdDate' => $result
                    ));
                } else {
                    throw new Myshipserv_Exception_MessagedException("Something went wrong.");
                }
            } else {
                throw new Myshipserv_Exception_MessagedException("Parameter ID is missing.");
            }
    }

    /**
    * Returns the closest timezone name based on the array defined, If not found, returns false
    * @param integer $timeZoneOffset
    * @return string or false
    */
    protected function getTimeZoneNameByOffset( $timeZoneOffset )
    {
        if ($timeZoneOffset == 0) {
           $zoneText = 'GMT+0';
        } else {
           $zoneText = ($timeZoneOffset > 0) ? 'GMT+'.sprintf('%02d',$timeZoneOffset).':'.sprintf('%02d',0.6 * (abs($timeZoneOffset)*100 % 100)) : 'GMT-'.sprintf('%02d',abs($timeZoneOffset)).':'.sprintf('%02d',0.6 * (abs($timeZoneOffset)*100 % 100));
        }

        foreach (self::$timezones as $key => $value) {
            if (strpos($value, $zoneText) !== false) {
                return $key;
            } 
        }
        return false;
    }


}
