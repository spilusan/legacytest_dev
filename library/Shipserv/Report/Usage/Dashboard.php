<?php
/**
*	Buyer Usage Dashboard 
*/
class Shipserv_Report_Usage_Dashboard
{
	const REPORT_ROOT = 0;
	const REPORT_DRILLDOWN_1 = 1;
		
	protected $reportType = null;
	protected $params = null;

	/**
	* Constructor
	* @param integer $reportType can be Shipserv_Report_Usage_Dashboard::REPORT_ROOT....
	* @param array $params URL params
	* @return object The instance of the class
	*/
	public function __construct($reportType, $params = null)
	{
		$this->reportType = $reportType;
		$this->params = $params;
	}

	/**
	* get the response of the report
	* @param bool $extended Full text return for engagement level
	* @return array Array of result row objects
	*/
	public function getResponse($extended = false)
	{
		$resultProcessorObj = null;
		
		switch ($this->reportType) {
			case self::REPORT_ROOT:
				$resultProcessorObj = new Shipserv_Report_Usage_Rec_Root($this->params, $extended);
				break;
			
			default:
				return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Error Processing Request, Invalid report type", 2), 404);
			break;
		}

		return $resultProcessorObj->getData();
	}
}