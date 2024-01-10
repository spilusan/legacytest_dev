<?
/*
* Edit the settings per user list
*/
class Shipserv_Profile_Targetcustomers_Reports_Storetargetstate extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		$result = $this->storeUserTargetInfo( $this->params );

		return array(
 			   '_debug' => null
			 , 'response' => $result
			);

	}
}
