<?php

/**
 * Controller for handling brand management related actions
 *
 * @package myshipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class CategoryAuthController extends Myshipserv_Controller_Action
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
        $ajaxContext->addActionContext('reject-category-auth-request', 'json')
			->addActionContext('authorise-category-auth-request', 'json')
			->addActionContext('remove-category-auth', 'json')
            ->initContext();
    }

	public function rejectCategoryAuthRequestAction ()
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
		if (!isset($params["companyId"]) or !isset($params["categoryId"]))
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}


		//fetch list of editors for this category brand
		$categoryEditors = Shipserv_CategoryAuthorisation::getCategoryEditors($params["categoryId"]);

		//check if user is admin of company that owns the brand
		if (!in_array($user->userId, $categoryEditors))
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		//notify about rejection
		$notificationManager = new Myshipserv_NotificationManager($db);
		$notificationManager->categoryAuthorisationRejected(Shipserv_CategoryAuthorisation::getCompanyRequestsForCategory($params["companyId"], $params["categoryId"]));

		Shipserv_CategoryAuthorisation::removeCompanyRequestsForCategory($params["companyId"], $params["categoryId"]);


		return;
	}

	public function authoriseCategoryAuthRequestAction ()
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
		if (!isset($params["companyId"]) or !isset($params["categoryId"]))
		{
			throw new Exception("Invalid parameters");
		}


		//fetch list of editors for this category brand
		$categoryEditors = Shipserv_CategoryAuthorisation::getCategoryEditors($params["categoryId"]);

		//check if user is editor for this category
		if (!in_array($user->userId, $categoryEditors))
		{
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}


		$grantedAuthorisation = null;


		//lets see if this request really exists
		if ($request = Shipserv_CategoryAuthorisation::fetch($params["companyId"], $params["categoryId"], false))
		{
			//and we do not have granted authorisation already (shouldn't happen usually)
			if ($auth = Shipserv_CategoryAuthorisation::fetch($params["companyId"], $params["categoryId"], true))
			{
				//remove request if we already have authorisation
				$request->remove();
			}
			else
			{
				$request->authorise();
				$grantedAuthorisation = $request;
			}
		}
		else
		{
			//request does not exist, but we may have granted authorisation already - check for it
			if (!$auth = Shipserv_CategoryAuthorisation::fetch($params["companyId"], $params["categoryId"], true))
			{
				//ok, no request, no granted auth - then create authorisation
				$grantedAuthorisation = Shipserv_CategoryAuthorisation::create($params["companyId"], $params["categoryId"], true);
			}
		}
		

		//if any authorisations were granted - send message
		if (!is_null($grantedAuthorisation))
		{
			//notify supplier about authorisations granted
			$notificationManager = new Myshipserv_NotificationManager($db);
			$notificationManager->categoryAuthorisationApproved(array($grantedAuthorisation));
		}

		return;
	}

    private function getUser ()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		return $user;
	}

}
