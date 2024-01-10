<?php
class Shipserv_Helper_Database {
    const
        SSERVDBA    = 'SSERVDBA',
        SSREPORT2   = 'SSREPORT2'
    ;

    const
        MAX_IN = 999
    ;

    const
        ESCAPE_LIKE_LEFT = 'left',
        ESCAPE_LIKE_RIGHT = 'right',
        ESCAPE_LIKE_BOTH = 'both'
    ;

    protected static function getPluginResource($resourceId) {
        return $GLOBALS["application"]->getBootstrap()->getPluginResource($resourceId);
    }

    /**
     * @return  Zend_Db_Adapter_Oracle
     */
    public static function getSsreport2Db() {
        $resource = self::getPluginResource('multidb');
        return $resource->getDb('ssreport2');
    }

    /**
     * @param   bool    $preferLocalStandby
     * @return  Zend_Db_Adapter_Oracle
     */
    public static function getStandByDb($preferLocalStandby = false) {
        if($preferLocalStandby && $_SERVER['APPLICATION_ENV'] == "development") {
            return self::getlocalStandByDb();
        } else {
            $resource = self::getPluginResource('multidb');
            return $resource->getDb('standbydb');
        }
    }

    /**
     * @return  Zend_Db_Adapter_Oracle
     */
    public static function getlocalStandByDb() {
        $resource = self::getPluginResource('multidb');
        return $resource->getDb('standbydblocal');
    }

    /**
     * @return  Zend_Db_Adapter_Oracle
     */
    public static function getDb() {
        return $GLOBALS['application']->getBootstrap()->getResource('db');
    }

    /**
     * Returns Oracle date expression for the given datetime
     *
     * @author  Yuriy Akopov
     * @date    2013-09-05
     *
     * @param   DateTime|int|string $datetime
     * @param   bool                $dateOnly
     *
     * @return  string
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getOracleDateExpr($datetime = null, $dateOnly = false) {
        if (is_null($datetime)) {
            $datetime = new DateTime();
        }

        try {
            if ($datetime instanceof DateTime) {
                $dt = $datetime;
            } else if (filter_var($datetime, FILTER_VALIDATE_INT)) {
                $dateStr = date('Y-m-d H:i:s', $datetime);  // no direct support for unix time in PHP 5.2 DateTime
                $dt = new DateTime($dateStr);
            } else if (strlen($datetime)) {
                $dt = new DateTime($datetime);
            } else {
                throw new Exception();
            }
        } catch (Exception $e) {
            throw new Shipserv_Helper_Database_Exception('Unknown datetime value supplied for conversion');
        }

        if ($dateOnly) {
            $oracleStr = "TO_DATE('" . $dt->format('Y-m-d') . "', 'YYYY-MM-DD')";
        } else {
            $oracleStr = "TO_DATE('" . $dt->format('Y-m-d H:i:s') . "', 'YYYY-MM-DD HH24:MI:SS')";
        }

        return $oracleStr;
    }

    /**
     * Toggles SELECT result for DATE fields for the given connection (whether just date is returned or time as well)
     *
     * @author  Yuriy Akopov
     * @date    2013-10-10
     *
     * @param   Zend_Db_Adapter_Oracle  $db
     * @param   bool                    $enableIsoDateTime
     */
    public static function setIsoDateOutput(Zend_Db_Adapter_Oracle $db, $enableIsoDateTime) {
        // @todo: the format values below should probably come from application db config
        if ($enableIsoDateTime) {
            $format = 'yyyy-mm-dd hh24:mi:ss';
        } else {
            $format = 'dd/MON/yyyy';
        }

        $db->query($db->quoteInto("ALTER SESSION SET nls_date_format = ?", $format));
    }

