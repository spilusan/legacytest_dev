<?php
/**
*	Buyer Usage Dashboard, list of Rfq or ORD wo IMO nr
*/
class Shipserv_Report_Usage_RfqOrdWoImo
{
	protected $dataSet;

	/**
	* Constructor, setting the ID
	* @param integer $byo Buyer Org Code
	* @param integer $range The time range
	* @return object the instance created
	*/
	public function __construct($byo = nul, $range = 0)
	{
		if ($byo === null) {
			throw new Myshipserv_Exception_JSONException("Error Processing Request", 404);
		} else {
			$this->dataSet = new Shipserv_Report_Usage_Data_RfqOrdWoImo($byo, $range);
		}
	}

	/**
	* Return an array response containing the drilldown data of Ord Po wo IMO
	* @return array List of Verified Accounts belonging to a BYO  
	*/
	public function getResult()
	{
		$result = array();
		$records = $this->dataSet->getData();
		foreach ($records as $record) {
			array_push($result, new Shipserv_Report_Usage_Rec_RfqOrdWoImo($record));
		}

		return $result;
	}
}