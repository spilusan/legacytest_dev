<?
/**
* Exclude buyer branch from target list
*/
class Shipserv_Profile_Targetcustomers_Reports_Exclude extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{
		if (array_key_exists('buyerId', $this->params)) {
			$id = $this->supplierBuyerRateObj->excludeBuyer((int)$this->params['buyerId']);
			//sending exclude email out
			//$this->notificationManager->targetBuyerExcluded($this->user->userId, $this->activeCompanyId, (int)$this->params['buyerId']); //It may be good to modify it to use the ID
			$this->notificationManager->targetBuyerExcluded($id); 
			//For test
			/*
				$this->notificationManager->targetBuyerTargeted($id);
				$this->notificationManager->targetBuyerLocked($id); 
				$this->notificationManager->targetBuyerUnlocked($id); 
			*/

		} else {
			throw new Myshipserv_Exception_MessagedException("Invalid Buyer ID.", 500);
		}

		return array(
 			   '_debug' => null
			 , 'response' => 'ok'
			);
	}

}
