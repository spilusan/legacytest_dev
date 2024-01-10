<?php
/**
 * Controller for handling autologin token
 *
 * @package myshipserv
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class TokenController extends Myshipserv_Controller_Action
{
	public function init()
	{
		parent::init();
	}
	
	
	/**
	 * this bit of function will check the token and making sure that the token is valid.
	 * if it is valid, it will then automatically log the user in. This function is using
	 *  Myshipserv_AutoLoginToken model
	 */
	public function checkAction()
	{
		
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->_getAllParams();
		
		// pull the token information
		$tokenAdapter = new Myshipserv_AutoLoginToken($db);
		$token = $tokenAdapter->checkToken($params["t"]);

		// logout current user
        $user = Shipserv_User::isLoggedIn();
        if (is_object($user))
		{
			$user->logout();
		}
	
		// check token
		if( $token !== false )
		{
			// log user automatically
			$userAdaptor = new Shipserv_Oracle_User($db);
			
			if( $token["PAT_PSU_ID"] != "" )
			{
				// get user
				try {
					$user = $userAdaptor->fetchUserById($token["PAT_PSU_ID"]);
				}
				catch (Exception $exception)
				{
					$user = null;
				}
				
				// get user's password
				$userAdaptor->fetchPagesUserByUsername($user->username, $password);
	
				if (is_object($user))
				{
					// if found set layout to empty, and redirect user automatically
					$this->_helper->layout->setLayout('empty');
					
					//login user
					$user = Shipserv_User::login($user->username, $password, false);
	
					// send response in case we're going to use a flash messenger
					$this->view->response =  "logging you in as " . $user->username . " and redirecting you to: " . $token["PAT_URL_REDIRECT"];
					
					// redirect user
					$this->redirect($token["PAT_URL_REDIRECT"]);
					
				}
				else
				{
					throw new Myshipserv_Exception_MessagedException("This link is no longer valid, user cannot be found on this link");
				}
			}
			else 
			{
				$this->redirect($token["PAT_URL_REDIRECT"]);
			}
		}
		
		// if token is not found
		else
		{
			throw new Myshipserv_Exception_MessagedException("This link is no longer valid.");
		}
		
		return;
		
	}
}