<?php
/*
* Application Usage Dashboard return timezone list
*/
class Reports_TimezoneRestController extends Myshipserv_Controller_RestController
{

  public function indexAction()
  {
	$response = new Shipserv_Report_Usage_Timezones();
	return $this->_replyJson($response->getTimezones());
  }

}
