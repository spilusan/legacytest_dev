<?php
/**
 * This staitic class will do some validations for the emails
 * centralizing some functions avoiding code duplication
 * 
 * @author attilaolbrich
 *
 */

class Myshipserv_NotificationManager_Email_Targetcustomers_Validation
{
	
	/**
	 * Return true, if we have to send the email only to BCC address
	 * @param integer $spbBranchCode
	 * 
	 * @return boolean
	 */
	public static function emailGoesToBccOnly($spbBranchCode)
	{
		
		try {
			$user = Shipserv_User::isLoggedIn();
			
			if ($user && $user->isShipservUser()) {
				return true;
			} else {
				$targetRate = new Shipserv_Supplier_Rate_Buyer($spbBranchCode);
				return !$targetRate->getRateObj()->canTargetNewBuyers();
			}
			
			// either access to AP enabled to everyone ($apAccess === false) or only to ShipMates but the current user is a ShipMate as well
			return false;
		} catch (Exception $e) {
			return true;
		}
		return false;
	}
}