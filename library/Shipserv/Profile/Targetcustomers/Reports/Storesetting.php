<?
/*
* Edit the settings per user (This class is abandoned, as the requirements has changed during development)
*/
class Shipserv_Profile_Targetcustomers_Reports_Storesetting extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		$retText = 'ok';

		if (array_key_exists('id', $this->params)) {
			if (array_key_exists('receiveNotifications', $this->params) || array_key_exists('canTargetExclude', $this->params)) {
				$this->storeUserTargetPerUser($this->params);
			} else {
				$retText = 'error: receiveNotifications and canTargetExclude parameters are missing';
			}
		} else {
			$retText = 'error: id parameter is missing';
		}

		return array(
 			   '_debug' => null
			 , 'response' => $retText
			);

	}
}
