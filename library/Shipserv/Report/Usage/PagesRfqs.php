<?php
/**
*	Buyer Usage Dashboard, Pages RFQ's
*/
class Shipserv_Report_Usage_PagesRfqs
{
	protected $dataSet;

	/**
	* Constructor, setting the ID
	* @param integer $byo Buyer Org Code
	* @param integer $range Montsh back from cuccent sysdate
	* @return object the instance created
	*/
	public function __construct($byo = null, $range = 36)
	{
		if ($byo === null) {
			throw new Myshipserv_Exception_JSONException("Error Processing Request", 404);
		} else {
			$this->dataSet = new Shipserv_Report_Usage_Data_PagesRfqs($byo, $range);
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
			array_push($result, new Shipserv_Report_Usage_Rec_PagesRfqs($record));
		}

		return $result;
	}
}