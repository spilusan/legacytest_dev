<?php
/**
 * Controller actions for SPR Ordering tab
 * Sample URL: /reports/data/supplier-performance-order/:type?tnid=XXX&byb=XXX&period=XXX
 *
 * @author attilaolbrich
 *
 */
class Spr_OrderRestController extends Myshipserv_Controller_RestController
{
	
	/**
	 * Maybe called on get request, and redirected to getAction
	 * @return undefined
	 */
	public function indexAction()
	{
		$this->getAction();
	}
	
	/**
	 * We want to allow post, or get for the same service, so  in case of get, forwarding it to the post
	 * @return undefined
	 */
	public function getAction()
	{
		$this->postAction();
	}
	
	/**
	 * Triggered when POST request is sent
	 *
	 * @return json
	 */
	public function postAction()
	{
		$type = $this->getRequest()->getParam('type', null);
		
		if ($type === null) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Report type is missing, incomplete URL", 500), 500);
		}
		
		$params = array(
				'tnid' => $this->getRequest()->getParam('tnid'),
				'byb' => $this->getRequest()->getParam('byb'),
				'period' => $this->getRequest()->getParam('period'),
				'page' => $this->getRequest()->getParam('page', 1),
				'pagesize' => $this->getRequest()->getParam('pagesize', 10)
		);
		
		if ($params['period'] === null) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("period parameter is mandantory", 500), 500);
		}
		
		$reporService = Myshipserv_Spr_OrderGateway::getInstance();
		$reply = $reporService->getReport($type, $params);

		if ($reporService->getStatus() !== true || $reply === null) {
			if ($reply === null) {
				return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Invalid report type", 500), 500);
			}
			$this->getResponse()->setHttpResponseCode(500);
		}
		
		return $this->_replyJson($reply);
	}
	
}