<?
/**
* List of Targeted buyers
*/
class Shipserv_Profile_Targetcustomers_Reports_Targeted extends Shipserv_Profile_Targetcustomers_Reports
{

	public function getData()
	{

		$result = array();
		$responses = $this->supplierBuyerRateObj->getTargetedBuyerList();
		$this->setBulkHierarchy($responses, 'BSR_BYB_BRANCH_CODE');

		foreach ($responses as $response) {
			$lockedFor = $response['LOCKED_FOR'];
			$isLocked = ($response['BSR_LOCKED_ORD_INTERNAL_REF_NO'] != null);
			$withChild =  ($response['IS_TOP']  == 1 && $response['BYB_PROMOTE_CHILD_BRANCHES'] == 0); 
			$additionalInfo = $this->getAdditionalInfo((int)$response['BSR_BYB_BRANCH_CODE'], true, $response['BSR_VALID_FROM'], $withChild );
			//Vesel date filter removed by request
			$vesselInfo = $this->getVesselInfo((int)$response['BSR_BYB_BRANCH_CODE'], null, $withChild);
			$quoteRate = ((int)$additionalInfo['RFQ_COUNT'] > 0) ? (int)$additionalInfo['QOT_COUNT'] / (int)$additionalInfo['RFQ_COUNT'] *100 : 0;
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
						, 'targetedBy' => $userName
						, 'targetedDate' => $response['BSR_VALID_FROM']->format('Y-m-d H:i:s')
						, 'lockedUntil' => (is_null($response['BSR_VALID_TILL']) ? null : $response['BSR_VALID_TILL']->format('Y-m-d H:i:s'))
						, 'rfqsReceived' => $additionalInfo['RFQ_COUNT']
						, 'quoteRate' => $quoteRate
						, 'isLocked' => $isLocked
						, 'lockedFor' => $lockedFor
						, 'vessel' => $vesselInfo
						, 'currentRate' => $this->currentRate
						, 'rate' => array(
								 'lockTarget' =>$response['_expanded']['rate']['SBR_LOCK_TARGET']
								, 'rateTarget' => $response['_expanded']['rate']['SBR_RATE_TARGET']
								, 'rateStandard' => $response['_expanded']['rate']['SBR_RATE_STANDARD']
							)
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