<?php

/**
 * Class for reading the Ports data from Oracle
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Countries extends Shipserv_Oracle
{
    const
        TABLE_NAME          = 'COUNTRY',
        COL_CODE_COUNTRY    = 'CNT_COUNTRY_CODE',
        COL_CODE_CONTINENT  = 'CNT_CON_CODE',
        COL_NAME_COUNTRY    = 'CNT_NAME',
        COL_NAME_RESTRICTED = 'IS_RESTRICTED'
    ;

    const
        CONTINENT_EUROPE        = 'EU',
        CONTINENT_NORTH_AMERICA = 'NA',
        CONTINENT_ASIA          = 'AS',
        CONTINENT_AFRICA        = 'AF',
        CONTINENT_SOUTH_AMERICA = 'SA',
        CONTINENT_OCEANIA       = 'OC',
        CONTINENT_ANTARCTICA    = 'AN'
    ;

    /**
     * Continents for grouping countries into, in the desired order
     *
     * @var array
     */
    protected static $continents = array(
        self::CONTINENT_EUROPE        => 'Europe',
        self::CONTINENT_NORTH_AMERICA => 'North America',
        self::CONTINENT_ASIA          => 'Asia',
        self::CONTINENT_AFRICA        => 'Africa',
        self::CONTINENT_SOUTH_AMERICA => 'South America',
        self::CONTINENT_OCEANIA       => 'Oceania',
        self::CONTINENT_ANTARCTICA    => 'Antarctica'
    );

    /**
     * All rows from the table to search for countries quickly
     *
     * @var array|null
     */
    protected static $countryRows = null;

	public function __construct (&$db = null)
	{
		if( $db == null )
		$db = $this->getDb();
		parent::__construct($db);
	}
	
	/**
	 * Fetches all countries and places them in an associative array
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchAllCountries($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT * FROM COUNTRY ORDER BY CNT_NAME ASC';
		
		if ($useCache) {
			$key = $this->memcacheConfig->client->keyPrefix . 'ALLCOUNTRIES_' . $this->memcacheConfig->client->keySuffix;
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		} else {
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}

    /**
     * Fetch only non restricted countries
     *
     * @param bool $useCache
     * @param int $cacheTTL
     * @return array|string
     */
    public function fetchNonRestrictedCountries($useCache = true, $cacheTTL = 86400)
    {
        $sql = 'SELECT * FROM ' .self::TABLE_NAME . ' WHERE ' . self::COL_NAME_RESTRICTED . ' = 0 ORDER BY CNT_NAME ASC';

        if ($useCache) {
            $key = $this->memcacheConfig->client->keyPrefix . 'ALLNONRESCOUNTRIES_' . $this->memcacheConfig->client->keySuffix;
            $result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
        } else {
            $result = $this->db->fetchAll($sql);
        }

        return $result;
    }

	
	/**
	 * Fetches country for a specific code
	 *
	 * @access public
	 * @param string $countryCode The 2-character code for the country to be fetched
	 * @param boolean $cache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchCountryByCode ($countryCode, $useCache = true, $cacheTTL = 86400)
	{
		
		$sql = 'SELECT *';
		$sql.= '  FROM COUNTRY';
		$sql.= ' WHERE CNT_COUNTRY_CODE = :countryCode';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTCODE_'.
			       $countryCode . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('countryCode' => strtoupper($countryCode)),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('countryCode' => $countryCode));
		}
		
		return $result;
	}
	
	public function fetchCountriesByCode (array $countryCodes)
	{
		$ccSql = $this->quoteArr($countryCodes);
		$sql = "SELECT * FROM COUNTRY WHERE CNT_COUNTRY_CODE IN ($ccSql)";
		return $this->db->fetchAll($sql);
	}
	
	/**
	 * Quote array of values for SQL.
	 *
	 * @return string
	 */
	private function quoteArr (array $vals)
	{
		$quotedArr = array();
		foreach ($vals as $v) $quotedArr[] = $this->db->quote($v);
		
		if ($quotedArr) $vSql = join(', ', $quotedArr);
		else $vSql = 'NULL';
		
		return $vSql;
	}
	
	/**
	 * Fetches all countries based on name or synonym
	 * 
	 * @access public
	 * @param string $name
	 * @param boolean $cache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchCountriesByName ($name, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT DISTINCT(cnt.cnt_country_code), cnt.cnt_con_code,';
		$sql.= '       cnt.cnt_name, cs.cnt_syn_synonym';
		$sql.= '  FROM COUNTRY cnt';
		$sql.= '  LEFT OUTER JOIN country_synonym cs';
		$sql.= '       ON cnt.cnt_country_code = cs.cnt_syn_cnt_country_code AND UPPER(cs.cnt_syn_synonym) LIKE :name';
		$sql.= ' WHERE UPPER(cnt.CNT_NAME) LIKE :name';
		$sql.= '    OR UPPER(cs.cnt_syn_synonym) LIKE :name';
		$sql.= ' ORDER BY CNT_NAME ASC, cs.cnt_syn_synonym ASC';
		
		$name = strtoupper($name);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'NAMECOUNTRIES_'.
			       $name . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('name' => $name.'%'),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('name' => $name.'%'));
		}
		
		return $result;
	}

    /**
     * Fetch non restricted countryes by name
     *
     * @param string $name
     * @param bool $useCache
     * @param int $cacheTTL
     * @return array|string
     */
    public function fetchNonRestrictedCountriesByName ($name, $useCache = false, $cacheTTL = 86400)
    {
        $sql = 'SELECT DISTINCT(cnt.cnt_country_code), cnt.cnt_con_code,';
        $sql.= '       cnt.cnt_name, cs.cnt_syn_synonym';
        $sql.= '  FROM ' .self::TABLE_NAME . ' cnt';
        $sql.= '  LEFT OUTER JOIN country_synonym cs';
        $sql.= '       ON cnt.cnt_country_code = cs.cnt_syn_cnt_country_code AND UPPER(cs.cnt_syn_synonym) LIKE :name';
        $sql.= ' WHERE UPPER(cnt.CNT_NAME) LIKE :name';
        $sql.= ' AND cnt.' . self::COL_NAME_RESTRICTED . ' = 0';
        $sql.= '    OR UPPER(cs.cnt_syn_synonym) LIKE :name';
        $sql.= ' ORDER BY CNT_NAME ASC, cs.cnt_syn_synonym ASC';

        $name = strtoupper($name);

        if ($useCache) {
            $key = $this->memcacheConfig->client->keyPrefix . 'NAMENONRESCOUNTRIES_' . $name . $this->memcacheConfig->client->keySuffix;
            $result = $this->fetchCachedQuery($sql, array('name' => $name.'%'), $key, $cacheTTL);
        } else {
            $result = $this->db->fetchAll($sql, array('name' => $name.'%'));
        }

        return $result;
    }
	
	/**
	 *
	 *
	 *
	 *
	 *
	 */
	public function fetchCountriesByContinent ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT CNT.CNT_COUNTRY_CODE, CNT.CNT_NAME,';
		$sql.= '       CON.CON_CODE, CON.CON_NAME,';
		$sql.= '       (SELECT COUNT(*)
						FROM PAGES_BROWSE_LINK PBL
					   WHERE pbl_prt_port_code IS NOT NULL
					     AND PBL.PBL_CNT_COUNTRY_CODE = CNT.CNT_COUNTRY_CODE
					   GROUP BY pbl_cnt_country_code) AS nonNullPorts';
		$sql.= '  FROM COUNTRY CNT, CONTINENT CON';
		$sql.= ' WHERE CNT.CNT_CON_CODE = CON.CON_CODE';
		$sql.= '   AND CNT.CNT_COUNTRY_CODE IN (SELECT DISTINCT(PBL_CNT_COUNTRY_CODE) FROM PAGES_BROWSE_LINK)';
		$sql.= ' ORDER BY CON.CON_NAME ASC, CNT.CNT_NAME ASC';
		
		$sqlData = array();
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CONCOUNTRIES_'.
			       $name . $this->memcacheConfig->client->keySuffix;
			
			$memcache = new Memcache();
			$memcache->connect($this->memcacheConfig->server->host,
							   $this->memcacheConfig->server->port);
			
			$cacheAlive = true;
			if (!$result = $memcache->get($key))
			{
				$result = $this->db->fetchAll($sql, $sqlData);
				
				$continents = array();
				foreach ($result as $country)
				{
					
					$continents[$country['CON_CODE']]['name'] = $country['CON_NAME'];
					$continents[$country['CON_CODE']]['countries'][$country['CNT_COUNTRY_CODE']] = array('name' => $country['CNT_NAME'],
																										 'hasPortSuppliers' => ($country['NONNULLPORTS']) ? true : false);
				}
				
				$result = $continents;
				$memcache->set($key, $continents, false, $cacheTTL);
			}
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
			
			$continents = array();
			foreach ($result as $country)
			{
				$continents[$country['CON_CODE']]['name'] = $country['CON_NAME'];
				$continents[$country['CON_CODE']]['countries'][$country['CNT_COUNTRY_CODE']] = array('name' => $country['CNT_NAME'],
																									 'hasPortSuppliers' => ($country['NONNULLPORTS']) ? true : false);
			}
			
			$result = $continents;
		}
		
		return $result;
	}
	
	/**
	 * Fetches all countries with the IS_POPULAR flag set to 1
	 * 
	 * @access public
	 * @param string $name
	 * @param boolean $cache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchPopularCountries ($useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM COUNTRY';
		$sql.= ' WHERE IS_POPULAR = 1';
		$sql.= ' ORDER BY CNT_NAME ASC';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'POPULARCOUNTRIES_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	/**
	 * Fetches the top countries from Oracle (as defined in the PAGES_TOP_COUNTRY table)
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchTopCountries ($useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT PTC.PCO_CNT_CODE, PTC.PCO_RANK, PTC.PCO_DISPLAY_TITLE';
		$sql.= '  FROM PAGES_TOP_COUNTRY PTC';
		$sql.= ' ORDER BY PTC.PCO_RANK ASC';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'TOPCOUNTRIES_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}

    /**
     * Returns array easy to build a grouped dropdown on the page
     *
     * @author  Yuriy Akopov
     * @date    2013-11-22
     * @story   S8766
     *
     * @return  array
     */
    public function getGroupedListForSelect() {
        $rows = $this->fetchAllCountries();

        $groups = array();
        foreach (self::$continents as $code => $name) {
            $groups[$name] = array();
        }

        foreach ($rows as $row) {
            $continent = $row[self::COL_CODE_CONTINENT];
            $continentName = self::$continents[$continent];

            $groups[$continentName][$row[self::COL_CODE_COUNTRY]] = $row[self::COL_NAME_COUNTRY];
        }

        foreach ($groups as $name => $items) {
            if (empty($items)) {
                unset($groups[$name]);
            }
        }

        return $groups;
    }

    /**
     * Returns country table mapped to country codes for countries quick access
     *
     * @author  Yuriy Akopov
     * @date    2013-11-22
     * @story   S8766
     *
     * @return  array
     */
    public function getCountryRows() {
        if (!is_null(self::$countryRows)) {
            return self::$countryRows;
        }

        $rows = $this->fetchAllCountries();
        self::$countryRows = array();

        foreach ($rows as $countryInfo) {
            self::$countryRows[$countryInfo[self::COL_CODE_COUNTRY]] = $countryInfo;
        }

        return self::$countryRows;
    }

    /**
     * Returns a row from the table for the given country
     *
     * @author  Yuriy Akopov
     * @date    2013-11-22
     * @story   S8766
     *
     * @param   string  $code
     *
     * @return  array|null
     */
    public function getCountryRow($code) {
        $countryRows = $this->getCountryRows();
        if (array_key_exists($code, $countryRows)) {
            return $countryRows[$code];
        }

        return null;
    }
}