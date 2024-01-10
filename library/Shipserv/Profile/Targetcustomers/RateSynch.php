<?php
/**
 * Wrapper class for target costumers (new name Active Promotion), rate sync with salesforce
 * @author attilaolbrich
 *
 */
class Shipserv_Profile_Targetcustomers_RateSynch
{
	
	/**
	 * Get the rates for the specified TNID
	 * @param int $tnid
	 * @return array[]
	 */
	public static function getRate($tnid = null)
	{
		//Try fetching rates
		try {
			//Create rate obj
			$rate = new Shipserv_Supplier_Rate($tnid);
			$rateData = $rate->getRate();
		} catch (Exception $e) {
			//In case of displayable errors, pass it to the frontend to display it
			return array(
					'status' => 'error',
					'exception' => $e->getMessage()
			);
		}

		//Everitnihg fine, supply additional info, ith the buyer can target or not, then return result
		$canTarget = ($rate->canTargetNewBuyers($tnid) === true) ? 'yes' : 'no';
		return  array(
				'status' => 'ok',
				'data' => array_merge(array('CAN_TARGET' => $canTarget), $rateData)
		);  
	}
	
	/**
	 * Sync the rate from SalesForce, and return the new rates
	 * @param int $tnid
	 * @return array[]
	 * 
	 * @throws Exception
	 */
	public static function synchRate($tnid = null)
	{
		//Initiate rate obj
		$app = new Myshipserv_Salesforce_ValueBasedPricing_Rate();
		
		//Update rate, cacth displayable errors
		try {
			$app->pullVBPAndPOPackPercentage($tnid);
		} catch (Myshipserv_Salesforce_Exception $e) {
			return array(
					'status' => 'error',
					'exception' => $e->getMessage()
			);
		}
		
		//If we had "unknown" errors, just pass it for displaying on frontend
		if ($app->getSyncErrorCount() !== 0) {
			return array(
					'status' => 'error',
					'exception' => "There were " . $app->getSyncErrorCount() . " errors during the sync."
			);
		}
		
		//Everithing is fine, return the updated values
		return self::getRate($tnid);
		
	}
}