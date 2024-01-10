<?php
/*
* Application Usage Dashboard drilldown management, export different reports to excel
*/
class Reports_SupplierUsageExportController extends Myshipserv_Controller_ExportController
{

	/**
	 * Set default document properties, like export filename
	 * {@inheritDoc}
	 * @see Myshipserv_Controller_Action_SSO::init()
	 */
	public function init()
	{
		$this->setExportFileName('supplier-usage-report.csv');
		parent::init();
	}
	
	/**
	* Called when simple GET request sent
	* @return json Buyer usage dashboard main list
	*/
	public function mainAction()
	{
		$response = new Shipserv_Report_SupplierUsage_Dashboard(Shipserv_Report_SupplierUsage_Dashboard::REPORT_ROOT, $this->params);
		return $this->_replyCsv($response->getResponse(false, true));
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
			case 'user-activity':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$reportType =  (array_key_exists('reportType', $params)) ? $params['reportType'] : '';
                $excludeShipMate = (array_key_exists('excludeSM', $params)) ? strtolower($params['excludeSM']) === 'true' : false;
				$response = new Shipserv_Report_SupplierUsage_UserActivity($id, $range, $reportType, $excludeShipMate);
				return $this->_replyCsv($response->getResult());
				break;
			default:
				return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Invalid report type"), 404);
				break;
		}
	}

}