<?php

/**
 * Class for reading the Membership data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_Memberships extends Shipserv_Oracle
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
		$sql = 'SELECT QO.QO_NAME AS BROWSE_PAGE_NAME, QO.QO_ID,';
		$sql.= '       QO.QO_DESCRIPTION, QO.QO_LOGO_PATH, QO.QO_WEBSITE';
		$sql.= '  FROM QUALITY_ORGANIZATION QO';
		$sql.= ' WHERE QO.QO_ID = :id';

		$sqlData = array('id' => $id);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'QOID_'.$id.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result[0];
	}

	/**
	 * Updates Logo Image
	 *
	 * @param string $imageName
	 * @param integer $membershipId
	 * @return boolean
	 */
	public function updateImage ($imageName,$qoId)
	{
		$sql = "UPDATE QUALITY_ORGANIZATION SET";
		$sql.= " QO_LOGO_PATH = :imageName";
		$sql.= " WHERE QO_ID=:qoId";

		$sqlData = array(
			'imageName'	=> $imageName,
			'qoId' => $qoId
		);
		$this->db->query($sql,$sqlData);

		

		$key = $this->memcacheConfig->client->keyPrefix . 'QOID_'.$qoId.
			       $this->memcacheConfig->client->keySuffix;

		$this->purgeCache($key);

		return true;
	}
}