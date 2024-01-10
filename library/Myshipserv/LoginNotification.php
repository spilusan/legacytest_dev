<?php
/**
 * Class to send notification to Account Manager in case of Pages User (not Shipmate)
 * logs in, and sends out outside of our IP range, and was not using superpassword for login 
 * @author attilaolbrich
 * Story when implemented S18391. 17/01/2017
 */

class Myshipserv_LoginNotification extends Shipserv_Memcache
{
	private static $instance;
	protected $skipSentCheck;
	
	/**
	 * Returns the *Singleton* instance of this class.
	 * @param boolean $skipSentCheck Skip the checking, storing of alrady sent (memcached)
	 * @return Singleton The *Singleton* instance.
	 */
	public static function send($skipSentCheck = false)
	{
		if (null === static::$instance) {
			static::$instance = new static();
		}
		static::$instance->skipSentCheck = $skipSentCheck;
		static::$instance->sendNotification();
		return static::$instance;
	}
	
	/**
	 * Protected what we have to hide
	 */
	protected function __construct()
	{
	}
	
	/**
	 * Protected what we have to hide
	 */
	private function __clone()
	{
		
	}
	
	/**
	 * Senidng email notification, by inserting into email alert.
	 */
	protected function sendNotification()
	{
		$casRest = Myshipserv_CAS_CasRest::getInstance();
		$user = Shipserv_User::getInstanceByEmail($casRest->getUserName());
		
		//Send the actual notification, if not Shipmate user, not Super password is used, Outside ShipServ IP
		if ($user && !$user->isShipservUser() && Myshipserv_Config::isCurrentIpSuperIp() === false && $casRest->getSuperPasswordUsed() === false) {
		//if ($user) { //For testing
			$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			if ($activeCompany->type === 'v') {
				$spbBranchCode = $activeCompany->id;
				if ($this->skipSentCheck === false) {
					//$mKey = Myshipserv_Config::getUserIp() . '_' . date('Ymd') . '_' . $spbBranchCode . '_' . $user->userId . '_' . $activeCompany->type;
					$mKey = date('Ymd') . '_' . $spbBranchCode . '_' . $user->userId . '_' . $activeCompany->type;
					$isSent = $this->memcacheGet('Myshipserv_LoginNotification', 'send', $mKey);
				} else {
					$isSent = false;
				}
				if (!$isSent) {
					if ($this->skipSentCheck === false) {
						$this->memcacheSet('Myshipserv_LoginNotification', 'send', $mKey, true);
					}
					//$supplier = Shipserv_Supplier::getInstanceById($spbBranchCode);
					$alert = new Myshipserv_EmailAlert();
					$alert->setAlertType('PGS_LOGIN');
					$alert->setInternalRefNo($user->userId);
					$alert->setSpbBranchCode($spbBranchCode);
					$alert->send();
				}
			}
		}
	}
}
