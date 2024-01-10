<?php

class Myshipserv_NotificationManager_Email_PeriodicReviewReq extends Myshipserv_NotificationManager_Email_Abstract
{
	private $user;
	private $reviewReqs;
	
	public function __construct ($recipientId)
	{
		parent::__construct(self::getDb());
		
		$uDao = new Shipserv_Oracle_User(self::getDb());
		$this->user = $uDao->fetchUserById($recipientId);
		
		$myRRs = self::fetchReviewRequests($this->user);
		if (!$myRRs)
		{
			throw new Myshipserv_NotificationManager_Exception("No pending review requests for user", Myshipserv_NotificationManager_Exception::RR_NONE_PENDING);
		}
		$this->reviewReqs = $myRRs;
	}
	
	private static function fetchReviewRequests (Shipserv_User $user)
	{
		$nEmail = self::normEmail($user->email);
		$myRRs = Shipserv_ReviewRequest::getRequestsByEmails(array($nEmail));
		foreach ($myRRs as $emKey => $rrArr)
		{
			if (self::normEmail($emKey) == $nEmail)
			{
				return $rrArr;
			}
		}
		
		throw new Exception("Expected email key not found");
	}
	
	private static function normEmail ($em)
	{
		return strtolower(trim($em));
	}
	
	private static function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	public function getRecipients ()
	{		
		$row['email'] = $this->user->email;
		$row['name'] = $this->user->email;
		
		return array($row);
	}
	
	public function getSubject ()
	{
		return 'New review requests';
	}
	
	public function getBody ()
	{
		$data['revReqs'] = $this->reviewReqs;
		$data['links']['reviews'] = $this->makeReviewsLink();
		
		$view = $this->getView();
		$view->data = $data;
		
		$res = $view->render('email/periodic-review-req.phtml');
		return array($this->user->email => $res);
	}
		
	private function makeReviewsLink ()
	{
		$params = array('u' => $this->user->userId);
		$ru = $this->makeLinkPath('review-requests', 'profile', null, $params);
		return 'https://' . $_SERVER['HTTP_HOST'] . $ru;
	}
}
