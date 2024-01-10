<?php

/**
 * Exception used by Myshipserv_UserCompany_Actions
 */
class Myshipserv_UserCompany_Actions_Exception extends Exception
{
	// Exception code: user tried to join company, but is already a member
	const EXC_JOIN_ALREADY_MEMBER = 100;
	
	// Exception code: user tried to join company, but already has an outstanding join request
	const EXC_JOIN_ALREADY_REQUESTED = 200;
	
	// User requested to join company, but does not have confirmed e-mail address
	const EXC_JOIN_EMAIL_UNCONFIRMED = 250;
	
	// User requested to join company, but company does not accept join requests
	const EXC_JOIN_NOT_JOIN_REQABLE = 275;
	
	// Exception code: user tried to leave company, but is not a member
	const EXC_LEAVE_NOT_MEMBER = 300;
	
	// Exception code: user tried to leave company, but is not a member
	const EXC_LEAVE_LAST_ADMIN = 400;
}

/**
 * Provide user-company interactions that may be carried out by a user.
 * Incorporates permission checking, access to data and notification triggers.
 */
class Myshipserv_UserCompany_Actions
{
	// Values for company type parameters:
	// Supplier branch
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	
	// Buyer organisation
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;

	// Buyer organisation
	const COMP_TYPE_BYB = Myshipserv_UserCompany_Company::TYPE_BYB;

    // Consortia company
    const COMP_TYPE_CON = Myshipserv_UserCompany_Company::TYPE_CON;
	
	// DB adapter
	private $db;
	
	// Access to Pages-specific user-company associations
	private $userCompanyDao;
	
	// Access to company join request data
	private $userCompanyRequestDao;
		
	// Access to TN-specific user-company associations
	private $tnUserCompanyDao;
	
	// ID of logged-in user
	private $myUserId;
	
	//Singleton instance
	private static $_instance;
	
	
	/**
	 * @param object $db DB connection
	 * @param int $myUserId ID of logged-in user
	 * @param string $myEmail DEPRECATED E-mail of logged-in user
	 */
	public function __construct ($db, $myUserId, $myEmail = null)
	{
		$this->db = $db;
		$this->userCompanyDao = new Shipserv_Oracle_PagesUserCompany($db);
		$this->userCompanyRequestDao = new Shipserv_Oracle_UserCompanyRequest($db);
		
		$this->myUserId = (int) $myUserId;
	}
	
	 
	/**
	 * Singleton function. Signature is different than the constructor because this getInstance method was created later (and got rid of useless params) 
	 *
	 * @param Int $myUserId
	 * @return Myshipserv_UserCompany_Actions
	 */
	public static function getInstance($myUserId)
	{
	    if (null === static::$_instance) {
	        static::$_instance = new static(Shipserv_Oracle::getDb(), $myUserId);
	    }
	    return static::$_instance;
	}
	
	
	
	/**
	 * Makes user's request to join company.
	 *
	 * @return void
	 */
	public function requestJoinCompany ($companyType, $companyId)
	{
		$companyType = (string) $companyType;
		$companyId = (int) $companyId;
		
		// Ensure target company accepts join requests
		if (!$this->isCompanyJoinReqable($companyType, $companyId))
		{
			throw new Myshipserv_UserCompany_Actions_Exception(
				"Company does not accept join requests: $companyType $companyId",
				Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_NOT_JOIN_REQABLE);
		}
		
		// Ensure not already a member
		if ($this->amMemberOf($companyType, $companyId))
		{
			throw new Myshipserv_UserCompany_Actions_Exception(
				"Already a member of company: $companyType $companyId",
				Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_ALREADY_MEMBER);
		}
		
		// Ensure requester's e-mail is confirmed
		$uDao = new Shipserv_Oracle_User($this->db);
		if (!$uDao->fetchUserById($this->myUserId)->isEmailConfirmed())
		{
			throw new Myshipserv_UserCompany_Actions_Exception(
				"Requesting user does not have confirmed e-mail",
				Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_EMAIL_UNCONFIRMED);
		}
		
		// Add entry to request table
		try
		{
			$reqId = $this->userCompanyRequestDao->addRequest($this->myUserId, $companyType, $companyId);
		}
		catch (Shipserv_Oracle_UserCompanyRequest_Exception $e)
		{
			if ($e->getCode() == Shipserv_Oracle_UserCompanyRequest_Exception::ADD_ALREADY_EXISTS)
			{
				throw new Myshipserv_UserCompany_Actions_Exception("Join request for company already exists",
					Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_ALREADY_REQUESTED);
			}
			else
			{
				throw $e;
			}
		}
		
		// Send notification
		$nm = new Myshipserv_NotificationManager($this->db);
		$nm->requestCompanyMembership($reqId, $this->myUserId);
		
		return $reqId;
	}
	
