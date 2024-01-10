<?
/**
* List of excluded buyers
*/
class Shipserv_Profile_Targetcustomers_Reports_Excluded extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		$result = array();
		$responses = $this->supplierBuyerRateObj->getExcludedSupplierList();
		$this->setBulkHierarchy($responses , 'BSR_BYB_BRANCH_CODE');

		foreach ($responses as $response) {
			$targetDate = $response['BSR_VALID_FROM']->format('Y-m-d');
			$withChild =  ($response['IS_TOP']  == 1 && $response['BYB_PROMOTE_CHILD_BRANCHES'] == 0); 
			$additionalInfo = $this->getAdditionalInfo((int)$response['BSR_BYB_BRANCH_CODE'], false, null, $withChild );
			$vesselInfo = $this->getVesselInfo((int)$response['BSR_BYB_BRANCH_CODE'], null, $withChild);
			$userName = $response['_expanded']['user']->firstName . ' ' . $response['_expanded']['user']->lastName;
			$userName = ($userName == ' ')? explode('@',$response['_expanded']['user']->email)[0] : $userName;

			array_push($result, 
					array(
						  'buyerId' => $response['BSR_BYB_BRANCH_CODE']
						, 'name' => $response['_expanded']['buyer']->getName()
						, 'Location' => $additionalInfo['CNT_NAME']
						, 'LocationCity' => $additionalInfo['BYB_CITY']
						, 'OrderValue' => $additionalInfo['GMV']
						, 'lastTransactionDate' => $additionalInfo['LAST_ORDER']
						, 'excludedBy' => $userName
						, 'excludeDate' => $targetDate
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