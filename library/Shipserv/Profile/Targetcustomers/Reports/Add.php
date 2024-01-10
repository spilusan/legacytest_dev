<?
/**
* Add buyer branch to target list
*/
class Shipserv_Profile_Targetcustomers_Reports_Add extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{
		if (array_key_exists('buyerId', $this->params)) {
			$id = $this->supplierBuyerRateObj->addTargetedBuyer((int)$this->params['buyerId']);

			//sending target email out
			//$this->notificationManager->targetBuyerTargeted($this->user->userId, $this->activeCompanyId, (int)$this->params['buyerId']);
			$this->notificationManager->targetBuyerTargeted($id);
		} else {
			throw new Myshipserv_Exception_MessagedException("Invalid Buyer ID.", 500);
		}

		return array(
 			   '_debug' => null
			 , 'response' => 'ok'
			);
	}

}