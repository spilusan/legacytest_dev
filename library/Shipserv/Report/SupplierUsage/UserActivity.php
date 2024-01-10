<?php
/**
 * List user activities
*/
class Shipserv_Report_SupplierUsage_UserActivity
{
	protected $dataSet;

	/**
	 * Constructor, setting the ID
	 * @param integer $byo        Buyer Org Code
	 * @param integer $range      Montsh back from cuccent sysdate
	 * @param string  $reportType Report type to filter
     * @param boolean $excludeShipmate
	 * @return object the instance created
	 */
	public function __construct($byo = null, $range = 36, $reportType = '', $excludeShipmate = false)
	{
		if ($byo === null) {
			throw new Myshipserv_Exception_JSONException("Error Processing Request", 404);
		} else {
			$this->dataSet = new Shipserv_Report_SupplierUsage_Data_UserActivity($byo, $range, $reportType, $excludeShipmate);
		}
	}

	/**
	 * Return an array response containing the drilldown data of verified accounts
	 * @return array List of Verified Accounts belonging to a BYO
	 */
	public function getResult()
	{
		$result = array();
		$records = $this->dataSet->getData();
		foreach ($records as $record) {
			array_push($result, new Shipserv_Report_SupplierUsage_Rec_UserActivity($record));
		}

		return $result;
	}
}