<?php

/**
 * NON-PUBLIC
 * Controller for UX design guidelines, example patterns and testing
 *
 * @package myshipserv
 * @author Kevin Bennett
 * @copyright Copyright (c) 2009, ShipServ
 */
class StyleController extends Myshipserv_Controller_Action
{
	public function preDispatch()
	{
		parent::preDispatch();
		if (!$this->user || !$this->user->isShipservUser())
		{
			$this->redirect('', array('code' => 301));
		}
	}
	/**
	 * If a user is logged in, this will show them their settings/profile page
	 * 
	 * @access public
	 */
	public function indexAction ()
	{	

	}
	
	public function buttonsAction() {}
	public function modalAction() {}
	public function tablesAction() {}
	public function usefulAction() {}
}