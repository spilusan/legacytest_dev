<?php

/**
 * Controller for handling enquiry form and sending, as well as the supplier 'basket'
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class EnquiryController extends Myshipserv_Controller_Action
{
    const UPLOAD_PATH = '/tmp/enquiry/upload/';
    const ACCEPTED_FILE_TYPE = 'gif|jpeg|jpg|png|bmp|pdf|doc|docx|rtf|xls|xlsx|csv|txt';
    
    /**
     * Initialise the controller - set up the context helpers for AJAX calls
     * 
     * @access public
     */
    public function init()
    {
    	parent::init();
    	
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('add-supplier-to-basket', 'json')
                    ->addActionContext('remove-supplier-from-basket', 'json')
                    ->addActionContext('fetch-supplier-basket', 'json')
                    ->addActionContext('blocked-RFQ-sender', 'json')
                    ->addActionContext('generate-form', 'html')
                    ->initContext();
    }
    
    /**
     * Helper function to handle/check custom routing for PPG company
     * Validation to check if any PPG is on the supplier basket
     * 
     */
    public function isPPGCompanies( $supplierBasket )
    {
		$config = $this->config;
		
		// PPG TNID - shipserv.enquiry.customRoute.PPG.tnid
		if( strstr($config["shipserv"]["enquiry"]["customRoute"]["PPG"]["tnid"], ',') )
		{
			$tnids = explode(",", $config["shipserv"]["enquiry"]["customRoute"]["PPG"]["tnid"]);
		}
		else
		{
			$tnids = array($config["shipserv"]["enquiry"]["customRoute"]["PPG"]["tnid"]);
		}
    	foreach ((array)$supplierBasket as $tnid )
    	{
    		if( in_array($tnid, $tnids) ) return true;
    	}
    	return false;
    }
    
    /**
     * JSON endpoints to view all, add, delete blocked RFQ senders
     */
    public function blockedSenderAction()
    {
		$params = $this->params;
		
		$adapter = new Shipserv_Oracle_Enquiry();
    	if( !isset( $params["a"] ) )
    	{
    		$result = $adapter->getBlockedUserBySupplierId( $params["tnid"] );
    		//$response = new Myshipserv_Response("200", "OK", $result );
    		$this->_helper->json((array) $result );
    	}
    	else if( $params["a"] == 'd' )
    	{
    		$result = array();
    		$res = $adapter->deleteBlockedUser(  $params['tnid'], $params['uid'] );
    		$result = $adapter->getBlockedUserBySupplierId( $params["tnid"] );
   			$response = new Myshipserv_Response("200", "OK", $result );
   			$this->_helper->json((array) $response->toArray() );
    	}
    	else if( $params['a'] == 'Y' || $params['a'] == 'N' )
    	{
    		try
    		{
    			$adapter->storeBlockedBuyer( $params['uid'], $params['tnid'], $params['a'] );
    			$result = $adapter->getBlockedUserBySupplierId( $params["tnid"] );
    			$response = new Myshipserv_Response("200", "OK", $result );
    		}
    		catch( Exception $e )
    		{
    			$message = $e->getMessage();
    			$response = new Myshipserv_Response($e->getCode(), "NOT OK", null );
		
    		}	
    		$this->_helper->json((array) $response->toArray() );
    	}
		
		
    }
    
    /**
     * URL to generate new CAPTCHA for RFQ form
     * this will return a url to the newly created image
     * @return JSON
     */
    public function generateCaptchaAction()
    {
    	$spamChecker = new Myshipserv_Security_Spam();
    	$captcha = $spamChecker->getCaptcha();
    	$url = $captcha->getImgUrl() . $captcha->getId() . $captcha->getSuffix();
    	$response = new Myshipserv_Response(200, "OK", $url );
    	$this->_helper->json((array) $response->toArray() );
    }
    
    /**
     * Action for creating the enquiry form page. Just used for testing.
     * 
     * @access public
     */
    public function indexAction ()
    {
    	$params = $this->params;
    	$this->_forward('rfq', 'enquiry', null, $params);
    }
    
    /**
     * URL/Action to download the attachment
     * @throws Myshipserv_Exception_MessagedException
     */
    public function seeAttachmentAction()
    {
    	$params = $this->params;
    	
    	$this->_helper->layout->disableLayout();
	    $this->_helper->viewRenderer->setNoRender();
	    
	    $filename = $this->view->uri()->deobfuscate( $params['f'] );
	    
	    if( $params['a'] == 'd' )
	    {
	    	if( file_exists( '/tmp/' . $filename ) === false )
		   	{
		   		throw new Myshipserv_Exception_MessagedException("File not found", 404);
		   	}
		    unlink( '/tmp/' . $filename );
	    }
	    else 
	    {
		    // check if file exist on tmp folder
		   	if( file_exists( '/tmp/' . $filename ) === false )
		   	{
		   		throw new Myshipserv_Exception_MessagedException("File not found", 404);
		   	}
		    
		    $data = file_get_contents('/tmp/' . $filename);
	    	$this->getResponse()->setHeader('Content-Disposition', 'attachment; filename=' . $filename)
	    	   					->setBody($data)
	     						->sendResponse();
	    }
    }
    
    /**
     * @deprecated we no longer need this, this is replaced with the new RFQ inbox
     */
	public function internalViewAction()
	{
		$db = $this->db;
		$user = $this->user;
		$this->view->user = $user;
		
		/**
		 * enq  = 168097
		 * tnid = 77996
		 * hash = 5F8B5643
		 * 
		 * /enquiry/internal-view/enquiryId/168097/supplierBranchId/77996/hash/5F8B5643
		 */
		
		$enquiryId = $this->_getParam('enquiryId');
		$tnid = $this->_getParam('supplierBranchId');
		$hash = $this->_getParam('hash');
		$params = $this->params;		
		
		try
		{
			// fetch the enquiry
			$enquiry = Shipserv_Enquiry::getMyshipservEnquiryInstanceById($enquiryId, $tnid, $hash, true);
			
			// fetch the supplier
			$supplier = Shipserv_Supplier::fetch($tnid, $db);
			
			// fix the unicode entities
			$unicodeReplace = new Shipserv_Unicode();
			$supplier->description = $unicodeReplace->UTF8entities($supplier->description);
			
			// fetch account data
			$accountAdapter = new Shipserv_Oracle_SupplierAccount($db);
			$accountData    = $accountAdapter->fetch($tnid);
			
			// if the lister is premium, check if we should show a renew message
			if ($accountData['PEA_ACCOUNT_TYPE'] == 'PREMIUM')
			{
				$accountData['showRenew'] = false;
				
				$endDate = strtotime($accountData['PEA_CONTRACT_EXPIRY_DATE']);
				if ($endDate - time() <= ( 60 * 60 * 24 * 28 ))
				{
					$accountData['showRenew'] = true;
				}
			}
			
			$this->view->accountData = $accountData;
			$this->view->hash 		 = $hash;
			$this->view->supplier    = $supplier;
			$this->view->enquiryId   = $enquiryId;
			$this->view->tnid        = $tnid;
			$this->view->enquiry     = $enquiry;
			$this->view->params   	 = $params;
			$this->view->config		 = Zend_Registry::get('config');
		}
		catch (Exception $e)
		{
			$this->view->errorMessage = 'There was a problem fetching the RFQ. Our support team has been informed.';
		}
		
	}
	
	/**
	 * Action to view the RFQ. This will redirect user to appriate page
	 * If user isn't logged in, they'll be redirected to the printable with an instruction to login to respond.
	 * If user is logged in, they will be redirected to RFQ inbox after successful security checks
	 * 
	 * enq  = 168097
	 * tnid = 77996
	 * hash = 5F8B5643
	 * 
	 * /enquiry/view/enquiryId/168097/supplierBranchId/77996/hash/5F8B5643
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function viewAction ()
	{	
		$params = $this->params;
		$db = $this->db;
		$user = $this->user;
		
		$enquiryId = $params['enquiryId'];
		$tnid      = $params['supplierBranchId'];
		$hash      = $params['hash'];

		$this->view->user = $user;
		
		// fetch the enquiry
		$enquiry = Myshipserv_Enquiry::fetch($enquiryId, $tnid, $hash);
		
		if( $user === false )
		{
			// if enquiry exists, redirect user to the new RFQ-Inbox
			$url = "/trade/view-rfq?id=" . $enquiryId . "&tnid=" . $tnid . "&hash=" . $hash . "&print=0&view=1";
			$this->redirect($url);
		}
		
		$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$company = Shipserv_User::getSelectedCompany($activeCompany->id);
		
		// if enquiry exists, redirect user to the new RFQ-Inbox
		$url = "/trade/rfq?enquiryId=" . $enquiryId . "&tnid=" . $tnid . "&hash=" . $hash;

		// check the permission
		if( $user->isShipservUser() == false || $user->canPerform('PSG_COMPANY_SWITCHER') == false)
		{
			// throw error if user isn't part of the company
			if( $user->isPartOfCompany($tnid) == false )
			{
				$supplier = Shipserv_Supplier::fetch($tnid);
				throw new Myshipserv_Exception_MessagedException("Your URL to the RFQ is incorrect. This might happen because you don't have the right access/company to view the RFQ. Please make sure that you are part of this Supplier (" . $supplier->name . ").");
			}
		}

		// blocking the user from email
		if( $params['block'] != "" )
		{
			$adapter = new Shipserv_Oracle_Enquiry();
			try
			{
				$adapter->storeBlockedBuyer( $params['block'], $tnid, 'Y' );
			}
			catch( Exception $e )
			{
				$message = $e->getMessage();
			}
		}
		
		// check if user has a valid supplier chosen on the top right
		// if the active/selected company is different, then change it
		if( $activeCompany->id !== $tnid )
		{
			$url = "/user/switch-company?tnid=v".$tnid."&redirect=" . $url;
		}

		$this->redirect($url);
			
	}

	/**
	 * URL/Action to fill in survey after responding to a RFQ
	 * @example http//.../enquiry/post-respond-survey/enquiryId/123456/supplierBranchId/123456/hash/13flskdfjiwuhfihssf;
	 *
	 */
	public function postRespondSurveyAction()
	{
		$params = $this->params;		
		$declineSource = ($params['declineSource']) ? $params['declineSource'] : 'EMAIL';
		$enquiryId = $params['enquiryId'];
		$tnid      = $params['supplierBranchId'];
		$hash      = $params['hash'];
		
		$this->view->enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($enquiryId, $tnid);
		$this->view->params = $params;
		
		$user = Shipserv_User::isLoggedIn();
		if( $user !== false )
		{
			$isAdmin = $user->isAdminOfSupplier($tnid);		
			$this->view->user = $user;	
		}
		
		if ($this->getRequest()->isPost())
        {
        	// store survey answer to the database
        	if( isset( $params['reason'] ) && count( $params['reason'] ) )
        	{
        		$output = implode("\n", $params['reason']);
        	}
        	
        	if( isset($params['other'] ) && $params['other'] != "" )
        	{
        		if( $output != "" )
        		{
        			$output .= "\nOther reason: ";
        		}
        		$output .= $params["other"];
        	}
        	
        	// store the answer to the survey table
    		$survey = Shipserv_Survey::getInstanceById( Shipserv_Survey::SID_ACCEPT_ENQUIRY );
			$result = $survey->storeAnswersForAcceptingEnquiry( $params["enquiryId"], $output, $tnid );
			$this->view->mode = 'completed';
        }
        else
        {
        	$this->view->mode = 'survey';
        	$this->view->enquiryId = $enquiryId;
        	$this->view->tnid = $tnid;
        	$this->view->hash = $hash;
        }
	}
	
	/**
	 * Action to control what happens when a supplier declines to quote on an enquiry
	 *
	 * @access public
	 */
	public function rejectAction ()
	{
		$params = $this->params;
		$enquiryId = $params['enquiryId'];
		$tnid      = $params['supplierBranchId'];
		$hash      = $params['hash'];

		$this->view->enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($enquiryId, $tnid);
		$this->view->params = $params;
		
		$user = Shipserv_User::isLoggedIn();
		if($user)
		{
			$isAdmin = $user->isAdminOfSupplier($tnid);		
			$this->view->user = $user;	
		}

		$declineSource = ($params['declineSource']) ? $params['declineSource'] : 'EMAIL';
		
        if ($this->getRequest()->isPost())
        {
			// store survey answer to the database
        	if( isset( $params['reason'] ) && count( $params['reason'] ) )
        	{
        		$output = implode("\n", $params['reason']);
        	}
        	
        	if( isset($params['other'] ) && $params['other'] != "" )
        	{
        		if( $output != "" )
        		{
        			$output .= "\nOther reason: ";
        		}
        		$output .= $params["other"];
        	}

        	if( $output != "" )
        	{
	        	// store the answer to the survey table
	    		$survey = Shipserv_Survey::getInstanceById( Shipserv_Survey::SID_DECLINE_ENQUIRY );
				$result = $survey->storeAnswersForDeclineEnquiry( $params["enquiryId"], $output, $params['supplierBranchId'] );
	            
	        	// check the survey output
	        	if( !isset( $params["only_send_to_shipserv"] ) )
	        	{
	        		$enquiry = Myshipserv_Enquiry::fetch( $enquiryId, $tnid, $hash );
	        		$enquiry->sendSupplierFeedbackToBuyer( $output, $tnid );
        		}
        	}        	
        	
        	// check if user wants to block the buyer
        	if( isset( $params["block_buyer"] ) && $params["block_buyer"] == "Y" )
        	{
        		// get user by using the inquiryId
        		$daoForInquiry = new Shipserv_Oracle_Enquiry();
        		$userId = $daoForInquiry->getSenderUserIdByInquiryId( $params["enquiryId"] );
        		$userId = $userId[0]['PIN_USR_USER_CODE'];
        		$daoForInquiry->storeBlockedBuyer($userId, $params["supplierBranchId"], 'Y');
        	}
        	$this->view->mode = 'completed';
        }
        else
        {
        	// reject the enquiry
			Myshipserv_Enquiry::reject($enquiryId, $tnid, $hash, $declineSource);
        	$this->view->mode = 'survey';
			$this->view->enquiryId = $enquiryId;
        	$this->view->tnid = $tnid;
        	$this->view->hash = $hash;
			$this->view->isAdmin = $isAdmin;
        }
	}

	/**
	 * Action/page to ask user which company that they want to use when sending 
	 * RFQ in pages. Please note that MEMCACHE needs to be running for this particular use case
	 * 
	 * @throws Exception
	 */
    public function sendFromLoginRegisterAction ()
    {
    	// initialise
    	$params = $this->params;
        $config = Zend_Registry::get('options');
        $cookieManager = $this->getHelper('Cookie');
        $errors = array();
        $spamChecker = new Myshipserv_Security_Spam();

        // connect to memcache and fetch the enquiry
        $memcache = new Memcache();
        $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);
        
        // set the layout to the new one
        $this->_helper->layout->setLayout('default');
        
        try
        {
        	// if for some reason user ended up in this page w/o any session then throw an error
            if (!$user = Shipserv_User::isLoggedIn())
            {
				Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
            }
            
            $key = $cookieManager->fetchCookie('enquiryStorage');
            
            if (!$key)
            {
            	$this->redirect("/search");
                //throw new Exception("No enquiry storage key");
            }
            
            // getting all related suppliers for this user
            $suppliers = $user->fetchCompanies()->getSupplierIds();
            $buyers = $user->fetchCompanies()->getBuyerIds();
            foreach( $suppliers as $r )
            {
            	$supplier = Shipserv_Supplier::fetch( $r );
            	$myCompanies[] = array("type" => "v", "name" => $supplier->name, "id" => $supplier->tnid, "value" => "v" . $supplier->tnid );
            }
            foreach( $buyers as $r )
            {
            	$buyer = Shipserv_Buyer::getInstanceById( $r );
            	$myCompanies[] = array("type" => "b", "name" => $buyer->name, "id" => $buyer->id, "value" => "b" . $buyer->id );
            }
            
            $this->view->myCompanies = $myCompanies;
            
            if (!$enquiry = $memcache->get($key))
            {
                throw new Myshipserv_Exception_MessagedException("Could not fetch enquiry from store");
            }
            
            $paramsFromMemcache = $memcache->get($key . "-data-for-params");
            
            $this->view->paramsFromMemcache = $paramsFromMemcache;
            $this->view->enquiry = $enquiry;
            $this->view->x = $key;

            // update the enquiry with the new username and userId
            $enquiry->username = $user->username;
            $enquiry->userId   = $user->userId;
            $enquiry->senderName = ($user->getDisplayName() == "")?$user->username:$user->getDisplayName();

            if( count($myCompanies) == 0 )
            {
            	
            	
            	$enquiry->senderCompany = ($user->companyName=="")?"-":$user->companyName;
            	$enquiry->senderEmail = $user->email;
            	$enquiry->companyId = '';
            	$enquiry->companyType = '';

            	// making sure that the buyer's company is defined on the mtml
            	$paramsFromMemcache['bCompanyName'] = $enquiry->senderCompany;
            	$paramsFromMemcache['bEmail'] = $user->email;
            	$paramsFromMemcache['bName'] = $user->getDisplayName();
            	 
            	$mtml = Shipserv_Mtml::createFromParam($paramsFromMemcache);
            	$enquiry->mtml = $mtml->xmlInString;
            	
            	// sending the RFQ through TN
            	$enquiry->send();
            	
            	$params['success'] = true;
            	$params['header']  = 'Thank You!';
            	$params['noBackButton'] = true;

            	if( $user->canSendPagesRFQDirectly() )
            	{
            		$params['message'] = 'The RFQ was successfully sent';
            	}
            	else
            	{
            		$params['message'] =
            		"There will be a delay of a few hours before your RFQ is sent.<br />
            		<br />
            		We're checking that your RFQ follows our <a href='/help#8'>terms of use policy</a>.  We check RFQs only the first few times you use Pages.<br />
            		<br />
            		Suppliers ask us to do this to ensure they get quality RFQs.  Sorry for the inconvenience.<br />
            		";
            	}
            	 
            	$this->_forward('success-page', 'enquiry', null, $params);
            	
            }
            
            if ( $this->getRequest()->isPost() || count($myCompanies) == 1 )
            {
            	if( isset($params['captcha']) )
            	{
            		$captcha = $spamChecker->getCaptchaFromMemcache();
            	
	            	if( (isset($params['captcha']) && $captcha->isValid($params['captcha'])) == false )
	            	{
	            		$this->redirect("/enquiry/send-from-login-register/m/c");	
	            	}
            	}
            	
            	$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
            	
            	if( count($myCompanies) == 1 )
            	{
            		$company = Shipserv_User::getSelectedCompany($activeCompany->id);
            	}
            	else
            	{
            		$company = Shipserv_User::getSelectedCompany(str_replace("v","", $params['tnid']));
            	}
            	// correctly store the detail info about an enquiry
            	$enquiry->senderCompany = $company->name;
            	$enquiry->senderEmail = $user->email;
            	$enquiry->companyId = $company->id;
            	$enquiry->companyType = ( strstr($params['tnid'], "v") !== false )?"SPB":"BYO";
            	
            	// making sure that the buyer's company is defined on the mtml
             	$paramsFromMemcache['bCompanyName'] = $company->name;
             	$paramsFromMemcache['bCity'] = $company->company->city;
             	$paramsFromMemcache['bProvince'] = $company->company->state;
             	$paramsFromMemcache['bPostcode'] = $company->company->zipCode;
             	$paramsFromMemcache['bCountry'] = $company->company->countryCode;
             	$paramsFromMemcache['bAddress1'] = $company->company->address1;
             	$paramsFromMemcache['bAddress2'] = $company->company->address2;
             	$paramsFromMemcache['bEmail'] = $user->email;
             	$paramsFromMemcache['bName'] = $user->getDisplayName();

             	// re-generate the MTML based on what the user has choosen above
            	$mtml = Shipserv_Mtml::createFromParam($paramsFromMemcache);
            	$enquiry->mtml = $mtml->xmlInString;
            	
            	if ($enquiry->send())
	            {
	                // if the enquiry was successfully sent, clear the 'basket'
	                $cookieManager->clearCookie('enquiryBasket');
	                
	                // clear the enquiry storage cookie
	                $memcache = new Memcache();
	                $memcache->connect($config['memcache']['server']['host'],
	                                   $config['memcache']['server']['port']);
	                
	                $memcache->delete($cookieManager->fetchCookie('enquiryStorage'));
	                $cookieManager->clearCookie('enquiryStorage');
	                
	                $params['success'] = true;
					$params['header']  = 'Thank You!';	
					$params['noBackButton'] = true;        
					        
					if( $user->canSendPagesRFQDirectly() )
					{
						$params['message'] = 'The RFQ was successfully sent';
					}
					else
					{
						$params['message'] =
						"There will be a delay of a few hours before your RFQ is sent.<br />
						<br />
						We're checking that your RFQ follows our <a href='/help#8'>terms of use policy</a>.  We check RFQs only the first few times you use Pages.<br />
						<br />
						Suppliers ask us to do this to ensure they get quality RFQs.  Sorry for the inconvenience.<br />
						";
					}
						
	                $this->_forward('success-page', 'enquiry', null, $params);
	            }
	            else
	            {
	                echo '<!--';
	                var_dump($enquiry);
	                echo '//-->';
	            }
            }
            else
            {
            	$captcha = $spamChecker->getCaptcha();
            	if( $spamChecker->checkTotalRFQSentPerDay($user, $formValues)>= 2 )
            	{
            		$this->view->captchaId = $captcha->getId();
            		$this->view->captcha = $captcha;
            		$this->view->params = $params;;
            	}
            }
        }
        catch (Exception $e)
        {
            $errors[] = $e->getMessage();
            trigger_error('Unexpected error in /enquiry/send-from-login-register: ' . (String) $e, E_USER_WARNING);
            echo '<!--';
			var_dump($errors);
			echo '//-->';
        }
        $this->view->errors = $errors;
        $this->view->message = $message;
    }
    
    /**
     * 
     * 
     */
    public function successPageAction ()
    {
    	$this->view->noHeader = true;
        $this->view->success = $this->_getParam('success');
		$this->view->header  = $this->_getParam('header');
		$this->view->message = $this->_getParam('message');
		$this->view->noBackButton = $this->_getParam('noBackButton');
		
		$this->view->user = Shipserv_User::isLoggedIn();
    }
    
    /**
     * Ajax action for creating the form for sending an enquiry.
     *
     * @access public
     */
    public function generateFormAction ()
    {
        $this->view->requiresLogin = true;
        if ($user = Shipserv_User::isLoggedIn())
        {
            $this->view->requiresLogin = false;
        }
        
        $config = Zend_Registry::get('options');
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        $tnids  = unserialize(stripslashes($_COOKIE[$config['shipserv']['enquiryBasket']['cookie']['name']]));
        if (!$tnids)
        {
            $tnids = array();
        }
        
        $this->view->supplierBasket = $tnids;
        $this->view->user           = $user;
        
        
    }
    
    /**
     * AJAX action to add a supplier to the basket (held in a cookie)
     * 
     * @access public
     */
    public function addSupplierToBasketAction ()
    {
        $config = $this->config;
        $db = $this->db;
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        if ($this->getRequest()->isPost())
        {
            $params = $this->getRequest()->getParams();
            
            $tnid = $params['tnid'];
            
            // create a profile adapter
            //$profile = new Shipserv_Adapters_Profile();
            
            // fetch the supplier profile
            $supplier = Shipserv_Supplier::fetch($tnid, $db);//$profile->fetch($tnid, true);
            
            // fetch the current basket
            $tnids = unserialize(stripslashes($_COOKIE[$config['shipserv']['enquiryBasket']['cookie']['name']]));
            
            // append the new tnid
            $tnids[$tnid] = $supplier->name;
            
            $expiry = ($cookie['expiry'] == 0) ? 0 : time() + $cookie['expiry'];
            
            setcookie($cookie['name'], serialize($tnids), $expiry,
                      $cookie['path'], $cookie['domain']);
        }
    }
    
    /**
     * AJAX action to remove a supplier (or set of suppliers) from the basket (held
     * in a cookie)
     * 
     * @access public
     */
    public function removeSupplierFromBasketAction ()
    {
        $config = $this->config;
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        if ($this->getRequest()->isPost())
        {
            $params = $this->params;
            
            $tnid = $params['tnid'];
            
            // fetch the current basket
            $tnids = unserialize(stripslashes($_COOKIE[$config['shipserv']['enquiryBasket']['cookie']['name']]));
            
            unset($tnids[$tnid]);
            
            $expiry = ($cookie['expiry'] == 0) ? 0 : time() + $cookie['expiry'];
            setcookie($cookie['name'], serialize($tnids), $expiry,
                      $cookie['path'], $cookie['domain']);
        }
    }
    
    /**
     * Ajax action to fetch a JSON list of the supplier basket
     * 
     * @access public
     */
    public function fetchSupplierBasketAction ()
    {
        $config = Zend_Registry::get('options');
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        $tnids  = unserialize(stripslashes($_COOKIE[$config['shipserv']['enquiryBasket']['cookie']['name']]));
        if (!$tnids)
        {
            $tnids = array();
        }
        
        $this->view->supplierBasket = $this->_helper->json->encodeJson($tnids);
    }


    public function backboneAction()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $params = $this->_getAllParams();
        $namespace = $params['namespace'];

        $config = Zend_Registry::get('options');
         
        $memcache = new Memcache();
        $memcache->connect($config['memcache']['server']['host'],
                           $config['memcache']['server']['port']);

        $key = session_id() . "-backbone-" . $namespace;
        
        switch( $method )
        {
            case "DELETE"   :   $memcache->delete($key,0);
                                break;
            case "GET"      :   $content = $memcache->get($key);
                                break;
            case "POST"     :
            case "PUT"      :   $contents = file_get_contents('php://input');
                                $memcache->set($key, json_decode($contents));
                                break;
        }
        
        $this->_helper->json((array) $contents );
    }

    private function getUploadedFiles($params)
    {
    	$files = array();

    	if( is_dir(self::UPLOAD_PATH . $params['hash'] ) === true )
    	{
    		// read the files that's been uploaded to the server asynchronously
    		if ($handle = opendir( self::UPLOAD_PATH . $params['hash'] ))
    		{
    			while (false !== ($file = readdir($handle)))
    			{
    				if ($file != "." && $file != "..")
    				{
    					$files[] = self::UPLOAD_PATH . $params['hash'] . '/' . $file;
    				}
    			}
    			closedir($handle);
    		}
    	}

    	return $files;
    	 
    }
    
    public function rfqAction ()
    {
    	// initialise all variables
        $params = $this->params;
        $config = $this->config;
        $db = $this->db;
        $spamChecker = new Myshipserv_Security_Spam();
        $captcha = $spamChecker->getCaptcha();
        $memcache = new Memcache();
        $maxSelectedSuppliers = $this->config['shipserv']['enquiryBasket']['maximum'];
        $cookieManager = $this->getHelper('Cookie');
        $enquiryBasket = $supplierBasket = $errors = $files = array();
        $boAdapter = new Shipserv_Oracle_BuyerOrganisations();
        $activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
		// connect to the memcache
        $memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);
        
        // setting new layout
        $this->_helper->layout->setLayout('default');
        $this->view->noHeader = true;
        
        // get the UOM
        $sql = "SELECT DISTINCT MSU_CODE, MSU_CODE_DESC FROM MTML_STD_UNIT WHERE MSU_CODE_TYPE='UOM' ORDER BY MSU_CODE ASC";
        $resultForUom = $db->fetchAll($sql);
        
        // pull countries
        $countryAdapter = new Shipserv_Oracle_Countries($db);
        $resultForCountries = $countryAdapter->fetchAllCountries();

        // pass all required variables to view
        $this->view->params = $params;
        $this->view->uom = $resultForUom;
        $this->view->country = $resultForCountries;
		$this->view->acceptedFileTypes = explode("|", self::ACCEPTED_FILE_TYPE);
		$this->view->acceptedFileCount = $this->config['shipserv']['enquiryBasket']['attachments']['count'];
		$this->view->requiresLogin = true;
		$this->view->hash = md5( session_id() . date('d m Y h:i:S'));

		// garbage collection for files uploaded on previous page
		if ($_COOKIE['rfqFormHash'] != "" && $this->getRequest()->isPost() == false) {
			// removing previous uploaded filescd /tm
			$this->removeUploadedFiles(array('hash' => $_COOKIE['rfqFormHash']));
		}

		// assign the hash of the form to the cookie
		setcookie('rfqFormHash', $this->view->hash, $expiry, $cookie['path'], $cookie['domain']);
		
        $this->getResponse()->setHeader('Expires', 'Mon, 22 Jul 2002 11:12:01 GMT', true);
        $this->getResponse()->setHeader('Cache-Control', 'no-cache', true);
        $this->getResponse()->setHeader('Pragma', 'no-cache', true);
         

        if ($user = Shipserv_User::isLoggedIn()) {
        	$this->view->requiresLogin = false;
        }
        
        // if clearBasket is passed as a parameter, and is defined as 1, then the enquiry basket should be cleared
        if ($this->_getParam('clearBasket') == 1) {
        	$cookieManager->clearCookie('enquiryBasket');
        	$cookieManager->clearCookie('rfqFormHash');
        } else {
        	$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
        }
         
        // if a TNID is set as a parameter, it should override whatever is in the enquiry basket
        if ($this->_getParam('tnid')) {
        	$supplier = Shipserv_Supplier::fetch($this->_getParam('tnid'), $db);
        	$supplierBasket = array($this->_getParam('tnid'));
        }
        
        if (count( $enquiryBasket['suppliers'] ) > 0) {
        	$supplierBasket = $enquiryBasket['suppliers'];
        }

        foreach ((array) $supplierBasket as $tnid) {
        	$supplierObject = Shipserv_Supplier::fetch($tnid, $db);
        	$selectedSupplier[$tnid] = $supplierObject->name;
        }
        

		$enquiryBasket = array('suppliers' => $supplierBasket);
        $cookieManager->encodeJsonCookie('enquiryBasket', $enquiryBasket);
        
        if (count($selectedSupplier) == 0) {
        	//throw new Myshipserv_Exception_MessagedException("Please select a supplier before sending a RFQ<br/><br/><a href='" . $_SERVER['HTTP_REFERER'] . "'>Click here</a> to go back", 200);
        }
        
        // pass the selected supplier to the view
        $this->view->selectedSupplier = $selectedSupplier;
        
        $showForm = true;
        if (!$supplierBasket) {
        	$supplierBasket  = array();
        	//$errors['top'][] = 'You have no suppliers selected. Please choose some suppliers first: <a class="back_to_results_button" href="/search/results/">back to search</a>.';
        	$showForm = false;
        }
        
        $this->view->showForm       		= $showForm;
        $this->view->maxSelectedSuppliers 	= $maxSelectedSuppliers;
        $this->view->errors         		= $errors;
        $this->view->supplierBasket 		= $supplierBasket;
        $this->view->user           		= $user;
        
        if ($this->view->requiresLogin == false) {
        	// pull country from the user for PPG custom routing
        	$companies = $this->_helper->companies->getMyCompanies($user);
        	$total = count( $companies );
        	if ($total == 1) {
        		if ($companies[0]["type"] == "v") {
        			$s = Shipserv_Supplier::fetch($companies[0]["id"]);
        			$country[] = $s->countryCode;
        		} else {
        			$b = $boAdapter->fetchBuyerOrganisationsByIds ((array)$companies[0]["id"]);
        			$country[] = $b[0]['BYO_COUNTRY'];
        		}
        		$this->view->defaultCountry = $country[0];
        	} else {
        		$this->view->defaultCountry = '';
        	}
        }
        
        /**
         * If the page request is not from a POST to this page, we should
         * populate some of the form values with those from the user object
         */
        $formValues = array();
        if (!$this->getRequest()->isPost() && is_object($user)) {
        	$formValues['sender-name']  = $user->firstName . ' ' . $user->lastName;
        	$formValues['company-name'] = $user->companyName;
        	$formValues['sender-email'] = $user->email;
        }
		// 
		/*
		var_dump( $_COOKIE );
		var_dump( $enquiryBasket );
		var_dump( $selectedSupplier );
		var_dump( $supplierBasket );
		var_dump( $this->isPPGCompanies($supplierBasket) );
	 	*/

		$this->view->countryIsCompulsory = $this->isPPGCompanies($supplierBasket);

        $showFileUploads = false;
        try
        {
        	//throw new Myshipserv_Exception_MessagedException("TEST", 500);
        	// process starts
        	if ($this->getRequest()->isPost()) {
        		if (!$user = Shipserv_User::isLoggedIn()) {
        			$username = '';
        			$userId   = '';
        		} else {
        			$username = $user->username;
        			$userId   = $user->userId;
        		}
                		
        		/**
        		 * The TNIDs of the recipients are held in a cookie
        		 * "SS_TNID_BASKET" as a serialized array
        		 */
        		$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
        
        		// get the first 15 TNIDs
        		$enquiryBasket['suppliers'] = array_slice( (array) $enquiryBasket['suppliers'], 0, $maxSelectedSuppliers, true );
        
        		if (!is_array($supplierBasket) || count($supplierBasket) < 1) {
        			throw new Myshipserv_Exception_MessagedException('There are no recipients for this enquiry');
        		}
        
        		// supplier's TNID of where this RFQ is going to
        		$tnids = $enquiryBasket['suppliers'];

        		$tnids = (array)$params['supplier'];
        		
        		// find the searchRecId if there is one
        		$searchRecId = $cookieManager->fetchCookie('search');
        
        		// find the getProfileId if there is one
        		$getProfileId = $cookieManager->fetchCookie('profile');

        		// get all uploaded files 
        		$files = $this->getUploadedFiles($params);

        		// create MTML from the parameters
				$mtml = Shipserv_Mtml::createFromParam($this->params);
				
				if ($this->params['lBuyerComments'] == "")
				{
					foreach ($this->params['section'] as $section) {
						foreach ($section['lineItems'] as $li) {
							$d[] = $li['lDescription'];
						}
					}
					$description = implode("\n", $d);
					$description .= "\n\nTo see more detail of this, please open this RFQ";
				} else {
					$description = $this->params['lBuyerComments'];
				}

				
				// check if this is a spam 
				// new automated vetting process that automatically mark a user as NOT TRUSTED/QUARANTINED
				// if they tried to send to more than 15 suppliers per week with the same RFQ content
				$params['enquiry-text'] = $description;
				$suspectedAsSpam = $spamChecker->checkRFQForSpam($user, $params, $enquiryBasket, $cookieManager, $files);
				
				// creating enquiry object based on the parameters/mtml
        		$enquiry = new Myshipserv_Enquiry(
	        		$user->username,
	        		$user->userId,
	        		$this->params['bName'],
	        		($this->params['bCompanyName']=="")?"-":$this->params['bCompanyName'],
	        		$this->params['bEmail'],
	        		$this->params['bPhone'],
	        		$this->params['dCountry'],
	        		$description, // content
	        		$this->params['rRfqSubject'],
	        		$this->params['vVesselName'],
	        		$this->params['vImoNumber'],
	        		$this->params['dDeliveryTo'],
	        		( $this->params['dDeliveryBy'] != "" && strstr(strtoupper($this->params['dDeliveryBy']), 'MM') === false )
        				? Shipserv_Mtml::convertDateToOracleDate($this->params['dDeliveryBy']) : null,
	        		$searchRecId,
	        		$getProfileId,
	        		($files === null)?array():$files,
	        		$tnids,
        			$mtml->xmlInString,
        			$activeCompany->id,
        			$activeCompany->type
        		);

        		if (!$user) {
        			/**
        			 * The user is not logged in, but we still want to capture
        			 * the enquiry so they don't have to fill it in again.
        			 *
        			 * Let's stick it in memcache, then forward the user to a
        			 * register/login page with a token containing the memcache
        			 * key so it can be retrieved later.
        			 */
        			// create a key and set a cookie with it so we can fetch it later
        			$key = md5(uniqid(rand(), true));
        			$enqCookie = $config['shipserv']['enquiryStorage']['cookie'];
        			$cookieManager->setCookie('enquiryStorage', $key);
        			$memcache->set($key, $enquiry);
        			$memcache->set($key . "-data-for-params", $this->params);
        			
        			// now redirect to the login/register part
        			//$this->redirect('/user/register-login/fromEnquiry/'.$key, array('exit', true));
        			
        			// since it's now handled by case, then use this instead
        			$this->redirect(
        				//$this->getUrlToCasLogin('/enquiry/send-from-login-register'), 
        				$this->getUrlToCasLogin('/user/cas?redirect='.urlencode('/enquiry/send-from-login-register')), 
        				array('exit', true)
        			);
        			
        		}  
        		
        		// send the RFQ to pages service
        		if ($enquiry->send()) {
        			// garbage collection operation
        			// clear cookie
        			$cookieManager->clearCookie('enquiryBasket');

        			$this->removeUploadedFiles($this->params);
        			
        			// clear the enquiry storage cookie
        			if ($cookieManager->fetchCookie('enquiryStorage')) {
        				$memcache->delete($cookieManager->fetchCookie('enquiryStorage'));
        			}
        
        			$cookieManager->clearCookie('enquiryStorage');
        
        			$params['success'] = true;
        			$params['header']  = 'Thank You!';
        			$params['noBackButton'] = true;
        				
        			if ($user->canSendPagesRFQDirectly()) {
        				$params['message'] = 'The RFQ was successfully sent';
        			} else {
        				$params['message'] = 
	        				"There will be a delay of a few hours before your RFQ is sent.<br />
	        				<br />
	        				We're checking that your RFQ follows our <a href='/help#8'>terms of use policy</a>.  We check RFQs only the first few times you use Pages.<br />
	        				<br />
	        				Suppliers ask us to do this to ensure they get quality RFQs.  Sorry for the inconvenience.<br />
	        				";
        			}
        			
        			// log the activity
        			$user->logActivity(Shipserv_User_Activity::ENQUIRY_SENT, 'PAGES_INQUIRY+PIN_SUBJECT', $user->userId, $formValues['enquiry-subject']);
        			$this->_forward('success-page', 'enquiry', null, $params);
        		}
        	}
        }
        catch (Myshipserv_Enquiry_Exception $e)
        {
        	$errors['top'][] = $e->getMessage();
        }
        catch (Exception $e)
        {
        	$errors['top'][] = $e->getMessage();
        }
        
        $this->view->showFileUploads = $showFileUploads;
        $this->view->formValues      = $formValues;
        $this->view->header          = $header;
        $this->view->message         = $message;
        $this->view->errors          = $errors;
        
        $this->view->basketCookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        // enable captcha when user's trying to send RFQ for the 3rd time
        if ($user = Shipserv_User::isLoggedIn()) {
        	if ($spamChecker->checkTotalRFQSentPerDay($user, $formValues)>= 2) {
        		$this->view->captchaId = $captcha->getId();
        		$this->view->captcha = $captcha;
        	}
        }
    }

    
    public function sendtestAction ()
    {
        $this->_helper->layout->setLayout('default');
		$params = $this->params;
		$mtml = Shipserv_Mtml::createFromParam($params);
		echo 'INPUT<textarea style="width:500px; height:500px;">' . print_r($params, true) . '</textarea>';
		echo 'MTML<textarea style="width:500px; height:500px;">' . $mtml->xmlInString . '</textarea>';
    }
    
    public function dataAction()
    {
    	$db = $this->db;
    	$params = $this->params;
    	
    	// removing stored enquiry on memcache
    	if ($params['type'] == "mcid") {
    		if ($params['mcid'] != "") {
    			$config = $this->config;
    			// connect to memcache and fetch the enquiry
    			$memcache = new Memcache();
    			$memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port']);
    			$memcache->delete($params['mcid'],0);
    			
    			$cookieManager = $this->getHelper('Cookie');
    			$cookieManager->clearCookie('enquiryBasket');
    			$cookieManager->clearCookie('enquiryStorage');
    			 
    			$response = new Myshipserv_Response(200, "OK", array() );
    		} else {
    			$response = new Myshipserv_Response(200, "OK", array() );
    		}
    	} else if ($params['type'] == "imo") {
            // getting the vesselname from the database
            if ($params['imo'] != "") {
    			$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
    			$db = $resource->getDb('ssreport2');

    			$sql = "
					SELECT 
					  * 
					FROM
					  (
					    SELECT
					      LRIMOSHIPNO IMO_NO,
					      SHIPNAME VESSEL_NAME,
					      'IHS' UPDATED_BY,
					      TO_DATE('01-JAN-2009') UPDATED_DATE
					    FROM
					      IHS_SHIP
					    WHERE
					      LRIMOSHIPNO=:imo
					
					    UNION
					
					    SELECT
					      DISTINCT VES_IMO_NO IMO_NO,
					      VES_NAME VESSEL_NAME,
					      'RFQ' UPDATED_BY,
					      TO_DATE('01-JAN-2011') UPDATED_DATE
					    FROM
					      VESSEL
					    WHERE
					      VES_IMO_NO=:imo
					  ) 
					  ORDER BY UPDATED_DATE DESC
    			";
    			$result = $db->fetchAll($sql, array("imo" => $params['imo']));
    			$response = new Myshipserv_Response(200, "OK", $result );
    		} else {
    			throw new Myshipserv_Exception_MessagedException("IMO number was not specified", 404);
    		}
    	} else if ($params['type'] == "uom") {
            // get the unit of measurements detail

            $sql = "SELECT DISTINCT MSU_CODE FROM MTML_STD_UNIT WHERE MSU_CODE_TYPE='UOM' ORDER BY MSU_CODE ASC";
    		$result = $db->fetchAll($sql);
    		$response = new Myshipserv_Response(200, "OK", $result );
    	} else if ($params['type'] == "port") {
            // get list of port by countryCode
            if ($params['countryCode'] != "") {
	    		$portAdapter = new Shipserv_Oracle_Ports($db);
	    		$resultForPorts = $portAdapter->fetchPortsByCountry($params['countryCode']);
	    		$response = new Myshipserv_Response(200, "OK", $resultForPorts );
    		} else {
    			throw new Myshipserv_Exception_MessagedException("countryCode was not specified", 404);
    		}
    	} else if ($params['type'] == "captcha") {
            // get/renew the captcha

            $spamChecker = new Myshipserv_Security_Spam();
    		$captcha = $spamChecker->getCaptchaFromMemcache();
    		
    		if (!$captcha->isValid($params['captcha'])) {
    			$response = new Myshipserv_Response(401, "Not Authorised", array() );
    		} else {
    			$response = new Myshipserv_Response(200, "Authorised", array() );
    		}
    	}
    
    	// converting the response to json type
    	$this->_helper->json((array)$response->toArray());
    }
    
    
    function uploadAction()
    {
    	/*
    	if ($this->_request->isOptions()) {
    		// we're using user_id and email here as a way to verify the upload and store the file in a specific directory,
    		// you can strip that out for your purposes.
    		$this->upload( $this->params['hash'] );
    	}
    	*/
    	if ($this->_request->isDelete() || $_SERVER['REQUEST_METHOD'] == 'DELETE') 
    	{
    		$this->delete( $this->params['hash'] );
    	}
    	else if($this->_request->isPost() || $this->_request->isGet())
    	{
    		$files = $this->getUploadedFiles($this->params);
    		if( $this->config['shipserv']['enquiryBasket']['attachments']['count'] == count($files) ) 
    		{
    			throw new Myshipserv_Exception_MessagedException("Maximum number of files supported is " . $this->config['shipserv']['enquiryBasket']['attachments']['count'], 400);
    		}
    		else
    		{
    			$this->upload( $this->params['hash'] );
    		}
    	}
    	exit;
    	 
    }
    
    public function upload($hash) 
	{
		if( !is_dir(self::UPLOAD_PATH) )
    	{
			if( !is_dir('/tmp/enquiry') )
			{
	      		mkdir('/tmp/enquiry');
				chmod('/tmp/enquiry', 0777);
				chmod('/tmp/enquiry', 0777);
    		}
    		if( !is_dir(self::UPLOAD_PATH) )
    		{
    			mkdir(self::UPLOAD_PATH);
    			chmod(self::UPLOAD_PATH, 0777);
    		}
    	}
    	
    	if ( $hash != "" ) 
    	{
    	    $userPath = self::UPLOAD_PATH . $hash;
            if (!file_exists($userPath))
            {
            	mkdir($userPath);
            	chmod($userPath, 0777);
            }
            
            $hash .= '/';
            
            $adapter = new Zend_File_Transfer_Adapter_Http();
    
    		$adapter->setDestination($userPath."/");
    		$adapter->addValidator('Extension', false, str_replace("|",",", self::ACCEPTED_FILE_TYPE)); //'jpg,png,gif');
    		
    		$fileSize = new Zend_Validate_File_FilesSize(array('max' => 1000000)); 
    		$fileSize->setMessage('The file you uploaded was too large. (Max 1MB)');
    		$adapter->addValidator($fileSize);
    		
    		$files = $adapter->getFileInfo();
    		foreach ($files as $file => $info)
    		{
    			$name = $adapter->getFileName($file);
    		
    			// file uploaded & is valid
    			if (!$adapter->isUploaded($file)) continue;

    			if (!$adapter->isValid($file)) 
    			{
    				foreach((array)$adapter->getMessages() as $error)
    				{
    					$datas[] = array("error" => $error);
    				}
    				continue;
    			}
    
    			try
    			{
    				// receive the files into the user directory
    				$adapter->receive($file); // this has to be on top
    		
    				$fileclass = new stdClass();
    		
    				$fileclass->name = basename($name);
    				$fileclass->size = $adapter->getFileSize($file);
    				$fileclass->type = $adapter->getMimeType($file);
    				$fileclass->delete_url = '/enquiry/upload/hash/' . $hash . '?files=' . $hash . "/" .basename($name);
    				$fileclass->delete_type = 'DELETE';
    				$fileclass->url = '/';
    		
    				$datas[] = $fileclass;
    		
    			}
    			catch (Zend_File_Transfer_Exception $e)
    			{
    				$datas[] = $e->getMesssage();
    			}
    			 
    		}
    	    		
    		header('Pragma: no-cache');
    		header('Cache-Control: private, no-cache');
    		header('Content-Disposition: inline; filename="files.json"');
    		header('X-Content-Type-Options: nosniff');
    		header('Vary: Accept');
    		
    		echo json_encode($datas);
    	}
    }
    
    public function delete($hash) 
    {
    	$fileName = $this->_request->getParam('files');
		$filePath = self::UPLOAD_PATH . $fileName;
    	$success = is_file($filePath) && $fileName[0] !== '.' && unlink($filePath);
    	echo json_encode($success);
    }

    public function removeUploadedFiles($params)
    {
    	$files = $this->getUploadedFiles($params);

    	// clear files
    	try
    	{
    		foreach((array)$files as $file)
    		{
    			unlink($file);
    		}
    		@rmdir(self::UPLOAD_PATH . $params['hash']);
    	}
    	catch(Exception $e)
    	{
    		echo $e->getMessage();
    	}
    	 
    }
}
