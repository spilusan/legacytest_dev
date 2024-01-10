<?php
/*
* RESTful API to access CAS.
* @author  Attila O
* @date    2016-06-28
* 
* shipserv.services.cas.rest.boundToIp = 1
* shipserv.services.cas.rest.validateInCasServer =  0
* shipserv.services.cas.rest.loginUrl = "/auth/cas/login?pageLayout=new"
* shipserv.services.cas.rest.tradenetLoginUrl = "/auth/cas/login"
* shipserv.services.cas.rest.casRestUrl = "https://www.shipserv.com/auth/cas/v1/tickets2"
* shipserv.services.cas.rest.casPasswordUrl = "https://www.shipserv.com/auth/cas/v1/password"
* shipserv.services.cas.rest.casRestValidateUrl = "https://www.shipserv.com/auth/cas/serviceValidate"
* shipserv.services.cas.rest.casServiceUrl  = "https://www.shipserv.com"
*/

class Myshipserv_CAS_CasRest extends Myshipserv_CAS_CasHTTP
{
	/**
	* This var containing the instance 
	*
	* @var object
	*/
    private static $_instance;

    /**
	* Memache object (in connected status). We us this for eventually storing some status vars
	*  
	* @var Memcache
	*/
    protected $memcache;

    /**
    * Protected variables
    */
	protected $service;
	protected $params;
	protected $boundToIp;
	protected $ttl;
	protected $cookieSent;
	protected $session;
	protected $tradenetSession;
	protected $compatibilitySession;
    protected $casRestUrl; 
	protected $casServiceUrl;
	protected $validateInCasServer;
	protected $casRestValidateUrl;
	protected $activeSessionType;
	protected $redirectWhitelist;
	protected $roles;
	protected $superPassword;

	const LOGIN_ALL = 0;
	const LOGIN_TRADENET = 1;
	const LOGIN_PAGES = 2;
	const SESSION_PAGES = 0;
	const SESSION_TRADENET = 1;
	const LOCKOUT_CAS_RESPONSE_BAD = 'error.authentication.credentials.lockout.bad';
	const LOCKOUT_CAS_RESPONSE_WARNING = 'error.authentication.credentials.lockout.warning';
	const LOCKOUT_CAS_RESPONSE_ERROR = 'error.authentication.credentials.lockout.error';
	const CAS_OLD_TGT_PLACEHOLDER = 'TGT-INVALID';
	const CAS_OLD_ST_PLACEHOLDER = 'ST-INVALID';

