<?php

/**
 * Abstract class for objects connecting to Oracle
 *
 * @abstract
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
abstract class Shipserv_Oracle extends Shipserv_Memcache
{
	const SSERVDBA  = 'sservdba';
	const SSREPORT2 = 'ssreport2';
	const STANDBY   = 'standby';
	
	/**
	 * Recommended cache TTL
	 */
	const MEMCACHE_TTL = 86400;
	
	/**
	 * Oracle DB resource
	 *
	 * @access protected
	 * @var object
	 */
	protected $db;
	
	/**
	 * Config detail for the website
	 * 
	 * @access protected
	 * @var object
	 */
	protected static $config = false;
	
	/**
	 * Sets the DB resource
	 *
	 * @access public
	 * @param object $db
	 */
	public function __construct (&$db = null)
	{
		if( $this->db == null )
		{
			$this->db = self::getDb();
		}
		else 
		{
			$this->db = $db;
		}
		
		// stored in static variable to be reused during the execution to speed up
		// @author Elvir <eleonard@shipserv.com>
		if( parent::$config == false )
		{
			$config = Zend_Registry::get('config');
			parent::$config = $config;
		}
		else 
		{
			$config = parent::$config;
		}

		$this->memcacheConfig = $config->memcache;
		
		$this->useMemcache = (bool) $this->memcacheConfig->enable;
	}
	
	/**
	 * Performs a query by first checking memcache, and then only connecting
	 * to Oracle if necessary
	 * 
	 * @access protected
	 * @param string $sql
	 * @param string $key
	 * @param int $cacheTTL
	 */
	protected function fetchCachedQuery ($sql, $sqlData, $key, $cacheTTL = self::MEMCACHE_TTL, $dbName = "", $debug = false )
	{
		
		// Note: $memcache could be null
		$memcache = $this::getMemcache();

		if( $dbName != "" )
		{
			$db = self::getDbByName($dbName);
		}
		else
		{
			$db = $this->db;
			if( $db == null )
			{
				$db = self::getDbByName('sservdba');
			}
		}
		
		if( $debug === true )
		{
			echo "<hr />";
			echo $sql;
			echo $dbName;
			print_r($db);
			die();
		}
		
		
		if ($memcache)
		{
			// Try to retrieve query from Memcache
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}
		
		// Otherwise, fetch from DB & save to cache
		if( $sqlData === null || count($sqlData) == 0 )
		{
			$result = $db->fetchAll($sql);
		}
		else
		{
			$result = $db->fetchAll($sql, $sqlData);
		}
		
		if ($memcache) $memcache->set($key, $result, false, $cacheTTL);
		
		return $result;
	}
	
	/**
	 * Fetch Query
	 * 
	 * @param string $sql
	 * @param array $sqlData
	 * @param string $dbName
	 * @param bool $debug
	 * 
	 * @return array
	 * 
	 */
	protected function fetchQuery($sql, $sqlData = null, $dbName = "", $debug = false )
	{
		if( $dbName != "" ) {
			$db = self::getDbByName($dbName);
		} else {
			$db = $this->db;
			if( $db == null )
			{
				$db = self::getDbByName('sservdba');
			}
		}
		
		if( $debug === true ) {
			echo "<hr />";
			echo $sql;
			echo $dbName;
			print_r($db);
			die();
		}
		
		if( $sqlData === null || count($sqlData) == 0 )
		{
			$result = $db->fetchAll($sql);
		}
		else
		{
			$result = $db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}

	public static function getDbByName( $name )
	{
		if( $name == 'sservdba' )
		{
			return self::getDb();
		}
		else if( $name == "ssreport2" )
		{
			return self::getSsreport2Db();
		}
		else if( $name == "standby" )
		{
			return self::getStandbyDb();			
		}
	}

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    public static function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	public static function getStandbyDb()
	{
		return Shipserv_Helper_Database::getStandByDb();
	}

	public static function getSsreport2Db()
	{
		return Shipserv_Helper_Database::getSsreport2Db();
	}
}