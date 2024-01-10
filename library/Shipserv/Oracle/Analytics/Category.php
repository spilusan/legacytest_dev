<?php
class Shipserv_Oracle_Analytics_Category extends Shipserv_Oracle_Analytics
{
	
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
	
}