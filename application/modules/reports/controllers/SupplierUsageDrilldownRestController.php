<?php
/*
 * Supplier Usage Dashboard drilldown management
*/
class Reports_SupplierUsageDrilldownRestController extends Myshipserv_Controller_RestController
{
	/**
	 * May be called on get request, and redirected to getAction
	 * @return undefined
	 */
	public function indexAction()
	{
		$this->getAction();
	}

	/**
	 * Main entry point for drilldownd, Accept route param type and id, where type is the type of the drilldown, and id is the entity id
	 * Triggered when GET request is sent
	 *
	 * @return json
	 */
	public function getAction()
	{
		$type = (array_key_exists('type', $this->params)) ? $this->params['type'] : null;
		$id = (array_key_exists('id', $this->params)) ? $this->params['id'] : null;

		if ($id === mull) {
			return $this->_replyJsonError(new Myshipserv_Exception_JSONException("ID parameter is missing"), 404);
		}

		return $this->taskManager($type, $id, $this->params);
	}

	/**
	 * This function will decide which class has to be invoked
	 * @param string $type   parameter type coming from URL param
	 * @param string $id     The id of the unique entity
	 * @param array  $params Array of URL params
	 * @return the JSON response
	 */
	protected function taskManager($type, $id, $params)
	{
		switch ($type) {
			case 'user-activity':
				$range =  (array_key_exists('range', $params)) ? $params['range'] : 36;
				$reportType =  (array_key_exists('reportType', $params)) ? $params['reportType'] : '';
                $excludeShipMate = (array_key_exists('excludeSM', $params)) ? strtolower($params['excludeSM']) === 'true' : false;
				$response = new Shipserv_Report_SupplierUsage_UserActivity($id, $range, $reportType, $excludeShipMate);
				return $this->_replyJson($response->getResult());
				break;
			default:
				return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Invalid report type"), 404);
				break;
		}
	}
}