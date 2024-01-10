<?php
/**
 * Myshipserv_Controller_Action
 * Base controller for extending, adds useful helper methods
 */
abstract class Myshipserv_Controller_Action extends Myshipserv_Controller_Action_SSO
{
	// some widely used parameter keys
	const
		PARAM_PAGE_NO   = 'pageNo',
		PARAM_PAGE_SIZE = 'pageSize',
		PARAM_ORDER_BY  = 'orderBy',
		PARAM_ORDER_DIR = 'orderDir'
	;

	/**
	 * @var Shipserv_User
	 */
	protected $user;

	/**
	 * @var Zend_Db_Adapter_Oracle
	 */
	static protected $db;

	/**
	 * @var Zend_Session_Namespace
	 */
	private $sessionNamespace;

	/**
	 * @var Zend_Session_Namespace
	 */
	public $requestCacheControl;

	/**
	 * @var array
	 */
	public $params;


	
	public function xssValidationForArray(&$val, $index) {
		$val = strip_tags($val);
	}

	public function init()
	{

		parent::init();

        //context needed for some js loading decisions
        $this->view->isCorporate = false;

		// namespacing for flashmessage
		$this->sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Flashmessages');
		$this->requestCacheControl = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_DeliverFreshContent');

		// initialise flashmessage
		if (!isset($this->sessionNamespace->errorMessages)) {
			$this->sessionNamespace->errorMessages = array();
		}

		if (!isset($this->sessionNamespace->successMessages)) {
			$this->sessionNamespace->successMessages = array();
		}

		// store config files
		$this->config = Zend_Registry::get('options');

		// store cookie manager
		$this->cookieManager = $this->getHelper('Cookie');


		// sanitise params and store it
		$filter = new Zend_Filter_StripTags();

		/* SANITIZING PARAMETERS 
		 * !!!! WARNING: this sanitization doesn't clean double and single quotes:
		 * - Why we apply strip_tags and not htmlspecialchars? 
		 *    - Because you may want to use one of the following characters in as string from $_GET/$_POST: <>&"'
		 *    - But we assume you never want to pass html through $_GET/$_POST
		 * - SO: you must be still very careful also when using $this->params or $this->getRequest()->getParam(), because for instance:
		 *    - If you don't use proper sql prepare statement (that is, you concatenate you $var directly into your query)
		 *      you are vulnerable to sql injections
		 *    - If you don't escape in js and php the GET/POST vars, or the strings coming from DB, you are still vulnerable to XSS:
		 *        - Stored xss example:
		 *        //$something_from_the_db is a malicious string that attacker has inserted into db (for simply instance submitting his address)
		 *        //for instance let's say that $something_from_the_db = "blabla ' onload='alert(\'malicious js script\')'"
		 *        echo "<anytag value='$something_from_the_db'></anytag>"; 
		 *        - Reflected xss example:
		 *        //Attacker call a shipser.com/page?var=' onload='alert(\'malicious js script\')'
 		 *        echo "<anytag value='" . $_GET['var]' . "></anytag>";    
 		 * - So whould you use also htmlspecialchars? Better than nothing, but...
 		 *    - You should always use it as this htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
 		 *    - For not writing that same things all the times, you'll find a handy _htmlspecialchars global func defined into Bootstrap.php
		 */ 
		//   
		foreach((array)$this->_getAllParams() as $key => $param)
		{
			if( is_array($param) === true )
			{
				array_walk_recursive($param, array($this, 'xssValidationForArray'));
				$sanitisedParams[$key] = $param;
			}
			else if( is_string($param) === true )
			{
				$sanitisedParams[$key] = strip_tags($param);
			}
			//_getAllParams() returns also action name, controller name, error handler name, module name, and maybe other zend things that might broken correct execution
			if (array_key_exists($key, $_REQUEST)) {
			    $this->getRequest()->setParam($key, $sanitisedParams[$key]);
			}			
		}

		// put the sanitised params
		$this->params = $sanitisedParams;
		/*
        $this->view->assign(array(
            'params' => $sanitisedParams
            , 'config' => $this->config
            , 'userInsideShipServIP' => $this->isIpInRange()
        ));
        */
		$this->view->params = $sanitisedParams;
		$this->view->config = $this->config;

		$this->view->userInsideShipServIP = $this->isIpInRange();

		// this should solve locked session issue
		// should improve performance of server too.
		if( $this->config['shipserv']['pages']['performance']['avoidSessionLocking'] == 1 && in_array($this->params['controller'], array('user')) === false )
		{
			session_write_close();
		}

		// store user details
		$this->user = $this->view->user	= Shipserv_User::isLoggedIn();
		$this->userTradenet = ($this->view->user!==false)? $this->view->userTradenet = $this->user->getTradenetUser(): false;

		//var_dump($this->user);
		//var_dump($this->user);
		//die();

		// db connection
		$this->db = $this->getInvokeArg('bootstrap')->getResource('db');

		foreach($this->config['shipserv']['shipmate'] as $x)
		{
			foreach($x as $p)
			{
				$tmp = explode(",", $p);
				$pages[$tmp[0]] = "/help/proxy?u=" . $this->view->uri()->obfuscate($tmp[1]);
			}
		}

		
		$this->view->customPages = $pages;
		// check if user had agreed on the terms and condition. If they haven't, then redirect them
		$isTradenetLoginPage = ($this->getRequest()->getParam('controller') === 'user' && $this->getRequest()->getParam('action') === 'login' && $this->getRequest()->getParam('pageLayout') !== 'new');
		if ($this->user !== false && $isTradenetLoginPage === false) {
			$permittedArea = array(
				'agreement',
				'logout',
				'login',
				'register-login',
				'contact',
				'buyerfaq',
				'list-company-type',
				'list-job-type',
				'locations',
				'list-company-annual-budget',
				'list-vessel-type',
				'complete-profile',
				'get-tnid-selector-content',
				'get-menu-options-to-display'
			);
			
			if ($this->user->hasCompletedDetailedInformation() === false && in_array($this->params['action'], $permittedArea) == false) {
				//benc=1&amp;registerRedirectUrl=2f736561726368
				$this->redirect("/user/register-login/update?benc=1&registerRedirectUrl=" . $this->view->uri()->obfuscate($_SERVER['REQUEST_URI']));
			} else if ($this->user->hasAgreedLatestAgreement() === false && in_array($this->params['action'], $permittedArea) == false) {
				$this->redirect("/profile/agreement?redirect=" . $_SERVER['REQUEST_URI']);
			}
		}
	}

