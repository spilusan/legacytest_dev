<?
/*
* List of Pending buyers
*/
class Shipserv_Profile_Targetcustomers_Reports_Pending extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{
		
		$result = array();
		$responses = $this->supplierBuyerRateObj->getPendingSupplierList();
		$this->setBulkHierarchy($responses);
		
		foreach ($responses as $response) {
			$withChild =  ($response['IS_TOP']  == 1 && $response['BYB_PROMOTE_CHILD_BRANCHES'] == 0); 
			$additionalInfo = $this->getAdditionalInfo((int)$response['BYB_BRANCH_CODE'], false, null, $withChild);
			$vesselInfo = $this->getVesselInfo((int)$response['BYB_BRANCH_CODE'], null, $withChild);

			array_push($result, 
					array(
 					     'buyerId' => $response['BYB_BRANCH_CODE']
						, 'name' => $response['BYB_NAME']
						, 'Location' => $additionalInfo['CNT_NAME']
						, 'LocationCity' => $additionalInfo['BYB_CITY']
						, 'OrderValue' => $additionalInfo['GMV']
						, 'lastTransactionDate' => $additionalInfo['LAST_ORDER']
						, 'vessel' => $vesselInfo
						, 'currentRate' => $this->currentRate
						, 'SpbEnabled' => $additionalInfo['SPB_ENABLED'] 
						)
				);
		}

		if (count($result) == 0) 
		{
			array_push($result, $this->resultIfEmpty());
		}

		return array(
 			   '_debug' => null
			 , 'response' => $result
			);
	}

}