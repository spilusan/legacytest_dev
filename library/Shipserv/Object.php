<?php

/**
 * Standard ShipServ Object
 *
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
abstract class Shipserv_Object extends Shipserv_Memcache
{
    /**
     * Cache of DB string field lengths
     *
     * @var array
     */
    protected static $fieldLengthCache = array();

    /**
     * Cache of DB float field precisions
     *
     * @var array
     */
    protected static $fieldPrecisionCache = array();

	public function __get ($name)
	{
		return $this->{$name} ?? null;
	}

	public function __set ($name, $value)
	{
		$this->{$name} = $value;
	}

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    public static function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}

	public static function getUser()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			return false;
		}

		return $user;
	}

	/**
	 * This function is available as long as the class extending this abstract class
	 * @return object loggedMember
	 */
	public static function getLoggedMember ()
	{
		$config  = self::getConfig();

		$auth = Zend_Auth::getInstance();
		$auth->setStorage(new Zend_Auth_Storage_Session($config->controlpanel->authentication->namespace));
		if ($auth->hasIdentity())
		{
			// Identity exists: get it
			$uObj = $auth->getIdentity();

			return $uObj;
		}

		return false;
	}

	public function wrapInArray( $data )
	{
		if( count( $data ) == 1 ) return array( $data );
		else if( count( $data ) > 1 ) return $data;
		else return array();
	}

	/**
	 * Camelcasing variables including array
	 * This function is refactored for speed optimisation, as sometimes it took half a second to run
	 * @param mixed $varName
	 * @return mixed
	 */
	public static function camelCase($varName)
	{
		if (is_array($varName)) {
			foreach ($varName as $key => $val) {
				$data[lcfirst(str_replace('_', '', ucwords(strtolower($key), '_')))] = $val;
			}
			return $data;
		} else {
			return lcfirst(str_replace('_', '', ucwords(strtolower($varName), '_')));
		}
	}
	
	/**
	 * Performs a query by first checking memcache, and then only connecting to Oracle if necessary
     *
     * Refactored by Yuriy Akopov on 2015-02-23
	 *
	 * @param   string      $sql
     * @param   array|null  $sqlData
	 * @param   string      $key
	 * @param   int         $cacheTTL
     * @param   string      $database
     *
     * @return  mixed
	 */
	protected function fetchCachedQuery($sql, $sqlData, $key, $cacheTTL = self::MEMCACHE_TTL, $database = Shipserv_Oracle::SSERVDBA) {

		if (is_object($memcache = $this::getMemcache())) {
			$result = $memcache->get($key);
			if ($result !== false) {
                return $result;
            }
		}

        $db = self::getDbByType($database);

		if (is_null($sqlData)) {
			$result = $db->fetchAll($sql);
		} else {
			$result = $db->fetchAll($sql, $sqlData);
        }

		if (is_object($memcache)) {
            $memcache->set($key, $result, false, $cacheTTL);
        }

		return $result;
	}

	public static function getDbByType($type)
	{
		switch ($type)
		{
			case Shipserv_Oracle::SSERVDBA : return self::getDb();
			case Shipserv_Oracle::SSREPORT2 : return self::getSsreport2Db();
			case Shipserv_Oracle::STANDBY : return self::getStandbyDb();
			default : return self::getDb();
		}
	}

	public static function getStandbyDb()
	{
		return Shipserv_Helper_Database::getStandByDb();
	}

	public static function getSsreport2Db()
	{
		return Shipserv_Helper_Database::getSsreport2Db();
	}

	public static function getHostname()
	{
		if ($_SERVER['APPLICATION_ENV'] == "production" || $_SERVER['APPLICATION_ENV'] == "development-production-data" /* || $_SERVER['APPLICATION_ENV'] == "ukdev5" */) {
			$hostname = "www.shipserv.com";
		} else if ( $_SERVER['APPLICATION_ENV'] == "testing" ) {
			$hostname = "test.shipserv.com";
		} else if ( $_SERVER['APPLICATION_ENV'] == "test2" ) {
			$hostname = "test2.shipserv.com";
		} else if ( $_SERVER['APPLICATION_ENV'] == "test3" ) {
            $hostname = "test3.shipserv.com";
        } else {
			$hostname = "ukdev.shipserv.com";
		}
		return $hostname;
	}

    /**
     * Rounds the given float value to the precision it is allowed in the database
     *
     * @author  Yuriy Akopov
     * @date    2013-09-05
     *
     * @param   float   $float
     * @param   string  $tableName
     * @param   string  $columnName
     * @param   Zend_Db_Adapter_Abstract    $db
     *
     * @return  float
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function roundFloatDbValue($float, $tableName, $columnName, Zend_Db_Adapter_Abstract $db = null) {
        if (is_null($db)) {
            $db = Shipserv_Helper_Database::getDb();
        }

        $tableName  = strtoupper($tableName);
        $columnName = strtoupper($columnName);

        // check if we already no the length of the field
        $config = $db->getConfig();
        $key = $config['dbname'] . strtolower($tableName) . '.' . strtolower($columnName);

        if (array_key_exists($key, self::$fieldPrecisionCache)) {
            $precision = self::$fieldPrecisionCache[$key];

        } else {
            $select = new Zend_Db_Select($db);

            $select
                ->from(
                    array('c' => 'USER_TAB_COLS'),
                    array('len' => 'DATA_PRECISION')
                )
                ->where('TABLE_NAME = :tablename')
                ->where('COLUMN_NAME = :columnname')
                ->where('DATA_TYPE = :datatype')
            ;

            $precision = (int) $db->fetchOne($select, array(
                'tablename'     => $tableName,
                'columnname'    => $columnName,
                'datatype'      => 'NUMBER'
            ));

            if (strlen($precision) === 0) {
                throw new Shipserv_Helper_Database_Exception('Unable to read precision of ' . $tableName . '.' . $columnName);
            }

            self::$fieldPrecisionCache[$key] = $precision;
        }

        $rounded = round($float, $precision);

        return $rounded;
    }

    /**
     * Truncates string value to the length it is allowed in the database
     *
     * @author  Yuriy Akopov
     * @date    2013-09-05
     *
     * @param   string  $string
     * @param   string  $tableName
     * @param   string  $columnName
     * @param   Zend_Db_Adapter_Abstract    $db
     *
     * @return  string
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function truncateStringDbValue($string, $tableName, $columnName, Zend_Db_Adapter_Abstract $db = null) {
        if (is_null($db)) {
            $db = Shipserv_Helper_Database::getDb();
        }

        $tableName  = strtoupper($tableName);
        $columnName = strtoupper($columnName);

        // check if we already no the length of the field
        $config = $db->getConfig();
        $key = $config['dbname'] . strtolower($tableName) . '.' . strtolower($columnName);

        if (array_key_exists($key, self::$fieldLengthCache)) {
            $length = self::$fieldLengthCache[$key];

        } else {
            $select = new Zend_Db_Select($db);
            $select
                ->from(
                    array('c' => 'USER_TAB_COLS'),
                    array('len' => 'DATA_LENGTH')
                )
                ->where('TABLE_NAME = :tablename')
                ->where('COLUMN_NAME = :columnname')
                ->where('DATA_TYPE = :datatype')
            ;

            $length = (int) $db->fetchOne($select, array(
                'tablename'     => $tableName,
                'columnname'    => $columnName,
                'datatype'      => 'VARCHAR2'
            ));

            if (strlen($length) === 0) {
                throw new Shipserv_Helper_Database_Exception('Unable to read length of ' . $tableName . '.' . $columnName);
            }

            self::$fieldLengthCache[$key] = $length;
        }

        $truncated = substr($string, 0, $length);

        return $truncated;
    }

    public function runInDebugMode( $state )
    {
    	$this->debugMode = $state;
    }

    /**
     * Returns true if the given IP is found in the list of given IPs (wildcards allowed in that list)
     * It is in controller because IP checks usually happen there
     *
     * @author  Yuriy Akopov
     * @date    2014-09-10
     * @story   DE5017
     *
     *
     * @param   string          $ip
     * @param   string|array    $range
     *
     * @return  bool
     * @throws  Exception
     */
    public function isIpInRange( $ip = null, $range = null ) {

    	if( $range === null ){
    		$range = Myshipserv_Config::getSuperIps();
    	}

    	if( $ip === null ){
    		$ip = Myshipserv_Config::getUserIp();
    	}

    	if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    		throw new Exception("Invalid IP address " . $ip . ", unable to validate against the given range");
    	}

    	$octets = explode('.', $ip);

    	if (!is_array($range)) {
    		$range = array($range);
    	}

    	foreach ($range as $rangeIp) {
    		$matched = true;

    		if( strpos($rangeIp, '/') !== false )
    		{
    			if( $this->isIpCIDRMatch($ip, $rangeIp) == false )
    			{
    				continue;
    			}
    			else
    			{
    				return true;
    			}

    			return false;
    		}
    		else
    		{
    			$rangeOctets = explode('.', $rangeIp);
    			foreach ($rangeOctets as $index => $rangeOctet) {
    				if ($rangeOctet === '*') {
    					continue;
    				}

    				if ($octets[$index] !== $rangeOctet) {
    					$matched = false;
    					break;
    				}
    			}

    			if ($matched) {
    				return true;
    			}
    		}
    	}

    	return false;
    }

    public function isIpCIDRMatch($ip, $range)
    {
    	list ($subnet, $bits) = explode('/', $range);
    	$ip = ip2long($ip);
    	$subnet = ip2long($subnet);
    	$mask = -1 << (32 - $bits);
    	$subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned

    	return ($ip & $mask) == $subnet;
    }

    public function getArrayFromXML($xml){
        $arr = array();

        foreach ($xml as $element)
        {
            $tag = $element->getName();
            $e = get_object_vars($element);
            if (!empty($e))
            {
                $arr[$tag] = $element instanceof SimpleXMLElement ? $this->getArrayFromXML($element) : $e;
            }
            else
            {
                $arr[$tag] = trim($element);
            }
        }

        return $arr;
    }
}
