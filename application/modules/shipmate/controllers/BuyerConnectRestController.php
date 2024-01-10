<?php
/**
 * Controller actions for Buyer Connect REST API calls
 * Sample URL: /reports/data/buyer-connect/<type>?params
 *
 * @author attilaolbrich
 *
 */

class Shipmate_BuyerConnectRestController extends Myshipserv_Controller_RestController
{
	/**
	 * Check if the user can access the report, Return an exception object if cannot with the proper exception text
	 * 
	 * @return Myshipserv_Exception_JSONException|NULL
	 */	
	protected function restrictedToAccessReport()
	{
		$user = Shipserv_User::isLoggedIn();
		
		if (!$user) {
			return new Myshipserv_Exception_JSONException("Must be logged in as a ShipMate");
		}
		
		if (!$user->isShipservUser()) {
			return new Myshipserv_Exception_JSONException("Must be logged in as a ShipMate");
		}
		
		return null;
	}
	
	/**
	 * Maybe called on get request, and redirected to getAction
	 * @return undefined
	 */
	public function indexAction()
	{
		$this->getAction();
	}
	
	/**
	 * Triggered when GET request is sent
	 *
	 * @return json
	 */
	public function getAction()
	{
		$this->sendRequest(Zend_Http_Client::GET);
	}

	/**
	 * Called when HTTP request was a post
	 */
	public function postAction()
	{
		$this->sendRequest(Zend_Http_Client::POST);
	}
	
	/**
	 * Called when HTTP request was a delete
	 */
	public function deleteAction()
	{
		$this->sendRequest(Zend_Http_Client::DELETE);
	}
	
	/**
	 * Central function to process different routes, and prepare the URL to call 
	 * @param int $method (Zend_Http_Client::GET/POST/DELETE)
	 * 
	 * @return string (json)
	 */
	protected function sendRequest($method)
	{
		
		//First check if can access this report
		$restricted = $this->restrictedToAccessReport();
		
		if ($restricted) {
			return $this->_replyJsonError($restricted, 500);
		}
		
		$id = $this->getRequest()->getParam('id', '');
		
		$reporService = Myshipserv_BuyerConnect_Gateway::getInstance();
		
		//Genearating the url from parameters passed by route
		if ($this->getRequest()->getParam('requestId')) {
			$urlAddition = 'transaction/' . $this->getRequest()->getParam('requestId') . '/' . $id;
		} else {
			$urlAddition = 'transaction/' . $id;
		}
		
		if ($this->getRequest()->getParam('extractedData')) {
			$urlAddition .= '/extractedData';
		}

		
		//Add additional parameters in case of we use workflowstatus
		if ($this->getRequest()->getParam('requestId') === 'workFlowStatus') {
			
			if ($this->getRequest()->getParam('status', null)) {
				$urlAddition .= '/status/' . $this->getRequest()->getParam('status');
			}

			if ($this->getRequest()->getParam('doctype', null)) {
				$urlAddition .= '/doctype/' . $this->getRequest()->getParam('doctype');
			}

			if ($this->getRequest()->getParam('supplier', null)) {
				$urlAddition .= '/supplier/' . $this->getRequest()->getParam('supplier');
			}

			if ($this->getRequest()->getParam('buyer', null)) {
				$urlAddition .= '/buyer/' . $this->getRequest()->getParam('buyer');
			}
		}
		
		//Determine HTTP method, and forwart to the appropriate service
		switch ($method) {
			case Zend_Http_Client::GET:
			     
			    $reply = $reporService->forward($urlAddition, $this->getRequest()->getQuery(), null, true);
				break;
			case Zend_Http_Client::DELETE:
			    $reply = $reporService->delete()->forward($urlAddition, $this->getRequest()->getQuery(), null, true);
				break;
			case Zend_Http_Client::POST:
			    $reply = $reporService->post($this->getRequest()->getRawBody())->forward($urlAddition, $this->getRequest()->getQuery(), null, true);
				break;
			default:
				return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Unsupported HTTP request, can be GET/POST/DELETE"), 500);
				break;
		}
		
		if ($reporService->getStatus() !== true) {
			$this->getResponse()->setHttpResponseCode(500);
		}
		
		return ($reply) ? $this->_replyJson($reply) : $this->_replyJson(array());
		
	}
	
}