<?php

class Myshipserv_NotificationManager_Email_ConfirmEmail extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $recipientUserId;
	
	public function __construct($db, $recipientUserId)
	{
		parent::__construct($db);
		$this->recipientUserId = $recipientUserId;
		$this->recipientUser = $this->getUser($recipientUserId);
	}
	
	
	public static function makeToken($userId, $unixTime)
	{
		$salt = 'sd0T3jgpDfib34';
		return md5("$salt-$userId-$unixTime");
	}
	
	/**
	 * @return array
	 */
	public function getRecipients()
	{
		$user = $this->getUser($this->recipientUserId);
		
		$row = array();
		$row['email'] = $user->email;
		$row['name'] = $user->email;
		
		return array($row);
	}
	
	
	public function getSubject ()
	{
		return 'Confirm your e-mail address';
	}
	
	
	public function getBody()
	{
	    $data = array('links' => array());
		$data['links']['confirm'] = $this->makeConfirmLink();
		
		$view = $this->getView();
		$view->data = $data;
		
		$res = $view->render('email/confirm-email.phtml');
		$recipientUser = $this->getUser($this->recipientUserId);
		return array($recipientUser->email => $res);
	}
	
	
	protected function makeConfirmLink()
	{		
		$params['u'] = $this->getUser($this->recipientUserId)->userId;
		$params['dt'] = time();
		$params['tok'] = self::makeToken($params['u'], $params['dt']);
		$relUrl = $this->makeLinkPath('confirm-email', 'user', null, $params);

		return 'https://' . $_SERVER['HTTP_HOST'] . $relUrl;

		// changed by Yuriy Akopov on 2015-12-18, DE6287, to support CAS login
		// commented out because UserController has been changed to use CAS automatically
		// in case if that results in some legacy behaviour broken, this can be uncommented for a less elegant fix
		/*
		$casUrl = Myshipserv_Config::getIni()->shipserv->services->sso->login->url;

		$casParams = http_build_query(array(
			'pageLayout' => 'new',
			'x' => 0,
			// @todo: legacy link was HTTP, but it needs to be HTTPS in environments where it is supported
			'service' => 'https://' . $_SERVER['HTTP_HOST'] . $relUrl
		));

		$finalUrl = $casUrl . '?' . $casParams;

		return $finalUrl;
		*/
	}
}
