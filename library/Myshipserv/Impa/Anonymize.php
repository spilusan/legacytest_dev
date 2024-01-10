<?php
/**
 * Manage IMPA anonymization session 
 * @author attilaolbrich
 *
 */

class Myshipserv_Impa_Anonymize
{
	/**
	 * Write impa anonymization status into session
	 * @param boolean $status
	 * @return boolean
	 */
	public static function setStatus($status)
	{
		$sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Impasettings');
		$sessionNamespace->impaAnonymizeStatus = (boolean)$status;
		return $status;
	}
	
	/**
	 * Get the current status of IMPA anonymization status from session
	 * @return bool
	 */
	public static function getStatus()
	{
		$user = Shipserv_User::isLoggedIn();
		if ($user && $user->isShipservUser()) {
			//This condition only applies if the logged in user is a ShipMate
			$sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Impasettings');
			//Make sure to return bool even if value is not set, and would return null
			return ($sessionNamespace->impaAnonymizeStatus === true);
		}

		return false;
	}
	
}