    /**
     * As Oracle has limitation on number or values listed in IN (...) clause, this functions runs the given query in pages
     * and 'glues' the results
     *
     * Another method to get over it is to create a temporary table with all the values and join on it - consider that as well
     * before using this method
     *
     * @author  Yuriy Akopov
     * @date    2013-10-14
     *
     * @param   Zend_Db_Select  $select     query to run (without the IN condition)
     * @param   string          $condition  constraint to add to the query without IN (e.g. to get "value IN (...)" supply "value"
     * @param   array           $in         values for IN clause
     * @param   array           $bind       query parameters, if any
     *
     * @return  array
     */
    public static function selectLongIn(Zend_Db_Select $select, $condition, array $in, $bind = array()) {
        foreach ($in as $key => $value) {
            $in[$key] = $select->getAdapter()->quote($value);
        }

        $rows = array();

        $start = 0;
        $step = self::MAX_IN;

        while (count($sliceIn = array_slice($in, $start, $step))) {
            $start += $step;

            $sliceSelect = clone($select);
            $sliceSelect->where($condition . ' IN (' . implode(',', $sliceIn) . ')');

            $sliceRows = $sliceSelect->getAdapter()->fetchAll($sliceSelect, $bind);

            $rows = array_merge($rows, $sliceRows);
        }

        return $rows;
    }

    /**
     * Prints out a given query for debug purposes
     *
     * @author  Yuriy Akopov
     * @date    2014-01-28
     *
     * @param   Zend_Db_Select  $select
     * @param   array $bindings
     * @param   bool    $exit
     */
    public static function debugPrintQuery(Zend_Db_Select $select, $bindings = null, $exit = true) {
        if (!in_array($_SERVER['APPLICATION_ENV'], array('development', 'testing'))) {
            return; // do nothing if we are not in a testing environment
        }

        $lines = array(
            '<hr />',
            $select->assemble()
        );

        if (!is_null($bindings)) {
            $lines[] = '<br />';
            $lines[] = '<b>Bindings:</b>: ' . print_r($bindings, true);
        }

        $lines[] = '<hr />';

        print implode(PHP_EOL, $lines);

        if ($exit) {
            die;
        }
    }


    /**
     * Escapes wildcard symbols in values supposed to be used in LIKE statements
     * Returns LIKE statement
     *
     * Based on http://stackoverflow.com/a/3683868/454266 - read it to learn why it is needed
     *
     * @author  Yuriy Akopov
     * @date    2014-02-20
     *
     * @param   Zend_Db_Adapter_Abstract    $db
     * @param   string                      $value
     * @param   string                      $direction
     * @param   string                      $escapeChar
     *
     * @return  string
     * @throws  Exception
     */
    public static function escapeLike(Zend_Db_Adapter_Abstract $db, $value, $direction = self::ESCAPE_LIKE_BOTH, $escapeChar = '=') {
        if (strlen($db->quote($escapeChar)) !== 3) {
            // actually, we can deal with any character, but that's completely avoidable, so let's keep it simpler
            // it's already confusing enough
            throw new Exception("Ambiguous escape character suggested");
        }

        $specialChars = array(
            $escapeChar,
            '_',
            '%'
        );

        $escapedChars = array();
        foreach ($specialChars as $sc) {
            $escapedChars[] = $escapeChar . $sc;
        }

        $value = str_replace($specialChars, $escapedChars, $value);
        switch ($direction) {
            case self::ESCAPE_LIKE_BOTH:
                $value = '%' . $value . '%';
                break;

            case self::ESCAPE_LIKE_LEFT:
                $value = '%' . $value;
                break;

            case self::ESCAPE_LIKE_RIGHT:
                $value = $value . '%';
                break;

            default:
                throw new Exception("Invalid direction specified for LIKE statement");
        }

        $statement = $db->quoteInto(" LIKE ? ESCAPE '" . $escapeChar . "'", $value);

        return $statement;
    }

    /**
     * Converts instantiated / scalar database date into an ISO string
     *
     * @param   Shipserv_Oracle_Util_DbTime|string  $dbDate
     *
     * @return  null|string
     */
    public static function dbDateToIso($dbDate) {
        if ($dbDate instanceof Shipserv_Oracle_Util_DbTime) {
            return $dbDate->format('Y-m-d H:i:s');
        }

        if (strlen($dbDate)) {
            $datetime = new DateTime($dbDate);
            return $datetime->format('Y-m-d H:i:s');
        }

        return null;
    }

