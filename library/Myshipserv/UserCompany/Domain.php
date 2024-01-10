<?php

/**
 * Exposes access to user-company associations (pulled & merged from TN & Pages).
 */
class Myshipserv_UserCompany_Domain
{
	// Values for company type
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;
	
	// DB adapter
	private $db;
	
	// Access to user data
	private $userDao;
	
	// Access to Pages-specific user-company associations
	private $userCompanyDao;
	
	// Access to TradeNet-specific user-company associations
	private $tnUserCompanyDao;
	
	/**
	 * @param int $myUserId ID of logged-in user
	 */
	public function __construct ($db)
	{
		$this->db = $db;
		$this->userDao = new Shipserv_Oracle_User($db);
		$this->userCompanyDao = new Shipserv_Oracle_PagesUserCompany($db);
	}
	
	/**
	 * Fetches users for a company.
	 * The implementation fetches user-company associations from TradeNet
	 * and then adds / overlays associations from Pages.
     *
     * By default the data will be memory cached for the time of loading the page,
     * $forceReread can disable it, when we need to run the function after database update again, like adding new user
     *
	 * @param string $companyType
     * @param inteter $companyId
     * @param boolean $excludeShipservUsers
     * @param boolier $forceReread
     *
	 * @return Myshipserv_UserCompany_UserCollection
	 */
	public function fetchUsersForCompany($companyType, $companyId, $excludeShipservUsers = false, $forceReread = false)
	{		
		$usersById = array();
		
		// Fetch user-company assocations from PAGES_USER_COMPANY
		$pucUsers = $this->userCompanyDao->fetchUsersForCompany($companyType, $companyId, $forceReread);
		
		$pucUsersById = array();
		foreach ($pucUsers as $u)
		{
			$pucUsersById[$u['PUC_PSU_ID']] = $u;
		}
		
		// Fetch user info from USERS & PAGES_USER
		$puColl = $this->userDao->fetchUsers(array_keys($pucUsersById));
		
		foreach ($puColl->makeShipservUsers() as $u)
		{
			$usersById[$u->userId] = new Myshipserv_UserCompany_User($u, $pucUsersById[$u->userId]['PUC_STATUS'], $pucUsersById[$u->userId]['PUC_LEVEL']);
		}
		
		
		foreach ($puColl->getInactiveUsers() as $u)
		{
			unset($usersById[$u->userId]);
		}
		
		
		// Exclude users with e-mail ending '@shipserv.com'
		// Commented out as a result of improvement to user-company model:
		// Shipserv e-mails are not imported by the tnImporter process.
		//if ($excludeShipservUsers) foreach ($usersById as $k => $u)
		//{
		//	$em = strtolower(trim($u->email));
		//	if (strstr($em, '@shipserv.com') == '@shipserv.com') unset($usersById[$k]);
		//}
		
		$resUsers = new Myshipserv_UserCompany_UserCollection(array_values($usersById));
		return $resUsers;
	}
	
	/**
	 * Note: this may return an inactive/deleted user.
	 * 
	 * @return Myshipserv_UserCompany_User or null
	 */
	public function fetchUserCompany ($userEmail, $companyType, $companyId, $forceReRead = false)
	{
		$normUserEmail = strtolower(trim($userEmail));
		$ucColl = $this->fetchUsersForCompany($companyType, $companyId, false, $forceReRead);
		foreach ($ucColl->getAllUsers() as $uc)
		{
			if (strtolower(trim($uc->email)) == $normUserEmail)
			{
				return $uc;
			}
		}
	}
}
