<?php

class Myshipserv_UserCompany_AdminActions_Exception extends Exception
{
	// Tries to add user to company of which user is already a member
	const CODE_ADD_ALREADY_MEMBER = 100;
	
	// Tries to add self to company
	const CODE_ADD_SELF = 200;
}

/**
 * Provide user-company interactions that may be carried out by a company
 * administrator. Incorporates permission checking, access to data
 * and notification triggers.
 */
class Myshipserv_UserCompany_AdminActions
{
	// Values for company type parameters:
	// Supplier branch
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	
	// Buyer organisation
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;
	
	// DB adapter
	private $db;
	
	// Access to user data
	private $userDao;
	
	// Access to Pages-specific user-company associations
	private $userCompanyDao;
	
	// Access to company join request data
	private $userCompanyRequestDao;
	
	// Access to TN-specific user-company associations
	private $tnUserCompanyDao;
	
	// ID of logged-in user
	private $myUserId;
	
	/**
	 * @param object $db DB adapter
	 * @param int $myUserId ID of logged-in user
	 */
	public function __construct ($db, $myUserId)
	{
		$this->db = $db;
		$this->userDao = new Shipserv_Oracle_User($db);
		$this->userCompanyDao = new Shipserv_Oracle_PagesUserCompany($db);
		$this->userCompanyRequestDao = new Shipserv_Oracle_UserCompanyRequest($db);
		
		$this->myUserId = (int) $myUserId;
	}
	
	public static function forLoggedUser ()
	{
		static $uActions;
		
		if (!$uActions)
		{
			$user = Shipserv_User::isLoggedIn();
			if (!$user)
			{
				throw new Exception("No logged-in user");
			}
			
			$uActions = new self(
				$GLOBALS["application"]->getBootstrap()->getResource('db'),
				$user->userId);
		}
		
		return $uActions;
	}
	
