<?php

/**
 * Controller for handling reviews/endorsements-related actions
 *
 * @package myshipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class ReviewsController extends Myshipserv_Controller_Action
{

    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     *
     * @access public
     */
    public function init ()
    {
    	parent::init();

        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('add-reply', 'json')
					->addActionContext('send-request', 'json')
					->addActionContext('ignore-request', 'json')
					->addActionContext('remove', 'json')
					->addActionContext('add-bulk-review', 'json')
					->addActionContext('supplier-count', 'json')
                    ->initContext();
    }

    public function allAction ()
	{

		$config   = Zend_Registry::get('options');
		$db = $this->db;
		$cookieManager = $this->getHelper('Cookie');


	    $user = Shipserv_User::isLoggedIn();
		$this->view->user = $user;

        $form     = new Myshipserv_Form_Search();

        $urlArray = array_reverse(explode('-', $this->_getParam('s')));
        $endorseeId     = $urlArray[0];
		$endorserId = $this->_getParam('e');

		if (is_object($user))
		{
			$this->view->userHasAdminRights = in_array($endorseeId, $user->fetchCompanies()->getSupplierIds());
		}
		else
		{
			$this->view->userHasAdminRights = false;
		}

		// create a supplier object
        $supplier = Shipserv_Supplier::fetch($endorseeId, $db);//$profile->fetch($endorseeId, true);

        // fix the unicode entities
        $unicodeReplace = new Shipserv_Unicode();
        $supplier->description = $unicodeReplace->UTF8entities($supplier->description);

        // fetch the current basket
        $enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
        $supplierBasket = $enquiryBasket['suppliers'];
        if (!$supplierBasket)
        {
            $supplierBasket = array();
        }

        $title = trim($supplier->name).', '.$supplier->city .', '.$supplier->countryName;
        $title.= ' - ShipServ Pages';
        $this->view->headTitle($title, 'SET');

		$primaryCategories   = array();
		$secondaryCategories = array();
		foreach ($supplier->categories as $id => $category)
		{
			if ($category['primary'] == true)
			{
				$primaryCategories[] = $category['name'];
			}
			else
			{
				$secondaryCategories[] = $category['name'];
			}
		}

		if ($supplier->description) // if there's a description, use that
		{
			$metaDescription = $this->view->string()->shortenToLastWord(strip_tags(str_replace("\n", "", $supplier->description)), 150);
		}
		elseif (count($primaryCategories) > 0) // if there are primary categories, use those
		{
			$metaDescription = 'Marine supplier of '.implode(', ', $primaryCategories);
		}
		elseif (count($secondaryCategories) > 0) // if there are secondary categories, use those
		{
			$metaDescription = 'Marine supplier of '.implode(', ', $secondaryCategories);
		}
		else
		{
			$metaDescription = 'Marine Supplier in '.$supplier->city.', '.$supplier->countryName;
		}

		// meta keywords
		$metaKeywords = 'Marine, Maritime, Shipping, Suppliers, Supply, Companies, Directory, Listings, Search, ShipServ, Parts, Equipment, Spares, Services, ';
		$metaKeywords.= $supplier->name.', '.$supplier->city.', '.$supplier->countryName;
		if (count($primaryCategories) > 0)
		{
			$metaKeywords.= ', '.implode(', ', $primaryCategories);
		}

		if (count($secondaryCategories) > 0)
		{
			$metaKeywords.= ', '.implode(', ', $secondaryCategories);
		}

		if (count($supplier->brands) > 0)
		{
			foreach ($supplier->brands as $brand)
			{
				$metaKeywords.= ', '.$brand['name'];
			}
		}

		$endorsementsAdapter = new Shipserv_Oracle_Endorsements($db);
		$endorsements = $endorsementsAdapter->fetchEndorsementsByEndorseeAndEndorser($endorseeId,$endorserId);


        // give the view the supplier data
                $this->view->basketCookie     = $config['shipserv']['enquiryBasket']['cookie'];
		$this->view->metaDescription  = $metaDescription;
		$this->view->metaKeywords     = $metaKeywords;
                $this->view->supplierBasket   = $supplierBasket;
                $this->view->supplier         = $supplier;
		$this->view->searchWhat       = $this->_getParam('searchWhat');
		$this->view->searchWhere      = $this->_getParam('searchWhere');
		$this->view->searchStart      = $this->_getParam('searchStart');
		$this->view->page             = $this->_getParam('page');
		$this->view->reviews = Shipserv_Review::getReviews($endorseeId, $endorserId);
		$this->view->endorsements = $endorsements;
		$this->view->endorserId =		$endorserId;
	}

	public function supplierAction ()
    {
		if($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->layout->setLayout('empty');
		}

		$db = $this->db;

	    $user = Shipserv_User::isLoggedIn();
		$this->view->user = $user;
		if (is_object($user))
		{
			$this->view->userSuppliers = $user->fetchCompanies()->getSupplierIds();
			$this->view->userBuyers = $user->fetchCompanies()->getBuyerIds();
		}
		else
		{
			$this->view->userSuppliers = array();
			$this->view->userBuyers    = array();
		}


        $urlArray = array_reverse(explode('-', $this->_getParam('s')));
        $tnid     = $urlArray[0];

		$this->view->userHasAdminRights = in_array($tnid, $this->view->userSuppliers);

		// create a supplier object

		$supplier = Shipserv_Supplier::fetch($tnid, $db);

		$profileDao = new Shipserv_Oracle_Profile($db);
		$endorsee = $profileDao->getSuppliersByIds(array($tnid));
		$endorseeInfo =  $endorsee[0];

		//retrieve list of endorsements for given supplier
		$endorsementsAdapter = new Shipserv_Oracle_Endorsements($db);
		$endorsements = $endorsementsAdapter->fetchEndorsementsByEndorsee($tnid,false);
		$endorseeIdsArray = array ();
		foreach ($endorsements as $endorsement)
		{
			$endorseeIdsArray[] = $endorsement["PE_ENDORSER_ID"];
		}

		$userEndorsementPrivacy = $endorsementsAdapter->showBuyers($tnid, $endorseeIdsArray);

		//get supplier's privacy policy
		$dbPriv = new Shipserv_Oracle_EndorsementPrivacy($db);
		$sPrivacy = $dbPriv->getSupplierPrivacy($tnid);

        // give the view the supplier data

		$this->view->endorseeInfo	  = $endorseeInfo;
        $this->view->supplier         = $supplier;
		$this->view->endorsements	  = $endorsements;
		$this->view->reviews	  = Shipserv_Review::fetchSummary($tnid);
		$this->view->userEndorsementsPrivacy = $userEndorsementPrivacy;
		$this->view->supplierPrivacy = $sPrivacy->getGlobalAnonPolicy();
		$this->view->defaultTab = 'reviews';

		if(!$this->getRequest()->isXmlHttpRequest()) {
			$this->_forward('profile', 'supplier');
			//$this->redirect( $supplier->getUrl() . "#review" );
		}
    }

	public function supplierCountAction ()
    {

		$db = $this->db;

        $urlArray = array_reverse(explode('-', $this->_getParam('s')));
        $tnid     = $urlArray[0];



        if( ctype_digit( $tnid ) === false )
        {
            throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        }
        else
        {
        	if( strlen( $tnid ) < 5 )
        	{
        		throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        	}
        }
		$count = Shipserv_Review::getReviewsCounts(array($tnid));

		$this->_helper->json((array)nl2br($count[$tnid]));

    }

	public function addReviewAction ()
    {

		$cookieManager = $this->getHelper('Cookie');

		$user = Shipserv_User::isLoggedIn();

		$db = $this->db;
		$config   = Zend_Registry::get('options');

		$params = $this->params;

		$this->view->formValues = $params;

		if (is_object($user) or $this->_getParam('reqcode'))
		{
			if ($this->_getParam('reqcode'))
			{
				if ($reviewRequest = Shipserv_ReviewRequest::fetchByCode($this->_getParam('reqcode')))
				{
					$endorseeId = $reviewRequest->endorseeId;
				}
				else
				{
					throw new Myshipserv_Exception_MessagedException("Sorry, this link for review submission is not valid any more");
				}
				//log out user if he is not who was suppose to get review request
				if (is_object($user))
				{
					if ($user->email != $reviewRequest->userEmail)
					{
						$user->logout();
						$this->redirect($_SERVER['REDIRECT_URL'], array('code' => 301));
					}
				}
			}
			else
			{

				$urlArray = array_reverse(explode('-', $this->_getParam('s')));
				$endorseeId  = $urlArray[0];

				$userBuyerIds = $user->fetchCompanies()->getBuyerIds();
				$this->view->userBuyers = $userBuyerIds;

				if ($this->_getParam('e'))
				{
					$this->view->endorserId = $this->_getParam('e');
				}
				else
				{

					foreach ($this->_helper->companies->getMyCompanies($user) as $userCompany)
					{
						if ($userCompany["type"]=="b")
						{
							$userCompanies[] = $userCompany;
						}
					}

					if (count($userCompanies)>1)
					{
						$this->view->userCompanies = $userCompanies;
					}
					elseif (count($userCompanies)==1)
					{
						$this->view->endorserId = $userCompanies[0]["id"];
					}

				}


			}

			if ($this->getRequest()->isPost())
			{
				//assume that user is not new
				$newUser = false;

				if ($user) {
					if ($user->isPartOfBuyer()) {
		            	$user->logActivity(Shipserv_User_Activity::REVIEW_CREATED, 'PAGES_USER', $user->userId, $user->email);
		        	}
		        }

				if ($params["reqcode"]){

					//check if user with given email exists
					$userAdaptor = new Shipserv_Oracle_User($db);
					try {
						$endorserUser = $userAdaptor->fetchUserByEmail($reviewRequest->userEmail);
					}
					catch (Exception $exception)
					{
						$endorserUser = null;
					}



					//if review submitted by unregistered visitor - create new user
					if (!is_object($endorserUser))
					{
						//create new user
						$password = $userAdaptor->genPassword ();
						$userAdaptor->createUser($reviewRequest->userEmail, "", "",$password);
						$endorserUser = $userAdaptor->fetchUserByEmail($reviewRequest->userEmail);
						$this->view->password = $password;

						//login user
						$user = Shipserv_User::login($endorserUser->username, $password, false);

						$this->_helper->viewRenderer('user-auto-created');

						$newUser = true;

					}

					//by replying to review request user verifies his email
					$endorserUser->confirmUserEmail();

					$userBuyerIds = $endorserUser->fetchCompanies()->getBuyerIds();

					$review = $reviewRequest->createReview(((is_object($endorserUser))?$endorserUser->userId:null), intval($params["overallImpression"]), $params["did"], $params["otd"], $params["cs"], $params["reviewComment"],(in_array($reviewRequest->endorserId, $userBuyerIds))?'Y':'N');

				}
				else
				{
					$review = Shipserv_Review::create($endorseeId, $params["buyerOrganisation"], $user->userId, intval($params["overallImpression"]), $params["did"], $params["otd"], $params["cs"], $params["reviewComment"],(in_array($params["buyerOrganisation"], $userBuyerIds))?'Y':'N');
					$endorserUser = $user;
				}

				if (trim($params["category1"])!="")
				{
					$review->addCategory($params["category1Id"], $params["category1"]);
				}

				$this->view->review = $review;

				//send join company request if user submitted review on behalf of company he is not part of
				if (!in_array($review->endorserId, $endorserUser->fetchCompanies()->getBuyerIds()))
				{
					try {
					//request join to buyer company for newly created user
						$uActions = new Myshipserv_UserCompany_Actions($db,$endorserUser->userId, $endorserUser->email);
						$reqId = $uActions->requestJoinCompany('BYO', $review->endorserId);

					}
					catch (Exception $e) {
						if (get_class($e)!="Myshipserv_UserCompany_Actions_Exception")
						{
							throw $e;
						}
					}

					//if user is not new, we need to let him know that we requested membership and only then his review will be visible
					if (!$newUser)
					{
						$this->_helper->viewRenderer('user-auto-joined');
					}
				}
				else
				{
					//if user is part - it means review was published, we can send notification
					$notificationManager = new Myshipserv_NotificationManager($db);
					$notificationManager->reviewAdded($review);
					$this->redirect('/supplier/profile/s/'.$endorseeId. '#reviews', array('code' => 301));
				}
			}
			else
			{
				//only pass overall impression parameter on initial page load
				if (isset($params["oi"]))
				{
					$this->view->formValues["overallImpression"] = $params["oi"];
				}
			}


		}

		// create a supplier object
		// NEED TO CHANGE THE OTHER PROFILE CODE TO USE THIS OBJECT
		$supplier = Shipserv_Supplier::fetch($endorseeId, $db);

                // fix the unicode entities
		$unicodeReplace = new Shipserv_Unicode();
		$supplier->description = $unicodeReplace->UTF8entities($supplier->description);

		// fetch the current basket
		$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
		$supplierBasket = $enquiryBasket['suppliers'];
		if (!$supplierBasket)
		{
			$supplierBasket = array();
		}


		/***** SEO STUFF *****/

		$title = trim($supplier->name).', '.$supplier->city .', '.$supplier->countryName;
		$title.= ' - ShipServ Pages';
		$this->view->headTitle($title, 'SET');

		if ($supplier->description) // if there's a description, use that
		{
			$metaDescription = $this->view->string()->shortenToLastWord(strip_tags(str_replace("\n", "", $supplier->description)), 150);
		}

		// meta keywords
		$metaKeywords = 'Review, Marine, Maritime, Shipping, Suppliers, Supply, Companies, Directory, Listings, Search, ShipServ, Parts, Equipment, Spares, Services, ';
		$metaKeywords.= $supplier->name.', '.$supplier->city.', '.$supplier->countryName;

		$this->view->user = $user;
		$this->view->basketCookie     = $config['shipserv']['enquiryBasket']['cookie'];
		$this->view->metaDescription  = $metaDescription;
		$this->view->metaKeywords     = $metaKeywords;
		$this->view->supplierBasket   = $supplierBasket;
		$this->view->supplier         = $supplier;

    }

	public function addBulkReviewAction ()
    {
		$db = $this->db;
		$params = $this->params;
		$user = $this->user;

		//these reviews can be submitted only by logged in users
		if (is_object($user))
		{
			//check if user actually belongs to endorser
			if (in_array($params["endorserId"], $user->fetchCompanies()->getBuyerIds()))
			{
				$review = Shipserv_Review::create($params["endorseeId"], $params["endorserId"], $user->userId, intval($params["overallImpression"]), $params["did"], $params["otd"], $params["cs"], $params["reviewComment"],'Y');
				if (trim($params["category1"])!="")
				{
					$review->addCategory($params["category1Id"], $params["category1"]);
				}
				$notificationManager = new Myshipserv_NotificationManager($db);
				$notificationManager->reviewAdded($review);

				return ($review->id);
			}
		}
    }


	public function editReviewAction ()
    {

		$db = $this->db;
		$config = $this->config;
		$params = $this->params;
		$user = $this->user;
		$cookieManager = $this->getHelper('Cookie');

		$this->_helper->viewRenderer('add-review');

		//only logged users can edit reviews
		if (is_object($user))
		{

			if ($this->_getParam('r'))
			{
				$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
				// Fetch company ids for which this user is admin
				$ucActions->fetchMyCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

				//retrieve review
				if ($review = Shipserv_Review::fetch($this->_getParam('r')))
				{
					if ($review->authorUserId == $user->userId or in_array($review->endorserId, $buyerAdminIds) )
					{
						$endorseeId = $review->endorseeId;

						$data = array (
							"overallImpression" => $review->overallImpression,
							"did" => $review->ratingItemsAsDescribed,
							"otd" => $review->ratingDeliveredOnTime,
							"cs" => $review->ratingCustomerService,
							"reviewComment" => $review->comment
						);

						if ($categories = $review->getCategories())
						{
							for ($i=0;$i<count($categories);$i++)
							{
								$data["category".($i+1)] = $categories[$i]["PEC_CATEGORY_TEXT"];
								$data["category".($i+1)."Id"] = $categories[$i]["PEC_CATEGORY_ID"];
							}
						}
						$this->view->formValues = $data;

						if ($this->getRequest()->isPost())
						{
							$this->view->formValues = $params;
							$review->overallImpression = intval($params["overallImpression"]);
							$review->ratingItemsAsDescribed = $params["did"];
							$review->ratingDeliveredOnTime = $params["otd"];
							$review->ratingCustomerService = $params["cs"];
							$review->comment = $params["reviewComment"];
							$review->isEdited = true;
							$review->update();
							$review->removeCategories();


							if (trim($params["category1"])!="")
							{
								$review->addCategory($params["category1Id"], $params["category1"]);
							}

							//if user is part - it means review was published, we can send notification
							$notificationManager = new Myshipserv_NotificationManager($db);
							$notificationManager->reviewEdited($review);

							$this->redirect('/supplier/profile/s/'.$endorseeId. '#reviews', array('code' => 301));
						}

					}
					else
					{
						throw new Myshipserv_Exception_MessagedException("You cannot edit this review");
					}
				}
				else
				{
					throw new Myshipserv_Exception_MessagedException("Review is not found");
				}
			}
			else
			{
				throw new Myshipserv_Exception_MessagedException("No review is defined for editing");
			}
		}
		else
		{
			throw new Myshipserv_Exception_MessagedException("Sorry, you do not have permission to edit reviews", 401);
		}

		// create a supplier object
		$supplier = Shipserv_Supplier::fetch($endorseeId, $db);


		// fix the unicode entities
		$unicodeReplace = new Shipserv_Unicode();
		$supplier->description = $unicodeReplace->UTF8entities($supplier->description);

		// fetch the current basket
		$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
		$supplierBasket = $enquiryBasket['suppliers'];
		if (!$supplierBasket)
		{
			$supplierBasket = array();
		}

		/***** SEO STUFF *****/

		$title = trim($supplier->name).', '.$supplier->city.', '.$supplier->countryName;
		$title.= ' - ShipServ Pages';
		$this->view->headTitle($title, 'SET');

		if ($supplier->description) // if there's a description, use that
		{
			$metaDescription = $this->view->string()->shortenToLastWord(strip_tags(str_replace("\n", "", $supplier->description)), 150);
		}

		// meta keywords
		$metaKeywords = 'Review, Marine, Maritime, Shipping, Suppliers, Supply, Companies, Directory, Listings, Search, ShipServ, Parts, Equipment, Spares, Services, ';
		$metaKeywords.= $supplier->name.', '.$supplier->city.', '.$supplier->countryName;


		$this->view->user = $user;
		$this->view->basketCookie     = $config['shipserv']['enquiryBasket']['cookie'];
		$this->view->metaDescription  = $metaDescription;
		$this->view->metaKeywords     = $metaKeywords;
		$this->view->supplierBasket   = $supplierBasket;
		$this->view->supplier         = $supplier;


    }


	/**
	 * Adds endorsee's comment for user submitted review
	 */
	public function addReplyAction ()
    {
		$user   = Shipserv_User::isLoggedIn();
		$db     = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->params;

		if (is_object($user))
		{
			// and if user belongs to endorsee's company
			$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
			$userSuppliers = $ucActions->fetchMyCompanies()->getSupplierIds();
			if ($review = Shipserv_Review::fetch($params["reviewId"])){
				if (in_array($review->endorseeId, $userSuppliers))
				{
					// sanitise the reply text
					$unicodeReplace = new Shipserv_Unicode();
					$replyText = strip_tags($unicodeReplace->UTF8entities($params["replyText"]));
					$review->updateReply($replyText);

					//send notification
					$notificationManager = new Myshipserv_NotificationManager($db);
					$notificationManager->reviewReplyPosted($review);

					$this->_helper->json((array)nl2br($replyText));
				}
			}
		}
    }

	/**
	 * Remove review request
	 */
	public function removeAction ()
    {
		$user   = $this->user;
		$params = $this->params;

		if (is_object($user))
		{
			if ($review = Shipserv_Review::fetch($params["reviewId"])){
				if ($review->authorUserId == $user->userId)
				{
					$review->delete();
				}
			}
		}
    }

	/**
	 * Remove review request
	 */
	public function ignoreRequestAction ()
    {
		$user   = $this->user;
		$params = $this->params;

		if (is_object($user))
		{
			if ($reviewRequest = Shipserv_ReviewRequest::fetchByCode($params["requestId"])){
				if ($user->email == $reviewRequest->userEmail)
				{
					$reviewRequest->delete();
				}
			}
		}
    }

	/**
	 * Remove review request
	 */
	public function invalidRequestAction ()
    {

		$params = $this->params;
		if ($reviewRequest = Shipserv_ReviewRequest::fetchByCode($this->_getParam('reqcode'))){
			$reviewRequest->delete();
		}

		throw new Myshipserv_Exception_MessagedException("Request for a review was successfully removed.");
    }

	public function userAutoCreatedAction ()
	{
		$user   = Shipserv_User::isLoggedIn();
		$review = Shipserv_Review::fetch($this->_getParam("r"));
		$this->view->user = $user;
		$this->view->review = $review;
	}

	/**
	 * Endorsement request from buyer
	 */
	public function sendRequestAction ()
    {
		$user   = Shipserv_User::isLoggedIn();
		$db     = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->params;

		//check if user is logged in
		if (is_object($user))
		{
			$userEndorsementAdapter = new Shipserv_Oracle_UserEndorsement($db);

			// and if user belongs to endorsee's company

			$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
			$userSuppliers = $ucActions->fetchMyCompanies()->getSupplierIds();

			if (in_array($params["endorseeId"], $userSuppliers))
			{
				// sanitise the request text
				$unicodeReplace = new Shipserv_Unicode();
				$requestText = strip_tags($unicodeReplace->UTF8entities($params["requestText"]));
				$userCompanyAdepter = new Myshipserv_UserCompany_Domain($db);
				$notificationManager = new Myshipserv_NotificationManager($db);
				$requestEmails = array();


				foreach ($userCompanyAdepter->fetchUsersForCompany('BYO', $params["endorserId"])->getAdminUsers() as $endorserUser)
				{
					if (!in_array(strtolower(trim($endorserUser->email)),$requestEmails))
					{
						$requestEmails[] = strtolower(trim($endorserUser->email));

						$reviewRequest = Shipserv_ReviewRequest::create($user->userId, $params["endorseeId"],$params["endorserId"], $endorserUser->email, $requestText);

						if ($endorserUser->alertStatus == Shipserv_User::ALERTS_IMMEDIATELY)
						{
							$notificationManager->requestReview(array("name"=>($endorserUser->firstName.' '.$endorserUser->lastName),"email"=>$endorserUser->email), $reviewRequest);

						}
					}
				}

				foreach (explode(",", $params["endorserEmails"]) as $endorserEmail)
				{
					$endorserEmail = strtolower(trim($endorserEmail));
					if (!in_array($endorserEmail,$requestEmails) and $endorserEmail!="")
					{
						$requestEmails[] = $endorserEmail;

						$reviewRequest = Shipserv_ReviewRequest::create($user->userId, $params["endorseeId"],$params["endorserId"], $endorserEmail, $requestText);

						$notificationManager->requestReview(array("name"=>"","email"=>$endorserEmail), $reviewRequest);
					}
				}

				$this->_helper->json((array)nl2br("success"));
			}
		}
    }

	public function generateAction ()
	{
		$remoteIp = Myshipserv_Config::getUserIp();
		if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
			throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
		}

		$generator = new Myshipserv_ReviewRequest_Generator();
		$generator->debug = true;
		$generator->generate();
	}

	public function externalRequestAction ()
	{
		try {
			$outputText = "";

			$config = $this->config;
			$params = $this->params;
			$db     = $this->getInvokeArg('bootstrap')->getResource('db');

			$remoteIp = Myshipserv_Config::getUserIp();

			if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
				throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
			}

			//check if all variables are provided
			$requiredParams = array ("endorserId","endorseeId");

			foreach ($requiredParams as $requiredParam)
			{
				if (!$params[$requiredParam])
				{
					throw new Myshipserv_Exception_MessagedException("Required variable ". $requiredParam ." is not supplied");
				}
			}

			//normilize ids
			$buyerAdaptor = new Shipserv_Oracle_UserCompanies_NormBuyer($db);
			$buyerAdaptor->addId($params["endorserId"]);
			$endorserId = $buyerAdaptor->canonicalize();
			$endorserId = array_shift($endorserId);
			$outputText .= "Endorser Id: ". $endorserId ."\n";

			$supplierAdaptor = new Shipserv_Oracle_UserCompanies_NormSupplier($db);
			$supplierAdaptor->addId($params["endorseeId"]);
			$endorseeId = $supplierAdaptor->canonicalize();
			$endorseeId = array_shift($endorseeId);
			$outputText .= "Endorsee Id: ". $endorseeId ."\n";

			// sanitise the request text
			$unicodeReplace = new Shipserv_Unicode();

			if (!$params["requestText"])
			{
				$requestText = "";
			}
			else
			{
				$requestText = strip_tags($unicodeReplace->UTF8entities($params["requestText"]));
			}

			$userCompanyAdeptor = new Myshipserv_UserCompany_Domain($db);
			$notificationManager = new Myshipserv_NotificationManager($db);
			$requestEmails = array();

			if (!$params["noAdmins"])
			{
				$companyAdminUsers = $userCompanyAdeptor->fetchUsersForCompany('BYO', $endorserId)->getAdminUsers();
				$outputText .= "Found admin users: ". count($companyAdminUsers) ."\n";
				foreach ($companyAdminUsers as $endorserUser)
				{

					if (!in_array(strtolower(trim($endorserUser->email)),$requestEmails))
					{
						$requestEmails[] = strtolower(trim($endorserUser->email));

						$reviewRequest = Shipserv_ReviewRequest::create(null, $endorseeId, $endorserId, $endorserUser->email, $requestText);

						if ($endorserUser->alertStatus == Shipserv_User::ALERTS_IMMEDIATELY)
						{
							$notificationManager->requestReview(array("name"=>($endorserUser->firstName.' '.$endorserUser->lastName),"email"=>$endorserUser->email), $reviewRequest);
							$outputText .= "Email sent to ".$endorserUser->email."\n";

						}
						else
						{
							$outputText .= "User ".$endorserUser->email." opted out from receiving requests by email\n";
						}
					}
				}
			}
			if (isset($params["endorserEmails"]) and trim($params["endorserEmails"])!="")
			{
				foreach (explode(",", $params["endorserEmails"]) as $endorserEmail)
				{
					$endorserEmail = strtolower(trim($endorserEmail));
					if (!in_array($endorserEmail,$requestEmails) and $endorserEmail!="")
					{
						$requestEmails[] = $endorserEmail;

						//check if user with given email exists
						$userAdaptor = new Shipserv_Oracle_User($db);
						try {
							$endorserUser = $userAdaptor->fetchUserByEmail($endorserEmail);
						}
						catch (Exception $exception)
						{
							$endorserUser = null;
						}

						$reviewRequest = Shipserv_ReviewRequest::create(null, $endorseeId, $endorserId, $endorserEmail, $requestText);

						if ($endorserUser)
						{
							if ($endorserUser->alertStatus == Shipserv_User::ALERTS_IMMEDIATELY)
							{
								$notificationManager->requestReview(array("name"=>($endorserUser->firstName.' '.$endorserUser->lastName),"email"=>$endorserUser->email), $reviewRequest);
								$outputText .= "Email sent to ".$endorserUser->email."\n";
							}
							else
							{
								$outputText .= "User ".$endorserUser->email." opted out from receiving requests by email\n";
							}
						}
						else
						{
							$notificationManager->requestReview(array("name"=>"","email"=>$endorserEmail), $reviewRequest);
							$outputText .= "Email sent to ".$endorserEmail."\n";
						}
					}
				}
			}

			if (!$params["debug"])
			{
				$this->_helper->json((array)nl2br("success"));
			}
			else
			{
				$this->_helper->json((array)$outputText);
			}
		}

		catch (Exception $e) {
			$this->_helper->json((array)array("error"=>$e->getMessage()));
		}
	}

	public function externalNormAction ()
	{
		try {

			$config = Zend_Registry::get('config');
			$params = $this->params;
			$db     = $this->getInvokeArg('bootstrap')->getResource('db');

			$remoteIp = $remoteIp = Myshipserv_Config::getUserIp();
			if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
				throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
			}

			//check if all variables are provided
			$requiredParams = array ("endorserId","endorseeId");

			foreach ($requiredParams as $requiredParam)
			{
				if (!$params[$requiredParam])
				{
					throw new Myshipserv_Exception_MessagedException("Required variable ". $requiredParam ." is not supplied");
				}
			}

			//normilize ids
			$buyerAdaptor = new Shipserv_Oracle_UserCompanies_NormBuyer($db);
			$buyerAdaptor->addId($params["endorserId"]);
			$endorserId = $buyerAdaptor->canonicalize();
			$endorserId = array_shift($endorserId);

			$supplierAdaptor = new Shipserv_Oracle_UserCompanies_NormSupplier($db);
			$supplierAdaptor->addId($params["endorseeId"]);
			$endorseeId = $supplierAdaptor->canonicalize();
			$endorseeId = array_shift($endorseeId);




			if (!$params["verbose"])
			{
				if (is_null($endorseeId) or is_null($endorserId))
				{
					$this->_helper->json((array)"error");
				}
				else
				{
					$this->_helper->json((array)"success");
				}

			}
			else
			{
				$this->_helper->json((array)(object)array("endorseeId"=>$endorseeId,"endorserId"=>$endorserId));
			}
		}
		catch (Exception $e) {
			$this->_helper->json((array)array("error"=>$e->getMessage()));
		}
	}

}
