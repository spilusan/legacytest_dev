<?php
/**
 * Data access layer for AutoLogin Token
 * 
 * @author Elvir <eleonard@shipserv.com?
 */
class Shipserv_Oracle_Analytics extends Shipserv_Oracle
{

	const MEMCACHE_TTL_1_MONTH = 2592000;
	
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

		$sql .= 'SELECT PAT.*';

		if ($page > 0)
		{
			$sql .= ', ROW_NUMBER() OVER (ORDER BY PAT_ID) R ';
		}

		$sql .= ' FROM PAGES_STATISTICS PST ';

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
	 * Pulls total number of unique IP address on pages_statistics table
	 * 
	 * @param $db
	 * @param $duration 1 month, 2 month or 3 days etc.
	 */
	public function getTotalVisitorOnPages( $db, $duration = "" )
	{
		$gApi = new Myshipserv_GAnalytics();
		
//		die( $gApi->getSiteVisits() );
		return $gApi->getSiteVisits();
		
	}
	
	public function durationIsValid( $duration, $fieldName )
	{
		
		if( strstr( $duration, "day") !== false)
			return $fieldName . " > ADD_DAYS( SYSDATE, -" . ereg_replace("[^0-9]+", "", $duration ) . " )";
		
		else if( strstr( $duration, "month") !== false)
			return $fieldName . " > ADD_MONTHS( SYSDATE, -" . ereg_replace("[^0-9]+", "", $duration ) . " )";
		
		else if( strstr( $duration, "year") !== false)
			return $fieldName . " > ADD_YEARS( SYSDATE, -" . ereg_replace("[^0-9]+", "", $duration ) . " )";
		
		else
			return false;
	}
	
	/**
	 * Get total inquiry for all categories and return it as an array
	 * 
	 * @param Zend_DB $db
	 */
	public function getTotalInquiryForAllCategory( $db, $duration = "" )
	{
		$sql = "SELECT
				  pst_category AS CATEGORY,
				  pst_category_id AS CATEGORY_ID,
				  count(pst_category_id) AS TOTAL 
				FROM
				  pages_inquiry JOIN pages_statistics ON pin_pst_id = pst_id
				WHERE
				  pst_category_id IS NOT Null 
				  AND pst_category IS NOT null
		";
		
		if( $duration != "" )
		{
			// add sql condition for duration
			if( $duration != "" && false === $this->durationIsValid( $duration, "pst_search_date_time" ) )
			{
				throw new Exception("Please check your duration date!");
			}
			else 
			{
				$sql .= " AND " . $this->durationIsValid( $duration, "pst_search_date_time" ) . " ";
			}
		}
			
		$sql .= " GROUP BY pst_category_id, pst_category";
		$result = $db->fetchAll($sql);
		foreach( $result as $row )
		{
			$data[ $row["CATEGORY_ID"] ] = $row["TOTAL"];
		}
		return $data;
	}
	
	/**
	 * Get total number of visitor viewing supplier page
	 * 
	 * @param Zend_Db $db
	 * @param int $supplierId 
	 */
	public function getTotalVisitorOnSupplier( $db, $supplierId, $duration = "" )
	{
		$sql = "
		SELECT 
			COUNT(*)
		FROM 
			PAGES_STATISTICS_SUPPLIER
		WHERE
		  PSS_SPB_BRANCH_CODE = " . $supplierId . "
		";
		
		// add sql condition for duration
		if( $duration != "" )
		{
			if( false === $this->durationIsValid( $duration, "pss_view_date" ) )
			{
				throw new Exception("Please check your duration date!");
			}
			else 
			{
				$sql .= " AND " . $this->durationIsValid( $duration, "pss_view_date" ) . " ";
			}
		}
		
		return $db->fetchOne( $sql );	
	}
	
	public function getTotalSearchForAllCategory( $db, $duration = "" )
	{
		$sql = "
			SELECT 
			  PST_CATEGORY_ID AS CATEGORY_ID,
			  COUNT(*) AS TOTAL
			FROM
			  PAGES_STATISTICS
			WHERE 
			  PST_CATEGORY_ID IS NOT null
		";
		
		if( $duration != "" )
		{
			// add sql condition for duration
			if( $duration != "" && false === $this->durationIsValid( $duration, "pst_search_date_time" ) )
			{
				throw new Exception("Please check your duration date!");
			}
			else 
			{
				$sql .= " AND " . $this->durationIsValid( $duration, "pst_search_date_time" ) . " ";
			}
		}
		
		$sql .= " GROUP BY PST_CATEGORY_ID ";
		
	
		$result = $db->fetchAll($sql);
		foreach( $result as $row )
		{
			$data[ $row["CATEGORY_ID"] ] = $row["TOTAL"];
		}
		return $data;
		
	}	
	
