<?php

class IndexController extends Myshipserv_Controller_Action
{	
    public function init()
    {
    	parent::init();
    }
    
    public function indexAction()
    {
    	// S5165: SEO Related 
		$this->redirect('/search', array('code' => 301));
    }
    
    public function headerAction ()
    {
        $this->view->user = $this->user;
        $this->_helper->viewRenderer('alert/index', null, true);
    }
}