<?php
/**
 * Data access layer for AutoLogin Token
 * 
 * @author Elvir <eleonard@shipserv.com?
 */
class Shipserv_Oracle_AutoLoginToken extends Shipserv_Oracle{
	
	/**
	 * Retrieve records that match supplied filter
	 *
	 * @param array $filters
	 * @param boolean $useCache
	 * @param integer $cacheTTL
	 * @return array
	 */
	public function fetch($filters = array(), $page = 0, $pageSize = 20,  $useCache = false, $cacheTTL = 86400) {
			
		$key = "";
		$sql = "";

		if ($page > 0)
		{
			$sql .= 'SELECT * FROM (';
		}

		$sql .= 'SELECT PAT.PAT_ID, PAT.PAT_PSU_ID, TO_CHAR(PAT.PAT_DATE_CREATED,\'YYYY-MM-DD HH24:MI:SS\') PAT_DATE_CREATED, PAT.PAT_TOKEN_ID, PAT.PAT_URL_REDIRECT, PAT.PAT_EXPIRY';

		if ($page > 0)
		{
			$sql .= ', ROW_NUMBER() OVER (ORDER BY PAT_ID) R ';
		}

		$sql .= ' FROM PAGES_AUTOLOGIN_TOKEN PAT ';

		$sqlData = array();

		if (count($filters)>0) $sql.= ' WHERE ';
		
		if (count($filters)>0)
		{
			$isFirst = true;
			foreach ($filters as $column=>$value)
			{
				if (!$isFirst)
				{
					$sql.= ' AND ';
				}
				else
				{
					$isFirst = false;
				}
				if (!is_null($value))
				{
					if (is_array($value)){
						$sql .= $column.' IN (' . $this->arrToSqlList($value) .') ';
					}
					else
					{
						$sql .= $column.' = :'.$column."_FILTER";
						$sqlData[$column."_FILTER"] = $value;
					}
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
			$key = $this->memcacheConfig->client->keyPrefix . 'AUTOLOGIN'.$key.
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
		$sqlData = array();
		$sql = 'DELETE FROM PAGES_AUTOLOGIN_TOKEN ';
		
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
					if (is_array($value)){
						$sql .= $column.' IN (' . $this->arrToSqlList($value) .') ';
					}
					else
					{
						$sql .= $column.' = :'.$column."_FILTER";
						$sqlData[$column."_FILTER"] = $value;
					}
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

	public function updateDateVisited( $tokenId )
	{
		$sql = "UPDATE PAGES_AUTOLOGIN_TOKEN SET";
		$sql.= " PAT_DATE_VISITED = SYSDATE ";
		$sql.= " WHERE ";
		$sql.= " 	PAT_TOKEN_ID=:tokenId";
		
		$sqlData = array(
			'tokenId'		=> $tokenId
		);
		$this->db->query($sql,$sqlData);
		
		return true;
	}
	public function store( $userId, $tokenId, $urlRedirect, $expiry)
	{
		// check if this exists
		if($auth = $this->fetch(array(
			"PAT_PSU_ID" => $userId,
			"PAT_TOKEN_ID" => $tokenId,
			"PAT_EXPIRY" => $expiry,
			"PAT_URL_REDIRECT" => $urlRedirect
		)))
		{
			$sql = "UPDATE PAGES_AUTOLOGIN_TOKEN SET";
			$sql.= " PAT_DATE_CREATED = SYSDATE ";
			$sql.= " WHERE PAT_PSU_ID=:userId";
			$sql.= " AND PAT_TOKEN_ID=:tokenId";
			$sql.= " AND PAT_URL_REDIRECT=:urlRedirect";
			$sql.= " AND PAT_EXPIRY=:expiry";

			$sqlData = array(
				'userId'		=> $userId,
				'tokenId'		=> $tokenId,
				'urlRedirect'	=> $urlRedirect,
				'expiry'		=> $expiry
			);
		
			$this->db->query($sql,$sqlData);

			$createdId = $auth["PAT_ID"];
			
		}
		else
		{
			$sql = "INSERT INTO PAGES_AUTOLOGIN_TOKEN (PAT_ID, PAT_PSU_ID, PAT_DATE_CREATED, PAT_TOKEN_ID, PAT_URL_REDIRECT, PAT_EXPIRY)";
			$sql.= " VALUES (SQ_PAGES_AUTOLOGIN_TOKEN_ID.nextval, :userId, SYSDATE, :tokenId, :urlRedirect, :expiry) ";


			$sqlData = array(
				'userId'		=> $userId,
				'tokenId'		=> $tokenId,
				'urlRedirect'	=> $urlRedirect,
				'expiry'		=> $expiry
			);

			$this->db->query($sql,$sqlData);

			$createdId = $this->db->lastSequenceId('SQ_PAGES_AUTOLOGIN_TOKEN_ID');

		}
	}

	private function _arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item)
		{
			$sqlArr[] = $this->db->quote($item);
		}
		if (!$sqlArr) $sqlArr[] = 'NULL';
		return join(', ', $sqlArr);
	}
}
?>
