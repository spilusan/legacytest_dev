<?php
/**
 * Front end to webstore
 * Web store backend is provided through ekmpowershop.com and available at store.shipserv.com
 * 
 */
class StoreController extends Myshipserv_Controller_Action
{
	public function init()
	{
		parent::init();
	}
	
	
	public function indexAction() {
	    //this page was moved to corporate
	    $this->redirect('/info/pages-for-suppliers/webstore');
	    
		$this->_helper->_layout->setLayout('default');
	}
	
	public function bannerAction() {
		$this->_helper->_layout->setLayout('default');
	}
}