	private function isCompanyJoinReqable ($companyType, $companyId)
	{
		$pco = Shipserv_Oracle_PagesCompany::getInstance()->fetchById($companyType, $companyId);
		return $pco['PCO_IS_JOIN_REQUESTABLE'] == 'Y';
	}
	
	/**
	 * Withdraws user's outstanding request to join company.
	 *
	 * @param int $reqId Join-request ID
	 * @return void
	 */
	public function withdrawJoinCompanyRequest ($reqId)
	{
		// Fails with exception if request doesn't exist
		$req = $this->userCompanyRequestDao->fetchRequestById($reqId);
		
		// Check user owns this request
		if ($req['PUCR_PSU_ID'] != $this->myUserId)
		{
			throw new Myshipserv_Exception_PermissionViolation("User not authorised");
		}
		
		try
		{
			$this->userCompanyRequestDao->updateRequest($reqId,
				Shipserv_Oracle_UserCompanyRequest::STATUS_WITHDRAWN);
		}
		catch (Exception $e)
		{
			throw $e;
		}
	}
	

	/**
	 * Leave company of which user is a member.
	 *
	 * @param Myshipserv_UserCompany_Company::TYPE_SPB|Myshipserv_UserCompany_Company::TYPE_BYO|Myshipserv_UserCompany_Company::TYPE_BYB $companyType
	 * @param Int $companyId
	 * @return void
	 */
	public function leaveCompany($companyType, $companyId)
	{
		$companyType = (string) $companyType;
		$companyId = (int) $companyId;
		
		// Ensure currently a member
		if (! $this->amMemberOf ($companyType, $companyId)) {
			throw new Myshipserv_UserCompany_Actions_Exception(
				"Not a member of company: $companyType $companyId",
				Myshipserv_UserCompany_Actions_Exception::EXC_LEAVE_NOT_MEMBER
	        );
		}

		// Ensure not the last admin member of company
		if ($this->amAdminOf($companyType, $companyId)) {
			$lastAdminEx = new Myshipserv_UserCompany_Actions_Exception('', Myshipserv_UserCompany_Actions_Exception::EXC_LEAVE_LAST_ADMIN);
			$ucDom = new Myshipserv_UserCompany_Domain($this->db);
			$uColl = $ucDom->fetchUsersForCompany($companyType, $companyId);
			foreach ($uColl->getAdminUsers() as $u) {
				// If any admin user is present that is not me, cancel the exception
				if ($u->userId != $this->myUserId) {
					$lastAdminEx = null;
					break;
				}
			}
			if ($lastAdminEx) {
			    throw $lastAdminEx;
			}
		}
		
		try
		{
			$this->userCompanyDao->insertUserCompany(
                $this->myUserId,
				$companyType, 
		        $companyId,
				Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
				Shipserv_Oracle_PagesUserCompany::STATUS_DELETED, 
		        true
	        );
			if ($companyType === Myshipserv_UserCompany_Company::TYPE_BYO) {
			    foreach ((Array) Shipserv_Buyer::getInstanceById($companyId)->getBranchesTnid(true, false, true) as $bybId) {
			        $this->userCompanyDao->insertUserCompany(
		                $this->myUserId,
		                Myshipserv_UserCompany_Company::TYPE_BYB,
		                $bybId,
		                Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
		                Shipserv_Oracle_PagesUserCompany::STATUS_DELETED,
		                true //upsert
	                );
			    }
			}
		}
		catch (Exception $e)
		{
			throw $e;
		}
	}
	
	
	/**
	 * Fetch user's companies.
	 * 
	 * @return Myshipserv_UserCompany_CompanyCollection
	 */
	public function fetchMyCompanies()
	{		
		$myCompanies = array();
		
		// Fetch Pages-specific company associations & overlay any association
		// already present from TN.
		foreach ($this->userCompanyDao->fetchCompaniesForUser($this->myUserId) as $pCompany) {			
			$pCompanyObj = new Myshipserv_UserCompany_Company(
				$pCompany['PUC_COMPANY_TYPE'], 
		        $pCompany['PUC_COMPANY_ID'], 
		        $pCompany['PUC_STATUS'], 
		        $pCompany['PUC_LEVEL'], 
		        $pCompany['PUC_IS_DEFAULT'], 
		        $pCompany['PUC_TXNMON'], 
		        $pCompany['PUC_WEBREPORTER'], 
		        $pCompany['PUC_MATCH'], 
		        $pCompany['PUC_BUY'], 
		        $pCompany['PUC_APPROVED_SUPPLIER'], 
		        $pCompany['PUC_TXNMON_ADM'], 
		        $pCompany['PUC_AUTOREMINDER'], 
		        $pCompany['CCF_RFQ_DEADLINE_CONTROL']
			);
			$myCompanies[] = $pCompanyObj;
		}

		return new Myshipserv_UserCompany_CompanyCollection($myCompanies);
	}
	
