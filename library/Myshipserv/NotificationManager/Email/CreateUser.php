<?php

class Myshipserv_NotificationManager_Email_CreateUser extends Myshipserv_NotificationManager_Email_Abstract
{
	private $newUserId;
	private $password;
	
	public function __construct ($db, $newUserId, $password)
	{
		parent::__construct($db);
		
		$this->newUserId = $newUserId;
		$this->password = $password;
	}
	
	/**
	 * @return array
	 */
	public function getRecipients ()
	{
		$user = $this->getUser($this->newUserId);
		
		$row = array();
		$row['email'] = $user->email;
		$row['name'] = $user->email;
		
		return array($row);
	}
	
	public function getSubject ()
	{
		return 'Welcome to ShipServ Pages';
	}
	
	public function getBody ()
	{
		$data['user'] = $recipientUser = $this->getUser($this->newUserId);
		$data['password'] = $this->password;
		
		$data['links']['homepage'] = 'https://' . $_SERVER['HTTP_HOST'] . '/search/';
		$data['links']['signinpage'] = $this->getUrlToCasLogin();
		
		$view = $this->getView();
		$view->data = $data;
		
		$res = $view->render('email/create-user.phtml');

		return array($recipientUser->email => $res);
	}

	protected function getUrlToCasLogin($ssl = false)
    {
    	$config  = Zend_Registry::get('config');
    	$hostname = $config->shipserv->application->hostname;
    	$r = ( $ssl == true ) ? 'https://' . $hostname . '/search' : 'https://' . $hostname . '/search';
    	$helper = new Myshipserv_View_Helper_Cas;
		$url = $helper->getPagesLogin() . urlencode($r);
		return $url;
    }
}
