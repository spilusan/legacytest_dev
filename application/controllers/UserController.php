<?php

/**
 * Controller for user actions - login, logout, register, profile, etc.
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class UserController extends Myshipserv_Controller_Action
{

	public function checkPermissionAction()
	{
		echo "X-Forwarded-For: " . $_SERVER['HTTP_X_FORWARDED_FOR'] . '<br />';
		echo "IP: " . Myshipserv_Config::getUserIp() . '<br />';
		echo "Within allowed network: " . ( ($this->isIpInRange() === true)?'Y':'N' ) . '<br />';
		$this->_helper->layout->setLayout('empty');
		$this->_helper->viewRenderer('alert/index', null, true);
	}

    /**
     * This action should be deleted, test for session migration to Redis
     *
     * @throws Zend_Exception
     */
	public function migratesessionAction()
    {
        // session management
        set_time_limit(0);

        $config = Zend_Registry::get('config');
        $sessionPrefix = 'PHP_SESSION_' . strtoupper($config->shipserv->redis->session->prefix) . ':';

        $redisPassword = $config->shipserv->redis->cluster->password;
        $redisSeeds = $config->shipserv->redis->cluster->seeds;
        $redisTimeout = $config->shipserv->redis->cluster->timeout;

        // set up redis authentication
        $options  = [
            'timeout' => $redisTimeout,
            'cluster' => 'redis',
            'parameters' => [
                'password' => $redisPassword
            ]
        ];

        // initialize redis cluster
        $client = new \Predis\Client('tcp://'.$redisSeeds, $options);

        $sessionPath = session_save_path();

        echo "Migrating path: $sessionPath<ul>";

        $files = scandir($sessionPath);
        $count = 0;
        $invalidCount = 0;
        foreach ($files as $file) {
            $fileName = $sessionPath . '/' . $file;
            if ($file != '.' && $file != '..' && is_file($fileName)) {
                $newSessionKey = $sessionPrefix . substr($file, 5);
                $content = file_get_contents($fileName);

                $sessionDecodeSuccess = session_decode($content);
                if ($sessionDecodeSuccess && isset($_SESSION['phpPgCAS']) && isset($_SESSION['phpPgCAS']['casTgt'])) {
                    $client->set($newSessionKey, $content);
                    // @todo successfully migrated session file might be deleted from the server
                    echo "<li>$newSessionKey</li>";
                    $count++;
                    flush();
                    ob_flush();
                } else {
                    $invalidCount++;
                }
            }

        }
        echo "</ul>$count session migrated<br>$invalidCount Session not migrated, exists but expired or user not logged in";

        die();
    }

	/**
	 * Initialise the controller for any ajax requests
	 *
	 * @access public
	 */
    public function init()
    {
        parent::init();
        
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('login', 'html')
					->addActionContext('perform-login', 'json')
					->addActionContext('cas-auth-check', 'json')
					->addActionContext('register', 'html')
					->addActionContext('perform-registration', 'json')
                    ->initContext();

    }

	/**
	 * If a user is logged in, this will show them their settings/profile page
	 *
	 * @access public
	 */
	public function indexAction()
	{

	}


	/**
	 * This end point is being used to automatically register an email address and add them to the pages.
	 * This is being used for SIR/SVR awareness campaign. We want supplier to access their report quite easily
	 *
	 * @example '/user/auto-registration?tnid=123456&email=email@address.co&a=[md5('AUTOREG' . $this->supplier->tnid . $this->email)];
	 * @throws Myshipserv_Exception_MessagedException
	 * @throws ProfileController_Exception
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function autoRegistrationAction()
	{
		$params = $this->params;

		// logout current user
        $user = Shipserv_User::isLoggedIn();
        if (is_object($user))
		{
			$user->logout();
		}

		// check the parameters
		if( !isset( $params['tnid']) || !isset( $params['email'] ) || $params['email'] == "" || !isset( $params['a'] ) )
		{
            throw new Myshipserv_Exception_MessagedException("Invalid url, please check your url and try again.", 404);
		}

		// check the hash
		if( md5('AUTOREG' . $params['tnid'] . $params['email'] ) != $params['a'] )
		{
			throw new Myshipserv_Exception_MessagedException("You are not authorised to access this page. Please contact our customer support at <a href='mailto:support@shipserv.com'>support@shipserv.com</a>.", 401);
		}

		// Prepare default return array
		$res = array(
			'ok' => false,
			'user' => null,
			'msg' => "Undefined error",
		);

		try
		{
			// Normalise & validate e-mail
			$params['eml'] = strtolower(trim($params['email']));
			if ($params['eml'] == '')
			{
				throw new ProfileController_Exception("Expected e-mail as 'eml'");
			}

			if (strlen($params['eml']) > 100)
			{
				$res['msg'] = "Email address may not exceed 100 characters.";
				//$this->view->assign($res);
				$this->_helper->json((array)$res);
				return;
			}

			// Type (v|b) doesn't need validation
			$params['type'] = 'v';
			$params['userLevel'] = "ADM";

			// Check company ID present & valid
			// multiples
			if( strstr( $params['tnid'], ',' ) !== false )
			{
				$params['id'] = explode(",", $params['tnid']);
			}
			else
			{
				$params['id'] = (array)$params['tnid'];
			}


			if ($params['id'] == null || count( $params['id']) == 0 )
			{
				throw new ProfileController_Exception("Expected company ID as 'id'");
			}

			foreach( $params['id'] as $tnid )
			{
				// Pass add-user request to business layer
				$uaa = new Myshipserv_UserCompany_AdminActions($db, 8007675);
				$typeConv = array('b' => 'BYO', 'v' => 'SPB');
				$res = $uaa->addUserToCompany(
					$params['eml'],
					@$typeConv[$params['type']],
					$tnid,
					$params['userLevel'],
				    $this->user->username
				);
			}


			// Fetch newly created/modified user-company relationship
			$ucd = new Myshipserv_UserCompany_Domain($this->getInvokeArg('bootstrap')->getResource('db'));
			$userCompany = $ucd->fetchUserCompany($params['eml'], @$typeConv[$params['type']], $params['id'], true);

			// Form user-company information for return
			$res['userCompany'] = array(
				'userId'      => $userCompany->userId,
				'username'    => $userCompany->username,
				'firstName'   => $userCompany->firstName,
				'lastName'    => $userCompany->lastName,
				'email'       => $userCompany->email,
				'companyType' => $params['type'],
				'companyId'   => $params['id'],
				'roles' => array('administrator' => ($params['userLevel']=="ADM")?true:false),
			);

			// Check status of user-company relationship and provide success message accordingly
			switch ($userCompany->getStatus())
			{
				case 'ACT':
					$res['msg'] = 'User added.';
					break;

				case 'PEN':
					$res['msg'] = 'User added pending confirmation of e-mail address.';
					break;

				default:
					// Logic error
					throw new ProfileController_Exception("Unable to add user: please try again later, or contact support.");
			}

			// Success
			$res['ok'] = true;
		}
		catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
		{
			if ($e->getCode() == Shipserv_Oracle_User_Exception_FailCreateUser::CODE_BAD_EMAIL)
			{
				$res['msg'] = "Invalid e-mail";
			}
			else
			{
				// Happens with bad data.
				// e.g. if there is a USER row but no PAGES_USER row, then the user isn't found, the code tries to create it and there's a PK clash.
				$res['msg'] = "Unable to create user: please try again later, or contact support.";
			}
		}
		catch (Myshipserv_UserCompany_AdminActions_Exception $e)
		{
			if ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_ALREADY_MEMBER)
			{
				$res['msg'] = "User is already a member, or pending member of company.";
			}
			elseif ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_SELF)
			{
				$res['msg'] = "You cannot add yourself to a company.";
			}
		}
		catch (ProfileController_Exception $e)
		{
			$res['msg'] = $e->getMessage();
		}
		catch (Exception $e)
		{
			$res['msg'] = $e->getMessage() . "Something went wrong, please try again later or contact support.";
		}

		// create session for newly register user
		// log user automatically
		$userAdaptor = new Shipserv_Oracle_User($db);

		// get user's password
		$data = $userAdaptor->getUserIdByEmail($params['email']);

		// get user
		try {

			$user = $userAdaptor->fetchUserById($data[0]['PSU_ID']);
		}
		catch (Exception $exception)
		{
			$user = null;
		}

		if (is_object($user))
		{
			// if found set layout to empty, and redirect user automatically
			$this->_helper->layout->setLayout('empty');
			$userAdaptor->fetchPagesUserByUsername($user->username, $password);

			//login user
			$loggedUser = Shipserv_User::login($user->username, $password, false);

		}
		else
		{
			throw new Myshipserv_Exception_MessagedException("This link is no longer valid, user cannot be found on this link");
		}


		//////////////////////////////////////////////////////////////////////////////////////////////////////////
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
			}
		}

		// fetch companies
		$companies = $user->fetchCompanies(true);

		// if user is part of companies, then default them to the first one and store it on the cookie
		if( !isset($activeCompany->id) && $params['tnid'] != "" )
		{
			// store the first company to the session
			$activeCompany->type = 'v';
			$activeCompany->id = $params['tnid'];
			$activeCompany->company = Shipserv_Supplier::fetch($params['tnid']);

			// store this to cookie
			setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => 'v')), time()+60*60*24*30, "/");
		}

        Myshipserv_Helper_Session::updateReportTradingAccounts();
		// log this activity
		$user->logActivity(Shipserv_User_Activity::USER_LOGIN, 'PAGES_USER', $user->userId, $user->email);
		//////////////////////////////////////////////////////////////////////////////////////////////////////////

		if( $params['r'] != "" )
		{
			$this->redirect($this->view->uri()->deobfuscate($params['r']), array('code' => 301));
		}
		else
		{
			$this->redirect('/reports?tnid=' . $params['tnid'], array('code' => 301));
		}
	}

	public function redirectToCasAction()
	{
		// find out where to return the user to once they've logged in/registered
		if ( $this->_getParam('returnUrl') != "" )
		{
			$host    = 'https://' . $_SERVER['HTTP_HOST'];
			$url = str_replace($host, '', urldecode($this->_getParam('returnUrl')));
		}
		else
		{
			$host    = 'https://' .  $_SERVER['HTTP_HOST'];
			$url = str_replace($host, '', $_SERVER['HTTP_REFERER']);
		}

		if ($params['registerRedirectUrl'])
		{
			$url = ($params['benc']) ? $this->view->uri()->deobfuscate($params['registerRedirectUrl']) : $params['registerRedirectUrl'];
		}

		if( $params['loginRedirectUrl'] )
		{
			$url = $params['loginRedirectUrl'];
		}
		else if( $params['registerRedirectUrl'] )
		{
			$url = $params['registerRedirectUrl'];
		}
		else if( $params['redirectUrl'])
		{
			$url = $params['redirectUrl'];
		}

		if( $params['benc'] == 1 )
		{
			$url = $this->view->uri()->deobfuscate( $url);
		}

		$url  = $this->getUrlToCasLogin($url, false);
		$this->redirect($url);

	}

	/**
	 * Page for Login or Register
	 */
	public function registerLoginAction()
	{
		$this->_helper->layout->setLayout('default');

		$config = $this->config;

		// set up a connection to Oracle
		$oracleReference = new Shipserv_Oracle_Reference($this->getInvokeArg('bootstrap')->getResource('db'));

		// fetch the company types
		$companyTypes = $oracleReference->fetchCompanyTypes();

		// fetch the job functions
		$jobFunctions = $oracleReference->fetchJobFunctions();

		$cookieManager = $this->getHelper('Cookie');

		// create the login and register form objects
		$loginForm = new Myshipserv_Form_Login();

		if (!$this->getRequest()->isPost()) {
			// For GET requests, have form pick up username from params
			$pLogin = $this->_getParam('loginUsername');
			if ($pLogin != '') 
			    $this->view->formValues = array('loginUsername' => $pLogin);
		}

		$registerForm = new Myshipserv_Form_Register($companyTypes, $jobFunctions);

		$user = null;

		// find out where to return the user to once they've logged in/registered
		if ($this->_getParam('returnUrl') != '') {
			$host    = 'https://' . $_SERVER['HTTP_HOST'];
			$referer = str_replace($host, '', urldecode($this->_getParam('returnUrl')));
		} else {
			$host    = 'https://' .  $_SERVER['HTTP_HOST'];
			$referer = str_replace($host, '', $_SERVER['HTTP_REFERER']);
		}

		$redirectUrl = $referer;
		//$this->redirect($this->getUrlToCasLogin($redirectUrl));

		// check to see if the user has an enquiry lined up
		$hasEnquiry = false;
		if ($cookieManager->fetchCookie('enquiryStorage')) {
			$hasEnquiry = true;
		}


		$params = $this->params;

        //$redirectUrl = null;
		if ($params['registerRedirectUrl']) {
			$redirectUrl = ($params['benc']) ? $this->view->uri()->deobfuscate($params['registerRedirectUrl']) : $params['registerRedirectUrl'];
		}

		$rememberMe = true;

		// is there a form to process
		try
        {
            if ($this->getRequest()->isPost()) {

				if( $params['loginRedirectUrl'] ) {
					$url = $params['loginRedirectUrl'];
				} else if ($params['registerRedirectUrl']) {
					$url = $params['registerRedirectUrl'];
				} else if ($params['redirectUrl']) {
					$url = $params['redirectUrl'];
				}

				switch ($params['act']) {
					case 'forgot':
						// ensure the user isn't already logged in:
						if ($user = Shipserv_User::isLoggedIn()) {
							// redirect them if they are
							$this->redirect('/search', array('exit' => true));
						}

						$config = $this->getInvokeArg('bootstrap')->getOptions();
						$forgPassForm = new Myshipserv_Form_Forgottenpassword();

						$errors = array();
						$success = false;
						try
						{
							if ($forgPassForm->isValid($params)) {
								$values = $forgPassForm->getValues();
								$authAdapter = new Shipserv_Adapters_Authentication();

								$result = $authAdapter->sendPassword($values['forgEmail']);

								if ($result['failure']) {
									$errors['top'][] = 'Your email address could not be found.';
								} else {
									$success = true;
								}
							} else {
								$errors = $forgPassForm->getMessages();

								$this->view->formValues = $params;
							}
						}
						catch (Exception $e)
						{
							$errors[] = $e->getMessage();
						}

						if (count($errors) > 0) {
							if ($errors['top'][0] != "Your email address could not be found.")
                                $errors['top'][] = "Please provide a valid email address";
						}

						$this->view->passwordHasBeenSent = $success;
						$this->view->errors  = $errors;

						break;

					case 'login':
						/**
						 * if the form is valid (i.e. username and password have been sent)
						 * then attempt to validate the user
						 */
						if ($url) {
							$redirectUrl = ($params['benc']) ? $this->view->uri()->deobfuscate($url) : $url;
						}

						$rememberMe = ($params['loginRememberMe']) ? true : false;

						if ($loginForm->isValid($params)) {
							$values = $loginForm->getValues();

							$user = Shipserv_User::login(
                                $values['loginUsername'],
                                $values['loginPassword'],
                                $rememberMe, $uFromTn
					        );

							if ($user === false) {
								// Handle failed login

								$user = null;

								if ($uFromTn) {
									// Special case fail: new Pages user created from existing TN account
									$errors['top'][] = 'Sorry, you cannot log in with TradeNet credentials, but a new Pages account has been created for you automatically.<br /><br />Your new Pages username and password has been sent to: ' . $uFromTn->email;
								} else {
									// Normal failed login
									$errors['top'][] = 'Sorry, your credentials are incorrect';
								}
							} else {

								// check if user had selected a company on previous session.
								// this is stored in cookie named below.
								// if there's such cookie, we'll initialise the default company
								// for this user

								// initialise the session
								$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();

								if (isset($_COOKIE['lastSelectedCompany'])) {
									// check if this user is part of the last selected company
									$lastSelectedCompany = json_decode( $_COOKIE['lastSelectedCompany'] );

									// go ahead store the default company to session if user is
									// belong to the company. if not we'll choose the first
									// company -- see below
									if ($user->isPartOfCompany( $lastSelectedCompany->tnid )) {
										$activeCompany->type = $lastSelectedCompany->type;
										$activeCompany->id = $lastSelectedCompany->tnid;
										$activeCompany->company = $lastSelectedCompany->type=='b' ? Shipserv_Buyer::getInstanceById( $lastSelectedCompany->tnid ):Shipserv_Supplier::fetch( $lastSelectedCompany->tnid );
									}
								}

								// fetch companies
								$companies = $user->fetchCompanies(true);

								// if user is part of companies, then default them to the first one and store it on the cookie
								if (!isset($activeCompany->id) && $companies[0]['id'] != '') {
									// store the first company to the session
									$activeCompany->type=$companies[0]['type'];
									$activeCompany->id = $companies[0]['id'];
									$activeCompany->company = $companies[0]['type']=='b' ? Shipserv_Buyer::getInstanceById( $companies[0]['id'] ):Shipserv_Supplier::fetch($companies[0]['id']);

									// store this to cookie
									setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => $companies[0]['type'])), time()+60*60*24*30, "/");
								}

								// log this activity
								$user->logActivity(Shipserv_User_Activity::USER_LOGIN, 'PAGES_USER', $user->userId, $user->email);

								/**
								 * If a login or registration occurs, flush the analytics log and trigger
								 * and update to retrospectively update previous searches and profile views
								 * with the username of the user.
								 */
								$this->_helper->getHelper('Analytics')->flushAnalLog();

								// check if there's an enquiry cookie set...
								if ($hasEnquiry) {
									// if there is, send them to a page where they can choose the company that they're belong to
									$redirectUrl = "/enquiry/send-from-login-register";
								}

								// handle redirection
								else if ($redirectUrl) {
									// append forward slash at the beginning
									if ($redirectUrl[0] != '/') {
										$redirectUrl = '/' . $redirectUrl;
									}

									// if redirection is going to the same page, then redirect user to /search
									if (strstr( $redirectUrl, 'register-login') !== false) {
										if ($user->hasCompletedDetailedInformation() == false) {
											$redirectUrl = "/user/register-login/update";
										} else {
											if ($user->hasAgreedLatestAgreement() == false) {
												$redirectUrl = '/profile/agreement';
											} else {
												$redirectUrl = '/search';
											}
										}
									}

									// if a redirect url has been set then send the user that way
									$redirectUrl = urldecode($redirectUrl);

								}

								// redirect user to /search
								else {
									$redirectUrl = '/search';
								}

								if ($user->hasCompletedDetailedInformation() == false) {
									$redirectUrl = "/user/register-login/update?benc=1&amp;registerRedirectUrl=" . $this->view->uri()->obfuscate($redirectUrl) ;
								} else {
									if ($user->hasAgreedLatestAgreement() == false) {
										$redirectUrl = '/profile/agreement?redirect=' . $redirectUrl;
									}
								}

                                Myshipserv_Helper_Session::updateReportTradingAccounts();

								$this->redirect($redirectUrl, array('code' => 301));

								// no alternative actions - just show a success message
								$loginSuccess = true;
						   }
						} else {
							// the login form is not valid - set the appropriate errors
							$errors = $loginForm->getMessages();
							$this->view->formValues = $params;
						}
					break;

					case 'register':
						// form was valid, attempt to register
						if ($url) {
							$redirectUrl = ($params['benc']) ? $this->view->uri()->deobfuscate($url) : $url;
						}

						$rememberMe = ($params['registerRememberMe']) ? true : false;

						// if the form is all ok/valid
						if ($registerForm->isValid($params)) {
							try {
								// registering user via service
								$user = Shipserv_User::register(
    						        $params['registerEmail'],
    								$params['registerPassword'],
    								$params['registerFirstName'],
    								$params['registerLastName'],
    								$params['registerCompany'],
    								$params['registerCompanyType'],
    								($params['registerOtherCompanyType']) ? $params['registerOtherCompanyType'] : '',
    								$params['registerJobFunction'],
    								($params['registerOtherJobFunction']) ? $params['registerOtherJobFunction'] : '',
    								$params['registerMarketingUpdated'] ? true : false,
    								$rememberMe
						        );

								// get the user object and send email activation right after registration
								$u = Shipserv_User::getInstanceByEmail($params['registerEmail']);
								$u->sendEmailActivation();

								/**
								 * If a login or registration occurs, flush the analytics log and trigger
								 * and update to retrospectively update previous searches and profile views
								 * with the username of the user.
								 */
								$this->_helper->getHelper('Analytics')->flushAnalLog();

								// check if there's an enquiry cookie set...
								if ($hasEnquiry) {
									// there is - send the enquiry
									$this->_forward('send-from-login-register', 'enquiry');
								} else if ($redirectUrl) {
									if ($redirectUrl[0]!='/') {
										$redirectUrl = '/'.$redirectUrl;
									}

									if (strstr( $redirectUrl[0], 'register-login') !== false) {
										$redirectUrl = '/search';
									}

									// if a redirect url has been set then send the user that way
									$this->redirect(urldecode($redirectUrl), array('code' => 301));
								} else {
									$this->redirect('/search', array('code' => 301));
								}

								// no alternative actions - just show a success message
								$registerSuccess = true;
							}
							catch (Shipserv_User_Exception_RegisterEmailInUse $e)
							{
								$errors['top'][] = 'Your e-mail address is already taken. If you have forgotten your password, recover it <a href="/user/forgotten-password">here</a>.';
								$this->view->formValues = $params;
							}
							catch (Exception $e)
							{
								$errors['top'][] = $e->getMessage() != '' ? $e->getMessage() : 'An error occurred: please try again later';
								$this->view->formValues = $params;
							}
						} else {
							$errors = $registerForm->getMessages();

							$this->view->formValues = $params;
						}
					break;
				}
			}
		}
		catch (Exception $e)
		{
			$errors[] = $e->getMessage();
			echo '<!--';
			var_dump($errors);
			echo '//-->';
			trigger_error('UserController::redirectToCasAction exception catched ' . (String) $e, E_USER_WARNING);
		}
		
		if ($loginSuccess || $registerSuccess) {
			$this->_helper->getHelper('Analytics')->flushAnalLog();
		}

		if ($this->_getParam('registerRedirectUrl') != '') {
			$this->view->redirectUrl = $this->_getParam('registerRedirectUrl');;
		}

		//If an explicit redirect is provided, pass it out to render as a hidden field
		if ($this->_hasParam('redirectUrl')) {
			$u = Shipserv_User::isLoggedIn();

			// additional logic making sure that logged user can go to the appropriate url
			if ($u !== false) {
				$this->redirect($this->_getParam('redirectUrl'), array('code' => 301));
			} else {
				//Ensure url is obfuscated
				if ($this->_getParam('benc') == '1') {
					$this->view->redirectUrl = $this->_getParam('redirectUrl');
				} else {
					$this->view->redirectUrl = $this->view->uri()->obfuscate($this->_getParam('redirectUrl'));
				}
			}
		} else {
			if ($params['registerRedirectUrl'] != '') {
				$this->view->redirectUrl  = $params['registerRedirectUrl'] ;
			} else {
				if ($referer != "" && strstr('/user/register-login', $referer ) === false) {
					$this->view->redirectUrl = $this->view->uri()->obfuscate($referer);
				}
			}
		}

		$user = Shipserv_User::isLoggedIn();


		if ($user !== false) {
            $this->view->user = $user;
		}

		$this->view->rememberMe   = $rememberMe;
		$this->view->hasEnquiry   = $hasEnquiry;
		//$this->view->user         = $user;
		$this->view->errors       = $errors;
		$this->view->loginForm    = $loginForm;
		$this->view->registerForm = $registerForm;
		$this->view->companyTypes = $companyTypes;
		$this->view->jobFunctions = $jobFunctions;

	}
	

	/**
	 * Allow a user to logout, and redirect to the homepage (or $_GET['logoutRedirectUrl'] if specified)
	 *
	 * @access public
	 */
    public function logoutAction()
    {

        $this->_helper->layout->setLayout('blank');

    	$user = Shipserv_User::isLoggedIn();

    	// preparing the redirection
        if ($this->getRequest()->getParam('logoutRedirectUrl')) {
        	$url = $this->getRequest()->getParam('logoutRedirectUrl');
        } else {
        	$url = $this->view->cas()->getRootDomain() .'/search';
        }

        if (strstr($url, '/auth/cas/logout') === false) {
        	$url = $this->view->cas()->getRootDomain() .'/search';
        }

		$casRest = Myshipserv_CAS_CasRest::getInstance();

		if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
			$casRest->logoutFromCas(Myshipserv_CAS_CasRest::LOGIN_PAGES);
			if ($user) {
				$user->logout(false); //Do not forget full session
			}
		} 

		if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES) === false && $casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET) === false) {
			Zend_Session::destroy(true);
		}

		$casRest->removeCookie();
		// $this->redirect(urldecode($url), array('exit' => true));
        $this->view->redirectUrl = urldecode($url);
    }

    
    /*
    * For compatibllity of old TradeNet logout, 
    */
    public function logoutRedirectorAction()
    {
    	$config = Zend_Registry::get('config');
		$casRest = Myshipserv_CAS_CasRest::getInstance();

		 if ($this->getRequest()->getParam('app')) { 
		 		if ($this->getRequest()->getParam('app') === 'pages') {
					if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
						$casRest->logoutFromCas(Myshipserv_CAS_CasRest::LOGIN_PAGES);
					} 		 			
		 		} else {
					if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
						$casRest->logoutFromCas(Myshipserv_CAS_CasRest::LOGIN_TRADENET);
					} 
		 		}
		 } else {
			if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
				$casRest->logoutFromCas(Myshipserv_CAS_CasRest::LOGIN_TRADENET);
			} 
		 }

		//The following part is a temporary fix while we have mixed brances on UKDEV with the two logout methodoligy, can be removed later
		$referrer = (array_key_exists('HTTP_REFERER', $_SERVER)) ? $_SERVER['HTTP_REFERER'] : null;
		if ($referrer) {
			$url = parse_url($referrer);
			//TODO finish this fix, check if coming from here, and if yes logiout from pages
			// /user/logout
		}
		
		if (array_key_exists('service', $this->params)) {
			$redirectUrl = urldecode($this->params['service']);
		} else if (array_key_exists('TARGET', $this->params)) {
			$redirectUrl = urldecode($this->params['TARGET']);
		} else {
			$path =  Myshipserv_CAS_CasRoleRedirector::getInstance()->getRootDomain();
			$redirectUrl = $path.'/search';
		}

		if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES) === false && $casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET) === false) {
			Zend_Session::destroy(true);
		}

		//$casRest->removeCookie(); //We do not need it anymore, as logoutFromCas implements this feature
		if ($this->getRequest()->getParam('casredir')) {
			$redirect = $redirectUrl;
		} else {
			$redirect = $config->shipserv->services->sso->logout->url.'cas?service='.urlencode($redirectUrl);
		}
		
        $this->redirect($redirect, array('exit' => true));
    }

    /**
     * Handling forgot password function
     *
     */
	public function forgottenPasswordAction()
	{
		// ensure the user isn't already logged in:
		if (Shipserv_User::isLoggedIn()) {
			// redirect them if they are
			$this->redirect('/search', array('exit' => true));
		}

		$config = $this->getInvokeArg('bootstrap')->getOptions();
		$forgPassForm = new Myshipserv_Form_Forgottenpassword();

		$errors = array();
		$success = false;
		try
        {
            if ($this->getRequest()->isPost()) {
				if ($forgPassForm->isValid($this->getRequest()->getParams())) {
					$values = $forgPassForm->getValues();

					$authAdapter = new Shipserv_Adapters_Authentication();

					$result = $authAdapter->sendPassword($values['forgEmail']);

					if ($result['failure']) {
						$errors['forgEmail'][] = 'Your email address could not be found in our system.';
					} else {
						$success = true;
					}
				} else {
					$errors = $forgPassForm->getMessages();

					$this->view->formValues = $params;
				}
			}
		}
		catch (Exception $e)
		{
			$errors[] = $e->getMessage();
		}

		$this->view->success = $success;
		$this->view->errors  = $errors;
	}

	/**
	 * Confirm user's e-mail address (link dispatched via e-mail)
	 *
	 * @example http:://.../u/<userId>/dt/<unixTime>/tok/<authToken>
	 */
	public function confirmEmailAction()
	{
		try
		{
			// Check auth token
			$boolTokenOk = $this->checkConfirmEmailAuthToken($this->_getParam('tok'), $this->_getParam('u'), $this->_getParam('dt'));
			if (!$boolTokenOk) {
				// Handle bad auth token
				throw new UserController_ConfirmEmailExc("Sorry, we're unable to confirm your e-mail address: please re-request confirmation, or contact support.");
			}

			// Fetch logged-in user, or null
			$logUser = Shipserv_User::isLoggedIn();

			// If user is logged in ...
			if ($logUser) {
				// If logged-in user does not match user specified by link ...
				if ($logUser->userId != $this->_getParam('u')) {
					// If redirection is enabled
					if ($this->_getParam('nr', 0) != 1) {
						// Redirect to log-in
						$this->redirectToLogin();
						exit;
					// If redirection is NOT enabled
					} else {
						throw new UserController_ConfirmEmailExc("Sorry, we're unable to confirm your e-mail address: please re-request confirmation, or contact support.");
					}
				}
			// If user is not logged in ...
			} else {
				// Redirect to log-in
				$this->redirectToLogin();
				exit;
			}

			// From here: user is logged in & matches user specified by link

			// Check that user's e-mail address is not already confirmed
			if ($logUser->isEmailConfirmed()) {
				throw new UserController_ConfirmEmailExc("Your e-mail address has already been confirmed");
			}

			// Mark e-mail confirmed for logged-in user
			Shipserv_User::confirmEmail(true);
			$logUser = Shipserv_User::isLoggedIn();

			// Import user's companies from TN
			$tni = new Myshipserv_tnImporter_UserCompany();
			$tni->setRowOwner('PGS');
			$tni->importUserCompaniesForEmail($logUser->email, $logUser->userId);

			// Update any user-company associations waiting on e-mail confirmation
			$pucDao = new Shipserv_Oracle_PagesUserCompany($this->db);
			$pucDao->activatePendingForUser($logUser->userId);

			$fwdParams = array('emailConfirmed' => true);

			//Adding this line for DE6527, (Attila O) re initalize the sesson for user company, for the label to be shown
			if ($logUser) {
				if (strstr($logUser->username, '@') !== false) {
					Shipserv_User::initialiseUserCompany();
				}
			}
			
			// Set-up view for success
			$this->_forward('overview', 'profile', null, $fwdParams);
		}
		catch (UserController_ConfirmEmailExc $e)
		{
			// Set-up view for failure
			$fwdParams = array('emailConfirmed'            => false,
							   'emailConfirmationError'    => true,
							   'emailConfirmationErrorMsg' => $e->getMessage() );

			// Set-up view for success
			$this->_forward('overview', 'profile', null, $fwdParams);
		}
	}

    /**
     * As the old rule assuming all supplier org IDs are under 20000 no longer works, we still need a way to tell supplier
     * IDs from buyer ones. This function assumes they don't overlap
     *
     * @author  Yuriy Akopov
     * @date    2013-11-29
     *
     * @param   int $companyId
     *
     * @return  bool
     */
    protected function isOrgIdInBuyerInterval($companyId) {
        $maxOrgId = Shipserv_Buyer::getMaxOrgId();

        return ($companyId <= $maxOrgId);
    }

    
    public function casAuthCheckAction()
    {
    	$refresh = 0;

    	if ($this->getRequest()->getParam('loggedIn') == 0 ) {
    		if( $this->view->user !== false ) {
    			$refresh = 1;
    		}
    	}

    	if($this->getRequest()->getParam('method') === 'iframe' ) {
    		if( $refresh == 1 && $_COOKIE['ra'] == null )
    		{
    			setcookie('ra', ++$_COOKIE['ra'], null, '/');

	    		echo '<script>';
	    		echo 'parent.location.reload()';
	    		echo '</script>';
    		}
    	}
    	else
    	{
    		header("Content-Type: application/json");
    		echo 'callback(' . "{'refresh' : $refresh}" . ')';
    	}
    	die();
    }
    

	/**
	 * Page to initialise CAS Ticket; and swap it to Pages Session
	 */
    public function casAction()
    {
        $this->_helper->layout->setLayout('empty');

        $params = $this->getRequest()->getParams();
        
    	// initialise the session
		$activeCompany = Myshipserv_Helper_Session::getNamespaceSafely('userActiveCompany');
    	
		if (isset( $_COOKIE['lastSelectedCompany'] ) && json_decode($_COOKIE['lastSelectedCompany'])) {
			// check if this user is part of the last selected company
			$lastSelectedCompany = json_decode($_COOKIE['lastSelectedCompany']);

			// go ahead store the default company to session if user is
			// belong to the company. if not we'll choose the first
			// company -- see below
			if (($this->user !== false && $this->user != null) && ($this->user->isPartOfCompany($lastSelectedCompany->tnid) || $this->user->isShipservUser())) {
				$activeCompany->type = $lastSelectedCompany->type;
				$activeCompany->id = $lastSelectedCompany->tnid;
				$activeCompany->company = $lastSelectedCompany->type=='b' ? Shipserv_Buyer::getInstanceById( $lastSelectedCompany->tnid ):Shipserv_Supplier::fetch( $lastSelectedCompany->tnid );
                Myshipserv_Helper_Session::updateReportTradingAccounts();
			}

		}

		$url = '/';
		// find out where to return the user to once they've logged in/registered
		if ($params['returnUrl'] != "") {
			$host    = 'https://' . $_SERVER['HTTP_HOST'];
			$url = str_replace($host, '', urldecode($params['returnUrl']));
		} else {
			$host    = 'https://' .  $_SERVER['HTTP_HOST'];
			$url = str_replace($host, '', $_SERVER['HTTP_REFERER']);
		}

		// deals with different kind of redirection URL
		if ($params['registerRedirectUrl']) {
			$url = $params['registerRedirectUrl'];
		} else if ($params['loginRedirectUrl']) {
			$url = $params['loginRedirectUrl'];
		} else if ($params['redirectUrl']) {
			$url = $params['redirectUrl'];
		} else if ($params['redirect']) {
			$url = $params['redirect'];
		}

		// if url is encrypted; then decrypted
		if ($params['benc'] == 1) {
			$url = $this->view->uri()->deobfuscate($url);
		}
		if (preg_match('/\/auth\/cas\/login/', $url)) {
		    $url = '/search'; 
		}

		if ($this->user != false && ($this->getRequest()->getParam('ticket') != "" ||  strstr($_SERVER['QUERY_STRING'], "ticket") !== false)) {
			$this->user->logActivity(Shipserv_User_Activity::USER_LOGIN, 'PAGES_USER', $this->user->userId, $this->user->email);
			
			if ($this->user->isPartOfBuyer()) {
				$this->user->logActivity(Shipserv_User_Activity::BUYER_COMPANY_USER_SIGN_IN, 'PAGES_USER', $this->user->userId, $this->user->email);
			}

			if ($this->user->isPartOfSupplier()) {
				//S18391 Send notification to AM
				Myshipserv_LoginNotification::send();
				$this->user->logActivity(Shipserv_User_Activity::SUPPLIER_COMPANY_USER_SIGN_IN, 'PAGES_USER', $this->user->userId, $this->user->email);
			}
		}

        if ($url === '/user/cas/redirect' || $url === '') {
		    //make sure there is no forever loop
		    $url = '/search';
        }

		$this->redirect($url);
    }

    public function singleSignOnBridgeAction()
    {
    	$config = Zend_Registry::get('config');
    	$cas = new Myshipserv_View_Helper_Cas();


		$redirect = $config->shipserv->services->cas->rest->loginUrl;

		if( $this->params['r'] != "" )
    	{
    		$redirect = $this->params['r'];
    	} else {
    		$redirect = $config->shipserv->services->cas->rest->loginUrl;
    	}

		if( $this->params['c'] != "1")
    	{
    		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/user/logout?logoutRedirectUrl=' . urlencode($redirect) . '&x=1';
    	}
    	else
    	{
    		$url = $cas->getRootDomain() . '/user/logout?x=2&logoutRedirectUrl=';
    		$url .= urlencode($cas->getRootDomain() . '/auth/cas/login?pageLayout=new&x=3&service=');
    		$url .= urlencode($cas->getRootDomain() . '/user/cas?x=4&redirect=' . $redirect);
    	}

    	$this->view->redirectTo = $url;
    	$this->redirect($url);
    }

	/**
	 * Interface to switch company from the top right header
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function switchCompanyAction()
	{
		$this->requestCacheControl->noCache = true;

		// create session
		$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$params = $this->params;

		/* *********************************************************************************************************** *
		 *  HOT FIX TO STOP NON-ACTIVATED SHIPSERV EMAIL SWITCHING/SPAWNING TO DIFFERENT COMPANIES
		 * *********************************************************************************************************** */
		$user = Shipserv_User::isLoggedIn();

		if ($user->emailConfirmed != "Y") {
			$user->sendEmailActivation();
			throw new Myshipserv_Exception_MessagedException("In order for you to see different company, you need to activate your account. We have sent you the activation email automatically.");
		}

		// making sure that the TNID is supplied, otherwise show error message (warning)
		if (empty($params['tnid'])) {
			throw new Myshipserv_Exception_MessagedException("Please check your TNID");
		}

		// as $params['tnid'] will be a|v51606 this regex will separate them
		preg_match("/[0-9]{1,}/i", $params['tnid'], $matches);

		$companyId = $matches[0];
		$companyType = strtolower($params['tnid'][0]);

		// if company type isn't specified, then check the length of the TNID
		if( !in_array($companyType,array('b','v', 'c')) )
		{
            if ($this->isOrgIdInBuyerInterval($companyId)) {
                $companyType = 'b';
            } else {
                $companyType = 'v';
            }

            /*
			 if( $companyId < 20000 )
			{
				$companyType = "b";
			}
			else
			{
				$companyType = "v";
			}
            */
		}
		// perform redirection
		if (!empty($params['redirect'])) {
			$url = $params['redirect'];
		} else {
			$referer = $_SERVER['HTTP_REFERER'];

			// if referer has some sort of TNID, then replace the TNID with the new one
			if (
					( strstr($referer, '/profile/') !== false && strstr($referer, '/id/') !== false && strstr($referer, '/userId/') === false && strstr($referer, '/enquiryId/') === false )
					|| ( strstr($referer, '/reports') !== false && strstr($referer, 'tnid') !== false )
					|| ( strstr($referer, '/enquiry-browser') !== false && strstr($referer, 'tnid') !== false ) )
			{
				$url = preg_replace("/[0-9]{3,}/i", $companyId, $referer);

				if (strstr( $url, 'type/b/')) {
					$url = str_replace("type/b/", "type/" . $companyType . "/", $url);
				}
				else if (strstr( $url, 'type/v/')) {
					$url = str_replace("type/v/", "type/" . $companyType . "/", $url);
				}
			// if referrer is itself, then redirect them to /profile/overview (user)
			} else if (strstr( $referer, 'switch-company' ) !== false) {
				$referer = "/profile/overview";
			} else {
				$url = $referer;
			}
		}

		// redirect them to /search if nothing's been set
		if ($url == null) {
			$url = "/search";
		}

		if ($user->isShipservUser() == false || $user->canPerform('PSG_COMPANY_SWITCHER') == false) {
			if ($user->isPartOfCompany($companyId) == false) {
				throw new Myshipserv_Exception_MessagedException("You are not allowed to see other company on your group. Please contact support.", 401);
			}
		}

		// if buyer
		if ($companyType === "b") {
			try
			{
				// if Buyer entered correct buyer org id
				$buyer = Shipserv_Buyer::getInstanceById( $companyId );
			}
			catch( Exception $e )
			{
				// if not found, check if it's being normalised to something else
				if (strstr($e->getMessage(), 'No row found') !== false) {
					// check normalisation
					$orgId = Shipserv_Buyer::getByoOrgCodeByTnid($companyId);
					$normalisedOrgId = Shipserv_Buyer::getNormalisedCompanyByOrgId($orgId);

					if ($normalisedOrgId != "") {
						$this->addSuccessMessage("Your buyer TNID: " . $companyId . " is being translated to: " . $normalisedOrgId . " (buyer organisation code)");
						$url = "/user/switch-company?tnid=" . $normalisedOrgId;
						$this->redirect("/user/info?r=" . $url);
					}
				}
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find buyer with TNID: " . $companyId . ".");
			}


			// if found
			if (!empty($buyer->name)) {
				$activeCompany->type="b";
				$activeCompany->id = $buyer->id;
				$activeCompany->company = $buyer;
				$activeCompany->company->tnid = $buyer->id;
				setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => 'b')), time()+60*60*24*30, "/");
			} else {
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find buyer with TNID: " . $companyId . ".");
			}

			if ($companyId != $buyer->id) {
				$this->addSuccessMessage("Your buyer TNID: " . $companyId . " is translated to: " . $buyer->id . " (buyer organisation code).");
				$url = str_replace($companyId, $buyer->id, $url);
				$this->redirect("/user/info?r=" .  urlencode($url));
			}
		}

		// if supplier
		else if ($companyType === "v") {

			$supplier = Shipserv_Supplier::getInstanceByIdWithNormalisationCheck($companyId, "", true);


			if ($supplier->tnid > 0) {
				$activeCompany->type="v";
				$activeCompany->id = $supplier->tnid;
				$activeCompany->company = $supplier;

				// store it on cookie
				setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => 'v')), time()+60*60*24*30, "/");
			} else {
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find supplier with TNID: " . $companyId);
			}

		}

        // if consortia
        else if ($companyType === "c") {
            $consortia = Shipserv_Consortia::getConsortiaInstanceById($companyId);
            if ($consortia->internalRefNo > 0) {
                $activeCompany->type="c";
                $activeCompany->id = $consortia->internalRefNo;
                $activeCompany->company = $consortia;

                // store it on cookie
                setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => 'c')), time()+60*60*24*30, "/");
            } else {
                throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find supplier with TNID: " . $companyId);
            }

        }

        //add list of companies to the session
        Myshipserv_Helper_Session::updateReportTradingAccounts();

		if ($showMessage == true) { //TODO $showMessage is undefined... maybe the dev meant to use a GET param or similar
			$this->view->url = $url;
		} else {
			if ($this->_request->isXmlHttpRequest()) {
				$rData = array(
					'tnid' 		=> $activeCompany->id,
					'company' 	=> $activeCompany->company->name,
					'type' 		=> $activeCompany->type
				);
				$response = new Myshipserv_Response("200", "OK", $rData);
				$this->_helper->json((array)$response->toArray());
				$this->redirect($url, array('code'=>301));
			} else {
				Myshipserv_LoginNotification::send();
				$this->redirect($url, array('code'=>301));
			}
		}
	}

	/**
	 * Helper to redirect user
	 * @param unknown_type $url
	 */
	public function redirect($url, array $options = [])
	{
		if (strstr( $url, "?") !== false) {
			if (strstr( $url, "refr=") !== false) {
				$tmp = explode("?", $url);
				$tmp = explode("&", $tmp[1]);
				foreach ($tmp as $x) {
					if (strstr($x, "refr") !== false) {
						$b = explode("=", $x);
						$old = $b[1];
						$new = $old+1;
					}
				}
				$url = str_replace("&refr=" . $old, "&refr=" . $new, $url);
			} else {
				$url .= "&refr=1";
			}
		} else {
				$url .= "?refr=1";
		}

		$this->_helper->redirector->gotoUrl($url, $options);
	}

	/**
	 * @deprecated
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function getUserActivityAction()
	{
		$params = $this->_getAllParams();

		if ($params['userId'] == "") {
			throw new Myshipserv_Exception_MessagedException("Invalid userId");
		}

		$user = Shipserv_User::getInstanceById( $params['userId']);

		$response = new Myshipserv_Response("200", "OK", $user->getActivity( Myshipserv_Period::dayAgo(30) )->toArray());
		$this->_helper->json((array)$response->toArray());
	}

	/**
	 * Dispatch, or re-dispatch e-mail to confirm user's e-mail address
	 */
	public function sendConfirmEmailAction()
	{
		// Ensure only POST handled
		if (!$this->getRequest()->isPost())
		{
			throw new Exception("Expected POST");
		}

		try
		{
			// Ensure user is logged-in & fetch user object
			$logUser = Shipserv_User::isLoggedIn();
			if (!$logUser) {
				throw new UserController_SendConfirmEmailExc("Your session has expired, please log back in and try again.");
			}

			// Check that user's e-mail address is not already confirmed
			if ($logUser->isEmailConfirmed()) {
				// Handle already confirmed
				throw new UserController_SendConfirmEmailExc("Your e-mail has already been confirmed.");
			}

			// Send notification
			$nm = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
			$nm->confirmEmail($logUser->userId);

			// Success
			if ($this->_getParam('redirOk') != '') {
				$this->redirect($this->_getParam('redirOk'));
				exit;
			}
		}
		catch (UserController_SendConfirmEmailExc $e)
		{
			// Handled fail
			if ($this->_getParam('redirFail') != '') {
				$rUrl = $this->_getParam('redirFail');
				if (strpos($rUrl, '?') === false) {
					$rUrl .= '?';
				} else {
					$rUrl .= '&';
				}
				$rUrl .= 'confEmlSendErr=' . urlencode(base64_encode($e->getMessage()));
				$this->redirect($rUrl);
				exit;
			}
		}
	}

	public function infoAction()
	{
		$params = $this->params;
		$this->view->url = $params['r'];
	}

	/**
	 * Logs user out if logged in.
	 * Picks up explicit user parameter ('u') if specified.
	 * Re-directs to log-in (for target user if specified).
	 * Passes return URL through to log-in controller for re-direct on success.
	 */
	protected function redirectToLogin($email = '', $exit = true, $redirectUrl = '')
	{
		// changed by Yuriy Akopov on 2015-12-18 because the old code was redirecting to Pages' own login page no longer
		// supported and replaced by CAS
		return parent::redirectToLogin();

		/*
		// Log out if already logged in: required for log-in action to behave properly
		if (Shipserv_User::isLoggedIn())
		{
			Shipserv_User::logout();
		}

		// Pick up intended user
		$intendedUsr = $this->_getParam('u');
		if ($intendedUsr != '')
		{
			// Does intented user check-out as a valid user ID?
			$uDao = new Shipserv_Oracle_User($this->getInvokeArg('bootstrap')->getResource('db'));
			$uArr = $uDao->fetchUsers(array($intendedUsr))->makeShipservUsers();
		}

		// Build parameters for redirect
		$params = array();

		// Form target URL for redirection on successful log-in: take currently requested URL and add parameter to indicate redirection has taken place (avoids looping).
		// Note: this implementation is bad - can't cope with '?' parameters.
		// Unfortunately when I tried to send through a URL containing '?', Apache fell over.
		// Even though the parameter is base64 encoded! (& it should need this at all - url encoding ought to be sufficient)
		// So, there's something dodgy in our Apache set up ...
		$loginOkRedirectUrl = $_SERVER['REQUEST_URI'];
		if (strpos($loginOkRedirectUrl, '?') === false)
		{
			$loginOkRedirectUrl .= '/nr/1';
		}
		else
		{
			throw new Exception();
		}

		$params['registerRedirectUrl'] = $this->view->uri()->obfuscate($loginOkRedirectUrl);
		$params['benc'] = 1;
		$params['loginUsername'] = $uArr ? $uArr[0]->username : '';

		// Effect redirect
		$this->_helper->redirector('register-login', 'user', null, $params);

		// Ensure exit
		exit;
		*/
	}

	private function getUserDao()
	{
		static $uDao;

		if (!$uDao) {
			$uDao = new Shipserv_Oracle_User($this->getInvokeArg('bootstrap')->getResource('db'));
		}

		return $uDao;
	}

	private function checkConfirmEmailAuthToken ($passedToken, $userId, $dateTime)
	{
		$checkToken = Myshipserv_NotificationManager_Email_ConfirmEmail::makeToken($userId, $dateTime);
		return ($passedToken == $checkToken);
	}

	/**
	 * Generate auto login token for any given user
	 * This was meant for TN application - but never been used
	 * @throws Exception
	 */
	public function apiAction()
	{
    	$params = $this->params;
    	$userId = $params['userId'];
		$remoteIp = Myshipserv_Config::getUserIp();
		$db = $this->db;

	    if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
            throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
        }


    	if (empty( $params['call'])) {
    		throw new Myshipserv_Exception_MessagedException("Please enter call (call type)", 404);
    	}

    	if (empty( $userId ) || ctype_digit($userId)===false) {
    		throw new Myshipserv_Exception_MessagedException("Please enter UserId", 404);
    	}

    	if( empty( $params['url'] ) )
    	{
    		throw new Myshipserv_Exception_MessagedException("Please specify which URL the user will be redirected to");
    	}

    	try
    	{
	    	$user = Shipserv_User::getInstanceById($userId);
    	}
    	catch(Exception $e)
    	{
    		throw new Exception("User cannot be found");
    	}

		$token = new Myshipserv_AutoLoginToken($db);
		$tokenId = $token->generateToken($userId, $params['url'], '1 click');
		$response = new Myshipserv_Response(200, "OK", array("token" => array("id" => $tokenId, 'url' => $token->generateUrlToVerify())));
		$this->_helper->json((array)$response->toArray());

	}

	public function printableAction()
	{
		$p = $this->params;
		if ($p['d'] == "" || $p['id'] == "" || $p['h'] == "") {
			throw new Myshipserv_Exception_MessagedException("Invalid url, please check your url and try again.", 404);
		}

		// check hash
		if ($p['h'] == md5($p['d'] . $p['id'])) {

			switch( $p['d'] )
			{
				case 'ord': $doc = Shipserv_Order::getInstanceById($p['id']);
							break;

				case 'poc': $doc = Shipserv_PurchaseOrderConfirmation::getInstanceById($p['id']);
							break;

				case 'qot': $doc = Shipserv_Quote::getInstanceById($p['id']);
							break;

				case 'rfq': $doc = Shipserv_Rfq::getInstanceById($p['id']);
							break;
			}

			$this->redirect($doc->getUrl(), array('code' => 301));
		}
		else
		{
			throw new Myshipserv_Exception_MessagedException("You are not authorised to see this document.", 401);
		}
		die();
	}

	/**
	 * Registration
	 * List company types
	 * http://dev2.myshipserv.com/user/list-company-type
	 */
	public function listCompanyTypeAction()
	{
		// set up a connection to Oracle
		$oracleReference = new Shipserv_Oracle_Reference($this->db);

		foreach ($oracleReference->fetchCompanyTypes() as $row) {
			$data[] = array('id' => $row['PCT_ID'], 'name' => $row['PCT_COMPANY_TYPE']);
		}

		// fetch the job functions

		$this->_helper->json((array)$data);

	}

	/**
	 * Registration
	 * Listing all job types
	 * http://dev2.myshipserv.com/user/list-job-type
	 */
	public function listJobTypeAction()
	{
		// set up a connection to Oracle
		$oracleReference = new Shipserv_Oracle_Reference($this->db);
		foreach ($oracleReference->fetchJobFunctions() as $row) {
			$data[] = array('id' => $row['PJF_ID'], 'name' => $row['PJF_JOB_FUNCTION']);
		}

		$this->_helper->json((array)$data);

	}

	/**
	 * Registration
	 * Listing list of company budget
	 * http://dev2.myshipserv.com/user/list-company-annual-budget
	 */
	public function listCompanyAnnualBudgetAction()
	{
		$data = Shipserv_User::getOptionListForAnnualBudget();
		$this->_helper->json((array)$data);
	}

	/**
	 * Registration
	 * List all available vessel types
	 * http://dev2.myshipserv.com/user/list-vessel-type
	 */
	public function listVesselTypeAction()
	{
		$vesselDao = new Shipserv_Oracle_Vessel;
		$this->_helper->json((array)$vesselDao->getTypes());
	}


	
	public function completeProfileAction()
	{
		$params = $this->params;

		// set up a connection to Oracle
		$oracleReference = new Shipserv_Oracle_Reference($this->getInvokeArg('bootstrap')->getResource('db'));

		// fetch the company types
		$companyTypes = $oracleReference->fetchCompanyTypes();

		// fetch the job functions
		$jobFunctions = $oracleReference->fetchJobFunctions();

		$cookieManager = $this->getHelper('Cookie');

		// create the login and register form objects
		$loginForm = new Myshipserv_Form_Login();

		if (!$this->getRequest()->isPost()) {
			// For GET requests, have form pick up username from params
			$pLogin = $this->_getParam('loginUsername');
			if ($pLogin != '') $this->view->formValues = array('loginUsername' => $pLogin);
		}

		$registerForm = new Myshipserv_Form_Register($companyTypes, $jobFunctions);

		$user = null;

		// find out where to return the user to once they've logged in/registered
		if ($this->_getParam('returnUrl')) {
			$host    = 'https://' .  $_SERVER['HTTP_HOST'];
			$referer = str_replace($host, '', urldecode($this->_getParam('returnUrl')));
		} else {
			$host    = 'https://' .  $_SERVER['HTTP_HOST'];
			$referer = str_replace($host, '', $_SERVER['HTTP_REFERER']);
		}

		// check to see if the user has an enquiry lined up
		$hasEnquiry = false;
		if ($cookieManager->fetchCookie('enquiryStorage')) {
			$hasEnquiry = true;
		}

		if ($this->getRequest()->isPost()) {
			if ($params['loginRedirectUrl']) {
				$url = $params['loginRedirectUrl'];
			} else if( $params['registerRedirectUrl']) {
				$url = $params['registerRedirectUrl'];
			} else if( $params['redirectUrl']) {
				$url = $params['redirectUrl'];
			}

			// form was valid, attempt to register
			if ($url) {
				$redirectUrl = ($params['benc']) ? $this->view->uri()->deobfuscate($url) : $url;
				if (strstr($redirectUrl, 'redirectUrl') !== false) {
					$parts = parse_url($redirectUrl);
					parse_str($parts['query'], $queryStrings);
					$redirectUrl = ($queryStrings['benc']) ? $this->view->uri()->deobfuscate($queryStrings['redirectUrl']) : $queryStrings['redirectUrl'];
				}
			}

			$rememberMe = ($params['registerRememberMe']) ? true : false;
			$registerSuccess = false;

			// -----------------------------------------------------------
			$errors = array();
			try
			{

				// concat the address
				if ($params['address1'] != "")
					$companyAddressAsArray[] = $params['address1'];

				if ($params['address2'] != "")
					$companyAddressAsArray[] = $params['address2'];

				if ($params['address3'] != "")
					$companyAddressAsArray[] = $params['address3'];

				$user = Shipserv_User::isLoggedIn();


				// storing details about the new information into db
				$user->updateDetailedInformation(
					array(
						"isDecisionMaker" 	=> $params['decision'] ? 1 : 0,
						"cAddress" 			=> implode("\n", $companyAddressAsArray),
						"cZipcode" 			=> $params['zip'],
						"cCountryCode" 		=> $params['country'],
						"cPhone" 			=> $params['phone'],
						"cWebsite"			=> $params['web'],
						"cSpending"			=> $params['spend'],
						"cNoOfVessel" 		=> $params['vesselNo'],
						"vesselType" 		=> $params['vesselType'],
						"jobType" 			=> $params['jobType'],
						"companyName" 		=> $params['companyName']
					)
				);

				// marking the user that he/she has completed the further questions
				// on the registration form
				$user->completeDetailedInformation();

				$termAndCondition = Shipserv_Agreement_TermAndCondition::getLatest();
				$termAndCondition->setUser($user);

				$privacyPolicy = Shipserv_Agreement_PrivacyPolicy::getLatest();
				$privacyPolicy->setUser($user);

				$termAndCondition->agree();
				$privacyPolicy->agree();
				$user->confirmTermsAndConditions();

				$user->purgeMemcache();

				/**
				 * If a login or registration occurs, flush the analytics log and trigger
				 * and update to retrospectively update previous searches and profile views
				 * with the username of the user.
				 */
				$this->_helper->getHelper('Analytics')->flushAnalLog();

				// check if there's an enquiry cookie set...
				if ($hasEnquiry) {
					// there is - send the enquiry
					$goToUrl = '/enquiry/send-from-login-register';
				} else if ($redirectUrl) {
					if ($redirectUrl[0]!='/') {
						$redirectUrl = '/'.$redirectUrl;
					}

					if (strstr( $redirectUrl[0], 'register-login') !== false) {
						$redirectUrl = '/search';
					}

					// if a redirect url has been set then send the user that way
					$goToUrl = urldecode($redirectUrl);
				} else {
					// $this->redirect('/search', array('code' => 301));
					$goToUrl = '/search';
				}

				// no alternative actions - just show a success message
				$registerSuccess = true;
			}
			catch (Shipserv_User_Exception_RegisterEmailInUse $e)
			{
				$errors['top'][] = 'Your e-mail address is already taken. If you have forgotten your password, recover it <a href="/user/forgotten-password">here</a>.';
			}
			catch (Exception $e)
			{
				$errors['top'][] = $e->getMessage() != '' ? $e->getMessage() : 'An error occurred: please try again later';
			}

			// final check for redirection
			if (strstr($goToUrl, 'register-login') !== false) {
				$goToUrl = '/search';
			}

			$this->_helper->json((array)
				array(
					'status' => $registerSuccess
					, 'errors' => $errors
					, 'redirect' => $goToUrl
				)
			);
		}
	}


	public function registerAction()
	{    
	    
		// ensure the user isn't already logged in:
		if ($user = Shipserv_User::isLoggedIn()) {
			throw new Myshipserv_Exception_MessagedException('You have already logged in and registered to ShipServ');
		}

		$params = $this->params;
		$errors = array();
		
		$keepLogged = false;
		if (isset($params['keepLoggedIn']) && $params['keepLoggedIn'] === 'keepLogged') {
		    $keepLogged = true;
		}
		
		if ($this->getRequest()->isPost()) {
			if ($params['loginRedirectUrl']) {

				$url = $params['loginRedirectUrl'];
			} else if ($params['registerRedirectUrl']) {
				$url = $params['registerRedirectUrl'];
			} else if ($params['redirectUrl']) {
				$url = $params['redirectUrl'];
			}

			// form was valid, attempt to register
			if ($url) {
				$redirectUrl = ($params['benc']? $this->view->uri()->deobfuscate($url) : $url);
			}

			$rememberMe = ($params['registerRememberMe']) ? true : false;
			$registerSuccess = false;

			// -----------------------------------------------------------
			try
			{
				// concat the address
				if ($params['address1'] != "")
					$companyAddressAsArray[] = $params['address1'];

				if ($params['address2'] != "")
					$companyAddressAsArray[] = $params['address2'];

				if ($params['address3'] != "")
					$companyAddressAsArray[] = $params['address3'];

				// registering user via service
				$user = Shipserv_User::register(
					$params['emil'],
					$params['pwd'],
					$params['firstName'],
					$params['lastName'],
					$params['companyName'],
					$params['companyType'],
					($params['companyOtherType']) ? $params['companyOtherType'] : '',
					$params['jobType'],
					($params['jobOtherType']) ? $params['jobOtherType'] : '',
					$params['news'] ? true : false,
					$rememberMe,
					$params['decision'] ? 1 : 0,
					implode("\n", $companyAddressAsArray),
					$params['zip'],
					$params['country'],
					$params['phone'],
					$params['web'],
					$params['spend'],
					(int)$params['vesselNo'],
					$params['vesselType']
				);

				// get the user object and send email activation right after registration
				$u = Shipserv_User::getInstanceByEmail($params['emil']);
				$u->sendEmailActivation();

				// marking the user that he/she has completed the further questions
				// on the registration form
				$u->completeDetailedInformation();

				$termAndCondition = Shipserv_Agreement_TermAndCondition::getLatest();
				$termAndCondition->setUser($u);
				$privacyPolicy = Shipserv_Agreement_PrivacyPolicy::getLatest();
				$privacyPolicy->setUser($u);
				$termAndCondition->agree();
				$privacyPolicy->agree();
				$user->confirmTermsAndConditions();
				
				/**
				 * If a login or registration occurs, flush the analytics log and trigger
				 * and update to retrospectively update previous searches and profile views
				 * with the username of the user.
				*/
				//$this->_helper->getHelper('Analytics')->flushAnalLog();

				// check if there's an enquiry cookie set...

				$cookieManager = $this->getHelper('Cookie');			
				if ($cookieManager->fetchCookie('enquiryStorage')) {
					// there is - send the enquiry
					//$this->_forward('send-from-login-register', 'enquiry');
					$goToUrl = '/enquiry/send-from-login-register';
				} else if ($redirectUrl) {
					if ($redirectUrl[0] !== '/' && substr($redirectUrl, 0, 4) !== 'http') {
						$redirectUrl = '/' . $redirectUrl;
					}
					if (strstr($redirectUrl[0], 'register-login') !== false) {
						$redirectUrl = '/search';
					}
					// if a redirect url has been set then send the user that way
					//$this->redirect(urldecode($redirectUrl), array('code' => 301));
					$goToUrl = urldecode($redirectUrl);
				} else {
					// $this->redirect('/search', array('code' => 301));
					$goToUrl = '/search';
				}

				$autologinSucceed = Shipserv_User::autoLoginViaCas($params['emil'], $params['pwd'], $keepLogged);
				if ($autologinSucceed && preg_match('/\/auth\/cas\/login/', $goToUrl)) {
				    $goToUrl = 'https://' . $this->config['shipserv']['application']['hostname'] . '/search';
				}

				// no alternative actions - just show a success message
				$registerSuccess = true;

			}
			catch (Shipserv_User_Exception_RegisterEmailInUse $e)
			{
                $errors['top'][] = ($e->getMessage() === "Your email address is already taken") ? 'Your e-mail address is already taken. Please go back to Step 1 and change your email address' : $e->getMessage();
			}
			catch (Exception $e)
			{
			    trigger_error('Error in /user/register: ' . (String) $e, E_USER_WARNING);
				$errors['top'][] = $e->getMessage() != '' ? $e->getMessage() : 'An error occurred: please try again later';
			}

			$this->_helper->json((array)
				array(
					'status' => $registerSuccess
					, 'errors' => $errors
					, 'redirect' => $goToUrl
				)
			);
		}
	}


	/**
	 * WS to tell which app an user can access
	 */
	public function canAccessAction()
	{
		$response = false;
		$user = null;

		try
		{
			// get pages user
			$user = Shipserv_User::getInstanceById($this->getRequest()->getParam('userId'));
			$userType = 'pages';
		}
		catch( Shipserv_Oracle_User_Exception_NotFound $e )
		{
			try
			{
				// try tradenet user
				$user = Shipserv_User::getInstanceByTnUserId($this->params['userId']);
				$userType = 'tn';
			}
			catch( Shipserv_Oracle_User_Exception_NotFound $e ){}
		}
		catch(Exception $e) {
		    
		}

		if ($user !== null) {
			if ($this->getRequest()->getParam('app') == 'webreporter') {
				$response = $user->canAccessFeature($user::BRANCH_FILTER_WEBREPORTER);
			} else if( $this->getRequest()->getParam('app') == 'txnmon') {
				$response = $user->canAccessFeature($user::BRANCH_FILTER_TXNMON);
			} else if( $this->getRequest()->getParam('app') == 'pricebenchmark') {
				$response = $user->canAccessPriceBenchmark();
			} else if( $this->getRequest()->getParam('app') == 'matchbenchmark') {
				$response = $user->canAccessMatchBenchmark();
			}
		}

		$this->_helper->json((array)$response);
	}


	/**
	* Endpoint for webreporter to to show the content for TNID selector
	*/
	public function getTnidSelectorContentAction()
	{
		$companySelector = new Shipserv_User_CompanySelectorList();
		$this->_helper->json((array)$companySelector->getSelecorList());
	}

	/**
	* Interface endpoint for returning menu items, wich have to be dispayed on Webreporter
	* URL params, type can be shipmate or analyse,
	* active-url can be the active url, but not mandantory, works only for analyse panel
	*/
	public function getMenuOptionsToDisplayAction()
	{

		if (!array_key_exists('type', $this->params)) {
			throw new Myshipserv_Exception_MessagedException("Menu type parameter missing. Possible values analyse, shipmate.", 500);
		}

		$menuOptions = Shipserv_Profile_Menuoptions::getInstance();
		if (!$menuOptions->isLoggedIn()) {
			$this->_helper->json((array)array(
				'loginStatus' => false
				)
			);
		}

		$activeUrl = (array_key_exists('active-url', $this->params)) ? $this->params['active-url'] : null;

		switch ($this->params['type']) {
			case 'analyse':
				$this->_helper->json((array)array(
						 'loginStatus' => true
						,'shipmate' => $menuOptions->isShipmate()
						,'menuOptions' => $menuOptions->getAnalyseTabOptions($activeUrl)
					)
				);
				break;
			
			case 'shipmate':
				$pages = array();
				foreach($this->config['shipserv']['shipmate'] as $x)
				{
					foreach($x as $p)
					{
						$tmp = explode(",", $p);
						$pages[$tmp[0]] = "/help/proxy?u=" . $this->view->uri()->obfuscate($tmp[1]);
					}
				}
				$menuOptions->setParams($this->params);
				$menuOptions->setCustomPages($pages);
				$this->_helper->json((array)array(
					 'loginStatus' => true
					,'shipmate' => $menuOptions->isShipmate()
					,'menuOptions' => $menuOptions->getShipmateTabOptions($activeUrl)
					)
				);
				break;
			
			default:
				throw new Myshipserv_Exception_MessagedException("Menu type parameter must be  analyse, shipmate.", 500);
				break;
		}

	}

	public function sendPaswordResetEmailAction()
	{
		if (array_key_exists('email', $this->params)) {
			$passwordReminder = new Shipserv_User_PasswordResetByCas();
			if ($passwordReminder->sendReminder($this->params['email'])) {
				$this->_helper->json((array)array('result' => 'ok'));
			} else {
				$this->_helper->json((array)array('result' => 'error'));
			}
		} else {
			$this->_helper->json((array)array('result' => 'error'));
		}
	}

	public function loadingAction() {
		$this->_helper->layout->setLayout('simple');
		$data = parse_url($this->params['u']);
		
		switch ($data['path']) {
			case '/buyer/spend-graph':
				//S20380 Restrict access to non full members
				if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
					$this->_helper->layout->setLayout('default');
					$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
				};
				break;
			default:
				break;
		}

		$this->view->params['url'] = $data['path'] . "?" . $data['query'] . "&l=1";
	}

	public function resendVerificationEmailToAction()
	{
		if (array_key_exists('email', $this->params)) {
			$selectedUser = Shipserv_User::getInstanceByEmail($this->params['email']);
			if ($selectedUser) {
				$selectedUser->sendEmailActivation();
				$this->_helper->json((array)array('result' => 'ok'));
			} else {
				$this->_helper->json((array)array('result' => 'error'));
			}

		} else {
			$this->_helper->json((array)array('result' => 'error'));
		}

	}

	/**
	* Login page UI using CAS webservice
	*/
	public function loginAction()
	{
	    $request = $this->getRequest();
	    $serviceUrl = null;
		
		//Temporarily removed as it looks like when redirecting many times double urldecoded strings are present and passing this will result of invalid service
		//var_dump($request->getParam('service'));
		if (!$request->getParam('defaultService')) {
			if ($request->getParam('service') || $request->getParam('TARGET')) {
				$service = ($request->getParam('service')? $request->getParam('service') : $request->getParam('TARGET'));
				while ($service != urldecode($service)) {
					$service = urldecode($service);
				}
				$parts = parse_url($service);
				if ($parts) {
					$serviceUrl = $parts['scheme'].'://'.$parts['host'];
				}
			}
		}
	    
	    //Init Cas
		$casRest = Myshipserv_CAS_CasRest::getInstance();
		//$casRest->setValidateOnCasServer();
		$casRest->setServiceParams($request->getParams());
		if ($serviceUrl) {
			$casRest->setServiceUrl($serviceUrl);
		}

	    //Set Pages or TradeNet layout
		$newLayout  = ($request->getParam('pageLayout') === 'new');

		//We also have to check the referrer, if logintoSystem wrongly redirects with parameter new, we still need the old Tradenet behaviour
		if (strpos($casRest->getServiceUrl(), '/LoginToSystem') !== false || strpos($casRest->getServiceUrl(), 'ST-') !== false) {
			$newLayout = false;
		}

		if ($newLayout === false) {
		    $this->_helper->layout->setLayout('login-to-tradenet');
		    $this->_helper->viewRenderer('user/login-to-tradenet', null, true);
		} else {
		    $this->_helper->viewRenderer('user/login-to-pages', null, true);
		}		
		$loginType = ($newLayout? Myshipserv_CAS_CasRest::LOGIN_PAGES : Myshipserv_CAS_CasRest::LOGIN_TRADENET);
		

		if (!$casRest->validateService()) {
			throw new Myshipserv_Exception_MessagedException("Sorry, you cannot log in, Login URL is invalid.", 500);
		}

		//If already logged in, redirect
		if ($casRest->casCheckLoggedIn($loginType)) {
                $validOnCasServer = true;

                if (array_key_exists('service', $this->params) || array_key_exists('TARGET', $this->params)) {
                    $validOnCasServer = $casRest->validateCurrentTgt();
                }

                if ($validOnCasServer === true) {
                    $redirectUrl = $casRest->generateRedirectUrl();
                    $casRest->updateCookie();
                    $this->redirect($redirectUrl, array('exit' => true));
                }
		}

		//Init some view vars
		$this->view->displayLoginError = false;
		$this->view->displayCaptchaError = ($casRest->shouldUseCaptcha()? 'Please confirm you are not a robot' : '');
		$this->view->isLockedOut = false;		
		$this->view->displayCaptcha = $casRest->shouldUseCaptcha();
		$this->view->userName = '';

		//Handle the post of user credentials
		if ($request->isPost()) {

		    //If username and/or password were not provided, show proper erros message and do not perform login
		    $this->view->userName = $request->getParam('username', '');
		    if (!$request->getParam('username') || !$request->getParam('password')) {
		        $this->view->displayLoginError = 'Please type username and password';
		        return;
		    }
		    
		    //If the user is locked out, raise error message without even checking posted data
		    if ($casRest->isLockedOut($request->getParam('username'))) {
		        $this->view->isLockedOut = true;
		        $this->view->displayLoginError = 'Account temporarily disabled';
		        return;
		    }

			//If ReCaptcha is not valid, show proper erros message and do not perform login
			if ($casRest->shouldUseCaptcha()) {
    			$reCaptchaResponse = $request->getParam('g-recaptcha-response');
    			if (!$reCaptchaResponse) {
    			    $this->view->displayCaptchaError = 'Please confirm you are not a robot';
    			    return;			    
    			}
    			if (!$this->_helper->googleReCaptcha->verifyUserResponse($reCaptchaResponse)) {
    			    $this->view->displayCaptchaError = 'Cannot validate ReCaptcha';
    			    return;
    			}
			}

            //Try to autenticate throught CAS lib
			$rememberMe = $request->getParam('rememberMe', false);
			if (!$casRest->casAuthenticate(trim($request->getParam('username')), trim($request->getParam('password')), $rememberMe, $loginType, false /*Do not acept super password S18818*/)) {
				$this->view->displayLoginError = 'Sorry, your credentials are incorrect';
				$this->view->displayCaptcha = $casRest->shouldUseCaptcha();
				$this->view->isLockedOut = $casRest->isLockedOut($request->getParam('username'));

				//Log failed sign in 
				try {
					$user = Shipserv_User::getInstanceByEmail($request->getParam('username'));
				} catch (Exception $e) {
					if (!($e instanceof Shipserv_Oracle_User_Exception_NotFound)) {
						throw $e;
					}
				}
				
				if ($user) {
					$user->logActivity(Shipserv_User_Activity::USER_FAILED_LOGIN, 'PAGES_USER', $user->userId, $user->email);
				}

				return;
			}
			
			// Login succeeded.
			// clear user group cache
			$dao = new Shipserv_Oracle_PagesUserCompany();
			$dao->flushUserCache();

			// Redirect to correct url
			$redirectUrl = $casRest->generateRedirectUrl();
			$this->redirect($redirectUrl, array('exit' => true));
		} 
	}


	/**
	 * Forgot password web interface using CAS webservice
	 */
	public function forgotPasswordAction()
	{
		$this->view->netId = (array_key_exists('netId', $this->params))? $this->params['netId'] : '';
		$this->view->error = '';
		if ($this->getRequest()->isPost()) {
			$this->view->cas = new Myshipserv_View_Helper_Cas();
			if ($this->view->netId === '') {
				$this->view->error = 'Please enter a username or Email.';
			} else {
				$casResetPassword = Myshipserv_CAS_CasResetPassword::getInstance();
				if ($casResetPassword->sendPasswordReminderEmail($this->view->netId) === true) {
					$this->_helper->viewRenderer('user/forgot-password-confirmation', null, true);
				} else {
					$this->view->error = $casResetPassword->getErrorMessage();
				}
			}
		}
	}

	
	public function changeForgottenPasswordAction()
	{

		//BUY-267 If we are logged in, logout the user from pages
		$casRestForLogout = Myshipserv_CAS_CasRest::getInstance();
		if ($casRestForLogout->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
			$cas = new Myshipserv_View_Helper_Cas();
			$redirectUrl = $cas->getRootDomain() . '/auth/cas/passwordManager?pmTask=changePassword&requestTicket=' . $this->getRequest()->getParam('requestTicket');
			$redirectTo = '/auth/cas/logout?app=pages&casredir=1&service=' . urlencode($redirectUrl);
			$this->redirect($redirectTo);
		}
		
		$ticket = (array_key_exists('requestTicket', $this->params)) ? $this->params['requestTicket'] : null;
		if (!$ticket) {
			$this->_helper->viewRenderer('user/forgot-password-expired', null, true);
		}

		$casResetPassword = Myshipserv_CAS_CasResetPassword::getInstance();
	
		if ($casResetPassword->validateTicket($ticket) === false) {
			$this->view->error = $casResetPassword->getErrorMessage();
			$this->_helper->viewRenderer('user/forgot-password-expired', null, true);
		} else {
		    $lastResetpassowrResponse = $casResetPassword->getLastPasswordResponse();

		    if (isset($lastResetpassowrResponse->message) && $lastResetpassowrResponse->message !== '' ) {
                $this->view->username = $lastResetpassowrResponse->message;
            }

			$this->view->validation = Myshipserv_Validate_ForgottenPasswordValidate::valid();
			if ($this->getRequest()->isPost()) {
				$validation = Myshipserv_Validate_ForgottenPasswordValidate::validate($this->params);
				if ($validation['valid'] === true) {
					if ($casResetPassword->resetPassword($this->params['username'], $ticket, $this->params['password'], $this->params['confirmPassword'])) {
						$this->view->sessionType = (strstr($this->params['username'], '@') !== false) ? Myshipserv_CAS_CasRest::SESSION_PAGES : Myshipserv_CAS_CasRest::SESSION_TRADENET;
						$this->view->cas = new Myshipserv_View_Helper_Cas();
						$this->_helper->viewRenderer('user/forgot-password-changed', null, true);
					} else {
						$this->view->validation = $validation;
						$this->view->validation['general'] = $casResetPassword->getErrorMessage();
					}
				} else {
					$this->view->validation = $validation;
				}
			}
		}
	}
	
	/**
	* Emulate the route of the old cas Password Manager for backward compatibility
	*/
	public function casPasswordManagerAction()
	{
		$pmTask = array_key_exists('pmTask', $this->params) ? $this->params['pmTask'] : '';

		switch ($pmTask) {
			case 'forgotPassword':
				$this->_forward('forgot-password');
				break;
			case 'changePassword':
				$this->_forward('change-forgotten-password');
				break;
			default:
				throw new Myshipserv_Exception_MessagedException("Sorry, URL is invalid.", 500);
				break;
		}
	}

	/**
	* Rerirect to Adim GW and repalce CAS Cookie
	*/
	public function loginToAdminGwAction()
	{
		$casRest = Myshipserv_CAS_CasRest::getInstance();
		
		//validating current TGT against CAS
		if ($casRest->validateCurrentTgt() === false) {
			$cas = new Myshipserv_View_Helper_Cas();
			$loginUrl = $cas->getCasRestLogin().'&service=' . urlencode(urlencode($cas->getRootDomain().'/user/cas?redirect='.urlencode('/shipmate')));
			$this->view->loginUrl = $cas->getRootDomain() .'/auth/cas/logout?app=pages&casredir=1&service=' . urlencode($loginUrl);
		} else {
			$this->redirect($casRest->forceRedirectToCasAsPagesApp('/shipservadmin/tradenetprofile/home.jpf', false, false));
		}
	}

	/**
	* Rerirect to pages Admin and repalce CAS Cookie
	*/
	public function loginToPagesAdmAction()
	{
		$casRest = Myshipserv_CAS_CasRest::getInstance();
		
		//validating current TGT against CAS
		if ($casRest->validateCurrentTgt() === false) {
			$cas = new Myshipserv_View_Helper_Cas();
			$loginUrl = $cas->getCasRestLogin().'&service=' . urlencode(urlencode($cas->getRootDomain().'/user/cas?redirect='.urlencode('/shipmate')));
			$this->view->loginUrl = $cas->getRootDomain() .'/auth/cas/logout?app=pages&casredir=1&service=' . urlencode($loginUrl);
		} else {
			$this->redirect($casRest->forceRedirectToCasAsPagesApp('/pages/admin/admin/adminHome.jsf', false, false));
		}
		
	}
	
	/**
	 * Internal webservice used by Cas to delete a locked status for a user
	 */
    public function clearLockoutStatusAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();        
        $response = array('success' => 1, 'message' => '');
        if (!$this->isIpInternal(Myshipserv_Config::getUserIp())) {
            $response['success'] = 0;
            $response['message'] = 'you are not allowed to perform this action';            
        } elseif (!$username = $this->getRequest()->getParam('username')) {
            $response['success'] = 0;
            $response['message'] = 'no username param provided';
        } else {
            $success = Myshipserv_CAS_CasRest::getInstance()->clearLockoutStatus($username);
            $response['success'] = (Bool) $success;
            if (!$success) {
                $response['message'] = 'no lockout status for this user, or error encountered';
            }
        }
        $this->_helper->json((array)$response);
    }
    
    /**
     * Internal webservice used by Cas to delete a captcha status for a user
     */
    public function clearCaptchaStatusAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $response = array('success' => 1, 'message' => '');
        if (filter_var(Myshipserv_Config::getUserIp(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE)) {
            $response['success'] = 0;
            $response['message'] = 'you are not allowed to perform this action';            
        } elseif (!$ip = $this->getRequest()->getParam('ip')) {
            $response['success'] = 0;
            $response['message'] = 'no ip param provided';
        } else {
            $success = Myshipserv_CAS_CasRest::getInstance()->clearCaptchaStatus($ip);
            $response['success'] = (Bool) $success;
            if (!$success) {
                $response['message'] = 'no lockout status for this IP, or error encountered';
            }
        }
        $this->_helper->json((array)$response);
    }    

    /**
	 * Welcome message action, show instead of general news
	 * @return unknown
	 */
	public function welcomeAction()
	{
		$this->_helper->layout->setLayout('empty');
	}

}

class UserController_SendConfirmEmailExc extends Exception
{

}

class UserController_ConfirmEmailExc extends Exception
{

}
