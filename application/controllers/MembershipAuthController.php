<?php

/**
 * Controller for handling membership management related actions
 *
 * @package myshipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class MembershipAuthController extends Myshipserv_Controller_Action
{
    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     *
     * @access public
     */
    public function init()
    {
    	parent::init();

	    $ajaxContext = $this->_helper->contextSwitch();
	    $ajaxContext
		    ->addActionContext('reject-membership-auth-request', 'json')
		    ->addActionContext('authorise-membership-auth-request', 'json')
		    ->addActionContext('remove-membership-auth', 'json')
		    ->addActionContext('upload', 'json')
		    ->initContext()
	    ;
    }

	/**
	 * @throws Exception
	 */
    public function rejectMembershipAuthRequestAction()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();

		//check if user is logged in
		try {
			$user = $this->getUser();
		} catch (Myshipserv_Exception_NotLoggedIn $e) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["membershipId"])) {
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own membership
		$membershipOwners = Shipserv_MembershipAuthorisation::getMembershipOwners($params["membershipId"]);

		//check if user is admin of company that owns the membership
		if (count(array_intersect($membershipOwners, $supplierAdminIds)) == 0) {
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		//notify about rejection
		$notificationManager = new Myshipserv_NotificationManager($db);
		$notificationManager->membershipAuthorisationRejected(Shipserv_MembershipAuthorisation::getCompanyRequestsForMembership($params["companyId"], $params["membershipId"]));

		Shipserv_MembershipAuthorisation::removeCompanyRequestsForMembership($params["companyId"], $params["membershipId"]);
		
		// [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
		Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_MEMBERSHIP, $params["membershipId"]);
	}

	/**
	 * @throws Exception
	 */
	public function authoriseMembershipAuthRequestAction()
	{
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();

		//check if user is logged in
		try {
			$user = $this->getUser();
		} catch (Myshipserv_Exception_NotLoggedIn $e) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		//check if required parameters are provided
		if (!isset($params["companyId"]) or !isset($params["membershipId"])) {
			throw new Myshipserv_Exception_MessagedException("Invalid parameters");
		}

		// Fetch company ids for which this user is admin
		$user->fetchCompanies()->getAdminIds($buyerAdminIds, $supplierAdminIds);

		//fetch list of companies that own membership
		$membershipOwners = Shipserv_MembershipAuthorisation::getMembershipOwners($params["membershipId"]);

		//check if user is admin of company that owns the membership
		if (count(array_intersect($membershipOwners, $supplierAdminIds)) == 0) {
			throw new Myshipserv_Exception_MessagedException("You don't have permissions to perform this action", 401);
		}

		$grantedAuthorisation = null;

		//lets see if this request really exists
		if ($request = Shipserv_MembershipAuthorisation::fetch($params["companyId"], $params["membershipId"], false)) {
			//and we do not have granted authorisation already (shouldn't happen usually)
			if ($auth = Shipserv_MembershipAuthorisation::fetch($params["companyId"], $params["membershipId"], true)) {
				//remove request if we already have authorisation
				$request->remove();
				
				// remove any alert from cache in-case any user of the company that approving the membership is looking
				Myshipserv_AlertManager::removeCompanyActionFromCache($params["companyId"], Myshipserv_AlertManager_Alert::ALERT_COMPANY_MEMBERSHIP, $params["membershipId"]);
			} else {
				$request->authorise();
				$grantedAuthorisation = $request;
			}
		} else {
			//request does not exist, but we may have granted authorisation already - check for it
			if (!$auth = Shipserv_MembershipAuthorisation::fetch($params["companyId"], $params["membershipId"], true)) {
				//ok, no request, no granted auth - then create authorisation
				$grantedAuthorisation = Shipserv_MembershipAuthorisation::create($params["companyId"], $params["membershipId"], true);
			}
		}

		//if any authorisations were granted - send message
		if (!is_null($grantedAuthorisation)) {
			//notify supplier about authorisations granted
			$notificationManager = new Myshipserv_NotificationManager($db);
			$notificationManager->membershipAuthorisationApproved(array($grantedAuthorisation));
		}
	}

	/**
	 * @return bool|Shipserv_User
	 * @throws Exception
	 */
    protected function getUser()
    {
		$user = Shipserv_User::isLoggedIn();
		if (!$user) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
		return $user;
	}
}