	/**
	 * Add user to company.
	 *
	 * @return void
	 * @throws Shipserv_Oracle_User_Exception_FailCreateUser
	 */
	public function addUserToCompany($userEmail, $companyType, $companyId, $userLevel, $addedByUsername)
	{
		$loggedUser = Shipserv_User::isLoggedIn();

		// Requesting user must be an administrator of target company
		// Bypass permission violation if logged user is SS user
		if (!$this->isAdminOf($this->myUserId, $companyType, $companyId) && strstr($_SERVER['REQUEST_URI'], 'auto-registration') == false && ( isset($loggedUser) && $loggedUser->isShipservUser() == false ) ) {
			throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}
		
		// Fetch user for new member from DB, creating it if need be
		try
		{
			$user = $this->userDao->fetchUserByEmail($userEmail);				
			$newUserCreated = false;
		}
		catch (Shipserv_Oracle_User_Exception_NotFound $e)
		{

			$cUsrRes = $this->userDao->createUser(
				$userEmail,				
				'',			// First name
				'',			// Last name
				null,		// Auto generate new password
				'',			// Company name
				Shipserv_User::EMAIL_CONFIRMED_CONFIRMED,		// E-mail confirmed - pending first log-in
				'PAGES'		// Created by. Todo - may want to modify this?
			);
			$user = $this->userDao->fetchUserByEmail($userEmail, true);
			$newUserCreated = true;

			// Notify user that a new account has been created
			Myshipserv_NotificationManager::getInstance()->createUser(
				$user->userId,
				$cUsrRes['password']
			);
		}
		
		// Reflexive action not allowed
		if ($user->userId == $this->myUserId) {
			throw new Myshipserv_UserCompany_AdminActions_Exception("User cannot add self to company", Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_SELF);
		}
				
		// Ensure user is not already a member, or pending member
		$ucd = new Myshipserv_UserCompany_Domain($this->db);
		$userCompany = $ucd->fetchUserCompany($userEmail, $companyType, $companyId);
		
		
		if ($userCompany) {
			switch ($userCompany->getStatus()) {
				case 'ACT':
				case 'PEN':
					throw new Myshipserv_UserCompany_AdminActions_Exception(
						"User already member, or pending member",
						Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_ALREADY_MEMBER
					);
			}
		}
		
		// Determine membership status based on e-mail confirmation status of user
		// Forcing status of active for user that is coming from auto-registration
		if ($user->isEmailConfirmed() || strstr($_SERVER['REQUEST_URI'], 'auto-registration') !== false) {
			$ucStatus = 'ACT';
		} else {
			$ucStatus = 'PEN';
		}

		// Add user-company association
		$this->userCompanyDao->insertUserCompany(
			$user->userId,
			$companyType,
			$companyId,
			$userLevel,
			$ucStatus,			// Status: pending e-mail confirmation, or active
			true,				// Upsert (if an inactive row is present, it is updated correctly)
		    // Apps permissions
            ($companyType == 'BYO'? 1 : 0), 
            ($companyType == 'BYO'? 1 : 0), 
            ($companyType == 'BYO'? 1 : 0),
            ($companyType == 'BYO'? 1 : 0),
            ($companyType == 'BYO'? 1 : 0),
	        ($companyType == 'BYO'? 1 : 0),
	        ($companyType == 'BYO'? 1 : 0)
		); 
		
		// Notify user that he/she is new member of company
		Myshipserv_NotificationManager::getInstance()->addCompanyUser(
			$user->userId, $companyType, $companyId, $addedByUsername
		);
	}

	
	/**
	 * Remove user from company.
	 * 
	 * @return void
	 */
	public function removeUserFromCompany($userId, $companyType, $companyId)
	{
		//Requesting user must be an administrator of target company
		if (!$this->isAdminOf($this->myUserId, $companyType, $companyId)) {
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}

		//Flag the user-byo relationship as DEL
		$this->userCompanyDao->insertUserCompany(
            $userId, 
	        $companyType, 
	        $companyId, 
	        Shipserv_Oracle_PagesUserCompany::LEVEL_USER, 
	        Shipserv_Oracle_PagesUserCompany::STATUS_DELETED, 
	        true
        );
		
		//Flag the user-byb relationship as DEL
		if ($companyType === Myshipserv_UserCompany_Company::TYPE_BYO) {
		    foreach ((Array) Shipserv_Buyer::getInstanceById($companyId)->getBranchesTnid(true, true) as $bybId) {
		        $this->userCompanyDao->insertUserCompany(
	                $userId,
	                Myshipserv_UserCompany_Company::TYPE_BYB,
	                $bybId,
	                Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
	                Shipserv_Oracle_PagesUserCompany::STATUS_DELETED,
	                true //upsert
                );
		    }
		}	
		//TODO: send notification to $userId
	}
	
	
	/**
	 * Called by admin user to set admin status for user.
	 *
	 * @return void
	 */
	public function setUserAdminForCompany($userId, $companyType, $companyId, $isAdminBool)
	{
		// Reflexive action not allowed
		if ($userId == $this->myUserId)
		{
			throw new Exception("User cannot modify own admin status");
		}
		
		// Requesting user must be an administrator of target company
		if (! $this->isAdminOf($this->myUserId, $companyType, $companyId))
		{
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}
		
		// Do update
		$this->userCompanyDao->updateLevel($userId, $companyType, $companyId, $isAdminBool ? Shipserv_Oracle_PagesUserCompany::LEVEL_ADMIN : Shipserv_Oracle_PagesUserCompany::LEVEL_USER);
		
		// todo: send notification to $userId
	}
	
