<?php

/**
 * Class for reading the Ports data from Oracle
 *
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Ports extends Shipserv_Oracle
{
    const
        TABLE_NAME = 'PORT',

        COL_CODE      = 'PRT_PORT_CODE',
        COL_COUNTRY   = 'PRT_CNT_COUNTRY_CODE',
        COL_NAME      = 'PRT_NAME',
        COL_LATITUDE  = 'PRT_LATITUDE',
        COL_LONGITUDE = 'PRT_LONGITUDE'
    ;

    const
        COL_DISTANCE = 'DISTANCE'
    ;

	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	/**
	 * Fetches all ports and places them in an associative array
	 *
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchAllPorts ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM PORT';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ALLPORTS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	public function fetchAllPortsGroupedByCountry()
	{
		// store the end result to memcached
		$key = $this->memcacheConfig->client->keyPrefix . 'SVR_PORT' . $this->memcacheConfig->client->keySuffix;
		
		$memcache = $this::getMemcache();
		
		if ($memcache)
		{
			// Try to retrieve query from Memcache
			$result = $memcache->get($key);
			if ($result !== false) return $result;
		}		
		
		
		$sql = ' SELECT cnt_country_code, cnt_name, cnt_con_code FROM country ORDER BY UPPER( CNT_NAME ) ASC';

		// get list of countries
		$countries = $this->db->fetchAll($sql, array());
		foreach( $countries as $country )
		{
			
			$sql = 'SELECT * FROM port WHERE prt_cnt_country_code = :countryCode ORDER BY UPPER( PRT_NAME ) ASC';
			$ports = $this->db->fetchAll($sql, array('countryCode' => $country['CNT_COUNTRY_CODE'] ) );
			
			// country
			$data[] = array( 'type' => 'country', 'DEPTH' => '1', 'id' => $country['CNT_COUNTRY_CODE'], 'PARENT_ID' => $country['CNT_COUNTRY_CODE'], 'name' => $country['CNT_NAME'], 'continent' => $country['CNT_CON_CODE']);
			
			// port
			foreach( $ports as $port )
			{
				$data[] = array( 'type' => 'port', 'DEPTH' => '2', 'id' => $port['PRT_PORT_CODE'], 'name' => $port['PRT_NAME'], 'PARENT_ID' => $country["CNT_COUNTRY_CODE"], 'continent' => $country['CNT_CON_CODE']);
			}

			$data[] = array( 'type' => 'port', 'DEPTH' => '2', 'id' => $country["CNT_COUNTRY_CODE"] . '-OTHER', 'name' => 'Other', 'PARENT_ID' => $country["CNT_COUNTRY_CODE"], 'continent' => $country['CNT_CON_CODE']);
		}
		
		if ($memcache) $memcache->set($key, $data, false, 86400);
		
		
		return $data;
	}

    /**
     * Return the list of all ports grouped by country where the country is not restricted
     *
     * @return array|string
     */
    public function fetchAllNonRestrictedPortsGroupedByCountry()
    {
        // store the end result to memcached
        $data = [];

        $key = $this->memcacheConfig->client->keyPrefix . 'SVR_NONRES_PORT' . $this->memcacheConfig->client->keySuffix;

        $memcache = $this::getMemcache();

        if ($memcache) {
            // Try to retrieve query from Memcache
            $result = $memcache->get($key);
            if ($result !== false) {
                return $result;
            }
        }

        $sql = 'SELECT
                  cnt_country_code,
                  cnt_name,
                  cnt_con_code
                FROM 
                  country 
                WHERE 
                  IS_RESTRICTED = 0
                ORDER BY UPPER( CNT_NAME ) ASC';

        // get list of countries
        $countries = $this->db->fetchAll($sql, array());
        foreach ($countries as $country) {
            $sql = 'SELECT * FROM port WHERE prt_cnt_country_code = :countryCode AND PRT_IS_RESTRICTED = 0 ORDER BY UPPER( PRT_NAME ) ASC';
            $ports = $this->db->fetchAll($sql, array('countryCode' => $country['CNT_COUNTRY_CODE']));

            // country
            $data[] = array('type' => 'country', 'DEPTH' => '1', 'id' => $country['CNT_COUNTRY_CODE'], 'PARENT_ID' => $country['CNT_COUNTRY_CODE'], 'name' => $country['CNT_NAME'], 'continent' => $country['CNT_CON_CODE']);

            // port
            foreach($ports as $port) {
                $data[] = array('type' => 'port', 'DEPTH' => '2', 'id' => $port['PRT_PORT_CODE'], 'name' => $port['PRT_NAME'], 'PARENT_ID' => $country["CNT_COUNTRY_CODE"], 'continent' => $country['CNT_CON_CODE']);
            }

            $data[] = array('type' => 'port', 'DEPTH' => '2', 'id' => $country["CNT_COUNTRY_CODE"] . '-OTHER', 'name' => 'Other', 'PARENT_ID' => $country["CNT_COUNTRY_CODE"], 'continent' => $country['CNT_CON_CODE']);
        }

        if ($memcache) {
            $memcache->set($key, $data, false, 86400);
        }

        return $data;
    }
	
	/**
	 * Fetches all ports for a given country code and places them in an
	 * associative array
	 * 
	 * @access public
	 * @param string $countryCode
	 * @param boolean $cache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchPortsByCountry ($countryCode, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM PORT';
		$sql.= ' WHERE PRT_CNT_COUNTRY_CODE = :countryCode';
		$sql.= ' ORDER BY PRT_NAME ASC';
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTPORTS_'.
			       $countryCode . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('countryCode' => $countryCode),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('countryCode' => $countryCode));
		}
		
		return $result;
	}
	
	public function fetchSupplierPorts ($countryCode, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM PORT';
		$sql.= ' WHERE PRT_CNT_COUNTRY_CODE = :countryCode';
		$sql.= '   AND PRT_PORT_CODE IN (SELECT DISTINCT(PBL_PRT_PORT_CODE) FROM PAGES_BROWSE_LINK)';
		$sql.= ' ORDER BY PRT_NAME ASC';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SUPPLIERPORTS_'.
			       $countryCode . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('countryCode' => $countryCode),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('countryCode' => $countryCode));
		}
		
		return $result;
	}
	
	/**
	 * Fetches all ports ... and places them in an
	 * associative array
	 * 
	 * @access public
	 * @param string $name
	 * @param boolean $useCache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchPortsByName ($name, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT DISTINCT(p.prt_port_code), p.prt_name, c.CNT_NAME, ';
		$sql.= '       ps.prt_syn_synonym';
		$sql.= '  FROM COUNTRY c, PORT p';
		$sql.= '  LEFT OUTER JOIN port_synonym ps';
		$sql.= '       ON p.PRT_PORT_CODE = ps.prt_syn_prt_port_code AND UPPER(ps.prt_syn_synonym) LIKE :name';
		$sql.= ' WHERE (UPPER(p.prt_name) LIKE :name OR UPPER(ps.prt_syn_synonym) LIKE :name)';
		$sql.= '   AND p.PRT_CNT_COUNTRY_CODE = c.CNT_COUNTRY_CODE';
		$sql.= ' ORDER BY p.prt_name ASC';
		
		$name = strtoupper($name);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'NAMEPORTS_'.
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
     * Fetches all ports ... and places them in an
     * associative array
     *
     * @access public
     * @param string $name
     * @param boolean $useCache Fetch from the cache if possible, and cache the result if cache is invalid
     * @param int $cacheTTL If using the cache, what TTL should be used?
     * @return array
     */
    public function fetchNonRestrictedPortsByName ($name, $useCache = true, $cacheTTL = 86400)
    {
        $sql = 'SELECT DISTINCT(p.prt_port_code), p.prt_name, c.CNT_NAME, ';
        $sql.= '       ps.prt_syn_synonym';
        $sql.= '  FROM COUNTRY c, PORT p';
        $sql.= '  LEFT OUTER JOIN port_synonym ps';
        $sql.= '       ON p.PRT_PORT_CODE = ps.prt_syn_prt_port_code AND UPPER(ps.prt_syn_synonym) LIKE :name';
        $sql.= ' WHERE (UPPER(p.prt_name) LIKE :name OR UPPER(ps.prt_syn_synonym) LIKE :name)';
        $sql.= '   AND p.PRT_CNT_COUNTRY_CODE = c.CNT_COUNTRY_CODE';
        $sql.= '   AND c.IS_RESTRICTED = 0';
        $sql.= '   AND p.PRT_IS_RESTRICTED = 0';
        $sql.= ' ORDER BY p.prt_name ASC';

        $name = strtoupper($name);

        if ($useCache)
        {
            $key = $this->memcacheConfig->client->keyPrefix . 'NAMEPORTS_'.
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
	 * Fetches a port by its code
	 * 
	 * @access public
	 * @param string $portCode
	 * @param boolean $useCache Fetch from the cache if possible, and cache the result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchPortByCode ($portCode, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM PORT';
		$sql.= ' WHERE PRT_PORT_CODE = :portCode';
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'PORTBYCODE_'.
			       $portCode . $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array('portCode' => $portCode),
											  $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, array('portCode' => $portCode));
		}
		
		return $result;
	}

    /**
     * Returns port raw table rows basing on the defined maximal distance from the given point
     *
     * @param   Myshipserv_GeoPoint $point
     * @param   float               $maxDistance
     *
     * @return array
     */
    public function getPortsByDistance(Myshipserv_GeoPoint $point, $maxDistance) {
        $db = $this->getDb();

        $formula = 'GEO_GET_DISTANCE(
            prt.' . self::COL_LATITUDE . ',
            prt.' . self::COL_LONGITUDE . ',
            ' . $point->getLatitude() . ',
            ' . $point->getLongitude() . '
        )';

        $selectDistance = new Zend_Db_Select($db);
        $selectDistance
            ->from(
                array('prt' => self::TABLE_NAME),
                array(
                    self::COL_CODE     => 'prt.' . self::COL_CODE,
                    self::COL_DISTANCE => new Zend_Db_Expr($formula)
                )
            )
            ->where('prt.' . self::COL_LATITUDE . ' IS NOT NULL')
            ->where('prt.' . self::COL_LONGITUDE . ' IS NOT NULL')
        ;

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('prt' => self::TABLE_NAME),
                'prt.*'
            )
            ->join(
                array('dst' => $selectDistance),
                'dst.' . self::COL_CODE . ' = prt.' . self::COL_CODE,
                'dst' . self::COL_DISTANCE
            )
            ->where('dst.' . self::COL_DISTANCE . ' <= ?', $maxDistance)
            ->order('dst.' . self::COL_DISTANCE . ' DESC')
        ;

        $rows = $db->fetchAll($select);
        return $rows;
    }

	/**
	 * Copied form Shipserv_Rfq by Yuriy Akopov on 2015-12-10
	 * Original code in Shipserv_Rfq not yet replaced by a call to this, because here the behaviour is different (a bit smarter)
	 *
	 * @param	string	$portStr
	 *
	 * @return	string|null
	 */
	public function getPortLabelByCode($portStr) {
		$adapterForCountry = new Shipserv_Oracle_Countries($this->db);

		// translate the delivery port
		$dataForPort = $this->fetchPortByCode(strtoupper($portStr));
		if (empty($dataForPort)) {
			return null; // port ID not recognised
		}

		$portName = $dataForPort[0]['PRT_NAME'];

		$tmp = explode("-", $portStr);
		$dataForCountry = $adapterForCountry->fetchCountriesByCode((array)$tmp[0]);
		if (empty($dataForCountry)) {
			return $portName; // country not recognised
		}

		return $portName . " " . $dataForCountry[0]['CNT_NAME'];
	}
}
