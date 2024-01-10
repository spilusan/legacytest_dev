<?php

/**
 * Class for reading buyer organisations data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_BuyerOrganisations extends Shipserv_Oracle
{
	public function __construct (&$db = "" )
	{
		if( $db == "" ) $db = $this->getDb();
		parent::__construct($db);
	}

	/**
	 * Fetches a list of organisations,
	 *
	 * @access public
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchBuyerOrganisations ($useCache = true, $cacheTTL = 3600)
	{
		$sql = "SELECT BYO.BYO_ORG_CODE, BYO.BYO_NAME";
		$sql.= " FROM BUYER_ORGANISATION BYO";
		$sql.= " WHERE UPPER(BYO.BYO_NAME) not like '%TEST%' and UPPER(BYO.BYO_NAME) not like '%DEMO%'";
		$sql.= " ORDER BY BYO.BYO_NAME ASC";

		$sqlData = array();

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BUYERORGANIZATIONSLIST'.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}


	/**
	 * Fetches a list of organisations by name.
	 * Updated: fetches only canonical buyer organisations.
	 *
	 * @access public
	 * @param string $name Search string
	 * @return array
	 */
	public function fetchBuyerOrganisationsByName ($name)
	{

		$sql = "SELECT BYO.BYO_ORG_CODE, BYO.BYO_NAME";
		$sql.= " FROM BUYER_ORGANISATION BYO";
		$sql.= " WHERE UPPER(BYO.BYO_NAME) LIKE :name";
		$sql.= " AND BYO.BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)";
		$sql.= " ORDER BY BYO.BYO_NAME ASC";

		$sqlData = array('name'        => '%'.strtoupper($name).'%');

		return  $this->db->fetchAll($sql, $sqlData);
	}

	/**
	 * Fetches a list of organisations.
	 *
	 * NOTE: this seems like a silly return type (a list of rows) for a method that fetches one ID?? Suggest fetchBuyerOrgById() instead.
	 *
	 * @access public
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchBuyerOrganisationById ($buyerOrganisationId, $useCache = true, $cacheTTL = 3600)
	{
		$sql = "SELECT *";
		$sql.= " FROM BUYER_ORGANISATION BYO";
		$sql.= " WHERE BYO.BYO_ORG_CODE = :buyerOrganisationId";

		$sqlData = array('buyerOrganisationId'	=> $buyerOrganisationId);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BUYERORGANISATION'.$buyerOrganisationId.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	/**
	 * Fetches a list of organisations by ids
	 *
	 * @access public
	 * @param array $idsArray Array with ids of buyer organisations to fetch
	 * @return array
	 */
	public function fetchBuyerOrganisationsByIds ($idsArray, $skipNormalisationCheck = false)
	{
		$sql = "SELECT *";
		$sql.= " FROM BUYER_ORGANISATION a";
		$sql.= " LEFT JOIN PAGES_COMPANY b ON a.BYO_ORG_CODE = b.PCO_ID AND b.PCO_TYPE = 'BYO'";
		$sql.= " WHERE a.BYO_ORG_CODE IN (" . $this->arrToSqlList($idsArray) . ")";

		if( $skipNormalisationCheck == false )
		$sql.= " AND a.BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)";
		$sql.= " ORDER BY a.BYO_NAME ASC";
		
		//$res = $this->db->fetchAll($sql);
		$res = Shipserv_Helper_Database::registryFetchAll(__CLASS__ . '_' . __FUNCTION__, $sql);

		return $res;
	}

	/**
	 * @access public
	 * @param int $id buyer org code
     * @param bool $skipNormalisation
	 * @return array
	 * @throws Exception if ID not found
	 */
	public function fetchBuyerOrgById ($id, $skipNormalisation = false)
	{
		$bArr = $this->fetchBuyerOrganisationsByIds(array($id), $skipNormalisation);

		return $bArr[0] ?? null;
	}

	/**
	 * Transforms values of input array into a quoted list suitable for
	 * a SQL in clause: e.g. 3, 'str val', ...
	 *
	 * @return string
	 */
	private function arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item)
		{
			$sqlArr[] = $this->db->quote($item);
		}
		if (!$sqlArr) $sqlArr[] = 'NULL';
		return join(', ', $sqlArr);
	}

    public function getSupplierTraded($buyerOrgCode)
    {
    	$sql = "
			SELECT DISTINCT
			  s.spb_branch_code
			  , s.spb_name
			FROM
			  rfq r JOIN supplier s
			    ON (s.spb_branch_code=r.spb_branch_code)
			WHERE
			  byb_branch_code IN
			  (SELECT DISTINCT byb_branch_code FROM buyer WHERE byb_byo_org_code=:buyerOrgCode)
			ORDER BY
			  s.spb_name ASC
    	";
    	return $this->getSsreport2Db()->fetchAll($sql, array("buyerOrgCode" => $buyerOrgCode));
    }

    
    public function updateName($id, $name)
    {
        $name = trim((String) $name);
        $id = (Int) $id;
        if (!strlen($name) || !$id) {
            throw new Exception("Shipserv_Oracle_BuyerOrganisations::updateName need an Int as id and a non empty String as name. id='$id' name='$name' received instead");
        }
        $this->db->query('UPDATE buyer_organisation SET byo_name = :name WHERE byo_org_code = :id', array('name' => $name, 'id' => $id));
    }
    
	/**
	 * Update the Satus af SPR KP reports access right
	 * @param int $id
	 * @param int $status
	 * @throws Exception
	 */
    public function updateSprAccessStatus($id, $status)
    {
    	$status = ((int)$status === 1) ? 1 : 0; 
    	$id = (Int)$id;
    	if (!$id) {
    		throw new Exception("Shipserv_Oracle_BuyerOrganisations::updateSprAccessStatus need an Int as id and status as 0 or 1");
    	}
    	$this->db->query('UPDATE buyer_organisation SET byo_access_kpi_sp = :status WHERE byo_org_code = :id', array('status' => $status, 'id' => $id));
    }
}
