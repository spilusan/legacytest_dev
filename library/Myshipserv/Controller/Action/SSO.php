<?php
/**
 * Myshipserv_Controller_Action
 * Base controller for extending, adds useful helper methods
 */

abstract class Myshipserv_Controller_Action_SSO extends Zend_Controller_Action
{

    protected $user;
    protected $userTradenet;
    protected $isAuthenticated = false;

    public function init()
    {
        $config = Zend_Registry::get('config');
        $this->view->loginUrl = $config->shipserv->services->sso->login->url;
        $this->view->logoutUrl = $config->shipserv->services->sso->logout->url;

        // once CAS's been initiated, then initialise user from cas
        $this->initialiseUserFromCAS();

        $this->view->executionStarts = new DateTime();
    }

    public function initialiseUserFromCAS($logType=Myshipserv_CAS_CasRest::LOGIN_ALL)
    {

    	$this->logger = new Myshipserv_Logger_File('cas/debugger');
    	//$this->logger->log('initialiseUserFromCAS() called on ' . $_SERVER['REQUEST_URI'], print_r(xdebug_get_function_stack(), true));
    	//$this->logger->log('initialiseUserFromCAS() called on ' . $_SERVER['REQUEST_URI']);

    	// for each page load, unfortunately, we need to check if tradenet user is logged in or not.
        $config = Zend_Registry::get('config');

        //If we are using cas REST
        $casRest = Myshipserv_CAS_CasRest::getInstance();

        if ($casRest->casCheckLoggedIn($logType)) {
            //If we are logged in as a pages user
            $user = Shipserv_User::isLoggedIn($logType);
            $this->user = $user;
            if ($user) {
                //We are logged in as Pages, If user is null, logged in only as TradeNet
                $this->userTradenet = $user->getTradenetUser();
                $this->isAuthenticated = true;  
            } else {
                //Logged in as a TradeNet user VIA CAS REST
                $tnUser = new Shipserv_Oracle_User_Tradenet();
                $this->userTradenet = $tnUser->fetchUserByName($casRest->getUserName());
                $this->isAuthenticated = true;
                $this->userTradenet->roles = array(); //TODO implement adding these values by Oracle Package call
                $this->view->user = $this->user;
                $this->view->userTradenet = $this->userTradenet;
                $this->view->isAuthenticated = $this->isAuthenticated;
                // setting cookie loggedIn=true
                setcookie('loggedIn', 'true', time()+86400, '/', $_SERVER['HTTP_HOST']);
            }
        } 
    }

    public function authenticateUsingCAS()
    {
    	$this->forceAuthentication();
    }

    public function forceAuthentication( $skipRedirection = false, $logType=Myshipserv_CAS_CasRest::LOGIN_ALL)
    {

    	$config = Zend_Registry::get('config');

        $cas = new Myshipserv_View_Helper_Cas();
        $casRest = Myshipserv_CAS_CasRest::getInstance();
        if ($casRest->casCheckLoggedIn($logType) === false) {
            if ($logType === Myshipserv_CAS_CasRest::LOGIN_TRADENET) {
                $urlForCASLogin = $cas->getCasRestTradenetLogin(true);
            } else {
                $urlForCASLogin = $cas->getCasRestLogin(true);
            }
            $this->redirect($urlForCASLogin);
        }

        //$this->initialiseUserFromCAS();

        if($skipRedirection === false)
        {
	        if( strstr($_SERVER['REQUEST_URI'], 'bridge') === false &&  $this->user === false && $this->userTradenet !== false  )
	        {
	        	$this->redirect('/LoginToSystem');
	        	exit;
	        }
        }
    }

    public function postLogin($user)
    {
    	// check if user had selected a company on previous session.
    	// this is stored in cookie named below.
    	// if there's such cookie, we'll initialise the default company
    	// for this user

    	// initialise the session
    	$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();

    	if( isset( $_COOKIE['lastSelectedCompany'] ) )
    	{
    		// check if this user is part of the last selected company
    		$lastSelectedCompany = json_decode( $_COOKIE['lastSelectedCompany'] );

    		// go ahead store the default company to session if user is
    		// belong to the company. if not we'll choose the first
    		// company -- see below
    		if( $user->isPartOfCompany( $lastSelectedCompany->tnid ) )
    		{
    			$activeCompany->type = $lastSelectedCompany->type;
	    		$activeCompany->id = $lastSelectedCompany->tnid;
	    		$activeCompany->company = $lastSelectedCompany->type=='b' ? Shipserv_Buyer::getInstanceById( $lastSelectedCompany->tnid ):Shipserv_Supplier::fetch( $lastSelectedCompany->tnid );
                Myshipserv_Helper_Session::updateReportTradingAccounts();
    		}
    	}

        // fetch companies
        $companies = $user->fetchCompanies(true);

        // if user is part of companies, then default them to the first one and store it on the cookie
        if( !isset($activeCompany->id) && $companies[0]['id'] != "" )
        {
            // store the first company to the session
            $activeCompany->type=$companies[0]['type'];
            $activeCompany->id = $companies[0]['id'];
            $activeCompany->company = $companies[0]['type']=='b' ? Shipserv_Buyer::getInstanceById( $companies[0]['id'] ):Shipserv_Supplier::fetch($companies[0]['id']);
            Myshipserv_Helper_Session::updateReportTradingAccounts();
            // store this to cookie
            setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => $companies[0]['type'])), time()+60*60*24*30, "/");
        }

        // log this activity
        $user->logActivity(Shipserv_User_Activity::USER_LOGIN, 'PAGES_USER', $user->userId, $user->email);
				
    }


    public function getUrlToCasLogin($r, $ssl = false)
    {
        $ssl = true; //Force HTTPS for story moving to HTTPS
    	if( $ssl == true )
    	{
	    	if( strstr($r, 'http://') === false )
	    	{
	    		$r = 'https://' . $_SERVER['HTTP_HOST'] . $r;
	    	}
    	}
    	else
    	{
    		if( strstr($r, 'http://') === false )
    		{
    			$r = 'http://' . $_SERVER['HTTP_HOST'] . $r;
    		}
    	}

	//$url = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/cas/login?x=0&pageLayout=new&service=' . urlencode($r);


      $helper = new Myshipserv_View_Helper_Cas;

      //$url = $helper->getPagesLogin() . urlencode($r);
      $url = $helper->getCasRestLogin() . '&service=' . urlencode($r);
      return $url;
    }

    public function redirectToCasLogin()
    {
    	$url = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/cas/login?x=0&pageLayout=new&service=' . urlencode($_SERVER['REQUEST_URI']);
    	return $url;
    }

}
