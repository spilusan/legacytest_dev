<?
/*
* List of company people, and CRUD
*/
class Shipserv_Profile_Targetcustomers_Reports_Settings extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		//TODO implement checkbox statuses, and store
		$users = $this->getApprovedUsers();
		$max = $this->getMaxQotPerBuyer();
		$response = array();

		foreach ($users as $user) {
			$user['maxQots'] = $max;
			$response[] = $user;
		}

		return array(
 			   '_debug' => null
			  , 'response' => $response
			);

	}
}