	public function preDispatch()
	{
		$requestCacheControl = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_DeliverFreshContent');

		Shipserv_User::initialiseUserCompany();
		// deliver fresh copy - no cache

		if( $requestCacheControl->noCache == true && $this->getRequest()->isXmlHttpRequest() == false )
		{
			$requestCacheControl->noCache = false;
			$this->getResponse()->clearHeaders()
				->setHeader('Expires', 'Thu, 19 Nov 1981 08:52:00 GMT', true)
				->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0 ', true)
				->setHeader('Pragma', 'no-cache', true);
		}

	}

	public function postDispatch() {
		/*
        // disabled by elvir, because it's breaking the login
        unset($this->view->config);
        unset($this->view->params);
        unset($this->view->config);
        unset($this->view->user);
        unset($this->view->userTradenet);
        unset($this->view->userInsideShipServIP);
        unset($this->view->customPages);
        unset($this->view->loginUrl);
        unset($this->view->logoutUrl);
        unset($this->view->isAuthenticated);
        unset($this->view->activeCompany);
        */
	}

	protected function addErrorMessage($message) {
		$errorMessages = $this->sessionNamespace->errorMessages;
		array_push($errorMessages, $message);
		$this->sessionNamespace->errorMessages = $errorMessages;
	}

	protected function addSuccessMessage($message) {
		$succesMessage = $this->sessionNamespace->successMessages;
		array_push($succesMessage, $message);
		$this->sessionNamespace->successMessages = $succesMessage;
		
	}

