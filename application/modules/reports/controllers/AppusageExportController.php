<?php
/*
* Application Usage Dashboard drilldown management, export different reports to excel
*/
class Reports_AppusageExportController extends Myshipserv_Controller_ExportController
{

	/**
	 * Set default document properties, like export filename
	 * {@inheritDoc}
	 * @see Myshipserv_Controller_Action_SSO::init()
	 */
	public function init()
	{
		$this->setExportFileName('buyer-usage-report.csv');
		parent::init();
	}
	
	/**
	* Called when simple GET request sent
	* @return json Buyer usage dashboard main list
	*/
	public function mainAction()
	{
		$response = new Shipserv_Report_Usage_Dashboard(Shipserv_Report_Usage_Dashboard::REPORT_ROOT, $this->params);
		return $this->_replyCsv($response->getResponse(true));
	}

	/**
	* Main entry point for drilldownd, Accept route param type and id, where type is the type of the drilldown, and id is the entity id
	* Triggered when GET request is sent 
	*
	* @return csv
	*/
	public function indexAction()
	{
		$type = (array_key_exists('type', $this->params)) ? $this->params['type'] : null;
		$id = (array_key_exists('id', $this->params)) ? $this->params['id'] : null;

		if ($id === mull) {
			return $this->_replyCsvError(new Myshipserv_Exception_JSONException("ID parameter is missing"), 404);
		}

		return $this->taskManager($type, $id, $this->params);
	}

	/**
	* This function will decide which class has to be invoked
	* @param string $type   parameter type coming from URL param
	* @param string $id     The id of the unique entity
	* @param array  $params Array of URL params
	* @return the CSV response 
	*/
	protected function taskManager($type, $id, $params)
	{
		switch ($type) {
			case 'verified-accounts':
				$response = new Shipserv_Report_Usage_VerifiedAccounts($id);
				return $this->_replyCsv($response->getResult());
				break;
			case 'non-verified-accounts':
				$response = new Shipserv_Report_Usage_NonVerifiedAccounts($id);
				return $this->_replyCsv($response->getResult());
				break;
			case 'succ-sign-ins':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_SignIns($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'failed-sign-ins':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_FailedSignIns($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'user-activity':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$reportType =  (array_key_exists('reportType', $params)) ? $params['reportType'] : '';
                $excludeShipMate = (array_key_exists('excludeSM', $params)) ? strtolower($params['excludeSM']) === 'true' : false;
				$response = new Shipserv_Report_Usage_UserActivity($id, $range, $reportType, $excludeShipMate);
				return $this->_replyCsv($response->getResult());
				break;
			case 'search-events':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_SearchEvents($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'supplier-page-impressions':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_SpbSearchImpressions($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'contact-requests':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_ContactRequests($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'pages-rfqs':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_PagesRfqs($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
			case 'active-trading-accounts':
				$response = new Shipserv_Report_Usage_ActiveTradingAccounts($id);
				return $this->_replyCsv($response->getResult());
				break;
			case 'rfq-ord-wo-imo-row':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$response = new Shipserv_Report_Usage_RfqOrdWoImo($id, $range);
				return $this->_replyCsv($response->getResult());
				break;
				
				
			default:
				return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Invalid report type"), 404);
				break;
		}
	}
}