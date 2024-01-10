<?php
/*
* Application Usage Dashboard REST controller
*/
class Reports_RestController extends Myshipserv_Controller_RestController
{

	/**
	* Called when simple GET request sent
	* @return json Buyer usage dashboard main list
	*/
	public function indexAction()
	{
		$response = new Shipserv_Report_Usage_Dashboard(Shipserv_Report_Usage_Dashboard::REPORT_ROOT, $this->params);
		return $this->_replyJson($response->getResponse());
	}

}