    /**
    * Get SYS_REFCURSOR result of oracle function call 
    * Added by Attila O 08/07/2016
    * @param Zend_Db_Adapter_Oracle $conn 
    * @param string $sql
    * @param array $bindParams (key value parirs of params to bind to SQL)
    * @return array;
    */
    public static function executeOracleFunctionReturningCursor($db, $sql, $bindParams=array())
    {
        //create connection
        $conn = $db->getConnection();

        if (!$conn) {
            $e = oci_error();
            trigger_error(htmlentities($e['message']), E_USER_ERROR);
        }

        $stid = oci_parse($conn, 'begin :cursor := '.$sql.'; end;');
        $p_cursor = oci_new_cursor($conn);

        //Send parameters variable  value  lenght
        oci_bind_by_name($stid, ':cursor', $p_cursor, -1, OCI_B_CURSOR);
        foreach ($bindParams as $key => $value) {
            $passedString = (string)$value;
            oci_bind_by_name($stid, ':'.$key, $passedString, strlen($passedString));
        }

        // Execute Statement
        oci_execute($stid);
        oci_execute($p_cursor, OCI_DEFAULT);

        oci_fetch_all($p_cursor, $cursor, null, null, OCI_FETCHSTATEMENT_BY_ROW);
        return $cursor;
    }
    
    /**
     * Zend registry caching query for fetchOne
     * @param string $key
     * @param string $sql
     * @param array $params
     * @param string $dbName
     * @throws Shipserv_Helper_Database_Exception
     * @return array
     */
    public static function registryFetchOne($key, $sql, $params = array(), $dbName = 'sservdba')
    {
    	$regKey = md5($key . '_' . $sql . '_' . serialize($params));
    	
    	if (Zend_Registry::isRegistered($regKey)) {
    		return Zend_Registry::get($regKey);
    	} else {
    		switch ($dbName) {
    			case 'sservdba':
    				$db = self::getDb();
    				break;
    			case 'ssreport2':
    				$db = self::getSsreport2Db();
    				break;
       			default:
       				throw new Shipserv_Helper_Database_Exception('Selected database "' . $dbName. '" does not exists');
    				break;
    		}
    		
    		$result = $db->fetchOne($sql, $params);
    		Zend_Registry::set($regKey, $result);

    		return $result;
    	}
    }
    
    /**
     * Zend registry caching query for fertchRow
     * @param string $key
     * @param string $sql
     * @param array $params
     * @param string $dbName
     * @throws Shipserv_Helper_Database_Exception
     * @return array
     */
    public static function registryFetchRow($key, $sql, $params = array(), $dbName = 'sservdba')
    {
    	$regKey = md5($key . '_' . $sql . '_' . serialize($params));
    	
    	if (Zend_Registry::isRegistered($regKey)) {
    		return Zend_Registry::get($regKey);
    	} else {
    		switch ($dbName) {
    			case 'sservdba':
    				$db = self::getDb();
    				break;
    			case 'ssreport2':
    				$db = self::getSsreport2Db();
    				break;
    			default:
    				throw new Shipserv_Helper_Database_Exception('Selected database "' . $dbName. '" does not exists');
    				break;
    		}
    		
    		$result = $db->fetchRow($sql, $params);
    		Zend_Registry::set($regKey, $result);
    		
    		return $result;
    	}
    }
    
    /**
     * Zend registry caching query for fertchAll
     * @param string $key
     * @param string $sql
     * @param array $params
     * @param string $dbName
     * @param bool $forceReread
     *
     * @throws Shipserv_Helper_Database_Exception
     * @return array
     */
    public static function registryFetchAll($key, $sql, $params = array(), $dbName = 'sservdba', $forceReread = false)
    {
    	$regKey = md5($key . '_' . $sql . '_' . serialize($params));
    	
    	if (Zend_Registry::isRegistered($regKey) && $forceReread === false) {
    		return Zend_Registry::get($regKey);
    	} else {
    		switch ($dbName) {
    			case 'sservdba':
    				$db = self::getDb();
    				break;
    			case 'ssreport2':
    				$db = self::getSsreport2Db();
    				break;
    			default:
    				throw new Shipserv_Helper_Database_Exception('Selected database "' . $dbName. '" does not exists');
    				break;
    		}
    		
    		$result = $db->fetchAll($sql, $params);
    		Zend_Registry::set($regKey, $result);
    		
    		return $result;
    	}
    }
    
}
