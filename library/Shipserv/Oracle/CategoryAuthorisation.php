<?php

class Shipserv_Oracle_CategoryAuthorisation extends Shipserv_Oracle{

	/**
	 * Retrieve records that match supplied filter
	 *
	 * @param array $filters
	 * @param boolean $useCache
	 * @param integer $cacheTTL
	 * @return array
	 */
	public function fetch($filters = array(), $page = 0, $pageSize = 100,  $useCache = false, $cacheTTL = 86400) {
		$key = "";
		$sql = "";

		if ($page > 0)
		{
			$sql .= 'SELECT * FROM (';
		}

		$sql .= 'SELECT SUC.*';

		if ($page > 0)
		{
			$sql .= ', ROW_NUMBER() OVER (ORDER BY SPB_NAME) R ';
		}

		$sql .= ' FROM SUPPLY_CATEGORY SUC ';

		if ($page > 0)
		{
			$sql .= ' LEFT JOIN SUPPLIER_BRANCH ON (SPB_BRANCH_CODE=SUPPLIER_BRANCH_CODE) ';
		}

		$sqlData = array();

		if (count($filters)>0)
		{
			$sql.= ' WHERE SUPPLIER_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)';

			foreach ($filters as $column=>$value)
			{

				$sql.= ' AND ';
				
				if (!is_null($value))
				{
					$sql .= $column.' = :'.$column."_FILTER";
					$sqlData[$column."_FILTER"] = $value;
				}
				else
				{
					$sql .= ' ('. $column.' IS NULL) ';
				}

				$key .= $column.$value;

			}
		}

		if ($page > 0)
		{
			$sql .= ')  WHERE R BETWEEN '.(($page-1)*$pageSize).' and '.($page*$pageSize);
		}



		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATEGORYAUTHS'.$key.
			       $this->memcacheConfig->client->keySuffix;


			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{

			$result = $this->db->fetchAll($sql,$sqlData);
		}

		return $result;
	}

	public function fetchAuths($filters = array(), $page = 1, $region = "", $name = "", $pageSize = 100) {
		$key = "";
		$sql = "";

		if ($page > 0)
		{
			$sql .= 'SELECT * FROM (';
		}

		$sql .= 'SELECT SUC.*';

		if ($page > 0)
		{
			$sql .= ', ROW_NUMBER() OVER (ORDER BY SPB_NAME) R, count(*) over () as totalCount ';
		}

		$sql .= ' FROM SUPPLY_CATEGORY SUC ';

		if ($page > 0)
		{
			$sql .= ' LEFT JOIN SUPPLIER_BRANCH ON (SPB_BRANCH_CODE=SUPPLIER_BRANCH_CODE) ';
			if ($region!="")
			{
				$sql .= ' INNER JOIN COUNTRY ON (SUPPLIER_BRANCH.SPB_COUNTRY=COUNTRY.CNT_COUNTRY_CODE) ';
			}
		}

		$sqlData = array();

		if (count($filters)>0)
		{
			$sql.= " WHERE SUPPLIER_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM) AND (IS_AUTHORISED IS NULL or IS_AUTHORISED='Y')";
			if ($region!="")
			{
				$sql.= " AND COUNTRY.CNT_CON_CODE='".$region."' ";
			}
			if ($name!="")
			{
				$sql.= " AND LOWER(SUPPLIER_BRANCH.SPB_NAME) LIKE '%".strtolower($name)."%' ";
			}
			foreach ($filters as $column=>$value)
			{
				$sql.= ' AND ';

				if (!is_null($value))
				{
					$sql .= $column.' = :'.$column."_FILTER";
					$sqlData[$column."_FILTER"] = $value;
				}
				else
				{
					$sql .= ' ('. $column.' IS NULL) ';
				}

			}
		}

		if ($page > 0)
		{
			$sql .= ')  WHERE R BETWEEN '.((($page-1)*$pageSize)+1).' and '.($page*$pageSize);
		}




		$result = $this->db->fetchAll($sql,$sqlData);

		return $result;
	}

	/**
	 * Remove records that match filters
	 *
	 * @param array $filers
	 * @return boolean
	 */
	public function remove ($filters = null)
	{
		$sqlData = array();

		$sql = 'DELETE FROM SUPPLY_CATEGORY ';

		if (is_array($filters) and count($filters) > 0 )
		{

			$sql.= ' WHERE ';
			$isFirst = true;
			foreach ($filters as $column=>$value)
			{
				if (!$isFirst){
					$sql.= ' AND ';
				}
				else
				{
					$isFirst = false;
				}
				if (!is_null($value))
				{
					$sql .= $column.' = :'.$column."_FILTER";
					$sqlData[$column."_FILTER"] = $value;
				}
				else
				{
					$sql .= $column.' IS NULL ';
				}

			}

			$result = $this->db->query($sql,$sqlData);

			//if we removing categories from certain supplier -we need to update supplier's timestamp
			if (isset($filters["SUPPLIER_BRANCH_CODE"]))
			{
				$this->updateSupplierTimestamp($filters["SUPPLIER_BRANCH_CODE"]);
			}
		}



		return true;
	}

	/**
	 * Create request or authorisation
	 *
	 * @param integer $companyId
	 * @param integer $categoryId
	 * @param string $isAuthorised
	 * @return  integer
	 */
	public function store ($companyId,$categoryId,$isAuthorised)
	{

		$sql = "INSERT INTO SUPPLY_CATEGORY (SUPPLIER_ORG_CODE, SUPPLIER_BRANCH_CODE, PRODUCT_CATEGORY_ID, PRIMARY_SUPPLY_CATEGORY, GLOBAL_SUPPLY_CATEGORY, IS_AUTHORISED)";
		$sql.= " VALUES ((SELECT SPB_SUP_ORG_CODE FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE=:companyId),:companyId, :categoryId, 0, 0, :isAuthorised) ";


		$sqlData = array(
			'companyId'	=> $companyId,
			'categoryId'	=> $categoryId,
			'isAuthorised'	=> $isAuthorised
		);

		$this->db->query($sql,$sqlData);

		$this->updateSupplierTimestamp($companyId);

	}
	/**
	 * Transfrom request to authorisation
	 *
	 * @param integer $companyId
	 * @param integer $categoryId
	 * @param string $isAuthorised
	 * @return boolean
	 */
	public function authorise ($companyId,$categoryId,$isAuthorised)
	{
		$sql = "UPDATE SUPPLY_CATEGORY SET";
		$sql.= " IS_AUTHORISED = :isAuthorised";
		$sql.= " WHERE SUPPLIER_BRANCH_CODE=:companyId";
		$sql.= " AND PRODUCT_CATEGORY_ID=:categoryId";

		$sqlData = array(
			'companyId'	=> $companyId,
			'categoryId'	=> $categoryId,
			'isAuthorised'	=> $isAuthorised
		);
		$this->db->query($sql,$sqlData);

		$this->updateSupplierTimestamp($companyId);

		return true;
	}

	/**
	 * update supplier's timestamp
	 *
	 * @param <type> $companyId
	 * @return <type>
	 */
	public function updateSupplierTimestamp ($companyId)
	{
		$sql = "UPDATE SUPPLIER_BRANCH SET";
		$sql.= " SPB_UPDATED_DATE = SYSDATE";
		$sql.= " WHERE SPB_BRANCH_CODE=:companyId";

		$sqlData = array(
			'companyId'	=> $companyId
		);
		$this->db->query($sql,$sqlData);

		return true;
	}
}
?>
