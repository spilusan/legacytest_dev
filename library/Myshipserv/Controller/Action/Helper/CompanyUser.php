<?php

class Myshipserv_Controller_Action_Helper_CompanyUser extends Zend_Controller_Action_Helper_Abstract
{
	public function removeUser($companyType, $companyId, $userId)
	{
		$uaActions = $this->getAdminActions();
		$uaActions->removeUserFromCompany($userId, $companyType, $companyId);
	}
	
	/**
	 * Fetch users in 3 buckets:
	 *  $pendingUsers - users waiting on join request
	 *  $approvedUsers - members
	 *  $pendingEmailConfUsers - members in pending state (awaiting e-mail confirmation)
	 */
	public function fetchUsers($companyType, $companyId, &$pendingUsers, &$approvedUsers, &$pendingEmailConfUsers = array())
	{
		$pendingUsers = $this->makePendingUsers($companyType, $companyId);
		$apRes = $this->makeApprovedPendingUsers($companyType, $companyId);
		$approvedUsers = $apRes['approved'];
		$pendingEmailConfUsers = $apRes['pending'];
	}
	
	/**
	 * Fetch users in 2 buckets:
	 *  'approved' - active members, or members in pending state (awaiting e-mail confirmation)
	 *  'pending' - users waiting on join request
	 */
	public function fetchUsersPenEmlAsAppr($companyType, $companyId)
	{
		$pendingUsers = $approvedUsers = $pendingEmailConfUsers = array();
		$this->fetchUsers($companyType, $companyId, $pendingUsers, $approvedUsers, $pendingEmailConfUsers);
		
		$combinedArr = $pendingEmailConfUsers;
		foreach ($approvedUsers as $k => $v)
		{
			$combinedArr[$k] = $v;
		}
		uasort($combinedArr, array($this, 'sortUsers'));
		
		return array(
			'approved' => $combinedArr,
			'pending' => $pendingUsers,
		);
	}

	public function fetchUsersByType($companyType, $companyId)
	{
		$pendingUsers = $approvedUsers = $pendingEmailConfUsers = array();
		$this->fetchUsers($companyType, $companyId, $pendingUsers, $approvedUsers, $pendingEmailConfUsers);
		return array(
			'approved' => $approvedUsers,
			'pending' => $pendingUsers,
			'pendingConfirmation' => $pendingEmailConfUsers
		);
	}
	
	public function approveJoinRequest($requestId)
	{
		$uaActions = $this->getAdminActions();
		$uaActions->processJoinRequest($requestId, true);
		
		$jReq = $uaActions->fetchJoinRequestById($requestId);
		$user = $this->fetchUser($jReq['PUCR_PSU_ID']);
		
		$uArr = $this->makeUser($user);
		$uArr['id'] = $user->userId;
		$uArr['roles'] = array('administrator' => false);
		
		$uArr['companyType'] = self::companyTypeToUi($jReq['PUCR_COMPANY_TYPE']);
		$uArr['companyId'] = $jReq['PUCR_COMPANY_ID'];
		
		// [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
		Myshipserv_AlertManager::removeCompanyActionFromCache($jReq['PUCR_COMPANY_ID'], Myshipserv_AlertManager_Alert::ALERT_COMPANY_USER_JOIN, $jReq['PUCR_PSU_ID']);
		
		return $uArr;
	}
	
	private static function companyTypeToUi($dbType)
	{
		static $typeMap = array('SPB' => 'v', 'BYO' => 'b');
		if (array_key_exists($dbType, $typeMap))
		{
			return $typeMap[$dbType];
		}
		throw new Exception("Unrecognised company type: '$dbType'");
	}
	
	public function rejectJoinRequest($requestId)
	{
		$uaActions = $this->getAdminActions();
		$uaActions->processJoinRequest($requestId, false);
	}
	
	private function fetchUser($userId)
	{
		$uDao = new Shipserv_Oracle_User($this->getDb());
		$userColl = $uDao->fetchUsers(array($userId));
		$userArr = $userColl->makeShipservUsers();
		if (! @$userArr[0]) throw new Exception('a');
		return $userArr[0];
	}
	
