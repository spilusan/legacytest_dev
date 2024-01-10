<?php

/**
 * Controller for handling brand management related actions
 *
 * @package myshipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class BrandAuthController extends Myshipserv_Controller_Action
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
		$ajaxContext = $this->_helper->contextSwitch();
		$ajaxContext->addActionContext('reject-brand-auth-request', 'json')
			->addActionContext('authorise-brand-auth-request', 'json')
			->addActionContext('set-brand-auth', 'json')
			->addActionContext('remove-brand-auth', 'json')
			->addActionContext('remove-company-auths', 'json')
			->addActionContext('authorise-company', 'json')
			->initContext();
	}

	/**
	 * This page is used to approve a brand auth request from a supplier
	 *
	 * @param int $_GET["brandId"]
	 * @param int $_GET["supplierId"]
	 * @param string $_GET["auth"] hash of both brandId and supplierId + a bit of string
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function claimOwnershipAction()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();

		// recalculate the hash of these parameters, please see BrandAuthInvite on NotificationManager
		$hash = md5( "action=".$params["a"] . "supplierId=".$params["supplierId"] . "brandId=" . $params["brandId"] . "brandOwnerId=" . $params["brandOwnerId"]);

		// initialise error flag
		$this->view->error = false;

		// check if login
		$user = Shipserv_User::isLoggedIn();

		// if for some reason, the auto login token didn't log user automatically, then throw an exception
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		// pass the user object to view to let the view know that user is logged in.
		$this->view->user = $user;

		// check the hash
		if( $hash == $params["auth"])
		{
			// prepare notification manager for enabling passive brand owner
			$notificationManager = new Myshipserv_NotificationManager($db);

			try
			{
				// enable passive brand owner
				Shipserv_Api_Brands::createBrandOwner ($params["brandOwnerId"], $params["brandId"], 'y', $notificationManager);
			}

				// catch exception and present it with user facing exception
			catch(Exception $e)
			{
				throw new Myshipserv_Exception_MessagedException("Another administrator has approved this request.");
			}

			// get all brand authorisation requests
			$brandAuthRequest = Shipserv_BrandAuthorisation::getCompanyRequestsForBrand($params["supplierId"], $params["brandId"]);

			// if request is available
			if( count( $brandAuthRequest ) > 0 )
			{
				if( $params["a"] == "approve")
				{
					$grantedAuthorisations = array();

					// authorising all requests
					foreach( $brandAuthRequest as $request)
					{
						$request->authorise();
						$grantedAuthorisations[] = $request;
					}

					// if any authorisations were granted - send message
					if (count($grantedAuthorisations)>0)
					{
						// notify supplier about authorisations granted
						$notificationManager->brandAuthorisationApproved($grantedAuthorisations);
					}
				}

				// if action is rejection
				else
				{
					$grantedAuthorisations = array();

					// authorising all requests
					foreach( $brandAuthRequest as $request)
					{
						$request->remove( true );
						$rejectedAuthorisations[] = $request;
					}

					// if any authorisations were granted - send message
					if (count($rejectedAuthorisations)>0)
					{
						// notify supplier about authorisations granted
						$notificationManager->brandAuthorisationRejected($rejectedAuthorisations);
					}
				}

				// get the adapter
				$brandAdapter = new Shipserv_Oracle_Brands( $db );

				$supplierObject = Shipserv_Supplier::fetch(intval($params["supplierId"]), $db);
				$supplierObject->purgeMemcache();
				// prepare the view
				$this->view->authLevelLabel = Shipserv_BrandAuthorisation::$displayAuthNames[ $params["authLevel"] ];
				$this->view->supplier = $supplierObject;
				$this->view->brandOwnerAction = $param["a"];
				$this->view->brand = $brandAdapter->fetch(intval($params["brandId"]));
				$this->view->linkToBrandManagementPage = "/profile/company-brands/type/v/id/" . $params["brandOwnerId"];

			}

			// if request cannot be found
			else
			{
				throw new Myshipserv_Exception_MessagedException("System cannot find this request");
				$this->view->error = "Invalid request. Your colleague might have approved this supplier.";
			}
		}

		// if hash didn't match
		else
		{
			throw new Myshipserv_Exception_MessagedException("You are not authorised to do this");
			$this->view->error = "You are not authorise to do this";
		}

		return ;
	}


	public function claimBrandOwnershipAction()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();

		// recalculate the hash of these parameters, please see BrandAuthInvite on NotificationManager
		$hash = md5( "action=".$params["a"] . "supplierId=".$params["supplierId"] . "brandId=" . $params["brandId"] . "brandOwnerId=" . $params["brandOwnerId"]);

		// initialise error flag
		$this->view->error = false;

		// check if login
		$user = Shipserv_User::isLoggedIn();

		// if for some reason, the auto login token didn't log user automatically, then throw an exception
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		// pass the user object to view to let the view know that user is logged in.
		$this->view->user = $user;

		// check the hash
		if( $hash == $params["auth"])
		{
			try
			{
				// prepare notification manager for enabling passive brand owner
				$notificationManager = new Myshipserv_NotificationManager($db);

				// enable passive brand owner
				Shipserv_Api_Brands::createBrandOwner ($params["supplierId"], $params["brandId"], 'Y', $notificationManager);
				$this->view->linkToBrandManagementPage = "/profile/company-brands/type/v/id/" . $params["supplierId"];

			}

				// catch exception and present it with user facing exception
			catch(Exception $e)
			{
				$this->view->linkToBrandManagementPage = "/profile/company-brands/type/v/id/" . $params["supplierId"];
				//throw new Myshipserv_Exception_MessagedException("Another administrator has approved this request.");
			}
		}

		// if hash didn't match
		else
		{
			throw new Myshipserv_Exception_MessagedException("You are not authorised to do this", 401);
			$this->view->error = "You are not authorise to do this";
		}

		return ;
	}

	public function addBrandOwnerAction() {

		$this->view->success = false;

		if($this->getRequest()->isPost() && $_REQUEST['acceptTerms'] == 'on')
		{
			$db = $this->getInvokeArg('bootstrap')->getResource('db');
			$notificationManager = new Myshipserv_NotificationManager($db);
			$notificationManager->brandOwnershipRequested($this->_getAllParams());
			$this->view->success = true;
		}
		else
		{
			if($this->_hasParam('brand'))
			{
				$brandAdapter = new Shipserv_Oracle_Brands($this->getInvokeArg('bootstrap')->getResource('db'));
				$this->view->brand = $brandAdapter->fetchBrand($this->_getParam('brand'));
			}
		}
	}

	public function rejectBrandAuthRequestAction ()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();


		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]) or !isset($params["authLevels"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters", 401);
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0 && $user->isShipservUser() === false )
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		//notify about rejection
		$notificationManager = new Myshipserv_NotificationManager($db);
		$notificationManager->brandAuthorisationRejected(Shipserv_BrandAuthorisation::getCompanyRequestsForBrand($params["companyId"], $params["brandId"]));
		//$output = $notificationManager->brandAuthorisationRejected(Shipserv_BrandAuthorisation::getCompanyRequestsForBrand($params["companyId"], $params["brandId"]));
		//die();
		Shipserv_BrandAuthorisation::removeCompanyRequestsForBrand($params["companyId"], $params["brandId"]);

		//  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
		Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH, $params["brandId"]);

		return;
	}

	public function authoriseBrandAuthRequestAction ()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();


		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]) or !isset($params["authLevels"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0)
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		$grantedAuthorisations = array ();

		$authLevels = explode(",", $params["authLevels"]);
		foreach ($authLevels as $authLevel)
		{
			//lets see if this request really exists
			if ($request = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"], $authLevel, false))
			{
				//and we do not have granted authorisation already (shouldn't happen usually)
				if ($auth = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"], $authLevel, true))
				{
					//remove request if we already have authorisation
					$request->remove();

					//  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
					Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH, $params["brandId"]);
				}
				else
				{
					$request->authorise();
					$grantedAuthorisations[] = $request;

					//  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
					Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH, $params["brandId"]);
				}
			}
			else
			{
				//request does not exist, but we may have granted authorisation already - check for it
				if (!$auth = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"], $authLevel, true))
				{
					//ok, no request, no granted auth - then create authorisation
					$grantedAuthorisations[] = Shipserv_BrandAuthorisation::create($params["companyId"], $params["brandId"], $authLevel, true);

					//  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
					Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH, $params["brandId"]);

				}
			}
		}

		//if any authorisations were granted - send message
		if (count($grantedAuthorisations)>0)
		{
			//notify supplier about authorisations granted
			$notificationManager = new Myshipserv_NotificationManager($db);
			$notificationManager->brandAuthorisationApproved($grantedAuthorisations);
		}

		//and remove all request that were not granted
		Shipserv_BrandAuthorisation::removeCompanyRequestsForBrand($params["companyId"], $params["brandId"]);

		return;
	}

	public function setBrandAuthAction ()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();


		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]) or !isset($params["authLevel"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0)
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		//lets see if this auth does not exist already
		if (!$auth = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"], $params["authLevel"], true))
		{
			Shipserv_BrandAuthorisation::create($params["companyId"], $params["brandId"], $params["authLevel"], true);
		}
		return;
	}

	public function removeBrandAuthAction ()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();


		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]) or !isset($params["authLevel"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0)
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		//lets see if this auth does exist
		if ($auth = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"], $params["authLevel"], true))
		{
			//permanently remove brand authorisation
			$auth->remove(true);
		}
		return;
	}

	public function removeCompanyAuthsAction ()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();


		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0)
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		Shipserv_BrandAuthorisation::removeCompanyAuthsForBrand($params["companyId"], $params["brandId"],true);


		return;
	}

	public function authoriseCompanyAction()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();

		//check if user is logged in
		try
		{
			$user = $this->getUser();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["brandId"]) or !isset($params["authLevels"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own brand
		$brandOwners = Shipserv_BrandAuthorisation::getBrandOwners($params["brandId"]);

		//check if user is admin of company that owns the brand
		if (count(array_intersect($brandOwners, $supplierAdminIds))==0)
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		$authLevels = explode(",", $params["authLevels"]);
		foreach ($authLevels as $authLevel)
		{
			//lets see if this auth does not exist already
			if (!$auth = Shipserv_BrandAuthorisation::fetch($params["companyId"], $params["brandId"],$authLevel, true))
			{
				Shipserv_BrandAuthorisation::create($params["companyId"], $params["brandId"], $authLevel, true);
			}
		}
		return;
	}

	private function getUser()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		return $user;
	}

	public function serviceAction()
	{
		$remoteIp = Myshipserv_Config::getUserIp();
		if (!$this->isIpInternal($remoteIp) && !$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
			throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
		}

		$params = $this->params;
		$notificationManager = new Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());

		//check if all variables are provided - different API methods require different mandatory parameters
		switch ($params['method']) {
			case 'saveCompanyBrand':
				$moreParams = array(
					'companyId',
					'brandId'
				);
				break;

			case 'requestAuthorisation':
				$moreParams = array(
					'companyId',
					'brandId',
					'authLevel'
				);
				break;

			case 'listAllAuthorisations':
				$moreParams = array(
					'companyId'
				);
				break;

			case 'removeAuthorisation':
				$moreParams = array(
					'companyId',
					'brandId',
					'authLevel'
				);
				break;

			case 'createBrandOwner':
				$moreParams = array(
					'companyId',
					'brandId',
					'isActive',
					'notify'
				);
				break;

			case 'removeBrandOwner':
				$moreParams = array(
					'companyId',
					'brandId',
					'notify'
				);
				break;

			default:
				$moreParams = array();
		}

		$requiredParams = array('method');
		$requiredParams = array_merge($requiredParams, $moreParams);

		foreach ($requiredParams as $paramName) {
			if (strlen($params[$paramName]) === 0) {
				throw new Myshipserv_Exception_MessagedException("Required variable " . $paramName . " is not supplied");
			}
		}

		// added by Yuriy Akopov on 2016-12-14 as a temporary measure because of a timeout on UAT3
        $oldExecTime = ini_set('max_execution_time', 60);

		// now execute the requested API method
		switch ($params["method"]) {
			case "saveCompanyBrand":
				// preparing parameters submitted to the webservice
				$authLevels = array();
				if (strlen($params['authLevelArray'])) {
					$authLevels = explode(',', $params['authLevelArray']);
				}

				$modelNames = array();
				if (strlen($params['modelNameArray'])) {
					$modelNames = explode(',', $params['modelNameArray']);
				}

				$result = Shipserv_Api_Brands::saveCompanyBrand($params['companyId'], $params['brandId'], $authLevels, $modelNames, $notificationManager);
				break;

			case 'requestAuthorisation':
				$auth = Shipserv_Api_Brands::requestAuthorisation($params['companyId'], $params['brandId'], $params['authLevel']);
				$result = Shipserv_Api_Brands::authToArray($auth);
				break;

			case 'listAllAuthorisations':
				$result = Shipserv_Api_Brands::listAllAuthorisations($params['companyId']);
				break;

			case 'removeAuthorisation':
				$result = Shipserv_Api_Brands::removeAuthorisation($params['companyId'], $params['brandId'], $params['authLevel']);
				break;

			case 'createBrandOwner':
			    // added by Yuriy Akopov on 2016-09-01, DE6813 to allow only valid supplier IDs into API. Modified by Claudio for DE7024
			    $supplier = Shipserv_Supplier::getInstanceById($params['companyId']);
			    if ((strlen($supplier->tnid) === 0) || ($supplier->tnid != $params['companyId'])) {
			        throw new Myshipserv_Exception_MessagedException("Company ID " . $params['companyId'] . " is not valid for brand ownership");
			    }			    
				$result = Shipserv_Api_Brands::createBrandOwner($params['companyId'], $params['brandId'], $params['isActive'],($params['notify']=='Y')?$notificationManager:null);
				break;

			case 'removeBrandOwner':
				$result = Shipserv_Api_Brands::removeBrandOwner($params['companyId'], $params['brandId'],($params['notify']=='Y')?$notificationManager:null);
				break;

			default:
				throw new Myshipserv_Exception_MessagedException("Unknown API method " . $params['method']);
		}

        // added by Yuriy Akopov on 2016-12-14 - see the comment for ini_set above)
        ini_set('max_execution_time', $oldExecTime);

		$this->_helper->json((array)$result);
	}
}