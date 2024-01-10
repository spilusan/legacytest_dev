<?php
/**
* Main controller for webreporter
*/
class Webreporter_IndexController extends Myshipserv_Controller_Action
{
    /**
    * Init webreporter session variables, and config
    * @return unknown
    */
    public function init()
    {
        parent::init();
        Myshipserv_Webreporter_Init::getInstance();
        
        $iniFile = '/prod/application.ini';
        $this->pagesConfig = new Zend_Config_Ini($iniFile, $_SERVER['APPLICATION_ENV']);
    }

    /**
    * Check session was refactored and does not check the session, but login status
    * @return string username stored in session
    */
    protected function checkSession()
    {

        // Get global variables
        $registry     = Zend_Registry::getInstance();
        $appUseLog    = $registry['app_use_log'];
        $appMessages  = $registry['app_config_messages'];
        $username     = null;

        $casRest = Myshipserv_CAS_CasRest::getInstance();

        if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_PAGES)) {
            $username = $casRest->getUserName();
        } else if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
            //We are logged in as Tradenet, but not logged in as pages, We should tell the customer that webreporter no longer accessible by tradenet, redirect to info page
            $this->redirect('/webreporter/not-available-for-tradenet');
        }

        //S17813 Allow only Pages Users to login
        if ($username === null) {
            $appUseLog->info($appMessages->en->NoUserAndNoSession);
            $appUseLog->info($appMessages->en->RedirectCASLogin);
            $this->redirect($this->getLoginUrl());
        }

        return $casRest->getUserName();
    }

    /**
    * Index action
    * @return unknown
    */
    public function indexAction()
    {
        //It was working different way in UKDEV
        if (isset($_SESSION) === false) { 
            _session_start(); 
        }

        $session_id = session_id();

        // Get global variables
        $registry      = Zend_Registry::getInstance();
        $appUseLog     = $registry['app_use_log'];
        $appMessages   = $registry['app_config_messages'];
        $appNamespace  = $registry['app_namespace'];
        $appConfigEnv  = $registry['app_config_env'];
        $appReportList = $registry['app_report_list'];
        // $appCustomReportList = $registry['app_custom_report_list'];

        $appUsername = $this->isLoggedInOnCasPages();

        if ($appUsername === false) {
            $appUsername = $this->checkSession();
            $isShipMate = false;
        } else {
            $isShipMate = ($this->_getParam('tab') == 'shipmate');
        }
        
        if ($isShipMate) {
            $this->getRequest()->setParam('forceTab', 'shipmate');
        } else {
            $this->getRequest()->setParam('forceTab', 'analyse');
        }
        
        $user = Shipserv_User::isLOggedIn();
        if (!($user && $user->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER))) {
            throw new Exception("Error Processing Request, please check the user rights.", 500);
        }
        
        $_SESSION[$appUsername]['isShipmate'] = $isShipMate;

        // Set session variables
        $_SESSION[$appUsername]['logPrefix'] = $appUsername . ' :: ';
        $_SESSION[$appUsername]['casUrlPwd'] = str_replace('<username>', $appUsername, (string)$appConfigEnv->cas->url->pwd);
        $appUseLog->info($appMessages->en->SessionUserReady . $appUsername);

        // Get user profiles
        if (array_key_exists('userProfiles', $_SESSION[$appUsername]) === false || $_SESSION[$appUsername]['userProfiles']['user']['usrsid'] !== $session_id || 1==1) {
            // S17880 Adding active selected company
            $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
            // Call 'get-usr-profiles' service
            $paramPost = array(
                'app_code'       => $appNamespace,
                'rpt_code'       => 'GET-USR-PROFILES',
                'user_name'      => $appUsername,
                'is_ship_mate'   =>  ($isShipMate) ? '1' : '0',
                'byo_tnid'       => $sessionActiveCompany->id,
                'byb_tnid'       => '99999',        // Dummy
                'to_date'        => '04-APR-1974',  // Dummy
                'from_date'      => '04-APR-1974',  // Dummy
                'rpt_as_of_date' => '04-APR-1974',  // Dummy
            );

            $data = array();
            $exception = '';

            $apiId  = mt_rand(10000, 99999);
            $apiUrl = (string)$appConfigEnv->app->url->api;

            $logMsg = $_SESSION[$appUsername]['logPrefix'] . 'id = ' . $apiId;

            $appUseLog->info($logMsg . ' ; url = ' . $apiUrl);
            $appUseLog->info($logMsg . ' ; post params = ' . json_encode($paramPost));


            $service = new Zend_Http_Client($apiUrl, array('timeout' => 1800, 'useragent' => $appNamespace));
            $service->setParameterPost($paramPost);

            try {
                $data = $service->request(Zend_Http_Client::POST)->getBody();
                $data = Zend_Json::decode($data);
            } catch (Zend_Exception $e) {
                $exception = $e->getMessage();
                $appUseLog->info($logMsg . ' ; post exception = ' . $exception);
            }
            if (strtolower($data['status']) !== 'ok') {

                $appUseLog->info($logMsg . ' ; post data = ' . json_encode($data));

                // Email error to someone
                $mail = new Zend_Mail();

                $mail->setBodyText(
                    $logMsg . ' ; url = ' . $apiUrl . "\n" .
                    $logMsg . ' ; post params = ' . json_encode($paramPost) . "\n" .
                    $logMsg . ' ; post data = ' . json_encode($data) . "\n" .
                    $logMsg . ' ; post exception = ' . $exception . "\n"
                );

                $mail->setFrom($appConfigEnv->api->error->email->from);
                $mail->addTo($appConfigEnv->api->error->email->to);
                $mail->setSubject($appConfigEnv->api->error->email->subject);
                $mail->send();

            } else {

            	if (count($data['user_profile']) === 0) {

                    // Redirect to exception
                    $appUseLog->info($logMsg . ' ; user is not allowed to use WebReporter.');
                    throw new ErrorException('You are not allowed to use WebReporter.', 403);

                    flush();
                    exit;
                }

                // Determine if we have additional buyers
                if (count($data['user_buyers']) === 0) {
                    $data['user_buyers'][] = array(
                        'bybtnid'  => $data['user_profile']['bybtnid'],
                        'bybname'  => $data['user_profile']['bybname'],
                        'istest'   => $data['user_profile']['bybistest'],
                        'isparent' => $data['user_profile']['bybisparent'],
                        'usrmd5'   => $data['user_profile']['usrmd5'],
                        'usrcode'  => $data['user_profile']['usrcode'],
                    );
                //S16570
                } elseif (substr($data['user_buyers'][0]['bybtnid'], 0, 4) !== 'ALL-' && count($data['user_buyers']) > 1) {
                    $allBuyersString = array(
                        'bybtnid' => '__ALL__',
                        'bybname' => 'All My Companies',
                        'bybistest' => 0,
                        'bybisparent' => 1,
                        'usrcode' => '',
                        'usrmd5' => '',
                        'bybname_sort' => ''
                    );
  
                    array_unshift($data['user_buyers'], $allBuyersString);
                }

                // Add report codes to the reports
                $reports = array();
                foreach ($data['user_reports'] as $report) {
                    foreach ($appReportList as $list) {
                        if ($report['rptiscustom'] === '0' && strtolower($report['rptname']) === strtolower($list['rptname'])) {
                            $reports[] = array(
                                'rptcode' => $list['rptcode'],
                                'rptname' => $list['rptname'],
                                'rptsort' => $registry['app_report_info'][$list['rptcode']]['rptcolsort'],
                            );
                        }
                    }
                }

                // Put it back to reports array
                $data['user_reports'] = $reports;
                $_SESSION[$appUsername]['userProfiles'] = array(
                    'user'  => array(
                        'usrsid'  => $session_id,
                        'usrmd5'  => $data['user_profile']['usrmd5'],
                        'usrcode' => $data['user_profile']['usrcode'],
                        'usrname' => $data['user_profile']['usrname'],
                        'other_usrmd5'  => (isset($data['user_profile']['other_usrmd5'])? $data['user_profile']['other_usrmd5'] : ''),
                        'other_usrcode' => (isset($data['user_profile']['other_usrcode'])? $data['user_profile']['other_usrcode'] : ''),
                        'other_usrname' => (isset($data['user_profile']['other_usrname'])? $data['user_profile']['other_usrname'] : ''),
                    ),
                    'buyer' => array(
                        'bybtnid'  => $data['user_profile']['bybtnid'],
                        'bybname'  => $data['user_profile']['bybname'],
                        'istest'   => $data['user_profile']['bybistest'],
                        'isparent' => $data['user_profile']['bybisparent'],
                    ),
                    'buyers'  => $data['user_buyers'],
                    'reports' => $data['user_reports'],
                    'default_buyer_branch' => $data['default_buyer_branch'],
                    // 'customs' => $data['user_custom_reports'],
                );
                //print_r($_SESSION[$appUsername]['userProfiles']);

                // Call 'get-currencies' service
                $paramPost = array(
                    'app_code'       => $appNamespace,
                    'rpt_code'       => 'GET-CURRENCIES',
                    'user_name'      => $appUsername,
                    'byb_tnid'       => '99999',        // Dummy
                    'to_date'        => '04-APR-1974',  // Dummy
                    'from_date'      => '04-APR-1974',  // Dummy
                    'rpt_as_of_date' => '04-APR-1974',  // Dummy
                );

                $data = array();
                $exception = '';

                $apiId  = mt_rand(10000, 99999);
                $apiUrl = (string)$appConfigEnv->app->url->api;

                $logMsg = $_SESSION[$appUsername]['logPrefix'] . 'id = ' . $apiId;
                $appUseLog->info($logMsg . ' ; url = ' . $apiUrl);
                $appUseLog->info($logMsg . ' ; post params = ' . json_encode($paramPost));

                $service = new Zend_Http_Client($apiUrl, array('timeout' => 1800, 'useragent' => $appNamespace));
                $service->setParameterPost($paramPost);

                try {
                    $data = $service->request(Zend_Http_Client::POST)->getBody();
                    $data = Zend_Json::decode($data);
                } catch (Zend_Exception $e) {
                    $exception = $e->getMessage();
                    $appUseLog->info($logMsg . ' ; post exception = ' . $exception);
                }

                if (strtolower($data['status']) !== 'ok') {

                    $appUseLog->info($logMsg . ' ; post data = ' . json_encode($data));

                    // Email error to someone
                    $mail = new Zend_Mail();

                    $mail->setBodyText(
                        $logMsg . ' ; url = ' . $apiUrl . "\n" .
                        $logMsg . ' ; post params = ' . json_encode($paramPost) . "\n" .
                        $logMsg . ' ; post data = ' . json_encode($data) . "\n" .
                        $logMsg . ' ; post exception = ' . $exception . "\n"
                    );

                    $mail->setFrom($appConfigEnv->api->error->email->from);
                    $mail->addTo($appConfigEnv->api->error->email->to);
                    $mail->setSubject($appConfigEnv->api->error->email->subject);
                    $mail->send();

                }

                // Process top and rest currencies
                $topRates  = array();
                $restRates = array();
                if (count($data['currencies']) > 0) {
                    foreach ($data['currencies'] as $currency) {
                        if ($currency['curristop'] === '1') {
                            $topRates[] = array(
                                'currcode' => $currency['currcode'],
                                'currname' => $currency['currname'],
                            );
                        } else {
                            $restRates[] = array(
                                'currcode' => $currency['currcode'],
                                'currname' => $currency['currname'],
                            );
                        }
                    }
                }

                $_SESSION[$appUsername]['userProfiles']['top_rates']  = $topRates;
                $_SESSION[$appUsername]['userProfiles']['rest_rates'] = $restRates;

                $_SESSION['texts']['shipserv_custom']  = $appMessages->en->ShipServCustomText;
                $_SESSION['texts']['shipserv_welcome'] = $appMessages->en->ShipServWelcomeText;
            }
        }

           // Determine text rotation for Footer text
        $looper = 1;
        if (empty($_SESSION['texts']['shipserv_footer_looper']) === true || isset($_SESSION['texts']['shipserv_footer_looper']) === false) {
            $_SESSION['texts']['shipserv_footer_looper'] = $looper;
        } else {
            $looper = $_SESSION['texts']['shipserv_footer_looper'];
            $looper = $looper + 1;

            if ($looper > 5) {
                $looper = 1;
            }
            $_SESSION['texts']['shipserv_footer_looper'] = $looper;
        }

        switch ($looper) {
            case 1:
                $_SESSION['texts']['shipserv_footer'] = $appMessages->en->ShipServFooterText01;
                break;
            case 2:
                $_SESSION['texts']['shipserv_footer'] = $appMessages->en->ShipServFooterText02;
                break;
            case 3:
                $_SESSION['texts']['shipserv_footer'] = $appMessages->en->ShipServFooterText03;
                break;
            case 4:
                $_SESSION['texts']['shipserv_footer'] = $appMessages->en->ShipServFooterText04;
                break;
            case 5:
                $_SESSION['texts']['shipserv_footer'] = $appMessages->en->ShipServFooterText05;
                break;
        }
        

        $this->view->data = array(
            'user'         => $_SESSION[$appUsername]['userProfiles']['user'],
            'buyer'        => $_SESSION[$appUsername]['userProfiles']['buyer'],
            'buyers'       => $_SESSION[$appUsername]['userProfiles']['buyers'],
            'reports'      => $_SESSION[$appUsername]['userProfiles']['reports'],
            // 'customs'      => $_SESSION[$appUsername]['userProfiles']['customs'],
            'casPwdUrl'    => $_SESSION[$appUsername]['casUrlPwd'],
            'top_rates'    => $_SESSION[$appUsername]['userProfiles']['top_rates'],
            'rest_rates'   => $_SESSION[$appUsername]['userProfiles']['rest_rates'],
            'text_welcome' => $_SESSION['texts']['shipserv_welcome'],
            'text_custom'  => $_SESSION['texts']['shipserv_custom'],
            'text_footer'  => $_SESSION['texts']['shipserv_footer'],
            'logoutUrl'    => (string)$appConfigEnv->cas->url->logout,
            'state'        => 'index',
            'applications' => array(
                'webreporter' 		=> $this->checkIfUserCanAccessApp($_SESSION[$appUsername]['userProfiles']['user']['usrcode'], 'webreporter'),
                'txnmon' 			=> $this->checkIfUserCanAccessApp($_SESSION[$appUsername]['userProfiles']['user']['usrcode'], 'txnmon'),
                'pricebenchmark' 	=> $this->checkIfUserCanAccessApp($_SESSION[$appUsername]['userProfiles']['user']['usrcode'], 'pricebenchmark'),
                'matchbenchmark'  => $this->checkIfUserCanAccessApp($_SESSION[$appUsername]['userProfiles']['user']['usrcode'], 'matchbenchmark')
        	),
            'isShipMate' =>  $_SESSION[$appUsername]['isShipmate'],
            'defaultBuyerBranch' => $_SESSION[$appUsername]['userProfiles']['default_buyer_branch']
        );

        $appUseLog->info($_SESSION[$appUsername]['logPrefix'] . 'user redirected to index/index/.');
        $this->_helper->viewRenderer('index/index', null, true);

        $user = Shipserv_User::isLOggedIn();
        if ($user) {
            $user->logActivity(Shipserv_User_Activity::WEBREPORTER_LAUNCH, 'PAGES_USER', $user->userId, $user->email);
        }
    }

    /**
    * Action to display help page
    * @return unknown
    */
    public function helpAction()
    {
        // Get global variables
        $registry     = Zend_Registry::getInstance();
        $appUseLog    = $registry['app_use_log'];

        $appUsername = $this->checkSession();
        $user = Shipserv_User::isLOggedIn();

        $this->view->isPagesUser = ($user) ? true : false;

        if ($user) {
            $this->view->isShipMate = $user->isShipservUser();
        } else {
            $this->view->isShipMate = false;
        }

        $appUseLog->info($_SESSION[$appUsername]['logPrefix'] . 'user redirected to index/help/.');
        $this->_helper->viewRenderer('index/help', null, true);
    }

    /**
    * Cas login action
    * Check if logged in, and redirect to login page if not, or to webreporter
    * @return unknown
    */
    public function casAction()
    {
        // Get global variables
        $registry     = Zend_Registry::getInstance();
        $appUseLog    = $registry['app_use_log'];
        $appMessages  = $registry['app_config_messages'];
             
        $user = Shipserv_User::isLOggedIn();

        if ($user) {
            $appUsername = $user->username;
            $appUseLog->info($appMessages->en->CASAuthUserPass . $appUsername);
            $appUseLog->info($appMessages->en->RedirectUser . $appUsername . ' to /index/ from casAction controller.');
            $this->redirect('/webreporter');
        } else {
            $this->redirect($this->getLoginUrl());
        }
        return;
    }

    /**
    * Redirect to login page action, Kept for compatibility 
    * @return unknown
    */
    public function loginAction()
    {
        $this->redirect($this->getLoginUrl());
    }

    /**
    * Redirect to pages logout page, kept for compatibility
    * @return unknown
    */
    public function logoutAction()
    {
        $cas = new Myshipserv_View_Helper_Cas();
        $logOutUrl = $cas->getCasLogout();
        $this->redirect($logOutUrl);
    }

    /**
    * Checks if the current user is logged in, and returns a user object if they are
    *
    * @access public
    * @static
    * @return Shipserv_User|bool
    */
    public static function isLoggedInOnCasPages()
    {
        $user = Shipserv_User::isLOggedIn();
        if ($user) {
            $username = $user->username;
            if (strstr($username, '@') !== false) {
                // Check if user is shipmate
                if ($user->isShipservUser()) {
                    return $username;
                }
            }
        }

        return false;
    }

    /**
    * Check if used can access an application
    * @param integer $userId the id of the user
    * @param string $application The application name
    * @return string the response from the backend service
    */
    public function checkIfUserCanAccessApp($userId, $application)
    {

    	$registry = Zend_Registry::getInstance();
    	$appConfigEnv = $registry['app_config_env'];
		$url = 'https://' . $appConfigEnv->app->domain . '/user/can-access';

    	$service = new Zend_Http_Client($url, array('timeout' => 1800));
    	$service->setParameterGet(
            array(
            'userId' => $userId
            , 'app' => $application
            )
        );

    	$data = null;
    	try {
    		$data = $service->request(Zend_Http_Client::POST)->getBody();
    		$data = Zend_Json::decode($data);
    	} catch (Zend_Exception $e) {
    		echo $e->getMessage();
    	}

    	return $data;
    }

    /**
    * Return login url for webreporter
    * @return string 
    */
    protected function getLoginUrl()
    {
        $cas = new Myshipserv_View_Helper_Cas();
        $loginUrl = $cas->getCasRestLogin().'&service='.urlencode($cas->getRootDomain() . '/user/cas?redirect='.urlencode($cas->getRootDomain() . '/webreporter'));
        return $loginUrl;
    }

}