	protected function clearErrorMessages() {
		$this->sessionNamespace->errorMessages = array();
	}

	protected function clearSuccessMessages() {
		$this->sessionNamespace->successMessages = array();
	}

	protected function redirectToLogin ($email = '', $exit = true, $redirectUrl = '')
	{
		$params = array();
		if ($this->_hasParam('email')) 				$params['email'] = $this->_getParam('email');
		if (!empty($email)) 						$params['email'] = $email; //Explicit param takes priority
		if ($this->_hasParam('redirectUrl')) 		$params['redirectUrl'] = $this->_getParam('redirectUrl');
		if ($redirectUrl != '' ) 					$params['redirectUrl'] = $redirectUrl;

		if( $params['redirectUrl'] == null )
		{
			$params['redirectUrl'] = $_SERVER['REQUEST_URI'];
		}

		if ($this->_hasParam('benc'))
		{
			$params['benc'] = $this->_getParam('benc');
		}
		
		$url = $this->config['shipserv']['services']['cas']['rest']['loginUrl'];

		$this->redirect($url);
		if ($exit) { 
		    exit;
		}
	}

    /**
	 * Returns buyer organisation current user belongs to
	 *
	 * @return  Shipserv_Buyer
	 * @throws  Exception
	 */
	public function getUserBuyerOrg() {
		$testingEnv = in_array($_SERVER['APPLICATION_ENV'], array('development', 'testing'));

		$user = Shipserv_User::isLoggedIn();
		if ($user === false) {
			if (!$testingEnv) {
				throw new Myshipserv_Exception_MessagedException("You need to be logged in to access buyer-related functionality", 403);
			}
		} else {
			$buyerOrgIds = $user->fetchCompanies()->getBuyerIds();
			$message = "This page is only accessible to buyers.";
			if (!empty($buyerOrgIds)) {
				$message .= " Buyer organisations accessible to you are: " . implode(', ', $buyerOrgIds) . ", so your can switch to any of them.";
			}
		}

		$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		if (strlen($activeCompany->id) === 0){
			if ($this->isInTestingEnvironment()) {
				$buyerOrgId = 23404;    //@todo: backdoor for load testing
			} else {
				throw new Myshipserv_Exception_MessagedException("There is no active buyer company selected", 403);
			}
		} else {
			$buyerOrgId = $activeCompany->id;
		}

		try {
			$buyerOrgCompany = Shipserv_Buyer::getInstanceById($buyerOrgId);
		} catch (Exception $e) {
			throw new Myshipserv_Exception_MessagedException(
				"Your selected organisation " . $buyerOrgId . " does not appear to be a buyer one. " . $message,
				403
			);
		}

		return $buyerOrgCompany;
	}

	/**
	 * Check if the user is logged in, returns the list of buyer branch IDs user is associated with
	 * or throws an exception when active company is not available or is not a buyer company
	 *
	 * @author  Yuriy Akopov
	 * @date    2013-10-28
	 *
	 * @return  array
	 * @throws  Exception
	 */
	protected function _getUserBuyerIds() {
		$buyerOrgCompany = $this->getUserBuyerOrg();
		$buyerIds = $buyerOrgCompany->getBranchesTnid();

		return $buyerIds;
	}


	/**
	 * Returns true if the app is running on developer or UAT mode (useful for testing)
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-01-24
	 *
	 * @param   bool    $strict
	 *
	 * @return bool
	 */
	protected function isInTestingEnvironment($strict = false) {
		$allowedEnv = array('development');
		if (!$strict) {
			$allowedEnv[] = 'testing';
			$allowedEnv[] = 'ukdev';
		}

		return in_array($_SERVER['APPLICATION_ENV'], $allowedEnv);
	}

