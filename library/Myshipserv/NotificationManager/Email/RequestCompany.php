<?php

/**
 * Represents e-mails to company admins on join request (which it does by proxying
 * to Myshipserv_NotificationManager_Email_RequestCompanyImpl. If there are no admins
 * for company, represents an e-mail to support instead.
 */
class Myshipserv_NotificationManager_Email_RequestCompany extends Myshipserv_NotificationManager_Email_Abstract
{
	const ITS_EMAIL = 'info@shipserv.com';
	
	private $joinReq;
	private $companyType;
	private $companyId;
	private $userId;
	
	// Email obj, or null
	// If this obj is present, this object acts as a proxy.
	private $email;
	
	public function __construct ($db, $joinReqId, $userId = null)
	{
		parent::__construct($db);
		$this->joinReq = self::getJoinRequest($db, $joinReqId);
		$this->companyType = $this->joinReq['PUCR_COMPANY_TYPE'];
		$this->companyId = $this->joinReq['PUCR_COMPANY_ID'];
		$this->userId = $userId;
		
		// If the admin email has recipients, use it.
		// Otherwise, leave $this->email null.
		$myEmail = new Myshipserv_NotificationManager_Email_RequestCompanyImpl($db, $this->joinReq['PUCR_COMPANY_TYPE'], $this->joinReq['PUCR_COMPANY_ID'], $this->userId);
		if ($myEmail->getRecipients()) $this->email = $myEmail;
	}
	
	private static function getJoinRequest ($db, $joinReqId)
	{
		$userCompanyRequestDao = new Shipserv_Oracle_UserCompanyRequest($db);
		return $userCompanyRequestDao->fetchRequestById($joinReqId);
	}
	
	public function getRecipients ()
	{
		if ($this->email) return $this->email->getRecipients();
		
		// If no admin email, email support
		return array(array('name' => self::ITS_EMAIL, 'email' => self::ITS_EMAIL));
	}
	
	public function getSubject ()
	{
		if ($this->email) return $this->email->getSubject();
		
		// If no admin email, email support
		return 'Pages join company request: no administrator';
	}
	
	public function getBody ()
	{
		if ($this->email) return $this->email->getBody();
		
		// If no admin email, email support ...
		
		// Fetch e-mail template
		$view = $this->getView();
		
		// Fetch standard view data
		$vData = Myshipserv_NotificationManager_Email_RequestViewHelper::toArr($this->getRequester(), $this->getCompany($this->companyType, $this->companyId, true), 'NOT APPLICABLE');
		
		// Add admin links to view data
		$adminLinkArr = $this->makeAdminLinks();
		$vData['links']['superApprove'] = $adminLinkArr['approve'];
		$vData['links']['superApproveAdmin'] = $adminLinkArr['approveAdmin'];
		$vData['links']['superDecline'] = $adminLinkArr['decline'];
		
		// Render view and return
		$view->data = $vData;
		return array(self::ITS_EMAIL => $view->render('email/request-company-noadmin.phtml'));
	}
	
	/**
	 * @return array
	 */
	private function makeAdminLinks ()
	{
		$params = array('reqId' => $this->joinReq['PUCR_ID'], 'req-action' => 'approve');
		$relUrlArr['approve'] = $this->makeLinkPath('process', 'join-req', 'ssadmin', $params);
		
		$params = array('reqId' => $this->joinReq['PUCR_ID'], 'req-action' => 'approve-admin');
		$relUrlArr['approveAdmin'] = $this->makeLinkPath('process', 'join-req', 'ssadmin', $params);
		
		$params = array('reqId' => $this->joinReq['PUCR_ID'], 'req-action' => 'decline');
		$relUrlArr['decline'] = $this->makeLinkPath('process', 'join-req', 'ssadmin', $params);
		
		$urlArr = array();
		foreach ($relUrlArr as $k => $ru) $urlArr[$k] = 'https://' . $_SERVER['HTTP_HOST'] . $ru;
		return $urlArr;
	}
	
	private function getRequester ()
	{

		if (is_null($this->userId))
		{
			$user = Shipserv_User::isLoggedIn();
			if (!$user)
			{
				Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
			}
		}
		else
		{
			$userDao = new Shipserv_Oracle_User($this->db);
			$user = $userDao->fetchUserById($this->userId);
		}
		
		return $user;
	}
}

/**
 * Represents e-mails to company admins on join request.
 */
class Myshipserv_NotificationManager_Email_RequestCompanyImpl extends Myshipserv_NotificationManager_Email_Abstract
{
	private $companyType;
	private $companyId;
	private $userId;
	
	public function __construct ($db, $companyType, $companyId, $userId = null)
	{
		parent::__construct($db);
		
		// todo: validate type
		$this->companyType = $companyType;
		
		// todo: validate id
		$this->companyId = $companyId;

		$this->userId = $userId;
	}
	
	public function getRecipients ()
	{		
		$res = array();
		foreach ($this->getRecipientUsers() as $u)
		{
			$row = array();
			$row['email'] = $u->email;
			$row['name'] = $u->email;
			
			$res[] = $row;
		}
		
		return $res;
	}
	
	public function getSubject ()
	{
		return 'Request to join company';
	}
	
	public function getBody ()
	{
		$res = array();
		foreach ($this->getRecipientUsers() as $u)
		{
			$res[$u->email] = $this->getBodyForUser($u);
		}
		return $res;
	}
	
	private function getBodyForUser ($user)
	{		
		$view = $this->getView();
		$view->data = Myshipserv_NotificationManager_Email_RequestViewHelper::toArr($this->getRequester(), $this->getCompany($this->companyType, $this->companyId, true), $this->makeCompanyUserLink($user));
		
		$res = $view->render('email/request-company.phtml');
		return $res;
	}
	
	private function getRecipientUsers ()
	{
		$ucDom = $this->getUserCompanyDomain();
		$uColl = $ucDom->fetchUsersForCompany($this->companyType, $this->companyId);
		return $uColl->getAdminUsers();
	}
	
	private function getRequester ()
	{
		if (is_null($this->userId))
		{
			$user = Shipserv_User::isLoggedIn();
			if (!$user)
			{
				Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
			}
		}
		else
		{
			$userDao = new Shipserv_Oracle_User($this->db);
			$user = $userDao->fetchUserById($this->userId);
		}
		
		return $user;
	}
	
	private function makeCompanyUserLink ($user)
	{
		$relUrl = $this->makeLinkPath('company-people', 'profile', null, array(
			'type' => $this->companyTypeMap[$this->companyType],
			'id' => $this->companyId,
			'u' => $user->userId));
		return 'https://' . $_SERVER['HTTP_HOST'] . $relUrl;
	}
	
	private function getUserCompanyDomain ()
	{
		return new Myshipserv_UserCompany_Domain($this->db);
	}
}

/**
 * Creates view array for populating e-mail template.
 */
class Myshipserv_NotificationManager_Email_RequestViewHelper
{
	private function __construct () { }
	
	public static function toArr ($requester, $company, $cuLink)
	{
		$data['requester']['email'] = $requester->email;
		if ($requester->firstName != '' && $requester->lastName != '') $data['requester']['name'] = array('firstName' => $requester->firstName, 'lastName' => $requester->lastName);
		
		$data['company'] = $company;
		
		$data['links']['companyUsers'] = $cuLink;
		
		return $data;
	}
}