	/**
	 * Fetch a company join request by ID.
	 *
	 * @return array Associative array of columns in PAGES_USER_COMPANY_REQUEST table
	 */
	public function fetchJoinRequestById ($joinReqId)
	{		
		// Exception thrown if row not found
		$jReq = $this->userCompanyRequestDao->fetchRequestById($joinReqId);
		
		// Fail if requesting user is not administrator of target company
		if (! $this->isAdminOf($this->myUserId, $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID']))
		{
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}
		
		return $jReq;
	}
	
	/**
	 * Fetch company join requests for a company.
	 *
	 * @return Myshipserv_UserCompany_RequestCollection
	 */
	public function fetchJoinRequestsForCompany ($companyType, $companyId, $excludeShipservUsers = false)
	{
		// Fail if requesting user is not administrator of target company
		//if (! $this->isAdminOf($this->myUserId, $companyType, $companyId))
		//{
			//echo 'WARNING!!!!!!!!';
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		//}
		
		// Fetch JRs for company
		$jrArr = $this->userCompanyRequestDao->fetchRequestsForCompany($companyType, $companyId);
		
		// If need be, strip out JRs from users with '@shipserv' e-mail
		// turned off by force
		if (false && $excludeShipservUsers)
		{
			// Index JRs by user
			$uidArr = array();
			foreach ($jrArr as $jr)
			{
				$uidArr[$jr['PUCR_PSU_ID']][] = $jr;
			}
			
			// Fetch & filter users
			$uDao = new Shipserv_Oracle_User($this->db);
			foreach ($uDao->fetchUsers(array_keys($uidArr))->makeShipservUsers() as $u)
			{
				$em = strtolower(trim($u->email));
				if (strstr($em, '@shipserv.com') == '@shipserv.com') unset($uidArr[$u->userId]);
			}
			
			// Replace $jrArr
			$jrArr = array();
			foreach ($uidArr as $myJrArr) foreach ($myJrArr as $jr) $jrArr[] = $jr;
		}
		
		return new Myshipserv_UserCompany_RequestCollection($jrArr);
	}
	
	/**
	 * Approve / reject join request for company.
	 *
	 * @return void
	 */
	public function processJoinRequest($joinReqId, $boolApprove)
	{
		// Exception thrown if row not found
		$jReq = $this->userCompanyRequestDao->fetchRequestById($joinReqId);
		
		// Fail if requesting user is not administrator of target company
		if (!$this->isAdminOf($this->myUserId, $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID'])) {
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}
		
		// Change status of request to confirmed / rejected
		// Note: throws exception if request is not pending
		$this->userCompanyRequestDao->updateRequest(
	        $joinReqId,
			$boolApprove? Shipserv_Oracle_UserCompanyRequest::STATUS_CONFIRMED : Shipserv_Oracle_UserCompanyRequest::STATUS_REJECTED
        );
		
		// Add user as member of company
		// Use 'upsert' because a 'logically' deleted row may exist
		// Note: throws exception on failure
		$this->userCompanyDao->insertUserCompany(
	        $jReq['PUCR_PSU_ID'],
			$jReq['PUCR_COMPANY_TYPE'], 
	        $jReq['PUCR_COMPANY_ID'],
			Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
			$boolApprove? Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE : Shipserv_Oracle_PagesUserCompany::STATUS_DELETED,
			true
        );
		if ($jReq['PUCR_COMPANY_TYPE'] === Myshipserv_UserCompany_Company::TYPE_BYO) {
		    foreach ((Array) Shipserv_Buyer::getInstanceById($jReq['PUCR_COMPANY_ID'])->getBranchesTnid(true, true) as $bybId) {
		        $this->userCompanyDao->insertUserCompany(
	                $jReq['PUCR_PSU_ID'],
	                Myshipserv_UserCompany_Company::TYPE_BYB,
	                $bybId,
	                Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
	                $boolApprove? Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE : Shipserv_Oracle_PagesUserCompany::STATUS_DELETED,
	                true, //upsert
	                1, 1, 1, 1, 1, 1, 1, //permissions
	                0 //default
                );
		    }
		}
		
		//  [corporate/shared alert cache removal] remove any alert from cache in-case any user of the company that doing the action is looking
		Myshipserv_AlertManager::removeCompanyActionFromCache($jReq['PUCR_COMPANY_ID'], Myshipserv_AlertManager_Alert::ALERT_COMPANY_USER_JOIN, $jReq['PUCR_PSU_ID']);
			
		// Send notification
		$nm = new Myshipserv_NotificationManager($this->db);
		if ($boolApprove) {
		    $nm->grantCompanyMembership ($jReq['PUCR_PSU_ID'], $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID']);
		} else { 
		    $nm->declineCompanyMembership ($jReq['PUCR_PSU_ID'], $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID']);
		}
	}
		
	
	/**
	 * Fetch users for company & their association status.
	 *
	 * @return Myshipserv_UserCompany_UserCollection
	 */
	public function fetchUsersForCompany ($companyType, $companyId, $excludeShipservUsers = false)
	{

		// Fail if requesting user is not administrator of target company
		if (! $this->isAdminOf($this->myUserId, $companyType, $companyId))
		{
			//throw new Myshipserv_Exception_PermissionViolation("Requesting user is not authorised");
		}
		
		$ud = new Myshipserv_UserCompany_Domain($this->db);
		return $ud->fetchUsersForCompany($companyType, $companyId, $excludeShipservUsers);
	}
	
	/**
	 * Indicates if specified user is an administrator of given company.
	 * 
	 * @param int $userId user to test
	 * @return bool
	 */
	private function isAdminOf ($userId, $companyType, $companyId)
	{
		$user = Shipserv_User::isLoggedIn();
		if( $user !== false && $user->isShipservUser())
		{
			return true;
		}
		
		$userDao = new Shipserv_Oracle_User($this->db);
		$userObj = $userDao->fetchUserById($userId);
		$ua = new Myshipserv_UserCompany_Actions($this->db, $userObj->userId, $userObj->email);
		return $ua->amAdminOf($companyType, $companyId);
	}
	
	private function isMemberOf ($userId, $companyType, $companyId)
	{
		$uca = new Myshipserv_UserCompany_Actions($db, $userId);
		return $uca->amMemberOf($companyType, $companyId);
	}
}
