<?php

class Shipserv_Oracle_CompanyBrandModel extends Shipserv_Oracle{

	/**
	 * Retrieve records that match supplied filter
	 *
	 * @param array $filters
	 * @param boolean $useCache
	 * @param integer $cacheTTL
	 * @return array
	 */
	public function fetch($filters = array(), $useCache = false, $cacheTTL = 86400) {
		$key = "";

		$sql = 'SELECT *';
		$sql.= ' FROM PAGES_COMPANY_BRAND_MODELS ';

		$sqlData = array();

		if (count($filters)>0)
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
					$sql .= ' ('. $column.' IS NULL) ';
				}

				$key .= $column.$value;

			}
		}

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDAUTHS'.$key.
			       $this->memcacheConfig->client->keySuffix;


			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{

			$result = $this->db->fetchAll($sql,$sqlData);
		}

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
		if (is_array($filters) and count($filters) > 0 )
		{
			$sql = 'DELETE ';
			$sql.= ' FROM PAGES_COMPANY_BRAND_MODELS ';
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
		}



		return true;
	}

	/**
	 * Create request or authorisation
	 *
	 * @param integer $companyId
	 * @param integer $brandId
	 * @param string $authLevel
	 * @param string $isAuthorised
	 * @return  integer
	 */
	public function store ($companyId, $brandId, $model)
	{
		$sql = "INSERT INTO PAGES_COMPANY_BRAND_MODELS (CBM_ID, CBM_COMPANY_ID, CBM_BRAND_ID, CBM_MODEL_NAME, CBM_DATE_ADDED)";
		$sql.= " VALUES (SEQ_PAGES_COMP_BRAND_MODELS.nextval, :companyId, :brandId, :model, SYSDATE) ";


		$sqlData = array(
			'companyId'	=> $companyId,
			'brandId'	=> $brandId,
			'model'	=> $model
		);

		$this->db->query($sql,$sqlData);

		$createdId = $this->db->lastSequenceId('SEQ_PAGES_COMP_BRAND_MODELS');

		return $createdId;

	}
}
?>
