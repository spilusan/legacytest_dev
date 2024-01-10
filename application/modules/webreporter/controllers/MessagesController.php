<?php
/**
* Controller for webreporter restriction message
*/
class Webreporter_MessagesController extends Myshipserv_Controller_Action
{

	/**
	* If the user is redirecet to this page, 
	* and logged in on CAS as TradeNet,
	* then we automatically destroy his TradeNet(only) CAS session
	* @return unknown
	*/
    public function init()
    {
        if ($this->getRequest()->getParam('mode') === 'session') {
        	$casRest = Myshipserv_CAS_CasRest::getInstance();
        	if ($casRest->casCheckLoggedIn(Myshipserv_CAS_CasRest::LOGIN_TRADENET)) {
        		$casRest->logoutFromCas(Myshipserv_CAS_CasRest::LOGIN_TRADENET);
        	}
        }
    }
    /**
    * Index action
    * @return unknown
    */
    public function indexAction()
    {
        $config = Zend_Registry::get('config');
    	$this->view->cas = new Myshipserv_View_Helper_Cas();
        $this->view->appDomain = $config->shipserv->application->hostname;
    }

}