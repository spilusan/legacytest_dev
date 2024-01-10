<?php

/**
 * Class for reading the Certification data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_Certifications extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}

	/**
	 * Fetches membership from Oracle by $id
	 *
	 * @access public
	 * @param string $id
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetch ($id, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT CO.CO_NAME AS BROWSE_PAGE_NAME, CO.CO_ID,';
		$sql.= '       CO.CO_DESCRIPTION, CO.CO_LOGO_PATH, CO.QO_WEBSITE';
		$sql.= '  FROM Certification_ORGANIZATION CO';
		$sql.= ' WHERE CO.CO_ID = :id';

		$sqlData = array('id' => $id);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'COID_'.$id.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result[0];
	}


}