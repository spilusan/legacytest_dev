<?php

class Shipserv_Oracle_Authentication implements Zend_Auth_Adapter_Interface
{
	private $userDao;
	private $username;
	private $password;
	
	public function __construct ($db, $username, $password = null)
	{
		$this->userDao = new Shipserv_Oracle_User($db);
		$this->username = trim($username);
		$this->password = (string) $password;
	}
	
    /**
     * Performs an authentication attempt
     *
     * @throws Zend_Auth_Adapter_Exception If authentication cannot be performed
     * @return Shipserv_Oracle_Authentication_Result
     */	
    public function authenticate ()
	{
		// Attempt to authenticate as a pages user
		$ssu = $this->doPagesAuthentication();
		if ($ssu) return new Shipserv_Oracle_Authentication_Result(Shipserv_Oracle_Authentication_Result::SUCCESS, $ssu);
		
		return $this->makeFailResult();
	}
	
	private function testSuperPw ()
	{
		$sCreds = $this->getSuperUserCredentials();
		return ($sCreds['enable'] == 1 && in_array($this->getRemoteAddr(), $sCreds['ips']) && $this->password == $sCreds['password']);
	}
	
	private function makeFailResult ()
	{
		return new Shipserv_Oracle_Authentication_Result(Shipserv_Oracle_Authentication_Result::FAILURE, null);
	}
	
	/**
	 * Attempt to authenticate as Pages user
	 *
	 * @return Shipserv_User or null
	 */
	private function doPagesAuthentication()
	{
		try
		{
			// Note: $pw & $status are return variables
			$pw = null;
			$ssu = $this->userDao->fetchPagesUserByUsername($this->username, $pw, $status);
			
			// Exclude inactive users (allowing null, '', 'ACT', ...)
			if ($status == 'INA') return;
			
			/*
			 * BUY-962 refactored as old plain text pw was still in use, Check against CAS server instead
			 */
			$userPasswordIsvalid = Myshipserv_CAS_CasRest::getInstance()->casCheckPasswordValid($this->username, $this->password);
			
			// Return ok if passwords match, or if super password was used
			if ($this->password == null || $userPasswordIsvalid === true || $this->testSuperPw()) return $ssu;
		}
		catch (Shipserv_Oracle_User_Exception_NotFound $e)
		{
			// Do nothing
		}
	}
	
	private function getRemoteAddr ()
	{
		return Myshipserv_Config::getUserIp();
	}
	
	/**
	 * @return array
	 */
	private function getSuperUserCredentials ()
	{
		$config = Zend_Registry::get('config');
		
		$res['enable'] = $config->shipserv->auth->enableSuper;
		$res['password'] = $config->shipserv->auth->superPassword;
		$res['ips'] = array();
		foreach (explode(',', $config->shipserv->auth->superIps) as $ip)
		{
			$ip = trim($ip);
			if ($ip != '') $res['ips'][] = $ip;
		}
		
		return $res;
	}
		
	private static function sendPasswordEmail ($email)
	{
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$aa = new Shipserv_Adapters_Authentication();
			$aa->sendPassword($email);
		}
		else
		{
			echo "DETECTED NOT IN PRODUCTION: SUPPRESSED PASSWORD SEND<br />";
		}
	}
}

/**
 * Specialised return type for log-in attempts which provides a third state
 * relating to TradeNet (i.e. non-Pages) application users.
 *
 * This third state is no longer used, but the class remains.
 */
class Shipserv_Oracle_Authentication_Result extends Zend_Auth_Result
{
	// Specialised result code specific to this class: 
	// indicates that Pages login failed, but username was recognised as
	// e-mail address of a TN user and that a Pages user was created 
	// and seeded from that account.
	const SOAR_FAILURE_ACC_CREATED_FROM_TN = -100;
	
	// Duplicates parent's 'code' member, adding specialised result codes
	// specific to this class
	private $soarCode;
	
	/**
	 * @param $code
	 * @param $identity
	 */
	public function __construct ($code, $identity)
	{
		switch ($code)
		{
			case self::SOAR_FAILURE_ACC_CREATED_FROM_TN:
				
				// Ensure identity is a Shipserv_User instance
				if (!($identity instanceof Shipserv_User)) throw new Exception("Expected Shipserv_User");
				
				// Remember custom failure condition
				$this->soarCode = self::SOAR_FAILURE_ACC_CREATED_FROM_TN;
				
				// Construct parent class with generic failure code
				parent::__construct(self::FAILURE, $identity);
				break;
			
			default:
				parent::__construct($code, $identity);
				$this->soarCode = $this->getCode();
		}
	}
	
	/**
	 * Returns a user if a new account was created from a TN account
	 * 
	 * @return Shipserv_User or null
	 */
	public function accCreatedFromTn ()
	{
		if ($this->soarCode == self::SOAR_FAILURE_ACC_CREATED_FROM_TN)
		{
			return $this->getIdentity();
		}
	}
}