	/**
	 * Fetch user's requested companies.
	 *
	 * @return Myshipserv_UserCompany_RequestCollection
	 */
	public function fetchMyRequestedCompanies ()
	{
		return new Myshipserv_UserCompany_RequestCollection(
			$this->userCompanyRequestDao->fetchRequestsForUser($this->myUserId));
	}
	
	/**
	 * Tests whether user is a member of company.
	 * 
	 * @return bool
	 */
	public function amMemberOf ($companyType, $companyId)
	{
		// Fetch all companies for requesting user
		$myCompaniesColl = $this->fetchMyCompanies();
		
		// Pull out array of buyer or supplier IDs to which user belongs
		if ($companyType == self::COMP_TYPE_BYO) $myCompaniesArr = $myCompaniesColl->getBuyerIds();
		elseif ($companyType == self::COMP_TYPE_SPB) $myCompaniesArr = $myCompaniesColl->getSupplierIds();
        elseif ($companyType == self::COMP_TYPE_CON) $myCompaniesArr = $myCompaniesColl->getConsortiaIds();
		else throw new Exception("Unrecognised company type: '$companyType'");
		
		// Loop on IDs and return true if there is a match
		foreach ($myCompaniesArr as $thisCompanyId) if ($thisCompanyId == $companyId) return true;
		return false;
	}
	
	/**
	 * Tests whether user is an administrator of company.
	 *
	 * @return bool
	 */
	public function amAdminOf ($companyType, $companyId)
	{

		// Fetch all companies for requesting user
		$myCompaniesColl = $this->fetchMyCompanies();
		
		// Pull out array of buyer or supplier IDs for which user is an administrator
		$myCompaniesColl->getAdminIds($buyerIds, $supplierIds, $consortiaIds);
		if ($companyType == self::COMP_TYPE_BYO) $myCompaniesArr = $buyerIds;
		elseif ($companyType == self::COMP_TYPE_SPB) $myCompaniesArr = $supplierIds;
        elseif ($companyType == self::COMP_TYPE_CON) $myCompaniesArr = $consortiaIds;
		else throw new Exception("Unrecognised company type: '$companyType'");
		
		// Loop on IDs and return true if there is a match
		foreach ($myCompaniesArr as $thisCompanyId) if ($thisCompanyId == $companyId) return true;
		return false;
	}
}
