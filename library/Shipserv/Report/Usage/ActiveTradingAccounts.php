<?php
/**
 *	Buyer Usage Dashboard, list of active trading accounts
*/
class Shipserv_Report_Usage_ActiveTradingAccounts
{
	protected $dataSet;

	/**
	 * Constructor, setting the ID
	 * @param integer $byo Buyer Org Code
	 * @return object the instance created
	 */
	public function __construct($byo = null)
	{
		if ($byo === null) {
			throw new Myshipserv_Exception_JSONException("Error Processing Request", 404);
		} else {
			$this->dataSet = new Shipserv_Report_Usage_Data_ActiveTradingAccounts($byo);
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
			array_push($result, new Shipserv_Report_Usage_Rec_ActiveTradingAccounts($record));
		}

		return $result;
	}
}