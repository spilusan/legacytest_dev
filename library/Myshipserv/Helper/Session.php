<?php
/**
 * A class to house general purpose functions related to session management
 *
 * @author  Yuriy Akopov
 * @date    2016-07-22
 * @story   DE6822
 */
class Myshipserv_Helper_Session
{
    protected static $fakeObj;

	/**
	 * Starts native PHP session ignoring Zend level if it hasn't been done before
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public static function startNativeSessionSafely()
	{
		switch (session_status()) {
			case PHP_SESSION_DISABLED:
				throw new Exception("Sessions appears to be disabled");

			case PHP_SESSION_ACTIVE:
				// session appears to be started already
				return false;

			case PHP_SESSION_NONE:
                // weird thing is that Zend_Session::start() doesn't have an effect
				_session_start();
				return true;

			default:
				throw new Exception("Unsupported or invalid session status returned");
		}
	}

	/**
	 * Starts Zend Session if it hasn't been done before
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	public static function startSessionSafely()
	{
        if (!_cookie_loggedin()) {
            return false;
        }
        
		if (Zend_Session::isStarted()) {
			return false;
		}

        Zend_Session::start();
		return true;
	}

	/**
	 * Returns a session namespace checking if the session is properly started in both Zend_Session and native PHP
	 *
	 * @param   string  $namespace
	 *
	 * @return  Myshipserv_Zend_Session_Namespace
	 */
	public static function getNamespaceSafely($namespace)
	{
        
        if (!_cookie_loggedin()) {
             return Myshipserv_Helper_FakeSession::getInstance();
        }

		if (self::startSessionSafely() === false) {
			// if Zend_Session is already started, it could be that session ID was switched by CAS and PHP native
			// session is terminated, so we need to restart it now
			Myshipserv_Helper_Session::startNativeSessionSafely();
		} else {
			// if Zend_Session is not started, then it will be automatically started along with the native PHP session
			// by Zend_Session_Namespace constructor
		}

		if (self::$fakeObj) {
		    return self::$fakeObj;
        }

		return new Myshipserv_Zend_Session_Namespace($namespace);
	}

	/**
	 * Returns session data about the current open company
	 *
	 * @return Zend_Session_Namespace
	 */
	public static function getActiveCompanyNamespace()
	{
		return self::getNamespaceSafely('userActiveCompany');
	}
	
	/**
	 * Returns the currently selected company id
	 *
	 * @return Int|Null
	 */
	public static function getActiveCompanyId()
	{
	    $company = self::getActiveCompanyNamespace();
	    if ($company && is_object($company) && isset($company->id) && (Int) $company->id) {
	        return (Int) $company->id;
	    } else {
	        return null;
	    }
	}

    /**
     * Returns the currently selected company type
     *
     * @param bool $translate
     * @return Int|Null
     *
     */
    public static function getActiveCompanyType($translate = false)
    {
        $company = self::getActiveCompanyNamespace();
        if ($company && is_object($company) && isset($company->type) && $company->type) {

            if ($translate === false) {
                return $company->type;
            }

            return self::getFullCompanyIdByShortId($company->type);

        } else {
            return null;
        }
    }

    /**
     * Update trading account list for reporting TNID accounts
     *
     */
	public static function updateReportTradingAccounts()
    {
        $activeCompany = self::getActiveCompanyNamespace();
        $reportBuyers  = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_WEBREPORTER, false);
        $activeCompany->reportTradingAccounts = $reportBuyers;
    }
    
    /**
     * Returns a (pseudo-) random GUID via a system call or re-implemented generator
     *
     * @author  Yuriy Akopov
     * @story   DEV-2563
     * @date    2018-02-21
     *
     * @return string
     */
	public static function getGuid()
    {
        if (function_exists('com_create_guid') !== false) {
            return trim(com_create_guid(), '{}');
        }

        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        $guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $guid;
    }


    /**
     * Returns the full company ID , like SPB, BYO, CON by v,b,c short ID
     * (case insensitive)
     *
     * @param string $shortId
     * @return mixed
     */
    public static function getFullCompanyIdByShortId($shortId)
    {
        $id = strtolower($shortId);

        $companyTypeMap = array(
            'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
            'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
        );

        if (array_key_exists($id, $companyTypeMap)) {
            return $companyTypeMap[$id];
        }

        return $shortId;
    }

    /**
     * Returns the short company ID , like  v,b,c by SPB, BYO, CON full id ID
     * (case insensitive)
     *
     * @param string $fullId
     * @return mixed
     */
    public static function getShortCompanyIdByFullId($fullId)
    {
        $id = strtoupper($fullId);

        $companyTypeMap = array(
            Myshipserv_UserCompany_Actions::COMP_TYPE_SPB => 'v',
            Myshipserv_UserCompany_Actions::COMP_TYPE_BYO => 'b',
            Myshipserv_UserCompany_Actions::COMP_TYPE_CON => 'c'
        );

        if (array_key_exists($id, $companyTypeMap)) {
            return $companyTypeMap[$id];
        }

        return $fullId;
    }

    public static function fake($fakeObj)
    {
        self::$fakeObj = $fakeObj;
    }
}
