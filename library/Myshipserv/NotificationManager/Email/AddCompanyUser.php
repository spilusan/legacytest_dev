<?php

class Myshipserv_NotificationManager_Email_AddCompanyUser extends Myshipserv_NotificationManager_Email_Abstract
{
	private $newMemberUserId;
	private $companyType;
	private $companyId;
	
	
	public function __construct($db, $newMemberUserId, $companyType, $companyId, $addedByUsername)
	{
		parent::__construct($db);
		
		$this->newMemberUser = $this->getUser($newMemberUserId);
		$this->newMemberUserId = $newMemberUserId;
		$this->companyId = $companyId;
		$this->companyType = $companyType;
		$this->addedByUsername = $addedByUsername;
	}
	
	/**
	 * @return array
	 */
	public function getRecipients()
	{
		$user = $this->getUser($this->newMemberUserId);
		
		$row = array();
		$row['email'] = $this->newMemberUser->email;
		$row['name'] = $this->newMemberUser->email;
		
		return array($row);
	}
	
	
	public function getSubject()
	{
		return 'New company membership';
	}
	
	
	public function getBody()
	{
		$recipientUser = $this->getUser($this->newMemberUserId);
		
		$data['company'] = $this->getCompany($this->companyType, $this->companyId, true);
		$data['links']['companyUsers'] = $this->makeCompanyLink($recipientUser);
		$data['username'] = $this->newMemberUser->username;
		$data['addedby'] = $this->addedByUsername;
		
		$view = $this->getView();
		$view->data = $data;
		
		$res = $view->render('email/add-company-user.phtml');
		return array($recipientUser->email => $res);
	}
}
