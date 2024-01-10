<?php
/**
* Controller actions for SPR report service gateway
* Sample URL: /reports/data/supplier-performance/gmv?tnid=52323&lowerdate=20150101&upperdate=20150601
* 
* @author attilaolbrich
*
*/
class Spr_ReportServiceController extends Myshipserv_Controller_RestController
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
	 * Triggered when GET request is sent
	 *
	 * @return json
	 */
	public function getAction()
	{
		$type = $this->getRequest()->getParam('type', null);
		
		if ($type === null) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Report type is missing, incomplete URL"), 500);
		}
		
		$reporService = Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR);
		$reply = $reporService->forward($type, $this->getRequest()->getQuery());
		if ($reporService->getStatus() !== true) {
			$this->getResponse()->setHttpResponseCode(500);
		}
			
		return $this->_replyJson($reply);
	}
	
}