	/**
	 * Pull total of search on given brandId
	 * 
	 * @param int $brandId
	 * @param string $duration '3 months', '53 days', '3 years'
	 * @throws Exception
	 * @return integer
	 */
	public function getTotalSearchOnBrand( $brandId, $duration = "" )
	{
		$sql = "
			SELECT 
			  COUNT(*) AS TOTAL
			FROM
			  PAGES_STATISTICS
			WHERE 
			  PST_BRAND_ID IS NOT null
			  AND PST_BRAND_ID = :brandId
		";
		
		// add sql condition for duration
		if( $duration != "" && false === $this->durationIsValid( $duration, "pst_search_date_time" ) )
		{
			throw new Exception("Please check your duration date!");
		}
		else if( $duration != "" )
		{
			$sql .= " AND " . $this->durationIsValid( $duration, "pst_search_date_time" ) . " ";
		}
		
		$sqlData = array(
			"brandId" => $brandId
		);
		
		$result = $this->db->fetchAll($sql, $sqlData);
		
		return $result[0]["TOTAL"];
		
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
	
	public function getTotalSearchByCategoryId($categoryId, $period = array())
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $categoryId . $this->memcacheConfig->client->keySuffix;
		
		$sql = "
			SELECT
			  COUNT(*)
			FROM
			  pages_search_stats
			WHERE
			  pst_search_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			  AND pst_category_id = :categoryId
		";
		$result = $this->fetchCachedQuery($sql, array('categoryId' => $categoryId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSREPORT2);
		
		return $result[0]['TOTAL'];
	}
	
	public function getTotalSupplierByCategoryId($categoryId)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $categoryId . $this->memcacheConfig->client->keySuffix;
		$sql = "
			SELECT
			  COUNT(*) TOTAL
			FROM
			  supply_category
			WHERE
			  PRODUCT_CATEGORY_ID = :categoryId		
		";

		$result = $this->fetchCachedQuery($sql, array('categoryId' => $categoryId), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSERVDBA);
		//$result = $this->getDb()->fetchAll($sql, array('categoryId' => $categoryId));
		return $result[0]['TOTAL'];
	}
	
	public function getTotalNewlyUpdatedSupplierByCategoryId($categoryId, $period)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $categoryId . $period['start']->format('d M Y') . $period['end']->format('d M Y') . $this->memcacheConfig->client->keySuffix;
		$sql = "
			SELECT
				COUNT(*) TOTAL
			FROM
				supply_category 
					JOIN supplier_branch 
						ON (supplier_branch_code=spb_branch_code 
							AND DIRECTORY_LIST_VERIFIED_AT_UTC BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999)
			WHERE
				PRODUCT_CATEGORY_ID = :categoryId
		";
		
		$result = $this->fetchCachedQuery($sql, array('categoryId' => $categoryId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSERVDBA);
		return $result[0]['TOTAL'];		
	}
	
	public function getTotalRfqSentToSupplierByCategoryId($categoryId, $period)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $categoryId . $this->memcacheConfig->client->keySuffix;
		$sql = "
			SELECT
			  COUNT(*) TOTAL
			FROM
			  rfq
			WHERE
			  spb_branch_code IN
			  (
			    SELECT
			      SUPPLIER_BRANCH_CODE
			    FROM
			      supply_category@livedb_link
			    WHERE
			      PRODUCT_CATEGORY_ID = :categoryId		
			  )
			  AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
		";
		
		$result = $this->fetchCachedQuery($sql, array('categoryId' => $categoryId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSREPORT2);
		return $result[0]['TOTAL'];
	}
	
	public function getTotalTransactionInTradenetByCategoryId( $categoryId, $period )
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $categoryId . $this->memcacheConfig->client->keySuffix;
		$sql = "
			SELECT 
			  MONTH_START, 
			  SUM(N_VALUED) N_VALUED, 
			  SUM(SUM_VALUE) SUM_VALUE, 
			  COUNT(DISTINCT PT_SUPPLIER_TNID) N_DISTINCT_SUPPLIERS, 
			  MAX(SUM_VALUE) SUPPLIER_MAX_VALUE 
			FROM 
			  (
			    SELECT 
			      TO_CHAR(PT_ORD_SUBMITTED_DATE, 'yyyy-mm') MONTH_START, 
			      PT_SUPPLIER_TNID, 
			      COUNT(NULLIF(PT_ORD_ADJ_TOTAL_COST_USD, 0)) N_VALUED, 
			      NVL(SUM(PT_ORD_ADJ_TOTAL_COST_USD), 0) SUM_VALUE 
			    FROM 
			      PAGES_TRANSACTION 
			    WHERE 
			      PT_ORD_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999 
			      AND PT_SUPPLIER_TNID IN 
			        (
					    SELECT
					      SUPPLIER_BRANCH_CODE
					    FROM
					      supply_category
					    WHERE
					      PRODUCT_CATEGORY_ID = :categoryId		
			        ) 
			    GROUP BY 
			      TO_CHAR(PT_ORD_SUBMITTED_DATE, 'yyyy-mm'), PT_SUPPLIER_TNID) 
			GROUP BY MONTH_START		
		";

		$total = 0;
		$parameters = array('categoryId' => $categoryId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y'));
		$result = $this->fetchCachedQuery($sql, $parameters, $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSERVDBA);
		foreach((array)$result as $row)
		{
			$total += $row['SUM_VALUE'];
			$totalSupplier += $row['N_DISTINCT_SUPPLIERS'];
		}

		if( $totalSupplier < 2 || $total < 10000 ) return 0;
		else return $total;
		
	}
}
?>