	/**
	 * Fetch users with pending join requests.
	 */
	private function makePendingUsers($companyType, $companyId)
	{
		$uaActions = $this->getAdminActions();
		$reqColl = $uaActions->fetchJoinRequestsForCompany($companyType, $companyId, !$this->isLoggedInUserShipserv());
		
		$pReqsByUserId = array();
		foreach ($reqColl->getPendingRequests() as $pReq) $pReqsByUserId[$pReq['PUCR_PSU_ID']] = $pReq;
		
		$uDao = new Shipserv_Oracle_User($this->getDb());
		$pendingUserColl = $uDao->fetchUsers(array_keys($pReqsByUserId));
		$pendingUsers = $this->makeUsers($pendingUserColl->makeShipservUsers());
		
		foreach ($pendingUsers as $uId => $u)
		{
			$pendingUsers[$uId]['joinRequest'] = $pReqsByUserId[$uId];
		}
		return $pendingUsers;
	}
	
	private function isLoggedInUserShipserv ()
	{
		return $this->getUser()->isShipservUser();
	}
	
	/**
	 * Fetch approved users and users pending e-mail confirmation.
	 */
	private function makeApprovedPendingUsers ($companyType, $companyId)
	{

		$uaActions = $this->getAdminActions();
		$userColl = $uaActions->fetchUsersForCompany($companyType, $companyId, !$this->isLoggedInUserShipserv());
		$approvedUsers = $this->makeUsers($userColl->getActiveUsers());
		$adminIds = array();
		foreach ($userColl->getAdminUsers() as $thisUser) $adminIds[] = $thisUser->userId;
		foreach ($approvedUsers as $uId => $u)
		{
			if (in_array($uId, $adminIds)) $approvedUsers[$uId]['roles'] = array('administrator' => true);
			else $approvedUsers[$uId]['roles'] = array('administrator' => false);
		}
		
		$pendingUsers = $this->makeUsers($userColl->getPendingUsers());
		$pAdminIds = array();
		foreach ($userColl->getPendingAdminUsers() as $thisUser) $pAdminIds[] = $thisUser->userId;
		foreach ($pendingUsers as $uId => $u)
		{
			if (in_array($uId, $pAdminIds)) $pendingUsers[$uId]['roles'] = array('administrator' => true);
			else $pendingUsers[$uId]['roles'] = array('administrator' => false);
		}
		
		return array('approved' => $approvedUsers, 'pending' => $pendingUsers);
	}
	
	private function makeUsers (array $users)
	{
		$resArr = array();
		foreach ($users as $u)
		{
			$resArr[$u->userId] = $this->makeUser($u);
		}
		uasort($resArr, array($this, 'sortUsers'));
		return $resArr;
	}
	
	private function sortUsers ($a, $b)
	{
		$aSort = strtolower(trim($a['lastName']) . ' ' . trim($a['firstName']));
		$bSort = strtolower(trim($b['lastName']) . ' ' . trim($b['firstName']));
		if (trim($a['lastName']) == '') $aSort = ord(255);
		if (trim($b['lastName']) == '') $bSort = ord(255);
		return $aSort == $bSort ? 0 : $aSort > $bSort;
	}
	
	private function makeUser ($user)
	{
		$res = array(
			'username'    => $user->username,
			'firstName'   => $user->firstName,
			'lastName'    => $user->lastName,
			'email'       => $user->email,
			// added by Yuriy Akopov on 2015-12-18, DE6288
			'emailConfirmed' => $user->emailConfirmed
		);
		
		$res['fullName'] = ($res['firstName'] || $res['lastName']) ? $res['firstName'].' '.$res['lastName'] : 'Unknown Name';
		
		return $res;
	}
	
	private function getAdminActions ()
	{
		$user = $this->getUser();
		return new Myshipserv_UserCompany_AdminActions($this->getDb(), $user->userId);
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
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
}