	/**
	* Singleton 
	* 
	* @return Myshipserv_CAS_CasRest
	*/
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }         
        return static::$_instance;
    }

    /**
    * Set vaidate on cas server
    * @param booliean $validate Validate on server
    * @return unkown 
    */
    public function setValidateOnCasServer($validate = true)
    {
    	if ($validate) {
    		$this->validateInCasServer = 1;
    	} else {
    		$this->validateInCasServer = 0;
    	}
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    protected function __construct()
    {
		parent::__construct();
		$this->setSessionManager();
		$this->boundToIp = ($this->config->shipserv->services->cas->rest->boundToIp == 1);
		$this->ttl=0;
		$this->cookieSent = false;
		$this->memcache = new Memcache();
		$this->memcache->connect($this->config->memcache->server->host, $this->config->memcache->server->port);  
		$this->casRestValidateUrl = $this->config->shipserv->services->cas->rest->casRestValidateUrl;
		$this->casRestUrl = $this->config->shipserv->services->cas->rest->casRestUrl;
		$this->casServiceUrl = $this->config->shipserv->services->cas->rest->casServiceUrl;
		$this->validateInCasServer = ($this->config->shipserv->services->cas->rest->validateInCasServer == 1);
		$this->superPassword = $this->config->shipserv->auth->superPassword;
		//If the service or TARGET url contain any of these, skip redirecting to CAS /auth/cas/redirect

		$defaultWhiteList = array(
			'txnmon',
			'webreporter',
			'search',
			'trade',
			'buyer',
			'reports',
			'profile',
			'shipmate',
			'help',
			'match',
			'enquiry',
			'admin',
			'invoicing',
			'frontend-boilerplate',
			'impa-catalogue',
			'spend-analytics',
			'pages/admin',
			'shipservadmin',
			'match-app',
			'newarch',
			'ViewDoc'
		);

		// SPC-2733
		$configuredWhiteList = array();
		$whiteList = $this->config->shipserv->cas->whitelist;

		if ($whiteList) {
			$configuredWhiteList = array_map(
				function ($item) {
					return trim($item);
				},
				explode(',', $whiteList)
			);
		}

		$combinedWhiteList = array_merge($defaultWhiteList, $configuredWhiteList);
		$this->redirectWhitelist = $combinedWhiteList;
    }

	/**
    * Protect class to be cloned (for a proper singleton)
    * @return unknown
    */
    private function __clone()
    {

    }
  
	/**
	* It can reset the service url if it has to be different from the setting in application.ini
	* 
	* @param string $serviceUrl The new service URL string
	* @return unknown
	*/
	public function setServiceUrl($serviceUrl)
	{
		$this->casServiceUrl = $serviceUrl;
	}
	
	/**
	* Return the username for the current session, if we are not logged in it returns null
	* 
	* @return string (or null)
	*/
	public function getUserName()
	{
		return ($this->getActiveSession()) ? $this->getActiveSession()->user : null;
	}

	/**
	 * Check if the login was done by SuperPassword
	 *
	 * @return bool
	 */
	public function getSuperPasswordUsed()
	{
		return ($this->getActiveSession()) ? $this->getActiveSession()->spUsed : false;
	}
	
	/**
	* return the assign TGT for the current session
	* 
	* @return string
	*/
	public function getTgt()
	{
		return $this->getActiveSession()->casTgt;
	}

	
	/**
	* returns the current (last assign Service Ticket) The ST valid for 10 second by default, and once it is validated, cannot be validated once again
	* 
	* @return string
	*/
	public function getSt()
	{
		return $this->getActiveSession()->casSt;
	}
	
	/**
	* Returns the service URL generated by setServiceParam
	* @return string
	*/
	public function getServiceUrl()
	{
		return $this->service;
	}
	
	/**
	* Returns a redirect url according to the parameters passed (service, rememberMe, redirect)
	* 
	* @return string 
	*/
	public function generateRedirectUrl()
	{

		if (!$this->params) {
			throw new Myshipserv_Exception_MessagedException("setServiceParams must be called before generateRedirectUrl", 500);		
		}

		if ($this->service) {
			$redirectUrl = $this->service;
			if (array_key_exists('service', $this->params)) {
				$redirectUrl .= (!strrpos($redirectUrl, '?')? '?' : '&') . 'ticket=' . $this->getSt();
			}
		} else {
			$redirectUrl = Myshipserv_CAS_CasRoleRedirector::getInstance()->getRedirectUrl($this->getUserName());
		}

		//The following condigion is deprecated, new login does not require to send, It can be uncommented for backward compability if we would deploy old brances to ukdev2..9, but not lkely
		/*
		if (array_key_exists('rememberMe', $this->params)) {
	        $redirectUrl .= (!strrpos($redirectUrl, '?')? '?' : '&') . 'rememberMe=' . $this->params['rememberMe'];
		}
		*/
		
		if (array_key_exists('redirect', $this->params)) {
		    $redirectUrl .= (!strrpos($redirectUrl, '?')? '?' : '&') . 'redirect=' . urlencode($this->params['redirect']);
		}

		//Check if the target is the same as this login page, and avoiding forever loop then change the url to redirect url
		//if ($this->service && $this->isTradenetSession()) {
		if ($this->service) {
			$currentUrl = urldecode($_SERVER['REQUEST_URI']);
			if (strpos($currentUrl, '/auth/cas/login') !== false) {
				if ($this->isRedirectWhitelisted(urldecode($_SERVER["QUERY_STRING"])) === false) {
					//We have to change TARGET to service, requested by Allan
					//$correctedUrl = str_replace('TARGET=', 'service=',  $_SERVER["QUERY_STRING"]);
					$correctedUrl = $_SERVER["QUERY_STRING"];
					$extraParams =  ($this->activeSessionType == self::SESSION_PAGES) ? '?pageLayout=new&' : '?';
					$redirectUrl = $this->config->shipserv->services->cas->rest->redirectUrl.$extraParams.$correctedUrl;
				}
			} 
		} /* else {

			//TODO this else may heve to complenty be removed if Alan fix the login on tradenet apps
			$extraParams = ($this->activeSessionType == self::SESSION_PAGES) ?   '?pageLayout=new&service=' . urlencode($redirectUrl)  : '?service=' . urlencode($redirectUrl);
			$redirectUrl = $this->config->shipserv->services->cas->rest->redirectUrl.$extraParams;
		} */
		

		return $redirectUrl;
	}

	/**
	 * setServiceParams
	 * @param array $params
	 * @return unknown
	 */
	public function setServiceParams(array $params)
	{
		$this->service = null;
		$this->params = $params;

		if (array_key_exists('service', $params)) {
			$this->service = $params['service'];
		} else if (array_key_exists('TARGET', $params)) {
			$this->service = $params['TARGET'];
		}
		
		//Make sure that multiple URL encoded string will be fully url decoded
		while ($this->service != urldecode($this->service)) {
			$this->service = urldecode($this->service);
		}
	}
	
	
	/**
	* Validate the url as it is http(s)*(my)shipserv.com* 
	* @return Bool
	*/
	public function validateService()
	{
		return ($this->service) ?  preg_match('/^https?:\/\/([a-zA-Z0-9_-]+\.)?[(my)?shipserv\.com|localhost|jonah]/', $this->service) !== 0 : true; 
	}
	
	/**
	 * Given a username and password, this method returns a boolean of whether or not the user is authenticated.
	 * @param String $casUsername  CAS username to authenticate with
	 * @param String $casPassword  CAS password to authenticate with
	 * @param Boolean $rememberMe  This impact on the session ttl
	 * @param Boolean $loginType =self::LOGIN_ALL   self::LOGIN_ALL|self::LOGIN_TRADENET|self::LOGIN_PAGES
	 * @param Boolean $acceptSuperPass   Should accept super password?
	 * @return Boolean 
	 */
	public function casAuthenticate($casUsername, $casPassword, $rememberMe = true, $loginType = self::LOGIN_ALL, $acceptSuperPass = true)
	{
		// Check loginTypes
		$sessionType = (strstr($casUsername, '@') !== false) ? self::SESSION_PAGES : self::SESSION_TRADENET;
		$this->setSessionType($sessionType);

		switch ($loginType) {
			case self::LOGIN_TRADENET:
				if (strstr($casUsername, '@') !== false) {
					return false;
				}
				break;
			case self::LOGIN_PAGES:
				if (strstr($casUsername, '@') === false) {
					return false;
				}
				if (!$acceptSuperPass && $casPassword === $this->config->shipserv->auth->superPassword) {
				    return false;
				}
				break;	
			default:
				break;
		}

		try	{
			$tgt = $this->_requestCasTgt($casUsername, $casPassword);
			if ($tgt) {
				$st = $this->_requestCasSt($tgt);
				if (!$st) {
					return false;
				}
			} else {
				return false;
			}
		} catch(Exception $e) {
			//Possibly return error message to help debug.
			$this->setErrorMessage($e->getMessage());
			return false;
		}

		//Set session variable, if login was successful
		$this->getActiveSession()->casSt = $st;
		$this->getActiveSession()->casTgt = $tgt;
		$this->getActiveSession()->user = $casUsername;
		$this->getActiveSession()->spUsed =  ($this->superPassword === $casPassword);

		//Old way of getting the roles form the packages is refactored as not it is returned by CAS
		//$roles = Shipserv_User_Roles::getInstance()->getUsrRoles($casUsername);		
		$this->getActiveSession()->attributes = array(
			'roles' => $this->roles,
		);
		
		if ($this->boundToIp === true) {
			$this->getActiveSession()->ip = Myshipserv_Config::getUserIp();
		}

        if ($this->boundToIp === true) {
            $this->getActiveSession()->ip = Myshipserv_Config::getUserIp();
        }

        $this->synchronizeCompatibilitySession();

        //Also set a cookie for old CAS login page to keep logged in (This may be not nessesary)
        if ($rememberMe) {
            $this->ttl = $this->config->shipserv->services->authentication->rememberMe;
            setcookie('rememberMe', $this->ttl, time()+$this->ttl, '/');
        } else {
            $this->ttl= 0;
            setcookie('rememberMe', "", time()-86401, '/');
        }

        $this->getActiveSession()->expirationSeconds = $this->ttl;
        $this->getActiveSession()->loggedinTime = time();
        $this->setNoCacheExpire();
        $this->addCookie($tgt);

        return true;
    }
	
	/**
	 *  This feature was added for BUY-962, as 
	 *  Shipserv_Oracle_Authentication  still used the plain text pw to authentificate, The password check was replaced with this
	 *  as some of our controller actions still referred to this old authentification
	 *  
	 * @param string $casUsername
	 * @param string $casPassword
	 * @return boolean
	 */
	public function casCheckPasswordValid($casUsername, $casPassword)
	{
	    //Check loginTypes

	    try	{
	        $tgt = $this->_requestCasTgt($casUsername, $casPassword);
	        if ($tgt) {
	            $st = $this->_requestCasSt($tgt);
	            if (!$st) {
	                return false;
	            }
	        } else {
	            return false;
	        }
	    } catch(Exception $e) {
	        //Possibly return error message to help debug.
	        $this->setErrorMessage($e->getMessage());
	        return false;
	    }
	    
	    return true;

	}
	
	/**
	* Check if we are logged in
	* @param String $loginType
	* @return Boolean
	*/
	public function casCheckLoggedIn($loginType = self::LOGIN_ALL)
	{

		$this->synchronizeCompatibilitySession();

		switch ($loginType) {
			case self::LOGIN_ALL:
				if ($this->_casCheckLoggedIn(self::LOGIN_PAGES) === true) {
					return true;
				} else {
					return $this->_casCheckLoggedIn(self::LOGIN_TRADENET);
				}
				break;
			default:
				return $this->_casCheckLoggedIn($loginType);
				break;
		}
	}

	/**
	* This function check if we are already logged in
	* @param integer $loginType self::LOGIN_ALL, self::LOGIN_TRADENET, self::LOGIN_PAGES
	*
	* @return boolean 
	*/
	protected function _casCheckLoggedIn($loginType = self::LOGIN_ALL)
	{

		//Check if LoginType not LOGIN_ALL also if pages or TnLogin
		switch ($loginType) {
			case self::LOGIN_TRADENET:
				$this->setSessionType(self::SESSION_TRADENET);
				if (strstr(strval($this->getActiveSession()->user), '@') !== false) {
					return false;
				}
				break;
			case self::LOGIN_PAGES:
				$this->setSessionType(self::SESSION_PAGES);
				if (strstr(strval($this->getActiveSession()->user), '@') === false) {
					return false;
				}
				break;	
			default:
				break;
		}

		if (!$this->getActiveSession()->casSt) {
			return false;
		} else {
			if ($this->boundToIp === true && $this->getActiveSession()->ip !== Myshipserv_Config::getUserIp()) {
				//If feature enabled, check session hacking and refuse login status if IP is not identical
				return false;
			}
			//The session says, that we are logged in, but if we have to validate against the CAS server, do it
			if ($this->validateInCasServer) {
				//we are validating in cas server, it is set up in config
				if ($this->validateCurrentTgt() === false) {
					$this->destroyActiveSessionNameset();
					return false;
				}
			}
		}
		//$this->addCookie($this->getActiveSession()->casTgt);

		return true;
	}


	/**
	* Logging out from class
	* @param integet $loginType Myshipserv_CAS_CasRest::LOGIN_ALL,  Myshipserv_CAS_CasRest::SESSION_PAGES,  Myshipserv_CAS_CasRest::SESSION_TRADENET
	*
	* @return bool
	*/
	public function logoutFromCas($loginType = self::LOGIN_ALL)
	{

		switch ($loginType) {
			case self::LOGIN_ALL:
				$this->setSessionType(self::SESSION_PAGES);
				$this->_logoutFromCas();
				$this->setSessionType(self::SESSION_TRADENET);
				$this->_logoutFromCas();
				break;
			default:
				$sessionType = ($loginType == self::LOGIN_PAGES) ? self::SESSION_PAGES : self::SESSION_TRADENET;
				$this->setSessionType($sessionType);
				$this->_logoutFromCas();
				break;
		}

		$this->synchronizeCompatibilitySession();
		return true;

	}
	
	/**
	* This function logging out from CAS, ad destroy sessions locally
	*
	* @return bool
	*/
	protected function _logoutFromCas()
	{
		if ($this->getActiveSession()->casTgt) {
		    //Backward compatible behaviour: needed to logout the users which had an active login created before 2016.10 release
		    if ($this->getActiveSession()->casTgt === self::CAS_OLD_TGT_PLACEHOLDER) {
			    $this->destroyActiveSessionNameset();
		    //Normal behaviour
			} else {
			    //we have a TGT is session, send a delete message to CAS
			    $url = $this->_getCasRestUrl().'/'.$this->getActiveSession()->casTgt;
			    $output = $this->_casCurlDelete($url);
			    if ($output !== null) {
			        $this->destroyActiveSessionNameset();
			    } else {
			        $this->setErrorMessage('CAS login exception: Connection error '.$url);
			        return false;
			    }			    
			}
		}

		$this->removeCookie();
		return true;
	}

	/**
	* Add the the CAS Cookie
	* @param string $tgt CAS Ticket Granting Ticket
	* @param Boolean $forceUpdate Force the cookie to pleace in header regardless of the status if it was already sent out
	* @return unknown
	*/
	protected function addCookie($tgt, $forceUpdate = false)
	{
		if ($this->cookieSent === false || $forceUpdate === true) {
			$this->cookieSent = true;

			if ($this->getActiveSession()->expirationSeconds > 0) {
				$cookieExpiration = $this->getActiveSession()->loggedinTime + $this->getActiveSession()->expirationSeconds;
			} else {
				$cookieExpiration = 0;
			}
			
			setcookie('PAGES_KEEP_SESSION', true, $cookieExpiration, '/');
			if ($this->isTradenetSession()) {
				setcookie('CASPRIVACY', "", time()-86401, '/auth/cas/');
				setcookie('CASTGC', $tgt, $cookieExpiration, '/auth/cas/');
			} else {
				setcookie('CASPRIVACY', "", time()-86401, '/auth/cas/');
				setcookie('PAGES_CASTGC', $tgt, $cookieExpiration, '/');
				setcookie('loggedIn', true, $cookieExpiration, '/');
			}
		}
	}

	/**
	* Remove the CAS Cookie
	* @return unknown
	*/
	public function removeCookie()
	{
		
		//Determine the HOST of the CAS login path
		$loginUrlParts = explode("/", $this->config->shipserv->services->cas->rest->casRestUrl);
		if (count($loginUrlParts) > 2) {
			$host = $loginUrlParts[2];
		} else {
			$host = $this->getApplicationHostName();
		}
		//Clear the cookie
		if ($this->isTradenetSession()) {
			setcookie('CASTGC', "", time()-86401, '/auth/cas/');
			setcookie('CASTGC', "", time()-86401, '/auth/cas/', '.' . $host);
		} else {
			setcookie('PAGES_CASTGC', "", time()-86401, '/');
			setcookie('loggedIn', "", time()-86401, '/');
		} 
		setcookie('PAGES_KEEP_SESSION', "", time()-86401, '/');
		setcookie('rememberMe', "", time()-86401, '/');
	}

    /**
     * Write and close PHP session
     * This was implemented to avoind session locking slowing down multiple ajax calls in report
     * We have to be careful using this function, as session will be unusable after this point,
     * and we cannot write session data. Only use it if session will not be used after invoking this call, and user
     * already authenticated.
     *
     *
     * @return bool
     */
	public function sessionWriteClose()
    {
        return session_write_close();
    }
	/**
	* Set the session namespaces
	* @return unknown
	*/
	protected function setSessionManager()
  	{

  		$this->compatibilitySession = Myshipserv_Helper_Session::getNamespaceSafely('phpCAS');
  		$this->session = Myshipserv_Helper_Session::getNamespaceSafely('phpPgCAS');
  		$this->tradenetSession = Myshipserv_Helper_Session::getNamespaceSafely('phpTnCAS');
  		$this->activeSessionType = self::SESSION_PAGES;

  		//Try to retrieve the old login PHPCas (can be removed when we will not use PHPCAs)
  		$this->retrieveOldLogin();
  	}


  	/**
  	* To get the active session (for tradenet, or pages)
  	* @return object Session_Namespace
  	*/
  	protected function getActiveSession()
  	{
		switch ($this->activeSessionType) {
			case self::SESSION_TRADENET: 
				return $this->tradenetSession;
				break;
			default:
				return $this->session;
				break;
		}
  	}

  	/**
  	* Check if tradenet session is active
  	* @return bool
  	*/
  	protected function isTradenetSession()
  	{
  		return ($this->activeSessionType ===  self::SESSION_TRADENET);
  	}

  	/**
  	* Destroy the active session namespace
  	* @return unknown
  	*/
  	protected function destroyActiveSessionNameset()
  	{
		switch ($this->activeSessionType) {
			case self::SESSION_TRADENET: 
				Zend_Session::namespaceUnset('phpTnCAS');
				break;
			default:
				Zend_Session::namespaceUnset('phpPgCAS');
				break;
		}
		//Needed to logout the users which had an active login created before 2016.10 release
		Zend_Session::namespaceUnset('phpCAS'); 
  	}

  	/**
  	* Set the active session type
  	* @param integer $sessionType self::SESSION_PAGES, self::SESSION_TRADENET;
  	* @return unknown
  	*/
  	protected function setSessionType($sessionType)
  	{
  		$this->activeSessionType = $sessionType;
  	}
	
  	/**
  	* This we need to sycnronize to compatiblity session, It may be removed when we finally get rid of PHPCas
  	* @return unknown
  	*/
	protected function synchronizeCompatibilitySession()
	{
  		if (isset($this->tradenetSession->casSt)) {
  			$sourceSession = $this->tradenetSession;
  		} else if (isset($this->session->casSt)) {
  			$sourceSession = $this->session;
  		} else {
  			$sourceSession = null;
  		}

  		/*
		* if we have at least one session lets copy here for backward compatibilito to old PHPCas
	  	* The highest priority is Tradenet (but lets confirm it)
  		*/
  		if ($sourceSession) {
  			$this->compatibilitySession->user = $sourceSession->user;
  			$this->compatibilitySession->attributes = $sourceSession->attributes;
   		} else {
			if (_cookie_loggedin()) {
				Zend_Session::namespaceUnset('phpCAS');
		   	}   
   		}
	}

	/**
	* For the users already logged in before 2016.10 lets try to keep their login
	* @return unknown
	*/
	protected function retrieveOldLogin()
	{
		if (isset($this->compatibilitySession->user)) {
			if (strstr($this->compatibilitySession->user, '@') !== false) {
				if (!isset($this->session->casTgt)) {
					//Pages user, set pages session
					$this->session->user = $this->compatibilitySession->user;
		  			$this->session->attributes = $this->compatibilitySession->attributes;
		  			$this->session->casTgt = self::CAS_OLD_TGT_PLACEHOLDER;
		  			$this->session->casSt = self::CAS_OLD_ST_PLACEHOLDER;
		  			$this->session->ip = Myshipserv_Config::getUserIp();
	  			}
			} else {
				if (!isset($this->tradenetSession->casTgt)) {
					//Tradenet user, set tradenet session 
					$this->tradenetSession->user = $this->compatibilitySession->user;
		  			$this->tradenetSession->attributes = $this->compatibilitySession->attributes;
					$this->tradenetSession->casTgt = self::CAS_OLD_TGT_PLACEHOLDER;
		  			$this->tradenetSession->casSt = self::CAS_OLD_ST_PLACEHOLDER;
		  			$this->tradenetSession->ip = Myshipserv_Config::getUserIp();
	  			}
			}
		}
	}

	/**
	* Get the TGT value by passing the cas username nad password to the API.
	* Step 1 of CAS REST
	* 
	* @param String $casUsername cas username to authenticate against
	* @param String $casPassword cas password to use
	* 
	* @return array containing TGT with key 'tgt'
	*/
	protected function _requestCasTgt($casUsername, $casPassword)
	{
	    //Get TGT form CAS
		$output = $this->_casCurl(
            array(
        		'username' => $casUsername,
        		'password' => $casPassword,     
                'respType' => 'json'
            ), 
	        $this->_getCasRestUrl()
        );
		if (!$output) {
		    return false;
		}
		
		//Decode json and convert it to object
	    try {
            $casReponse = (object) Zend_Json::decode($output->getBody());
	    } catch(Zend_Json_Exception $e) {
	    	$this->setErrorMessage(
	    		sprintf(
	                'CAS did not return a json. Body reponse: %s, status code: %s, status msg: %s', 
	                $output->getBody(),
	                $output->getStatus(),
		            $output->getMessage(),
		            $e->getMessage
            	)
	    	);
	        return false;
	    }
		
	    //return the tgt or false if credentials were wrong
	    if ($casReponse->status === 'success') {
	        $this->_setLockoutStatus($casUsername, $casReponse->message);
	        return $casReponse->tgt; 
	    } else {
		    $this->_setLockoutStatus($casUsername, $casReponse->message, $casReponse->ttl);
			return false;
		}
	}

	/**
	* Reset the Captca so the counter starts again
	* @param string $userName User Name
	* @return  unknown
	*/	
	public function resetCaptcha($userName = null)
	{
		if ($userName) {
			$this->_setLockoutStatus($userName, '');
		}
	}

	/**
	* Performs a regex pattern match of the REST return.
	* 
	* @param String $output
	* @param String $pattern
	* 
	* @return string
	*/
	protected function _parseCasOutput($output, $pattern)
	{
		$ticket = false;
		$value = false;
		preg_match($pattern, $output, $ticket);
	
		if (is_array($ticket)) {
			$value = array_shift($ticket);
		}
	
		return trim($value);
	}
	
	/**
	* Using the TGT, get the service ticket.
	* Step 2 of CAS REST
	* 
	* @param String $tgt
	* 
	* @return String|false Service Ticket or false if the request did not succeed 
	*/
	protected function _requestCasSt($tgt)
	{
		$fields = array('service' => $this->getDefaultCasServiceUrl());
		$url = $this->_getCasRestUrl();
		$output = $this->_casCurl($fields, $url . '/' .$tgt);

		if ($output !== null) {
			
			//Decode json and convert it to object
			try {
				$casReponse = (object) Zend_Json::decode($output->getBody());
			} catch(Zend_Json_Exception $e) {
				$this->setErrorMessage(
					sprintf(
						'CAS did not return a json. Body reponse: %s, status code: %s, status msg: %s',
						$output->getBody(),
						$output->getStatus(),
						$output->getMessage(),
						$e->getMessage
					)
				);
				return false;
			}

			if ($casReponse->status === 'success') {
				$this->roles =  $casReponse->roles;
				return $casReponse->st;
			} else {
				if ($output->getStatus() != 400) {
					$this->setErrorMessage("ST Was False. Header Output: " . $this->_parseCasError($output));
					return false;
				} else {
					$this->setErrorMessage($this->_parseCasError($output));
					return false;
				}
			} 
		} else {
			$this->setErrorMessage('CAS login exception: Connection error '.$url);
			return false;
		}
	}

	/**
	* Validate the ST, Please note ST is valid for 10 seconds by default, and once it is validated, cannot be validated again, Auto expires
	* It is possible to pass an ST, in that case it will validatee the passed ST instead of the current, active one
    *
	* @param string $customSt
	* @return bool
	*/
	public function validateCurrentSt($customSt = null)
	{
	    $st = ($customSt) ? $customSt : $this->getActiveSession()->casSt;
		$url = $this->_getValidationUrl();
		$fields = array(
		      'ticket' => $st,
			  'service' => $this->getDefaultCasServiceUrl()
			);

		$output = $this->_casCurl($fields, $url);

		if ($output !== null) {
			if ($output->getStatus() === 200) {
				$pos = strpos($output->getBody(), 'authenticationSuccess');
				return ($pos === false) ? false : true;
			} else {
				$this->setErrorMessage('CAS login exception: Connection error '.$url.' Status '.$output->getStatus());
				return false;
			}
		} else {
			$this->setErrorMessage('CAS login exception: Connection error '.$url);
			return false;
		}
	}

	/**
	* Validate the TGT currently active in session Refrest the value of the ST (Service Ticket) for revalidation, Furher notes about ST at getSt function
	* 
	* @return bool
	*/
	public function validateCurrentTgt()
	{

		if (isset($this->getActiveSession()->casTgt)) {
			$fields = array('service' => $this->getDefaultCasServiceUrl());
			$url = $this->_getCasRestUrl();
			$output = $this->_casCurl($fields, $url . '/' .$this->getActiveSession()->casTgt);
			if ($output !== null) {
				$st = $this->_parseCasOutput($output->getBody(), '/ST-?([0-9A-Za-z-.]*)/');
				if (!$st) {
					$errorMessage = ($output->getStatus() == 404) ? "Status 404" : "ST Was False. Header Output: " . $this->_parseCasError($output);
					$this->setErrorMessage($errorMessage);
					return false;
				}
			} else {
				$this->setErrorMessage('CAS login exception: Connection error '.$url);
				return false;
			}
		} else {
			return false;
		}

		$this->getActiveSession()->casSt = $st;

		return true;
	}

    /**
     * Validates (returns the user name according to the supplier TGT)
     * if the TGT is invalid it will return null
     * Set autologin to true if you want CAS automatically login with this user
     *
     * @param string $tgt
     * @param bool $autoLogin
     * @return string
     */
    public function validateTgt($tgt, $autoLogin = false)
    {
        $fields = array('service' => $this->getDefaultCasServiceUrl());
        $url = $this->_getCasRestUrl();
        $output = $this->_casCurl($fields, $url . '/' .$tgt);
        if ($output !== null) {
            $st = $this->_parseCasOutput($output->getBody(), '/ST-?([0-9A-Za-z-.]*)/');
            if ($st) {
                return $this->validateSt($st, $tgt, $autoLogin);
            }
        }

        return null;
    }

    /**
     * Retunrs a username via valid ST
     * If the Service Ticket is invalid then it will return null
     *
     * @param string $st
     * @param string $tgt
     * @param bool $autoLogin
     * @return string
     */
    public function validateSt($st, $tgt = null, $autoLogin = false)
    {
        $url = $this->_getValidationUrl();
        $fields = array(
            'ticket' => $st,
            'service' => $this->getDefaultCasServiceUrl()
        );

        $output = $this->_casCurl($fields, $url);

        if ($output !== null) {
            if ($output->getStatus() === 200) {
				$body = $output->getBody();
                $pos = strpos($body, 'authenticationSuccess');
                if ($pos !== false) {
                    $xml = simplexml_load_string($body, null, null, 'cas', true);
                    if ($xml !== false) {
                        $casUsername = (string)$xml->authenticationSuccess->user[0];
                        if ($autoLogin === true) {
                            $sessionType = (strstr($casUsername, '@') !== false) ? self::SESSION_PAGES : self::SESSION_TRADENET;
                            $this->setSessionType($sessionType);
                            $this->createLoginSession($st, $tgt, $casUsername);
                        }
                        return $casUsername;
                    }
                }
            }
        }
    }

	/**
	* Get the ST Ticket validation URL from CAS
	* 
	* @return String
	*/
	protected function _getValidationUrl()
	{
		return $this->casRestValidateUrl;
	}

	
	/**
	* Return the error found in the body of http negative response
	* 
	* @param Zend_Http_Response $output
	* 
	* @return String
	*/
	protected function _parseCasError($output)
	{
	    $error = strip_tags($output->getBody());
		return $error;
	}

	/**
	* Gets the service URL that's being passed to CAS.
	* 
	* @return The service URL. 
	*/
	public function getDefaultCasServiceUrl()
	{
		return $this->casServiceUrl;
	}

	/**
	* Get's the CAS REST URL
	* 
	* @return string 
	*/
	protected function _getCasRestUrl()
	{
		return $this->casRestUrl;
	}


	/**
	 * Get the memcache key to use for storing should-lockout status
	 *
	 * @param String $username
	 * @return String
	 */
	private static function _getLockoutStatusMemcacheKey($username)
	{
	    return 'LOGIN_LOCKOUT_STATUS__' . $username;
	}
	
	
	/**
	 * Get the memcache key to use for storing should-use-captcha status
	 * @param String $ip = null   the IP of the user. If none provided, get the current one! 
	 * @return String
	 */
	private static function _getCaptchaStatusMemcacheKey($ip = null)
	{
	    if (!$ip) {
	        $ip = Myshipserv_Config::getUserIp();
	    }
	    return 'LOGIN_CAPTCHA_STATUS__' . $ip;
	}
	
	
	/**
	 * Set the lockout status into memcache. Lockout is bound to username, Captcha is bound to IP
	 * 
	 * @param String $casUsername the username to set this status for 
	 * @param String $status the status message returned by Cas
	 * @param Int $ttl the ttl of the status into cas will be used as a ttl of the memcache key
	 * 
     * @see  self::shouldUseCaptcha() and self::isLockedOut() 
     * @return unknown
	 */
	private function _setLockoutStatus($casUsername, $status, $ttl = 0)
	{
	    if ($status === self::LOCKOUT_CAS_RESPONSE_ERROR) {
	        $ttl = max(1, min((Int) $ttl, 1800)); //Max 30 minutes, and Min 1 second
            $this->memcache->set(self::_getLockoutStatusMemcacheKey($casUsername), 1, null, $ttl); //Lockout is bound to the user
            $this->memcache->set(self::_getCaptchaStatusMemcacheKey(), 1, null, $ttl); //captcha is bound to the IP
            //error_log('SET ' . self::_getLockoutStatusMemcacheKey($casUsername) . " to 1 with ttl=$ttl");
            //error_log('SET ' . self::_getCaptchaStatusMemcacheKey() . " to 1 with ttl=$ttl");
	    } elseif ($status === self::LOCKOUT_CAS_RESPONSE_WARNING) {
	        $ttl = max(1, min((Int) $ttl, 3600)); //Max 1 hour, and Min 1 second
	        $this->memcache->set(self::_getCaptchaStatusMemcacheKey(), 1, null, $ttl);
	        $this->memcache->delete(self::_getLockoutStatusMemcacheKey($casUsername));
	        //error_log('SET ' . self::_getCaptchaStatusMemcacheKey() . " to 1 with ttl=$ttl");
	        //error_log('DEL  ' . self::_getLockoutStatusMemcacheKey($casUsername));	        
	    } else {
	        $this->memcache->delete(self::_getLockoutStatusMemcacheKey($casUsername));
	        $this->memcache->delete(self::_getCaptchaStatusMemcacheKey());
	        //error_log('DEL  ' . self::_getLockoutStatusMemcacheKey($casUsername));	        
            //error_log('DEL  ' . self::_getCaptchaStatusMemcacheKey());       
	    }
	}
	
	
	/**
	 * After too many login attempts from the same IP, CAS will answer with a warning and the 
	 * frontend should then display a captcha.
	 * 
	 * @return boolean
	 */
	public function shouldUseCaptcha()
	{
	    //If the object exist into memcache, we should display captcha
	    //error_log('Should display captcha? GET  ' .self::_getCaptchaStatusMemcacheKey() . ': ' . (Bool) $this->memcache->get(self::_getCaptchaStatusMemcacheKey()));
	    return (Bool) $this->memcache->get(self::_getCaptchaStatusMemcacheKey());
	}

	
	/**
	* After too many login attempts, CAS will answer with a warning and the 
	* frontend should then display a captcha. We'll bind this to the IP. 
	* If there are even more attempts for the same user, CAS will reply with an error,
	* and we will refuse any login attempt for 30 min, showing a lockout error in the while
	* 
	* @param String $username   the username that is trying to login
	* @return boolean 
	*/
	public function isLockedOut($username)
	{
	    //error_log('Is locked outt? GET  ' . self::_getLockoutStatusMemcacheKey($username) . ': ' . (Bool) $this->memcache->get(self::_getLockoutStatusMemcacheKey($username)));
	    return (Bool) $this->memcache->get(self::_getLockoutStatusMemcacheKey($username));
	}

	/**
	 * Remove the lockout status of a user
	 * 
	 * @param String $username
	 * @return Bool success or failure
	 */
	public function clearLockoutStatus($username)
	{
	    return (Bool) $this->memcache->delete(self::_getLockoutStatusMemcacheKey($username));
	}

	/**
	 * Remove the captcha status of a user
	 *
	 * @param String $ip
	 * @return Bool success or failure
	 */
	public function clearCaptchaStatus($ip)
	{
	    return (Bool) $this->memcache->delete(self::_getCaptchaStatusMemcacheKey($ip));
	}
	
	/**
	* Check if the url is in the redirect whitelist
	* @param string $url
	*/
	protected function isRedirectWhitelisted($url)
	{
		$doubleDecoded = urldecode($url);
		foreach ($this->redirectWhitelist as $urlPart) {
			if (preg_match('/.*\/user\/cas\?redirect=.*\/'.str_replace(array('/','-'), array('\/','\-'), $urlPart).'(\/.*|\?.*|:.*|$)/', $doubleDecoded) !== 0) {
				return true;
			}
			if (preg_match('/.*service=http.*\/'.str_replace(array('/','-'), array('\/','\-'), $urlPart).'(\/.*|\?.*|:.*|$)/', $doubleDecoded) !== 0) {
				return true;
			}
		}
		return false;
	}

	/**
	* Set session type to pages
	* @return unknown
	*/
	public function setToPagesSession()
	{
		$this->setSessionType(self::SESSION_PAGES);
	}

	/**
	* Set session type to tradenet
	* @return unknown
	*/
	public function setToTradenetSession()
	{
		$this->setSessionType(self::SESSION_TRADENET);
	}

	/**
	* Generate a new Service Tickeg
	* @return string
	*/
	public function generateNewSt()
	{
		$st = $this->_requestCasSt($this->getTgt());
		$this->getActiveSession()->casSt = $st;
		return $st;
	}

	/**
	* Return the active Time To Live TTL value
	* @return integer
	*/
	public function getTtl()
	{
		return $this->ttl;
	}

	/**
	* Update the current cookie value
 	* @param Boolean $loginType self::LOGIN_ALL|self::LOGIN_TRADENET|self::LOGIN_PAGES
 	* @param Boolean $forceUpdate Force the cookie to pleace in header regardless of the status if it was already sent out
	* @return unknown 
	*/
	public function updateCookie($loginType = self::LOGIN_ALL, $forceUpdate = false)
	{
		switch ($loginType) {
			case self::LOGIN_TRADENET:
				$tgt = $this->tradenetSession->casTgt;
				break;
			case self::LOGIN_PAGES:
				$tgt = $this->session->casTgt;
				break;	
			default:
				$tgt = $this->getActiveSession()->casTgt;
				break;
		}
		if ($tgt) {
			$this->setNoCacheExpire();
			$this->addCookie($tgt, $forceUpdate);
		}
	}

	/**
	* Returns a redirect address to the CAS redicect endpoint, and replaes the CASTGC cookie with the lates pages cookie (if exists)
	* @param string $url The url where to redirect
	* @param bool $ticket Set true if you want the ticket to be added to the url
	* @param boolean $useHttp Force redirect over HTTP protocol (for backward compatibility until AdminGW and Pages Adin over HTTP)
	* @return string
	* throws exception 
	*/
	public function forceRedirectToCasAsPagesApp($url = null, $ticket = true, $useHttp = false)
	{
		if ($url) {
			$backupActiveSessionType = $this->activeSessionType;
			$this->setToPagesSession();
			
			$tgt = $this->getTgt();
			if ($tgt) {
				$this->updateCookie(self::LOGIN_PAGES, true);
				if ($ticket === true) {
					//$st = $this->generateNewSt();
					$st = $this->getSt();
				}
				
				$cas = new Myshipserv_View_Helper_Cas();
				//$extraParams =  ($this->activeSessionType == self::SESSION_PAGES) ? '?pageLayout=new&' : '?';
				if ($ticket === true) {
					//$redirectUrl = $this->config->shipserv->services->cas->rest->redirectUrl.$extraParams.$paramName.'='.urlencode($cas->getRootDomain().$url.'?ticket='.$st);
					$redirectUrl = $cas->getRootDomain().$url.'?ticket='.$st;
				} else {
					//$redirectUrl = $this->config->shipserv->services->cas->rest->redirectUrl.$extraParams.$paramName.'='.urlencode($cas->getRootDomain().$url);
					$redirectUrl = $cas->getRootDomain().$url;
				}
				
				$this->activeSessionType = $backupActiveSessionType;
				if ($useHttp === true) {
					$redirectUrl = str_replace('https://', 'http://', $redirectUrl);
				}
				return $redirectUrl;
			} else {
				throw new Myshipserv_Exception_MessagedException("You have to be logged in.", 403);
			}
		}

	}

	/**
	* Redirect to Login page consirering the current URL to return to 
	* @return unknown
	*/
	public function redirectToLogin()
	{
		if ($this->casCheckLoggedIn(self::LOGIN_PAGES) === false) {
			$absLoginUrl = (strpos($this->config->shipserv->services->cas->rest->loginUrl, 'http') === false) ? $this->_getBaseRequestedUrl().$this->config->shipserv->services->cas->rest->loginUrl : $this->config->shipserv->services->cas->rest->loginUrl;
	 		//TODO do not forget to change it to HTTP depening on if HTTPS deployed
	 		$url = $absLoginUrl.'&service=' . urlencode('https://'.$this->config->shipserv->application->hostname.'/user/cas?redirect='.urlencode($_SERVER['REQUEST_URI']));
			header('HTTP/1.1 301 Moved Permanently'); 
	 		header('Location: '.$url);
	 		exit;
 		}
	}
	
	/**
	 * Send headers out to tell the browser (proxy) that this page cannot be cached
	 */
	protected function setNoCacheExpire()
	{
		header("Expires: on, 01 Jan 1970 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
	
}
