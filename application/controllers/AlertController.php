<?php
/**
 * Controller for handling Alerts
 *
 * @package myshipserv
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class AlertController extends Myshipserv_Controller_Action
{
    
    /**
     * Add some context switching to the controller so that the appropriate
     * actions invoke XMLHTTPRequest stuff
     * 
     * @access public
     */
    public function init ()
    {
    	parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'json')
		            ->addActionContext('get-alerts', 'json')->setAutoJsonSerialization(true)
		            ->initContext();
 	}
    
 	/**
 	 * REST function to pull and delete alert
 	 * $_GET method will pull all available alerts for logged member
 	 * $_DELETE method will delete specified key in id
 	 * 
 	 * @param string id for deletion
 	 */
    public function indexAction ()
    {
		// since this is an Ajax response, we need to use blank layout
		$this->_helper->layout->setLayout('empty');

		$db = $this->db;
		$params = $this->_getAllParams();
        $requestMethod = strtolower($_SERVER['REQUEST_METHOD']);  

        // example: GET /alert?groupBy=type
       	// example: GET /alert
        if( $requestMethod == "get" )
        {		      
        	try
        	{  	
	        	$alertManager = new Myshipserv_AlertManager( $db );
	        	$output = $alertManager->getAlert( 
	        		( isset ( $params["filterBy"] ) ? $params["filterBy"]:"" ),
	        		( isset ( $params["groupBy"] ) ? $params["groupBy"]:"" ),
	        		( isset ( $params["sortBy"] ) ? $params["sortBy"]:"" ), 
	        		false // return as array
		       	);
        	}
        	catch(Myshipserv_Exception_NotLoggedIn $e)
        	{
        		$output = array();
        	}
        }
        
        // example: DELETE /alert
        else if( $requestMethod == "delete" )
        {
        	$output = Myshipserv_AlertManager::removeMemcacheData($params["id"]);
        }
        
        // if request method is not supported
        else 
        {
        	throw new Exception($requestMethod . " is not supported");
        }
        
        $this->_helper->json((array)$output);
        
    }
    
    public function companyEnquiriesAction()
    {
    	$params = $this->_getAllParams();
    	
    	$user = Shipserv_User::isLoggedIn();
    	if( $user->isShipservUser() )
    	$result = array(0);
    	else
    	$result = array(Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countUnreadEnquiriesActions(($params['tnid']!="")?$params['tnid']:null));
    	$this->_helper->json((array)$result);
    }
    
    
    /**
     * This function will hide the alert and set cookie to browser
     * /alert/hide/id/Alert__companyId314__typecompanyUserJoin_c1d80f1a8c81c1912b4ea8a45a30c598
     */
    public function hideAction()
    {
		$this->_helper->layout->setLayout('empty');

		$db = $this->db;
    	$cookieManager = $this->getHelper('Cookie');
		$params = $this->_getAllParams();
        $alertManager = new Myshipserv_AlertManager( $db );
        
        // validate input
        if( !isset( $params["id"] ) || $params["id"] == "" || strstr( strval($params["id"]), 'Myshipserv_AlertManager_Alert') === false )
        	throw new Myshipserv_Exception_MessagedException("Id required.");

        // suppress the key/id
        $output = $alertManager->suppressData($params["id"]);
		
        $this->_helper->json((array)$output);
    }
    
    /**
     * This function will restore the alert
     * 
     * /alert/show/id/Alert__companyId314__typecompanyUserJoin_c1d80f1a8c81c1912b4ea8a45a30c598
     */
    public function showAction()
    {
		$this->_helper->layout->setLayout('empty');

		$db = $this->db;
        $params = $this->_getAllParams();
        $cookieManager = $this->getHelper('Cookie');
        $alertManager = new Myshipserv_AlertManager( $db );

        // validate input
        if( !isset( $params["id"] ) || $params["id"] == "" || strstr( $params["id"], 'Myshipserv_AlertManager_Alert') === false )
        	throw Exception("Invalid parameter");

        // remove key
		$output = $alertManager->unsuppressData( $params["id"] );
		
        $this->_helper->json((array)$output);
    }
    
    /**
     * This function delete the alert from memcache
     * /alert/delete/id/Alert__companyId314__typecompanyUserJoin_c1d80f1a8c81c1912b4ea8a45a30c598
     */
    public function deleteAction()
    {
		$this->_helper->layout->setLayout('empty');

		$db = $this->db;
        $params = $this->_getAllParams();
        
        // validate input
        if( !isset( $params["id"] ) || $params["id"] == "" || strstr( $params["id"], 'Myshipserv_AlertManager_Alert') === false )
         	throw Exception("Invalid parameter");
        
        // remove id from memcache
		$output = Myshipserv_AlertManager::removeMemcacheData($params["id"]);
		
		$this->_helper->json((array)$output);
    }

    public function debugAction()
    {
		$this->_helper->layout->setLayout('empty');
		
		$db = $this->db;
		
		$pendingAction = new Myshipserv_Controller_Action_Helper_PendingAction();
		$data = $pendingAction->getData();
		
		echo "DATA:";
		print_r( $data );
		echo "------------------------------";
		
		echo "MEMCACHE:";
	   	print_r( Shipserv_Memcache::peekMemcache() );
		echo "-----------------------";
        die();
    }
    
}