<?
/*
* List of company people, and CRUD
* Call looks like: /profile/target-customers-request?type=store-max-quote-count&cbStatus=1&max=10
*/
class Shipserv_Profile_Targetcustomers_Reports_Storemaxquote extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		$retText = 'ok';
	
		if (array_key_exists('max', $this->params) && array_key_exists('status', $this->params)) {
			$this->storeMaxQuotesPerBuyer($this->params);
		} else {
			$retText = 'error: max or status parameters are missing';
		}

		return array(
 			   '_debug' => null
			 , 'response' => $retText
			);
	}
}
