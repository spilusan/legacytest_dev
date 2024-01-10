<?php
/*
* Application Usage Dashboard return countires
*/
class Reports_CountriesRestController extends Myshipserv_Controller_RestController
{
	/**
	* Index action
	* @return unknown
	*/
	public function indexAction()
	{
		$response = new Shipserv_Report_Usage_Countries();
		return $this->_replyJson($response->getCountries());
	}

}
