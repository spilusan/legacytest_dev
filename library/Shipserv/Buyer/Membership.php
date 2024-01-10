<?php
/**
 * Check the membersip for the active company
 * @author attilaolbrich
 */

class Shipserv_Buyer_Membership
{
	/**
	 * Return if the user has access to full membership options
	 * @return boolean
	 */
	public static function errorOnBasicMembership()
	{
		
		$user = Shipserv_User::isLoggedIn();
		if ($user && $user->isShipservUser()) {
			return true;
		}
			
		$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		
		if ($activeCompany->type === 'b') {
			if (Shipserv_Oracle_PagesCompany::getInstance()->getMembershipLevel('BYO', (int)$activeCompany->id) === 1) {
				return true;
			}
		}
		
		return false;
	}
}
