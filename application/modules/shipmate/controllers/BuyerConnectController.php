<?php
/**
 * Controller actions for Buyer Connect non REST calls
 *
 * @author attilaolbrich
 *
 */

class Shipmate_BuyerConnectController extends Myshipserv_Controller_Action
{
    
    /**
     * Allows only access to ShipMate user
     * @return unknown
     */
    public function init()
    {
        parent::init();
        parent::preDispatch();
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        $this->abortIfNotShipMate();
    }
    
	/**
	 * Download document action
	 * 
	 * @return unknown
	 */
    protected function downlodDocAction()
	{

	    $id = $this->getRequest()->getParam('id', 0);

		$client = new Zend_Http_Client();
		$client->setUri(Zend_Registry::get('config')->shipserv->services->buyerconnect->url . '/transaction/document/' . $id);
		$client->setHeaders("Accept-Language", "en");
		$client->setMethod(Zend_Http_Client::GET);
		$response = $client->request();
		$responseCode = $response->getStatus();
		
		switch ($responseCode) {
		    case 200:
		        $this->_helper->viewRenderer->setNoRender(true);
		        $this->view->layout()->disableLayout();
		        
		        //Inherint content type, and disposition from the header if exists
		        $forwardHeaders = array(
		            'content-type',
		            'content-disposition'
		        );
		       
		        $headers = $response->getHeaders();
		        if (is_array($headers)) {
		            foreach ($headers as $key => $header) {
		                if (in_array(strtolower($key), $forwardHeaders)) {
		                    header($key .': ' .  $header);
		                }
		            }
		                
		        }
		        
		        echo $response->getBody();
		        return;
		      break;
		    case 404:
		        throw new Myshipserv_Exception_MessagedException("File not found", 404);
		        break;
		    default:
		        throw new Myshipserv_Exception_MessagedException("$responseCode Error occured ", $responseCode);
		    break;
		}
	}
	
}