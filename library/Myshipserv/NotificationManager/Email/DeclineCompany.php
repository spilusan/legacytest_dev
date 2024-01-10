<?php

class Myshipserv_NotificationManager_Email_DeclineCompany extends Myshipserv_NotificationManager_Email_Abstract
{
	private $recipientUserId;
	private $companyType;
	private $companyId;
	
	public function __construct ($db, $recipientUserId, $companyType, $companyId)
	{
		parent::__construct($db);
		
		// todo: validate
		$this->recipientUserId = $recipientUserId;
		
		// todo: validate type
		$this->companyType = $companyType;
		
		// todo: validate id
		$this->companyId = $companyId;
	}
	
	/**
	 * @return array
	 */
	public function getRecipients ()
	{
		$user = $this->getUser($this->recipientUserId);
		
		$row = array();
		$row['email'] = $user->email;
		$row['name'] = $user->email;
		
		return array($row);
	}
	
	public function getSubject ()
	{
		return 'Company membership request declined';
	}
	
	public function getBody ()
	{
		$recipientUser = $this->getUser($this->recipientUserId);
		
		$data['company'] = $this->getCompany($this->companyType, $this->companyId, true);
		$data['links']['companyUsers'] = $this->makeCompanyLink($recipientUser);
		
		$view = $this->getView();
		$view->data = $data;
		
		$res = $view->render('email/decline-company.phtml');
		return array($recipientUser->email => $res);
	}
}
