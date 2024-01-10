<?php
/**
 * Manage SPR anonymization session 
 * @author attilaolbrich
 *
 */

class Myshipserv_Spr_Anonymize
{
	/**
	 * Write SPR anonymization status into session
	 * @param boolean $status
	 * @return boolean
	 */
	public static function setStatus($status)
	{
		$sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Sprsettings');
		$sessionNamespace->sprAnonymizeStatus = (boolean)$status;
		return $status;
	}
	
	/**
	 * Get the current status of SPR anonymization status from session
	 * @return bool
	 */
	public static function getStatus()
	{
		$user = Shipserv_User::isLoggedIn();
		if ($user && $user->isShipservUser()) {
			//This condition only applies if the logged in user is a ShipMate
			$sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Sprsettings');
			//Make sure to return bool even if value is not set, and would return null
			return ($sessionNamespace->sprAnonymizeStatus === true);
		}

		return false;
	}
	
	/**
	 * This function will anonimise SPR data, in case of the flag is set
	 * $data parameter is passed as reference, so it will not return the anomimized data, will do on the dataset itself
	 * so we can save some memory
	 * It is capable of processing more levels of arrays
	 * 
	 * Will return true if anonimization took place
	 * 
	 * @param array $data
	 * @param array $structure
	 * @return boolean
	 */
    public static function anonimizeData(&$data, $structure)
    {
        $counts = array();
        foreach (array_keys($structure) as $structKey) {
            $counts[$structKey] = 1;
        }
        
        $sessionNamespace = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_Sprsettings');
        if ($sessionNamespace->sprAnonymizeStatus === true) {

            //Lets loop through the entire structure wiht this recursive lambda function
            $anonimize = function (&$data, &$parentCounts, $parentKey = 'root') use (&$anonimize, &$structure) {
                $clonedCounts = array();
                foreach (array_keys($structure) as $structKey) {
                    $clonedCounts[$structKey] = 1;
                }
                
                if (is_array($data)) {
                    foreach ($data as $key => &$value) {
                        foreach ($structure as $structKey => $structValue) {
                            $structKeyArray = explode('/', $structKey);
                            $parsedStructKey = (count($structKeyArray) === 1) ? $structKeyArray[0] : $structKeyArray[1];
                            $hasParentFilter = (count($structKeyArray) > 1) ? $structKeyArray[0] : null;
                            if ($key === $parsedStructKey && ($hasParentFilter === null || $hasParentFilter === $parentKey)) {
                                if (is_string($value)) {
                                    if ($structValue === null) {
                                        $value = '';
                                    } else {
                                        $value =  str_replace('{X}', $parentCounts[$structKey]++, $structValue);
                                    }
                                } elseif (is_array($value)) {
                                    foreach ($value as &$actualValue) {
                                        if (is_string($actualValue)) {
                                            if ($structValue === null) {
                                                $actualValue = '';
                                            } else {
                                                $actualValue =  str_replace('{X}', $parentCounts[$structKey]++, $structValue);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        $passKey = (is_numeric($key)) ? $parentKey : $key;
                        $anonimize($value, $clonedCounts, $passKey);
                    }    
                } 
            };
        
            $anonimize($data, $counts);
            return true;
        }
        
        return false;
    }

}