	/**
	 * Throws an exception when the currently logged it user is not a ShipMate
	 *
	 * @author  Yuriy Akopov
	 * @date    2013-11-18
	 *
	 * @returns Shipserv_User
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	public function abortIfNotShipMate() {
		$user = $this->abortIfNotLoggedIn();

		if ($user && !$user->isShipservUser()) {
			throw new Myshipserv_Exception_MessagedException("Access denied: this page is only available to ShipMates", 403);
		}

		return $user;
	}

	/**
	 * Throws an exception when the app is accessed anonymously
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-02-18
	 *
	 * @returns Shipserv_User
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	public function abortIfNotLoggedIn() {
		if (($user = Shipserv_User::isLoggedIn()) === false) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		return $user;
	}

	/**
	 * Throws an exception if the app is accessed from outside the defined set of IP addresses
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-07-31
	 *
	 * @return  string
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	public function abortIfUnknownIP() {
		$userIP = Shipserv_Helper_Security::getRealUserIP();
		if (!Shipserv_Helper_Security::isValidInternalIP($userIP)) {
			if (!($this->isInTestingEnvironment(true) and ($userIP === '127.0.0.1'))) {
				throw new Myshipserv_Exception_MessagedException("Access denied for IP " . $userIP, 403);
			}
		}

		return $userIP;
	}

	/**
	 * Helper function which treats empty string parameters as not specified (thus less annoying checks in every controller action)
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-07-02
	 *
	 * @param   string      $paramName
	 * @param   mixed|null  $ifEmpty
	 *
	 * @return  string|mixed|null
	 */
	public function _getNonEmptyParam($paramName, $ifEmpty = null) {
		$value = $this->_getParam($paramName);

		if (strlen($value) === 0) {
			$value = $ifEmpty;
		}

		if (is_array($value) and empty($value)) {
			$value = $ifEmpty;
		}

		return $value;
	}

	/**
	 * Returns true if the given IP is found in the list of given IPs (wildcards allowed in that list)
	 * It is in controller because IP checks usually happen there
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-09-10
	 * @story   DE5017
	 *
	 *
	 * @param   string          $ip
	 * @param   string|array    $range
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public function isIpInRange( $ip = null, $range = null ) {

		if( $range === null ){
			$range = Myshipserv_Config::getSuperIps();
		}

		if( $ip === null ){
			$ip = Myshipserv_Config::getUserIp();
		}

		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			throw new Exception("Invalid IP address " . $ip . ", unable to validate against the given range");
		}

		$octets = explode('.', $ip);

		if (!is_array($range)) {
			$range = array($range);
		}

		foreach ($range as $rangeIp) {
			$matched = true;

			if( strpos($rangeIp, '/') !== false )
			{
				if( $this->isIpCIDRMatch($ip, $rangeIp) == false )
				{
					continue;
				}
				else
				{
					return true;
				}

				return false;
			}
			else
			{
				$rangeOctets = explode('.', $rangeIp);
				foreach ($rangeOctets as $index => $rangeOctet) {
					if ($rangeOctet === '*') {
						continue;
					}

					if ($octets[$index] !== $rangeOctet) {
						$matched = false;
						break;
					}
				}

				if ($matched) {
					return true;
				}
			}
		}

		return false;
	}

	
	public function isIpInternal($ip)
	{
	    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE);
	}
	
	
	public function isIpCIDRMatch($ip, $range)
	{
		list ($subnet, $bits) = explode('/', $range);
		$ip = ip2long($ip);
		$subnet = ip2long($subnet);
		$mask = -1 << (32 - $bits);
		$subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
		return ($ip & $mask) == $subnet;
	}

	protected function _getDateTimeParam($paramName, $default = null) {
		$valueStr = $this->_getParam($paramName, $default);

		if (strlen($valueStr) === 0) {
			return null;
		}

		return new DateTime($valueStr);
	}

    protected function slowLoadingPage(){
        if( $this->params['l'] == "" ){
            $this->redirect('/user/loading?u=' . urlencode($_SERVER['REQUEST_URI'] ), array('exit' => true));
            exit();
        }
    }

	/**
	 * Remembers the current URL as the most recently run search in order to be able to return to it later
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-07-21
	 * @story   DE6822
	 */
	public function rememberSearchUri()
	{
		$history = Shipserv_Helper_History_Custom::getSearchHistory();
		$history->addUrl();
	}
}
